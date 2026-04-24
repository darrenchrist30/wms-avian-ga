<?php

namespace App\Jobs;

use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\ItemAffinity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RecalculateAffinityJob
 *
 * Dijalankan secara async setelah sebuah InboundOrder berstatus 'completed'.
 *
 * Logic:
 *   1. Ambil semua item dalam order yang baru selesai
 *   2. Untuk setiap pasangan item (A, B) dalam order yang sama:
 *      - Increment co_occurrence_count
 *   3. Hitung ulang affinity_score berdasarkan co_occurrence_count
 *      relatif terhadap maksimum (normalisasi 0–1)
 */
class RecalculateAffinityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private readonly int $inboundOrderId
    ) {}

    public function handle(): void
    {
        $order = InboundOrder::with('items')->find($this->inboundOrderId);

        if (!$order || $order->status !== 'completed') {
            Log::warning('[RecalculateAffinityJob] Order tidak ditemukan atau belum completed', [
                'inbound_order_id' => $this->inboundOrderId,
            ]);
            return;
        }

        $itemIds = $order->items->pluck('item_id')->unique()->values()->toArray();

        if (count($itemIds) < 2) {
            // Hanya 1 item, tidak ada pasangan → tidak ada yang dihitung
            return;
        }

        Log::info('[RecalculateAffinityJob] Menghitung ulang affinitas', [
            'inbound_order_id' => $this->inboundOrderId,
            'item_count'       => count($itemIds),
        ]);

        DB::transaction(function () use ($itemIds) {
            // Buat semua kombinasi pasangan (A, B) dengan A < B untuk menghindari duplikat
            $pairs = [];
            for ($i = 0; $i < count($itemIds); $i++) {
                for ($j = $i + 1; $j < count($itemIds); $j++) {
                    $pairs[] = [
                        min($itemIds[$i], $itemIds[$j]),
                        max($itemIds[$i], $itemIds[$j]),
                    ];
                }
            }

            foreach ($pairs as [$itemA, $itemB]) {
                $existing = ItemAffinity::where('item_id', $itemA)
                    ->where('related_item_id', $itemB)
                    ->first();

                if ($existing) {
                    $existing->increment('co_occurrence_count');
                } else {
                    ItemAffinity::create([
                        'item_id'             => $itemA,
                        'related_item_id'     => $itemB,
                        'co_occurrence_count' => 1,
                        'affinity_score'      => 0.0,
                    ]);
                }
            }

            // Normalisasi: cari max co_occurrence, hitung ulang semua affinity_score
            $maxCount = ItemAffinity::max('co_occurrence_count');

            if ($maxCount > 0) {
                // Update semua baris sekaligus via raw SQL untuk efisiensi
                DB::statement('UPDATE item_affinities SET affinity_score = co_occurrence_count / ?', [$maxCount]);
            }
        });

        Log::info('[RecalculateAffinityJob] Affinitas berhasil diperbarui', [
            'inbound_order_id' => $this->inboundOrderId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[RecalculateAffinityJob] Job gagal', [
            'inbound_order_id' => $this->inboundOrderId,
            'error'            => $exception->getMessage(),
        ]);
    }
}
