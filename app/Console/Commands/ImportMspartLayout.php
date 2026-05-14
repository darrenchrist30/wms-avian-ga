<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Models\Item;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Zone;
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
                            {--dry-run      : Preview tanpa menyimpan ke database}';

    protected $description = 'Import lokasi fisik mspart (blok/grup/kolom/baris) ke sub-rak WMS';

    private bool $dryRun = false;
    private array $numToLetter = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '5' => 'E', '6' => 'F', '7' => 'G'];
    private array $validGrup   = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

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

        // Filter active + has valid location
        $located = array_filter($mspartRows, function ($r) {
            if (($r['isdel'] ?? '1') !== '0') return false;
            $blok = trim($r['blok'] ?? '');
            $grup = strtoupper(trim($r['grup'] ?? ''));
            if ($blok === '' || $blok === 'NULL' || !ctype_digit($blok) || (int) $blok <= 0) return false;
            if (isset($this->numToLetter[$grup])) $grup = $this->numToLetter[$grup];
            return in_array($grup, $this->validGrup);
        });
        $this->line("  Aktif + punya lokasi: " . count($located));
        $this->newLine();

        if (empty($located)) {
            $this->warn('Tidak ada item dengan data lokasi valid. Periksa file MSpart.sql.');
            return self::SUCCESS;
        }

        // 4. Build location map: kode → [blok, grup, kolom, baris]
        $locationMap = [];
        foreach ($located as $r) {
            $kode = trim($r['kode'] ?? '');
            if (!$kode) continue;

            $blok  = (int) trim($r['blok']);
            $grup  = strtoupper(trim($r['grup']));
            $kolom = (int) (trim($r['kolom'] ?? '1') ?: '1');
            $baris = (int) (trim($r['baris'] ?? '1') ?: '1');

            if (isset($this->numToLetter[$grup])) {
                $grup = $this->numToLetter[$grup];
            }

            $kolom = max(1, min(7, $kolom));
            $baris = max(1, min(9, $baris));

            $locationMap[$kode] = compact('blok', 'grup', 'kolom', 'baris');
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

        // 6. Find unique (blok, grup) pairs
        $pairs = [];
        foreach ($locationMap as $loc) {
            $key = $loc['blok'] . '_' . $loc['grup'];
            $pairs[$key] = ['blok' => $loc['blok'], 'grup' => $loc['grup']];
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

            $parentRack = Rack::whereHas('zone', fn($q) => $q->where('warehouse_id', $warehouseId))
                ->where('code', (string) $blok)
                ->first();

            if (!$parentRack) {
                $this->warn("  Rak induk untuk blok {$blok} tidak ditemukan — lewati.");
                continue;
            }

            $subCode = $blok . $grup;  // e.g. "1A", "4G"

            $subRack = Rack::where('zone_id', $parentRack->zone_id)
                ->where('code', $subCode)
                ->first();

            if (!$subRack) {
                if (!$this->dryRun) {
                    $subRack = Rack::create([
                        'zone_id'       => $parentRack->zone_id,
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
                $racksReused++;
            }

            $subRackMap[$key] = $subRack;
        }

        $this->line("  Sub-rak dibuat : {$racksCreated}");
        $this->line("  Sub-rak dipakai: {$racksReused}");
        $this->newLine();

        // 8. Create cells per (kolom, baris) in each sub-rack
        $this->info('Membuat sel per (kolom, baris)...');
        $cellsCreated = 0;
        $cellsReused  = 0;

        // Collect all unique (blok, grup, kolom, baris) combos
        $cellSpecs = [];
        foreach ($locationMap as $loc) {
            $key  = $loc['blok'] . '_' . $loc['grup'];
            $cKey = $key . '_' . $loc['kolom'] . '_' . $loc['baris'];
            $cellSpecs[$cKey] = $loc;
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
                if (!$this->dryRun && ($cell->blok !== $loc['blok'] || $cell->grup !== $loc['grup'])) {
                    $cell->update([
                        'blok'  => $loc['blok'],
                        'grup'  => $loc['grup'],
                        'kolom' => $loc['kolom'],
                        'baris' => $loc['baris'],
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

            foreach ($locationMap as $kode => $loc) {
                $item = Item::where('sku', $kode)->first();
                if (!$item) { $skipped++; continue; }

                $cKey   = $loc['blok'] . '_' . $loc['grup'] . '_' . $loc['kolom'] . '_' . $loc['baris'];
                $cellId = $cellMap[$cKey] ?? null;
                if (!$cellId) { $skipped++; continue; }

                $qty = (int) round($stockAgg[$kode] ?? 0);
                if ($qty <= 0 && !empty($stockAgg)) { $skipped++; continue; }

                // Skip if stock already assigned to this cell
                if (Stock::where('item_id', $item->id)->where('cell_id', $cellId)->exists()) {
                    $skipped++;
                    continue;
                }

                if (!$this->dryRun) {
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
                }
                $assigned++;
            }

            $this->line("  Item dihubungkan : {$assigned}");
            $this->line("  Item dilewati    : {$skipped}");
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
