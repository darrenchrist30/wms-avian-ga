<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ItemsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $errors   = [];
    public int   $imported = 0;
    public int   $skipped  = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $sku          = strtoupper(trim($row['sku'] ?? ''));
            $name         = trim($row['nama_sparepart'] ?? '');
            $categoryName = trim($row['kategori'] ?? '');
            $unitCode     = strtoupper(trim($row['kode_satuan'] ?? ''));
            $movementType = strtolower(trim($row['tipe_pergerakan'] ?? 'fast_moving'));

            // Wajib diisi
            if (!$sku || !$name || !$categoryName || !$unitCode) {
                $this->errors[] = "Baris {$rowNum}: Kolom SKU, Nama Sparepart, Kategori, dan Kode Satuan wajib diisi.";
                continue;
            }

            // Skip jika SKU sudah ada
            if (Item::where('sku', $sku)->exists()) {
                $this->skipped++;
                continue;
            }

            // Cari kategori berdasarkan nama
            $category = ItemCategory::whereRaw('LOWER(name) = ?', [strtolower($categoryName)])->first();
            if (!$category) {
                $this->errors[] = "Baris {$rowNum}: Kategori \"{$categoryName}\" tidak ditemukan di database.";
                continue;
            }

            // Cari satuan berdasarkan kode
            $unit = Unit::whereRaw('UPPER(code) = ?', [strtoupper($unitCode)])->first();
            if (!$unit) {
                $this->errors[] = "Baris {$rowNum}: Satuan \"{$unitCode}\" tidak ditemukan di database.";
                continue;
            }

            // Validasi tipe pergerakan
            if (!in_array($movementType, ['fast_moving', 'slow_moving', 'dead'])) {
                $movementType = 'fast_moving';
            }

            $minStock  = max(0, (int) ($row['min_stok'] ?? 0));
            $maxStock  = max(0, (int) ($row['maks_stok'] ?? 0));
            $deadstock = max(1, (int) ($row['deadstock_threshold_hari'] ?? 90));

            try {
                Item::create([
                    'sku'                      => $sku,
                    'name'                     => $name,
                    'category_id'              => $category->id,
                    'unit_id'                  => $unit->id,
                    'movement_type'            => $movementType,
                    'erp_item_code'            => trim($row['erp_item_code'] ?? '') ?: null,
                    'barcode'                  => trim($row['barcode'] ?? '') ?: null,
                    'description'              => trim($row['deskripsi'] ?? '') ?: null,
                    'min_stock'                => $minStock,
                    'max_stock'                => $maxStock,
                    'reorder_point'            => max(0, (int) ($row['reorder_point'] ?? 0)),
                    'weight_kg'                => is_numeric($row['berat_kg'] ?? '') ? (float) $row['berat_kg'] : null,
                    'volume_m3'                => is_numeric($row['volume_m3'] ?? '') ? (float) $row['volume_m3'] : null,
                    'deadstock_threshold_days' => $deadstock,
                    'is_active'                => true,
                ]);
                $this->imported++;
            } catch (\Exception $e) {
                $this->errors[] = "Baris {$rowNum} ({$sku}): Gagal disimpan — " . $e->getMessage();
            }
        }
    }
}
