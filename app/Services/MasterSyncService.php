<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\Log;

/**
 * Service: MasterSyncService
 *
 * Bertanggung jawab menyinkronkan master data dari ERP ke WMS.
 * Mendukung upsert (create + update) dan deaktivasi item.
 *
 * Prinsip desain:
 *   - Partial success: 1 item gagal tidak menghentikan item lain
 *   - Change detection: hanya update jika data benar-benar berubah
 *   - Pre-loaded lookups: kategori & satuan dimuat sekali ke memory (efisien)
 *   - Idempotent: kirim payload sama berkali-kali → hasil sama
 *   - Traceable: setiap item dapat status jelas: created/updated/skipped/failed
 */
class MasterSyncService
{
    // ═══════════════════════════════════════════════════════════════════════
    // ITEMS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Sinkronisasi master data item dari ERP.
     *
     * @param  array $rows  Array item dari ERP (sudah divalidasi FormRequest)
     * @return array        Laporan sync: total, created, updated, skipped, failed, results[]
     */
    public function syncItems(array $rows): array
    {
        // ── Pre-load lookup tables ke memory (cegah N+1 query) ───────────
        // Struktur: ['KODE' => id]  →  O(1) lookup per item
        $categoryMap = ItemCategory::where('is_active', true)->pluck('id', 'code');
        $unitMap     = Unit::where('is_active', true)->pluck('id', 'code');

        $report = $this->emptyReport(count($rows));

        foreach ($rows as $index => $row) {
            $result = $this->processOneItem($row, $categoryMap, $unitMap);

            $report[$result['status']]++;
            $report['results'][] = $result;
        }

        Log::info('[MasterSync:Items] Sync selesai', [
            'total'   => $report['total_received'],
            'created' => $report['created'],
            'updated' => $report['updated'],
            'skipped' => $report['skipped'],
            'failed'  => $report['failed'],
        ]);

        return $report;
    }

