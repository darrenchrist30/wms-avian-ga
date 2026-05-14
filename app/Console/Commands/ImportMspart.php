<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan Command: import:mspart
 *
 * Import master sparepart dari file SQL perusahaan ke WMS.
 *   - MSpart.sql  → tabel `items` (master data)
 *   - StockSpart.sql → tabel `stock_records` (stok awal)
 *
 * Cara pakai:
 *   php artisan import:mspart
 *   php artisan import:mspart --mspart="C:/path/MSpart.sql" --stock="C:/path/StockSpart.sql"
 *   php artisan import:mspart --dry-run
 */
class ImportMspart extends Command
{
    protected $signature = 'import:mspart
                            {--mspart=  : Path ke file MSpart.sql (default: Downloads)}
                            {--stock=   : Path ke file StockSpart.sql (default: Downloads)}
                            {--cell-id= : ID sel untuk stok awal (skip jika tidak disediakan)}
                            {--dry-run  : Preview tanpa menyimpan ke database}';

    protected $description = 'Import master sparepart dari MSpart.sql dan StockSpart.sql ke WMS';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('=== DRY RUN MODE — tidak ada perubahan yang disimpan ===');
        }

        $this->info('=== Import Master Sparepart ke WMS ===');
        $this->newLine();

        // 1. Resolve file paths
        $mspartPath = $this->resolvePath($this->option('mspart'), 'MSpart.sql');
        $stockPath  = $this->resolvePath($this->option('stock'), 'StockSpart.sql');

        if (!$mspartPath || !file_exists($mspartPath)) {
            $this->error("File MSpart.sql tidak ditemukan: {$mspartPath}");
            return self::FAILURE;
        }
        $this->line("MSpart.sql  : {$mspartPath}");

        $hasStock = $stockPath && file_exists($stockPath);
        if ($hasStock) {
            $this->line("StockSpart.sql: {$stockPath}");
        } else {
            $this->warn('StockSpart.sql tidak ditemukan — hanya master item yang akan diimport.');
        }
        $this->newLine();

        // 2. Parse SQL files
        $this->info('Membaca file SQL...');
        $mspartRows = $this->parseSqlInserts($mspartPath, 'mspart');
        $this->line("  mspart     : " . count($mspartRows) . " baris ditemukan");

        $stockRows = [];
        if ($hasStock) {
            $stockRows = $this->parseSqlInserts($stockPath, 'Qspart');
            $this->line("  Qspart     : " . count($stockRows) . " baris ditemukan");
        }
        $this->newLine();

        if (empty($mspartRows)) {
            $this->error('Tidak ada data di MSpart.sql — periksa file.');
            return self::FAILURE;
        }

        // 3. Filter active items only (isdel = '0')
        $mspartActive = array_filter($mspartRows, fn($r) => ($r['isdel'] ?? '1') === '0');
        $this->line('Item aktif (isdel=0) di mspart: ' . count($mspartActive));

        // 4. Get or create default category
        $category = $this->ensureDefaultCategory();
        $this->line("Kategori default: [{$category->id}] {$category->name}");

        // 5. Collect unique units
        $unitCodes = [];
        foreach ($mspartActive as $r) {
            $sat = strtoupper(trim($r['sat'] ?? ''));
            if ($sat) $unitCodes[$sat] = true;
        }
        // Also from stock rows (items only in qspart)
        foreach ($stockRows as $r) {
            $sat = strtoupper(trim($r['sat'] ?? ''));
            if ($sat) $unitCodes[$sat] = true;
        }

        // 6. Ensure units exist
        $unitMap = $this->ensureUnits(array_keys($unitCodes));
        $this->line('Unit diproses: ' . count($unitMap));

        // 7. Confirm before importing
        $this->newLine();
        if (!$this->dryRun && !$this->confirm('Lanjutkan import item?', true)) {
            $this->info('Import dibatalkan.');
            return self::SUCCESS;
        }

        // 8. Import items
        $this->newLine();
        $this->info('Mengimport items dari mspart...');
        [$itemsCreated, $itemsSkipped] = $this->importItems($mspartActive, $category, $unitMap);
        $this->line("  Dibuat  : {$itemsCreated}");
        $this->line("  Dilewati (sudah ada): {$itemsSkipped}");

        // 9. Import stock if file available
        if ($hasStock && !empty($stockRows)) {
            $this->newLine();
            $this->info('Memproses stok dari StockSpart.sql...');

            // Sum duplicates and filter stock > 0
            $aggregated = [];
            foreach ($stockRows as $r) {
                $kode  = trim($r['kode'] ?? '');
                $stock = (float) ($r['stock'] ?? 0);
                if (!$kode) continue;
                if (!isset($aggregated[$kode])) {
                    $aggregated[$kode] = ['kode' => $kode, 'nama' => $r['nama'], 'sat' => $r['sat'], 'stock' => 0.0, 'minstock' => $r['minstock'], 'maxstock' => $r['maxstock']];
                }
                $aggregated[$kode]['stock'] += $stock;
            }

            $withStock = array_filter($aggregated, fn($r) => $r['stock'] > 0);
            $this->line('Item dengan stok > 0: ' . count($withStock));

            // Ensure items from qspart that aren't in mspart
            $qspartOnlyItems = array_filter($withStock, fn($r) => !Item::where('sku', $r['kode'])->exists());
            if (!empty($qspartOnlyItems)) {
                $this->line('Item di StockSpart tapi tidak di mspart: ' . count($qspartOnlyItems) . ' → akan dibuat juga');
                [$c2, $s2] = $this->importItems($qspartOnlyItems, $category, $unitMap, isQspart: true);
                $itemsCreated += $c2;
            }

            // Pick starting cell (auto-distribute from there)
            $startCellId = $this->option('cell-id') ? (int) $this->option('cell-id') : null;
            if (!$startCellId && !$this->dryRun) {
                $startCellId = $this->pickCell();
            }

            if (!$startCellId) {
                $this->warn('Tidak ada sel dipilih — import stok dilewati. Item sudah diimport.');
            } else {
                $startCell = Cell::find($startCellId);
                if (!$startCell) {
                    $this->error("Sel ID {$startCellId} tidak ditemukan.");
                } else {
                    // Load cells starting from picked cell (same warehouse, active, has capacity)
                    $warehouseId = $startCell->rack?->zone?->warehouse_id;
                    $availCells  = Cell::with('rack.zone')
                        ->where('is_active', true)
                        ->where('status', '!=', 'blocked')
                        ->whereHas('rack.zone', fn($q) => $q->where('warehouse_id', $warehouseId))
                        ->where(fn($q) => $q->where('id', '>=', $startCellId)->orWhere('capacity_used', '<', DB::raw('capacity_max')))
                        ->orderByRaw('id >= ? DESC', [$startCellId])
                        ->orderBy('id')
                        ->get();

                    $totalCapacity = $availCells->sum(fn($c) => $c->capacity_max - $c->capacity_used);
                    $itemCount     = count($withStock);

                    $this->newLine();
                    $this->line("Mendistribusikan {$itemCount} item ke {$availCells->count()} sel (kapasitas total tersedia: {$totalCapacity})");

                    if ($totalCapacity < $itemCount) {
                        $this->warn("Kapasitas tersedia ({$totalCapacity}) < jumlah item ({$itemCount}). Beberapa item mungkin tidak tersimpan.");
                    }

                    if (!$this->dryRun && !$this->confirm("Lanjutkan import stok?", true)) {
                        $this->info('Import stok dibatalkan.');
                    } else {
                        [$sCreated, $sSkipped] = $this->importStockDistributed($withStock, $availCells, $warehouseId);
                        $this->line("  Stok dibuat  : {$sCreated}");
                        $this->line("  Stok dilewati: {$sSkipped}");
                    }
                }
            }
        }

        $this->newLine();
        $this->info($this->dryRun ? 'Dry run selesai — tidak ada perubahan disimpan.' : 'Import selesai!');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────

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

    private function parseSqlInserts(string $path, string $tableName): array
    {
        $content = file_get_contents($path);
        $rows    = [];

        // Find column names from CREATE TABLE or first INSERT
        preg_match(
            '/insert\s+into\s+`?' . preg_quote($tableName, '/') . '`?\s*\(([^)]+)\)\s+values/i',
            $content,
            $colMatch
        );
        if (empty($colMatch[1])) return [];

        $columns = array_map(
            fn($c) => trim($c, " `'\"\t"),
            explode(',', $colMatch[1])
        );

        // Extract all INSERT lines for this table
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
        // Strip outer parentheses
        $tuple = preg_replace('/^\s*\(\s*/', '', $tuple);
        $tuple = preg_replace('/\s*\)\s*$/', '', $tuple);

        $values  = [];
        $current = '';
        $inStr   = false;
        $len     = strlen($tuple);

        for ($i = 0; $i < $len; $i++) {
            $ch = $tuple[$i];

            if (!$inStr && $ch === 'N' && substr($tuple, $i, 4) === 'NULL') {
                $values[] = null;
                $i += 3;
                // skip optional comma
                while ($i + 1 < $len && $tuple[$i + 1] === ',') {
                    $i++;
                    break;
                }
                $current = '';
                continue;
            }

            if ($ch === "'" && !$inStr) {
                $inStr = true;
                continue;
            }

            if ($ch === "'" && $inStr) {
                // Check for escaped quote \'  (previous char was backslash)
                if ($i > 0 && $tuple[$i - 1] === '\\') {
                    $current .= "'";
                    continue;
                }
                $values[] = stripcslashes($current);
                $current  = '';
                $inStr    = false;
                // skip optional comma after closing quote
                while ($i + 1 < $len && in_array($tuple[$i + 1], [',', ' '])) {
                    if ($tuple[$i + 1] === ',') { $i++; break; }
                    $i++;
                }
                continue;
            }

            if ($inStr) {
                $current .= $ch;
            }
        }

        return $values;
    }

    private function ensureDefaultCategory(): ItemCategory
    {
        $existing = ItemCategory::first();
        if ($existing) return $existing;

        if ($this->dryRun) {
            return new ItemCategory(['id' => 0, 'name' => 'Spare Part Umum', 'code' => 'SPART-UMUM']);
        }

        return ItemCategory::firstOrCreate(
            ['code' => 'SPART-UMUM'],
            ['name' => 'Spare Part Umum', 'color_code' => '#6c757d', 'is_active' => true]
        );
    }

    private function ensureUnits(array $codes): array
    {
        $map = [];
        foreach ($codes as $code) {
            $code = strtoupper(trim($code));
            if (!$code) continue;

            $unit = Unit::whereRaw('UPPER(code) = ?', [$code])->first();
            if (!$unit && !$this->dryRun) {
                $unit = Unit::create([
                    'code'      => $code,
                    'name'      => $code,
                    'is_active' => true,
                ]);
            }
            if ($unit) $map[$code] = $unit->id;
        }
        return $map;
    }

    private function importItems(array $rows, ItemCategory $category, array $unitMap, bool $isQspart = false): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            $sku  = trim($r['kode'] ?? '');
            $nama = trim($r['nama'] ?? '');
            $sat  = strtoupper(trim($r['sat'] ?? ''));

            if (!$sku || !$nama) { $skipped++; continue; }

            if (Item::where('sku', $sku)->exists()) { $skipped++; continue; }

            $unitId = $unitMap[$sat] ?? ($unitMap['PCS'] ?? null);
            if (!$unitId) {
                // Use first available unit as fallback
                $unitId = Unit::first()?->id;
            }
            if (!$unitId) { $skipped++; continue; }

            if (!$this->dryRun) {
                $minStock = $isQspart ? (int) ($r['minstock'] ?? 0) : (int) ($r['minstok'] ?? 0);
                $maxStock = $isQspart ? (int) ($r['maxstock'] ?? 0) : (int) ($r['maxstok'] ?? 0);

                Item::create([
                    'category_id'  => $category->id,
                    'unit_id'      => $unitId,
                    'sku'          => $sku,
                    'erp_item_code' => $sku,
                    'name'         => $nama,
                    'min_stock'    => max(0, $minStock),
                    'max_stock'    => max(0, $maxStock),
                    'reorder_point' => max(0, $minStock),
                    'movement_type' => 'slow_moving',
                    'is_active'    => true,
                ]);
            }
            $created++;
        }

        return [$created, $skipped];
    }

    private function importStockDistributed(array $aggregated, $cells, ?int $warehouseId): array
    {
        $created  = 0;
        $skipped  = 0;
        $cellIdx  = 0;
        $cellList = $cells->values();
        $batch    = 'IMPORT-' . now()->format('Ymd');

        // Track how many items are added to each cell during this import
        $addedToCell = [];

        foreach ($aggregated as $r) {
            $kode  = trim($r['kode']);
            $stock = (int) round($r['stock']);

            if ($stock <= 0) { $skipped++; continue; }

            $item = Item::where('sku', $kode)->first();
            if (!$item) { $skipped++; continue; }

            // Find a cell with remaining capacity
            while ($cellIdx < $cellList->count()) {
                $cell     = $cellList[$cellIdx];
                $used     = $cell->capacity_used + ($addedToCell[$cell->id] ?? 0);
                $remaining = $cell->capacity_max - $used;
                if ($remaining > 0) break;
                $cellIdx++;
            }

            if ($cellIdx >= $cellList->count()) {
                $skipped++;
                continue;
            }

            $cell = $cellList[$cellIdx];

            // Skip if already has stock record in this cell
            if (Stock::where('item_id', $item->id)->where('cell_id', $cell->id)->exists()) {
                $skipped++;
                continue;
            }

            if (!$this->dryRun) {
                Stock::create([
                    'item_id'      => $item->id,
                    'cell_id'      => $cell->id,
                    'warehouse_id' => $warehouseId,
                    'quantity'     => $stock,
                    'inbound_date' => now()->toDateString(),
                    'status'       => 'available',
                    'batch_no'     => $batch,
                ]);
            }

            $addedToCell[$cell->id] = ($addedToCell[$cell->id] ?? 0) + 1;
            $created++;
        }

        // Update capacity_used for all affected cells
        if (!$this->dryRun) {
            foreach ($addedToCell as $cid => $added) {
                $c = $cellList->firstWhere('id', $cid);
                if ($c) {
                    $c->capacity_used += $added;
                    $c->updateStatus();
                }
            }
        }

        return [$created, $skipped];
    }

    private function pickCell(): ?int
    {
        $cells = Cell::with('rack.zone.warehouse')
            ->where('is_active', true)
            ->where('status', '!=', 'blocked')
            ->orderBy('id')
            ->limit(30)
            ->get();

        if ($cells->isEmpty()) {
            $this->warn('Tidak ada sel aktif ditemukan di database.');
            return null;
        }

        $choices = $cells->mapWithKeys(function ($cell) {
            $wh    = $cell->rack?->zone?->warehouse?->name ?? '-';
            $label = "[{$cell->id}] {$cell->code} ({$wh}) — kapasitas: {$cell->capacity_used}/{$cell->capacity_max}";
            return [$cell->id => $label];
        })->toArray();

        $this->newLine();
        $this->line('Pilih sel untuk menyimpan stok awal:');
        foreach ($choices as $id => $label) {
            $this->line("  {$label}");
        }
        $this->newLine();

        $cellId = (int) $this->ask('Masukkan ID sel (atau 0 untuk skip import stok)', 0);

        return $cellId > 0 ? $cellId : null;
    }
}
