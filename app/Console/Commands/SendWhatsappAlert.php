<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Stock;
use App\Models\Zone;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsappAlert extends Command
{
    protected $signature   = 'wms:send-wa-alert {--force : Kirim tanpa cek jadwal}';
    protected $description = 'Kirim ringkasan alert WMS (low stock + deadstock) ke supervisor via WhatsApp';

    public function handle(): int
    {
        $token   = config('services.fonnte.token');
        $numbers = config('services.fonnte.supervisor_numbers', []);
        $days    = (int) config('services.fonnte.deadstock_days', 90);

        if (empty($numbers)) {
            $this->warn('WA_SUPERVISOR_NUMBERS belum diisi di .env — alert tidak dikirim.');
            Log::warning('[WA Alert] WA_SUPERVISOR_NUMBERS kosong, alert tidak dikirim.');
            return self::FAILURE;
        }

        $message = $this->buildMessage($days);

        if (empty($token)) {
            // Tidak ada token — simpan ke log saja agar bisa dilihat saat demo
            Log::info('[WA Alert] FONNTE_TOKEN belum diisi. Pesan yang akan dikirim:' . PHP_EOL . $message);
            $this->info('FONNTE_TOKEN belum diisi. Pesan disimpan ke log.');
            $this->line($message);
            return self::SUCCESS;
        }

        $success = 0;
        foreach ($numbers as $number) {
            $number = $this->normalizePhone($number);
            try {
                $response = Http::withHeaders(['Authorization' => $token])
                    ->asForm()
                    ->post('https://api.fonnte.com/send', [
                        'target'  => $number,
                        'message' => $message,
                    ]);

                if ($response->successful() && ($response->json('status') ?? false)) {
                    $success++;
                    Log::info("[WA Alert] Terkirim ke {$number}");
                } else {
                    Log::warning("[WA Alert] Gagal ke {$number}: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("[WA Alert] Error ke {$number}: " . $e->getMessage());
            }
        }

        $this->info("WA Alert dikirim: {$success}/" . count($numbers) . " nomor berhasil.");
        return self::SUCCESS;
    }

    private function buildMessage(int $deadstockDays): string
    {
        $now = now()->locale('id')->isoFormat('dddd, D MMMM Y HH:mm');

        // ── Low Stock ───────────────────────────────────────────────────────
        $lowStockItems = Item::where('is_active', true)
            ->whereRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stock_records s WHERE s.item_id = items.id AND s.status = "available") < items.min_stock')
            ->with('category')
            ->orderByRaw('(SELECT COALESCE(SUM(s.quantity),0) FROM stock_records s WHERE s.item_id = items.id AND s.status = "available") ASC')
            ->take(10)
            ->get();

        // ── Deadstock ───────────────────────────────────────────────────────
        $deadstockItems = Stock::with('item.category')
            ->deadstock($deadstockDays)
            ->orderByRaw('COALESCE(last_moved_at, inbound_date) ASC')
            ->take(10)
            ->get();

        // ── Kapasitas Kritis ────────────────────────────────────────────────
        $criticalZones = Zone::with('racks.cells')->get()
            ->map(function ($zone) {
                $cells = $zone->cells;
                $max   = $cells->sum('capacity_max');
                $used  = $cells->sum('capacity_used');
                $pct   = $max > 0 ? round($used / $max * 100, 1) : 0;
                return ['name' => $zone->name, 'pct' => $pct];
            })
            ->filter(fn($z) => $z['pct'] >= 85)
            ->values();

        // ── Susun Pesan ─────────────────────────────────────────────────────
        $lines = [];
        $lines[] = "🏭 *WMS AVIAN — Ringkasan Alert*";
        $lines[] = "📅 {$now}";
        $lines[] = str_repeat('─', 30);

        // Low Stock
        $lines[] = "";
        $lines[] = "⚠️ *STOK MENIPIS* ({$lowStockItems->count()} item)";
        if ($lowStockItems->isEmpty()) {
            $lines[] = "  ✅ Semua stok dalam batas normal";
        } else {
            foreach ($lowStockItems as $item) {
                $currentQty = \Illuminate\Support\Facades\DB::table('stock_records')
                    ->where('item_id', $item->id)
                    ->where('status', 'available')
                    ->sum('quantity');
                $lines[] = "  • {$item->sku} — {$item->name}";
                $lines[] = "    Stok: {$currentQty} / Min: {$item->min_stock} unit";
            }
        }

        // Deadstock
        $lines[] = "";
        $lines[] = "🕰️ *DEADSTOCK* (≥{$deadstockDays} hari tidak bergerak, {$deadstockItems->count()} record)";
        if ($deadstockItems->isEmpty()) {
            $lines[] = "  ✅ Tidak ada item deadstock";
        } else {
            foreach ($deadstockItems as $ds) {
                $sku  = $ds->item?->sku ?? '–';
                $name = $ds->item?->name ?? '–';
                $days = $ds->days_since_last_movement ?? 0;
                $qty  = $ds->quantity;
                $lines[] = "  • {$sku} — {$name}";
                $lines[] = "    Qty: {$qty} unit | Diam: {$days} hari";
            }
        }

        // Zona Kritis
        $lines[] = "";
        $lines[] = "📦 *KAPASITAS KRITIS* (≥85%)";
        if ($criticalZones->isEmpty()) {
            $lines[] = "  ✅ Semua zona dalam kapasitas normal";
        } else {
            foreach ($criticalZones as $z) {
                $lines[] = "  • {$z['name']}: {$z['pct']}% penuh";
            }
        }

        $lines[] = "";
        $lines[] = str_repeat('─', 30);
        $lines[] = "🔗 Cek dashboard: " . config('app.url');

        return implode("\n", $lines);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }
}
