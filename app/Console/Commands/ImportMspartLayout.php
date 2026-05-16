<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Models\Item;
use App\Models\Rack;
use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan Command: import:mspart-layout
 *
 * Import lokasi fisik item (blok/grup/kolom/baris) dari MSpart.sql ke WMS.
 * Membuat sub-rak per (blok+grup) dan sel per (kolom, baris) di dalamnya.
 *
 * Cara pakai:
 *   php artisan import:mspart-layout
 *   php artisan import:mspart-layout --mspart="C:/path/MSpart.sql" --stock="C:/path/StockSpart.sql"
 *   php artisan import:mspart-layout --warehouse=1 --dry-run
 */
class ImportMspartLayout extends Command
{
    protected $signature = 'import:mspart-layout
                            {--mspart=      : Path ke MSpart.sql (default: Downloads)}
                            {--stock=       : Path ke StockSpart.sql (default: Downloads)}
                            {--warehouse=   : ID gudang target (default: gudang pertama)}
                            {--sync-stock   : Pindahkan ulang stock IMPORT/MSPART ke cell MSPART yang benar}
                            {--dry-run      : Preview tanpa menyimpan ke database}';

    protected $description = 'Import lokasi fisik mspart (blok/grup/kolom/baris) ke sub-rak WMS';

