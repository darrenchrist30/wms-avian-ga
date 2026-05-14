<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\Item;
use App\Models\Rack;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class MspartImportController extends Controller
{
    private array $numToLetter = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '5' => 'E', '6' => 'F', '7' => 'G'];
    private array $validGrup   = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    public function index()
    {
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        return view('location.mspart.import', compact('warehouses'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'mspart_file'   => 'required|file|max:20480',
            'stock_file'    => 'nullable|file|max:20480',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'dry_run'       => 'nullable|boolean',
        ]);

        $dryRun      = (bool) $request->input('dry_run', false);
        $warehouseId = (int) $request->input('warehouse_id');
        $log         = [];

        // 1. Parse MSpart file
        $mspartContent = file_get_contents($request->file('mspart_file')->getRealPath());
        $mspartRows    = $this->parseSqlInserts($mspartContent, 'mspart');

        if (empty($mspartRows)) {
            return response()->json(['error' => 'File MSpart.sql tidak dapat diparse. Pastikan format INSERT INTO mspart (...) VALUES (...) sudah benar.'], 422);
        }

        // Filter valid rows
        $located = array_filter($mspartRows, function ($r) {
            if (($r['isdel'] ?? '1') !== '0') return false;
            $blok = trim($r['blok'] ?? '');
            $grup = strtoupper(trim($r['grup'] ?? ''));
            if ($blok === '' || $blok === 'NULL' || !ctype_digit($blok) || (int) $blok <= 0) return false;
            if (isset($this->numToLetter[$grup])) $grup = $this->numToLetter[$grup];
            return in_array($grup, $this->validGrup);
        });

        $log[] = ['type' => 'info', 'msg' => 'Total baris MSpart.sql: ' . count($mspartRows)];
        $log[] = ['type' => 'info', 'msg' => 'Aktif + punya lokasi valid: ' . count($located)];

        if (empty($located)) {
            return response()->json(['error' => 'Tidak ada item dengan data lokasi valid di MSpart.sql.'], 422);
        }

        // 2. Build location map
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

        // 3. Parse StockSpart file (optional)
        $stockAgg = [];
        if ($request->hasFile('stock_file')) {
            $stockContent = file_get_contents($request->file('stock_file')->getRealPath());
            $stockRows    = $this->parseSqlInserts($stockContent, 'Qspart');
            foreach ($stockRows as $r) {
                $kode = trim($r['kode'] ?? '');
                $qty  = (float) ($r['stock'] ?? 0);
                if (!$kode || $qty <= 0) continue;
                $stockAgg[$kode] = ($stockAgg[$kode] ?? 0) + $qty;
            }
            $log[] = ['type' => 'info', 'msg' => 'Item dengan stok > 0 dari StockSpart.sql: ' . count(array_filter($stockAgg))];
        }

        // 3b. Cross-check SKU overlap with WMS items table
        $mspartKodes  = array_keys($locationMap);
        $matchedKodes = Item::whereIn('sku', $mspartKodes)->pluck('sku')->all();
        $matchedCount = count($matchedKodes);
        $unmatchedCount = count($mspartKodes) - $matchedCount;
        $log[] = [
            'type' => $unmatchedCount > 0 ? 'warn' : 'success',
            'msg'  => "SKU MSpart cocok di tabel items: {$matchedCount} / " . count($mspartKodes) .
                      ($unmatchedCount > 0 ? " — {$unmatchedCount} SKU tidak ditemukan di items (akan dilewati saat assign stok)" : ''),
        ];

        // 4. Find unique (blok, grup) pairs
        $pairs = [];
        foreach ($locationMap as $loc) {
            $key          = $loc['blok'] . '_' . $loc['grup'];
            $pairs[$key]  = ['blok' => $loc['blok'], 'grup' => $loc['grup']];
        }
        $log[] = ['type' => 'info', 'msg' => 'Pasangan (blok, grup) unik: ' . count($pairs)];

        // 5. Create / reuse sub-racks
        $subRackMap   = [];
        $racksCreated = 0;
        $racksReused  = 0;

        foreach ($pairs as $key => $pair) {
            $blok = $pair['blok'];
            $grup = $pair['grup'];

            $parentRack = Rack::whereHas('zone', fn($q) => $q->where('warehouse_id', $warehouseId))
                ->where('code', (string) $blok)
                ->first();

            if (!$parentRack) {
                $log[] = ['type' => 'warn', 'msg' => "Rak induk blok {$blok} tidak ditemukan — lewati."];
                continue;
            }

            $subCode = $blok . $grup;

            $subRack = Rack::where('zone_id', $parentRack->zone_id)
                ->where('code', $subCode)
                ->first();

            if (!$subRack) {
                if (!$dryRun) {
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

        $log[] = ['type' => 'success', 'msg' => "Sub-rak dibuat: {$racksCreated}, dipakai ulang: {$racksReused}"];

        // 6. Create cells per (kolom, baris)
        $cellSpecs = [];
        foreach ($locationMap as $loc) {
            $key  = $loc['blok'] . '_' . $loc['grup'];
            $cKey = $key . '_' . $loc['kolom'] . '_' . $loc['baris'];
            $cellSpecs[$cKey] = $loc;
        }

        $cellMap      = [];
        $cellsCreated = 0;
        $cellsReused  = 0;

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
                if (!$dryRun) {
                    $cell = Cell::create([
                        'rack_id'       => $subRack->id,
                        'code'          => $cellCode,
                        'label'         => $cellCode,
                        'level'         => $loc['baris'],
                        'column'        => $loc['kolom'],
                        'blok'          => $loc['blok'],
                        'grup'          => $loc['grup'],
                        'kolom'         => $loc['kolom'],
                        'baris'         => $loc['baris'],
                        'capacity_max'  => 20,
                        'capacity_used' => 0,
                        'status'        => 'available',
                        'is_active'     => true,
                    ]);
                }
                $cellsCreated++;
            } else {
                if (!$dryRun && ($cell->blok !== $loc['blok'] || $cell->grup !== $loc['grup'])) {
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

        $log[] = ['type' => 'success', 'msg' => "Sel dibuat: {$cellsCreated}, dipakai ulang: {$cellsReused}"];

        // 7. Assign stock to cells
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

            if (Stock::where('item_id', $item->id)->where('cell_id', $cellId)->exists()) {
                $skipped++;
                continue;
            }

            if (!$dryRun) {
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

                $used = Stock::where('cell_id', $cellId)->where('status', 'available')->count();
                Cell::where('id', $cellId)->update([
                    'capacity_used' => $used,
                    'status'        => $used > 0 ? 'partial' : 'available',
                ]);
            }
            $assigned++;
        }

        $log[] = ['type' => 'success', 'msg' => "Item dihubungkan ke sel: {$assigned}, dilewati: {$skipped}"];

        return response()->json([
            'dry_run' => $dryRun,
            'summary' => [
                'mspart_rows'    => count($mspartRows),
                'located_items'  => count($located),
                'matched_skus'   => $matchedCount,
                'unmatched_skus' => $unmatchedCount,
                'racks_created'  => $racksCreated,
                'racks_reused'   => $racksReused,
                'cells_created'  => $cellsCreated,
                'cells_reused'   => $cellsReused,
                'stock_assigned' => $assigned,
                'stock_skipped'  => $skipped,
            ],
            'log' => $log,
        ]);
    }

    // ── SQL parser (same logic as artisan command) ──────────────────────────────

    private function parseSqlInserts(string $content, string $tableName = 'mspart'): array
    {
        $rows = [];

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
}