    /**
     * Proses satu item dari ERP.
     * Di-isolasi dalam try-catch agar error satu item tidak menghentikan lainnya.
     */
    private function processOneItem(array $row, $categoryMap, $unitMap): array
    {
        $erpCode = $row['erp_item_code'];

        try {
            // ── 1. Resolve Category ────────────────────────────────────────
            $categoryId = $this->resolveCategoryId($row, $categoryMap);
            if ($categoryId === false) {
                return $this->failResult('item', $erpCode,
                    "Kategori '{$row['category_code']}' tidak ditemukan di WMS. "
                    . "Daftarkan kategori ini terlebih dahulu via menu Master → Kategori."
                );
            }

            // ── 2. Resolve Unit ────────────────────────────────────────────
            $unitId = $this->resolveUnitId($row, $unitMap);
            if ($unitId === false) {
                return $this->failResult('item', $erpCode,
                    "Satuan '{$row['unit_code']}' tidak ditemukan di WMS. "
                    . "Daftarkan satuan ini terlebih dahulu via menu Master → Satuan."
                );
            }

            // ── 3. Cari item existing (termasuk yang soft-deleted) ─────────
            $existing = Item::withTrashed()->where('erp_item_code', $erpCode)->first();

            // ── 4. Bangun atribut yang akan disimpan ───────────────────────
            $attributes = $this->buildItemAttributes($row, $categoryId, $unitId, $existing);

            // ── 5. CREATE (item baru) ──────────────────────────────────────
            if (!$existing) {
                // Untuk item baru, category & unit WAJIB ada
                if (!$categoryId || !$unitId) {
                    return $this->failResult('item', $erpCode,
                        'Item baru memerlukan category_code dan unit_code yang valid.'
                    );
                }

                // Cek apakah SKU sudah dipakai item lain (konflik)
                $skuConflict = Item::withTrashed()
                    ->where('sku', $attributes['sku'])
                    ->where('erp_item_code', '!=', $erpCode)
                    ->exists();

                if ($skuConflict) {
                    return $this->failResult('item', $erpCode,
                        "SKU '{$attributes['sku']}' sudah digunakan item lain. "
                        . "Kirim field 'sku' yang berbeda atau hubungi admin WMS."
                    );
                }

                $item = Item::create(array_merge($attributes, ['erp_item_code' => $erpCode]));

                return [
                    'erp_item_code' => $erpCode,
                    'status'        => 'created',
                    'id'            => $item->id,
                    'sku'           => $item->sku,
                    'name'          => $item->name,
                ];
            }

            // ── 6. UPDATE / SKIPPED (item sudah ada) ──────────────────────
            $existing->fill($attributes);
            $changes = array_keys($existing->getDirty());

            // Tidak ada perubahan → skip (hemat query & log)
            if (empty($changes)) {
                return [
                    'erp_item_code' => $erpCode,
                    'status'        => 'skipped',
                    'id'            => $existing->id,
                    'sku'           => $existing->sku,
                ];
            }

            $existing->save();

            // Pulihkan item yang sebelumnya dinonaktifkan & sekarang aktif lagi
            if ($existing->trashed() && ($row['is_active'] ?? true)) {
                $existing->restore();
            }

            return [
                'erp_item_code' => $erpCode,
                'status'        => 'updated',
                'id'            => $existing->id,
                'sku'           => $existing->sku,
                'changes'       => $changes,
            ];

        } catch (\Throwable $e) {
            Log::error('[MasterSync:Items] Error proses item', [
                'erp_item_code' => $erpCode,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return $this->failResult('item', $erpCode, 'Error sistem: ' . $e->getMessage());
        }
    }

    /**
     * Bangun array atribut item dari payload ERP.
     * Untuk field optional: jika tidak dikirim ERP, pakai nilai existing (preserve).
     */
    private function buildItemAttributes(array $row, $categoryId, $unitId, ?Item $existing): array
    {
        return array_filter([
            'name'                    => $row['name'],
            'sku'                     => $row['sku'] ?? $existing?->sku ?? $row['erp_item_code'],
            'category_id'             => $categoryId ?: $existing?->category_id,
            'unit_id'                 => $unitId     ?: $existing?->unit_id,
            'barcode'                 => $row['barcode']     ?? $existing?->barcode,
            'description'             => $row['description'] ?? $existing?->description,
            'weight_kg'               => $row['weight_kg']   ?? $existing?->weight_kg,
            'volume_m3'               => $row['volume_m3']   ?? $existing?->volume_m3,
            'min_stock'               => $row['min_stock']   ?? $existing?->min_stock  ?? 0,
            'max_stock'               => $row['max_stock']   ?? $existing?->max_stock  ?? 0,
            'reorder_point'           => $row['reorder_point'] ?? $existing?->reorder_point ?? 0,
            'movement_type'           => $row['movement_type'] ?? $existing?->movement_type ?? 'slow_moving',
            'item_size'               => $row['item_size']   ?? $existing?->item_size,
            'deadstock_threshold_days'=> $row['deadstock_threshold_days'] ?? $existing?->deadstock_threshold_days ?? 90,
            'is_active'               => $row['is_active']   ?? $existing?->is_active  ?? true,
        ], fn($v) => $v !== null);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Resolve category_id dari category_code.
     * Return: int (ID) | null (tidak dikirim, pakai existing) | false (dikirim tapi tidak ketemu)
     */
    private function resolveCategoryId(array $row, $categoryMap): int|null|false
    {
        if (empty($row['category_code'])) {
            return null; // tidak dikirim → pakai existing
        }

        $id = $categoryMap[$row['category_code']] ?? null;
        return $id ?? false; // null = tidak ketemu → fail
    }

    /**
     * Resolve unit_id dari unit_code.
     */
    private function resolveUnitId(array $row, $unitMap): int|null|false
    {
        if (empty($row['unit_code'])) {
            return null;
        }

        $id = $unitMap[$row['unit_code']] ?? null;
        return $id ?? false;
    }

    private function emptyReport(int $total): array
    {
        return [
            'total_received' => $total,
            'created'        => 0,
            'updated'        => 0,
            'skipped'        => 0,
            'failed'         => 0,
            'results'        => [],
        ];
    }

    private function failResult(string $type, string $key, string $reason): array
    {
        $keyField = $type === 'item' ? 'erp_item_code' : 'erp_vendor_id';
        return [
            $keyField => $key,
            'status'  => 'failed',
            'reason'  => $reason,
        ];
    }
}