    private bool $dryRun = false;
    private array $numToLetter = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '5' => 'E', '6' => 'F', '7' => 'G'];
    private array $validGrup   = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        if ($this->dryRun) {
            $this->warn('=== DRY RUN — tidak ada perubahan disimpan ===');
        }

        $this->info('=== Import Layout Mspart ke WMS ===');
        $this->newLine();

        // 1. Resolve files
        $mspartPath = $this->resolvePath($this->option('mspart'), 'MSpart.sql');
        $stockPath  = $this->resolvePath($this->option('stock'),  'StockSpart.sql');

        if (!$mspartPath || !file_exists($mspartPath)) {
            $this->error("File MSpart.sql tidak ditemukan: {$mspartPath}");
            return self::FAILURE;
        }
        $this->line("MSpart.sql     : {$mspartPath}");

        $hasStock = $stockPath && file_exists($stockPath);
        if ($hasStock) {
            $this->line("StockSpart.sql : {$stockPath}");
        } else {
            $this->warn('StockSpart.sql tidak ditemukan — stok tidak akan diimport.');
        }
        $this->newLine();

        // 2. Resolve warehouse
        $warehouseId = $this->option('warehouse')
            ? (int) $this->option('warehouse')
            : \App\Models\Warehouse::where('is_active', true)->value('id');

        if (!$warehouseId) {
            $this->error('Tidak ada gudang aktif di database.');
            return self::FAILURE;
        }
        $this->line("Gudang ID      : {$warehouseId}");
        $this->newLine();

        // 3. Parse MSpart.sql
        $this->info('Membaca MSpart.sql...');
        $mspartRows = $this->parseSqlInserts($mspartPath);
        $this->line("  Total baris    : " . count($mspartRows));

        // Filter active + has blok/grup. Kolom/baris is validated separately so
        // bad physical coordinates can be reported instead of silently corrected.
        $located = array_filter($mspartRows, function ($r) {
            if (($r['isdel'] ?? '1') !== '0') return false;
            $blok = trim($r['blok'] ?? '');
            $grup = strtoupper(trim($r['grup'] ?? ''));
            if ($blok === '' || $blok === 'NULL' || !ctype_digit($blok) || (int) $blok <= 0) return false;
            if (isset($this->numToLetter[$grup])) $grup = $this->numToLetter[$grup];
            return in_array($grup, $this->validGrup);
        });
        $this->line("  Aktif + punya blok/grup: " . count($located));
        $this->newLine();

        if (empty($located)) {
            $this->warn('Tidak ada item dengan data lokasi valid. Periksa file MSpart.sql.');
            return self::SUCCESS;
        }

        // 4. Build location map: kode → [blok, grup, kolom, baris]
        $locationMap = [];
        $invalidLocations = [];
        foreach ($located as $r) {
            $kode = trim($r['kode'] ?? '');
            if (!$kode) continue;

            $blok  = (int) trim($r['blok']);
            $grup  = strtoupper(trim($r['grup']));
            $kolomRaw = trim((string) ($r['kolom'] ?? ''));
            $barisRaw = trim((string) ($r['baris'] ?? ''));
            $kolom = ctype_digit($kolomRaw) ? (int) $kolomRaw : 0;
            $baris = ctype_digit($barisRaw) ? (int) $barisRaw : 0;

            if (isset($this->numToLetter[$grup])) {
                $grup = $this->numToLetter[$grup];
            }

            if ($kolom < 1 || $kolom > 7 || $baris < 1 || $baris > 9) {
                $invalidLocations[] = [
                    'kode' => $kode,
                    'nama' => trim($r['nama'] ?? ''),
                    'blok' => $blok,
                    'grup' => $grup,
                    'kolom' => $kolomRaw === '' ? 'NULL' : $kolomRaw,
                    'baris' => $barisRaw === '' ? 'NULL' : $barisRaw,
                ];
                continue;
            }

            $locationMap[$kode] = compact('blok', 'grup', 'kolom', 'baris');
        }

        $this->line('  Lokasi valid 4 atribut: ' . count($locationMap));
        if ($invalidLocations) {
            $this->warn('  Lokasi invalid (kolom harus 1-7, baris harus 1-9): ' . count($invalidLocations));
            foreach (array_slice($invalidLocations, 0, 25) as $bad) {
                $this->line("    - {$bad['kode']} {$bad['nama']} => {$bad['blok']}-{$bad['grup']}-{$bad['kolom']}-{$bad['baris']}");
            }
            if (count($invalidLocations) > 25) {
                $this->line('    ... ' . (count($invalidLocations) - 25) . ' item lain');
            }
        }

        if (empty($locationMap)) {
            $this->warn('Tidak ada item dengan 4 atribut lokasi valid. Periksa blok/grup/kolom/baris di MSpart.sql.');
            return self::SUCCESS;
        }

        // 5. Aggregate stock from StockSpart.sql
        $stockAgg = [];
        if ($hasStock) {
            $this->info('Membaca StockSpart.sql...');
            $stockRows = $this->parseSqlInserts($stockPath, 'Qspart');
            foreach ($stockRows as $r) {
                $kode  = trim($r['kode'] ?? '');
                $qty   = (float) ($r['stock'] ?? 0);
                if (!$kode || $qty <= 0) continue;
                $stockAgg[$kode] = ($stockAgg[$kode] ?? 0) + $qty;
            }
            $this->line("  Item dengan stok > 0: " . count(array_filter($stockAgg)));
            $this->newLine();
        }
        $syncStock = (bool) $this->option('sync-stock') && !empty($stockAgg);

        // 6. Find physical (blok, grup) pairs. Empty shelf groups must exist too
        // so every visible rack bay can be hovered by column even when it has no stock.
        $pairs = [];
        foreach ($locationMap as $loc) {
            $key = $loc['blok'] . '_' . $loc['grup'];
            $pairs[$key] = ['blok' => $loc['blok'], 'grup' => $loc['grup']];
        }
        $physicalBlocks = Rack::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->whereIn('code', array_map('strval', range(1, 11)))
            ->pluck('code')
            ->map(fn($code) => (int) $code)
            ->all();
        foreach ($physicalBlocks as $blok) {
            foreach ($this->validGrup as $grup) {
                $key = $blok . '_' . $grup;
                $pairs[$key] = ['blok' => $blok, 'grup' => $grup];
            }
        }
        $this->line('Pasangan (blok, grup) unik: ' . count($pairs));

        // 7. Ensure sub-racks exist
        if (!$this->dryRun && !$this->confirm('Lanjutkan membuat sub-rak dan sel?', true)) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Membuat sub-rak per (blok, grup)...');

        $subRackMap   = [];  // "blok_grup" => Rack instance
        $racksCreated = 0;
        $racksReused  = 0;

        foreach ($pairs as $key => $pair) {
            $blok = $pair['blok'];
            $grup = $pair['grup'];

            $parentRack = Rack::where('warehouse_id', $warehouseId)
                ->where('code', (string) $blok)
                ->first();

            if (!$parentRack) {
                $this->warn("  Rak induk untuk blok {$blok} tidak ditemukan — lewati.");
                continue;
            }

            $subCode = $blok . $grup;  // e.g. "1A", "4G"

            $subRack = Rack::where('warehouse_id', $warehouseId)
                ->where('code', $subCode)
                ->first();

            if (!$subRack) {
                if (!$this->dryRun) {
                    $subRack = Rack::create([
                        'warehouse_id'  => $warehouseId,
                        'code'          => $subCode,
                        'name'          => "Rak {$blok} Grup {$grup}",
                        'total_levels'  => 9,
                        'total_columns' => 7,
                        'pos_x'         => $parentRack->pos_x,
                        'pos_z'         => $parentRack->pos_z,
                        'is_active'     => true,
                    ]);
                }
                $racksCreated++;
            } else {
                if (!$this->dryRun && !$subRack->is_active) {
                    $subRack->update(['is_active' => true]);
                }
                $racksReused++;
            }

            $subRackMap[$key] = $subRack;
        }

        $this->line("  Sub-rak dibuat : {$racksCreated}");
        $this->line("  Sub-rak dipakai: {$racksReused}");

        $obsoleteDeactivated = 0;
        $validSubCodes = array_map(fn($pair) => $pair['blok'] . $pair['grup'], $pairs);
        $obsoleteSubRacks = Rack::where('warehouse_id', $warehouseId)
            ->whereRaw("code REGEXP '^[0-9]+[A-H]$'")
            ->whereNotIn('code', $validSubCodes)
            ->withCount(['cells as stock_records_count' => function ($q) {
                $q->join('stock_records', 'stock_records.cell_id', '=', 'cells.id');
            }])
            ->get();

        foreach ($obsoleteSubRacks as $rack) {
            if ($rack->stock_records_count > 0) {
                $this->warn("  Sub-rak {$rack->code} tidak ada lokasi valid, tetapi masih punya stok. Tidak dinonaktifkan.");
                continue;
            }
            if (!$this->dryRun) {
                Cell::where('rack_id', $rack->id)->update(['is_active' => false, 'capacity_used' => 0, 'status' => 'available']);
                $rack->update(['is_active' => false]);
            }
            $obsoleteDeactivated++;
        }
        if ($obsoleteDeactivated > 0) {
            $this->line("  Sub-rak invalid dinonaktifkan: {$obsoleteDeactivated}");
        }
        $this->newLine();

        // 8. Create cells per (kolom, baris) in each sub-rack
        $this->info('Membuat sel per (kolom, baris)...');
        $cellsCreated = 0;
        $cellsReused  = 0;

        // Build the full physical grid for each blok-grup pair: 7 columns x 9 rows.
        // Empty cells must exist so the 3D plan can hover/select every physical column.
        $cellSpecs = [];
        foreach ($pairs as $pair) {
            for ($kolom = 1; $kolom <= 7; $kolom++) {
                for ($baris = 1; $baris <= 9; $baris++) {
                    $loc = [
                        'blok' => $pair['blok'],
                        'grup' => $pair['grup'],
                        'kolom' => $kolom,
                        'baris' => $baris,
                    ];
                    $key  = $loc['blok'] . '_' . $loc['grup'];
                    $cKey = $key . '_' . $loc['kolom'] . '_' . $loc['baris'];
                    $cellSpecs[$cKey] = $loc;
                }
            }
        }

        $cellMap = [];  // "blok_grup_kolom_baris" => cell_id
        foreach ($cellSpecs as $cKey => $loc) {
            $rackKey = $loc['blok'] . '_' . $loc['grup'];
            $subRack = $subRackMap[$rackKey] ?? null;
            if (!$subRack) continue;

            $cellCode = "{$loc['blok']}-{$loc['grup']}-{$loc['kolom']}-{$loc['baris']}";

            $cell = Cell::where('rack_id', $subRack->id)
                ->where('level', $loc['baris'])
                ->where('column', $loc['kolom'])
                ->first();

            if (!$cell) {
                if (!$this->dryRun) {
                    $cell = Cell::create([
                        'rack_id'      => $subRack->id,
                        'code'         => $cellCode,
                        'label'        => $cellCode,
                        'level'        => $loc['baris'],
                        'column'       => $loc['kolom'],
                        'blok'         => $loc['blok'],
                        'grup'         => $loc['grup'],
                        'kolom'        => $loc['kolom'],
                        'baris'        => $loc['baris'],
                        'capacity_max' => 20,
                        'capacity_used'=> 0,
                        'status'       => 'available',
                        'is_active'    => true,
                    ]);
                }
                $cellsCreated++;
            } else {
                if (!$this->dryRun) {
                    $cell->update([
                        'blok'  => $loc['blok'],
                        'grup'  => $loc['grup'],
                        'kolom' => $loc['kolom'],
                        'baris' => $loc['baris'],
                        'is_active' => true,
                    ]);
                }
                $cellsReused++;
            }

            if ($cell) {
                $cellMap[$cKey] = $cell->id;
            }
        }

        $this->line("  Sel dibuat : {$cellsCreated}");
        $this->line("  Sel dipakai: {$cellsReused}");
        $this->newLine();

        // 9. Assign items to cells
        if (!empty($locationMap)) {
            $this->info('Menghubungkan item ke sel...');
            $assigned = 0;
            $skipped  = 0;
            $synced   = 0;
            $removed  = 0;
            $invalidRemoved = 0;
            $touchedCellIds = [];

            foreach ($locationMap as $kode => $loc) {
                $item = Item::where('sku', $kode)->first();
                if (!$item) { $skipped++; continue; }

                $cKey   = $loc['blok'] . '_' . $loc['grup'] . '_' . $loc['kolom'] . '_' . $loc['baris'];
                $cellId = $cellMap[$cKey] ?? null;
                if (!$cellId) { $skipped++; continue; }

                $qty = (int) round($stockAgg[$kode] ?? 0);
                if ($qty <= 0 && !empty($stockAgg)) { $skipped++; continue; }

                if (!$syncStock) {
                    // Skip if stock already assigned to this cell
                    if (Stock::where('item_id', $item->id)->where('cell_id', $cellId)->exists()) {
                        $skipped++;
                        continue;
                    }
                }

                if (!$this->dryRun) {
                    if ($syncStock) {
                        $stale = Stock::where('item_id', $item->id)
                            ->where('cell_id', '!=', $cellId)
                            ->where(function ($q) {
                                $q->where('batch_no', 'like', 'IMPORT-%')
                                  ->orWhere('batch_no', 'like', 'MSPART-%');
                            });
                        $oldCellIds = $stale->pluck('cell_id')->all();
                        $deleted = $stale->delete();
                        $removed += $deleted;
                        $touchedCellIds = array_merge($touchedCellIds, $oldCellIds);
                    }

                    Stock::updateOrCreate(
                        ['item_id' => $item->id, 'cell_id' => $cellId],
                        [
                            'warehouse_id' => $warehouseId,
                            'quantity'     => max(1, $qty),
                            'inbound_date' => now()->toDateString(),
                            'status'       => 'available',
                            'batch_no'     => 'MSPART-' . now()->format('Ymd'),
                        ]
                    );

                    // Update cell capacity
                    $used = Stock::where('cell_id', $cellId)->where('status', 'available')->count();
                    Cell::where('id', $cellId)->update([
                        'capacity_used' => $used,
                        'status'        => $used > 0 ? 'partial' : 'available',
                    ]);
                    $touchedCellIds[] = $cellId;
                }
                $syncStock ? $synced++ : $assigned++;
            }

            $this->line("  Item dihubungkan : {$assigned}");
            if ($syncStock && $invalidLocations) {
                foreach ($invalidLocations as $bad) {
                    $item = Item::where('sku', $bad['kode'])->first();
                    if (!$item) continue;
                    if (!$this->dryRun) {
                        $stale = Stock::where('item_id', $item->id)
                            ->where('batch_no', 'like', 'MSPART-%')
                            ->whereHas('cell', fn($q) => $q->whereNotNull('grup'));
                        $oldCellIds = $stale->pluck('cell_id')->all();
                        $deleted = $stale->delete();
                        $invalidRemoved += $deleted;
                        $touchedCellIds = array_merge($touchedCellIds, $oldCellIds);
                    }
                }
            }
            if ($syncStock) {
                $this->line("  Stock disinkron  : {$synced}");
                $this->line("  Stock lama hapus : {$removed}");
                $this->line("  Stock invalid hapus: {$invalidRemoved}");
            }
            $this->line("  Item dilewati    : {$skipped}");

            if (!$this->dryRun && $syncStock && $touchedCellIds) {
                foreach (array_unique(array_filter($touchedCellIds)) as $cid) {
                    $used = Stock::where('cell_id', $cid)->where('status', 'available')->count();
                    Cell::where('id', $cid)->update([
                        'capacity_used' => $used,
                        'status'        => $used > 0 ? 'partial' : 'available',
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info($this->dryRun ? 'Dry run selesai.' : 'Import layout mspart selesai!');
        $this->line('Buka visualisasi 3D di /warehouse-3d untuk melihat hasilnya.');

        return self::SUCCESS;
    }

    // ── SQL Parser ─────────────────────────────────────────────────────────────

    private function parseSqlInserts(string $path, string $tableName = 'mspart'): array
    {
        $content = file_get_contents($path);
        $rows    = [];

        preg_match(
            '/insert\s+into\s+`?' . preg_quote($tableName, '/') . '`?\s*\(([^)]+)\)\s+values/i',
            $content,
            $colMatch
        );
        if (empty($colMatch[1])) return [];

        $columns = array_map(fn($c) => trim($c, " `'\"\t"), explode(',', $colMatch[1]));

        preg_match_all(
            '/insert\s+into\s+`?' . preg_quote($tableName, '/') . '`?\s*\([^)]+\)\s+values\s*(\(.*?\));/is',
            $content,
            $matches
        );

        foreach ($matches[1] as $valueGroup) {
            $values = $this->parseValuesTuple($valueGroup);
            if (count($values) === count($columns)) {
                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    private function parseValuesTuple(string $tuple): array
    {
        $tuple   = preg_replace('/^\s*\(\s*/', '', $tuple);
        $tuple   = preg_replace('/\s*\)\s*$/', '', $tuple);
        $values  = [];
        $current = '';
        $inStr   = false;
        $len     = strlen($tuple);

        for ($i = 0; $i < $len; $i++) {
            $ch = $tuple[$i];

            if (!$inStr && $ch === 'N' && substr($tuple, $i, 4) === 'NULL') {
                $values[] = null;
                $i += 3;
                while ($i + 1 < $len && $tuple[$i + 1] === ',') { $i++; break; }
                $current = '';
                continue;
            }

            if ($ch === "'" && !$inStr) { $inStr = true; continue; }

            if ($ch === "'" && $inStr) {
                if ($i > 0 && $tuple[$i - 1] === '\\') { $current .= "'"; continue; }
                $values[] = stripcslashes($current);
                $current  = '';
                $inStr    = false;
                while ($i + 1 < $len && in_array($tuple[$i + 1], [',', ' '])) {
                    if ($tuple[$i + 1] === ',') { $i++; break; }
                    $i++;
                }
                continue;
            }

            if ($inStr) $current .= $ch;
        }

        return $values;
    }

    private function resolvePath(?string $option, string $filename): ?string
    {
        if ($option) return $option;
        $candidates = array_filter([
            isset($_SERVER['USERPROFILE']) ? $_SERVER['USERPROFILE'] . "\\Downloads\\{$filename}" : null,
            isset($_SERVER['HOME'])        ? $_SERVER['HOME'] . "/Downloads/{$filename}"           : null,
            base_path($filename),
        ]);
        foreach ($candidates as $path) {
            if ($path && file_exists($path)) return $path;
        }
        return null;
    }
}
