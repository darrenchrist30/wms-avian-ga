<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Stock;
use App\Services\CellCapacityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOpeningStock extends Command
{
    protected $signature = 'import:opening-stock
                            {--file= : Path to MSpart SQL file (default: storage/app/mspart.sql)}
                            {--date= : Opening stock inbound_date YYYY-MM-DD (default: today)}
                            {--dry-run : Preview what would be imported without writing}
                            {--force : Re-import even if item already has stock at its home cell}';

    protected $description = 'Import opening stock quantities from MSpart SQL into stock_records using home_cell_id';

    public function handle(CellCapacityService $capacityService): int
    {
        $filePath = $this->option('file') ?: storage_path('app/mspart.sql');
        $dryRun   = (bool) $this->option('dry-run');
        $force    = (bool) $this->option('force');
        $date     = $this->option('date') ?: now()->toDateString();

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->line('  Place the MSpart SQL dump at: ' . storage_path('app/mspart.sql'));
            $this->line('  Or specify: --file=/absolute/path/to/mspart.sql');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No data will be written to the database.');
        }

        $this->info("Reading: {$filePath}");
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Collect only INSERT INTO mspart lines
        $insertLines = array_filter($lines, fn($l) => preg_match('/insert\s+into\s+`?mspart`?/i', $l));

        if (empty($insertLines)) {
            $this->error('No INSERT rows for mspart found. Check the file format.');
            return self::FAILURE;
        }

        $total = count($insertLines);
        $this->info("{$total} MSpart rows found. Opening stock date: {$date}");
        $this->newLine();

        $stats    = array_fill_keys(['imported', 'no_item', 'no_cell', 'has_stock', 'zero_qty', 'deleted', 'errors'], 0);
        $warnings = [];

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        foreach ($insertLines as $line) {
            $bar->advance();

            $values = $this->extractValues($line);
            if (count($values) < 12) {
                continue;
            }

            // Column order: kode, nama, stok, sat, minstok, maxstok, blok, grup, kolom, baris, note, isdel
            $kode  = (string) ($values[0] ?? '');
            $stok  = $values[2];
            $isdel = (string) ($values[11] ?? '0');

            if ($isdel === '1') {
                $stats['deleted']++;
                continue;
            }

            $qty = (int) round((float) ($stok ?? 0));
            if ($qty <= 0) {
                $stats['zero_qty']++;
                continue;
            }

            // Find item and eager-load homeCell with rack (for warehouse_id)
            $item = Item::where('sku', strtoupper($kode))
                ->with('homeCell.rack')
                ->first();

            if (!$item || !$item->home_cell_id) {
                $stats['no_item']++;
                continue;
            }

            $cell = $item->homeCell;
            if (!$cell || !$cell->is_active) {
                $stats['no_cell']++;
                continue;
            }

            if (!$force) {
                $alreadyHasStock = Stock::where('item_id', $item->id)
                    ->where('cell_id', $cell->id)
                    ->whereIn('status', ['available', 'reserved'])
                    ->where('quantity', '>', 0)
                    ->exists();

                if ($alreadyHasStock) {
                    $stats['has_stock']++;
                    continue;
                }
            }

            // Warn if import would overflow the cell
            $points    = $capacityService->pointsForQuantity($item, $qty);
            $capMax    = $capacityService->capacityMax($cell);
            $capUsed   = $capacityService->usedPoints($cell);
            if ($capUsed + $points > $capMax) {
                $warnings[] = sprintf(
                    '  OVERFLOW %s → %s: needs %d pts, only %d free (cap_max=%d)',
                    $kode, $cell->code, $points, $capMax - $capUsed, $capMax
                );
            }

            if ($dryRun) {
                $warnings[] = sprintf('  WOULD IMPORT: %-30s → %-15s qty=%d pts=%d', $kode, $cell->code, $qty, $points);
                $stats['imported']++;
                continue;
            }

            DB::beginTransaction();
            try {
                Stock::create([
                    'item_id'               => $item->id,
                    'cell_id'               => $cell->id,
                    'warehouse_id'          => $cell->rack?->warehouse_id,
                    'inbound_order_item_id' => null,
                    'lpn'                   => null,
                    'batch_no'              => 'OPENING',
                    'quantity'              => $qty,
                    'inbound_date'          => $date,
                    'status'                => 'available',
                ]);

                $capacityService->refresh($cell);

                DB::commit();
                $stats['imported']++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $warnings[] = "  ERROR {$kode}: " . $e->getMessage();
                $stats['errors']++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        if (!empty($warnings)) {
            foreach ($warnings as $w) {
                $this->line($w);
            }
            $this->newLine();
        }

        $action = $dryRun ? 'Would import' : 'Imported';
        $this->info('=== Opening Stock Import Summary ===');
        $this->line("  {$action}          : {$stats['imported']}");
        $this->line("  No item / home_cell : {$stats['no_item']}");
        $this->line("  Cell inactive       : {$stats['no_cell']}");
        $this->line("  Already has stock   : {$stats['has_stock']} (skipped, use --force to re-import)");
        $this->line("  Zero quantity       : {$stats['zero_qty']}");
        $this->line("  Deleted in MSpart   : {$stats['deleted']}");
        $this->line("  Errors              : {$stats['errors']}");

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Extract the value tokens from a single INSERT INTO mspart ... VALUES (...); line.
     *
     * Handles:
     *  - Quoted strings:  'SEAL TAPE (TBA)'  — parentheses inside quotes are safe
     *  - NULL literals
     *  - Escaped quotes:  'O\'Brien'  (backslash-escaped)
     */
    private function extractValues(string $line): array
    {
        // Locate 'values' keyword and take everything after it
        $pos = stripos($line, 'values');
        if ($pos === false) {
            return [];
        }

        $after = ltrim(substr($line, $pos + 6)); // skip 'values' + leading space

        // Strip leading '(' and trailing ')' / ');'
        if ($after === '' || $after[0] !== '(') {
            return [];
        }

        $after = substr($after, 1);                    // remove leading (
        $after = rtrim($after, " \t\r\n;");            // remove trailing whitespace / ;
        if (substr($after, -1) === ')') {
            $after = substr($after, 0, -1);            // remove trailing )
        }

        return $this->parseTokens($after);
    }

    /**
     * State-machine CSV-like parser for SQL value lists.
     * Correctly handles quoted strings with escaped characters.
     */
    private function parseTokens(string $raw): array
    {
        $values = [];
        $i      = 0;
        $len    = strlen($raw);

        while ($i < $len) {
            // skip whitespace / commas between tokens
            while ($i < $len && in_array($raw[$i], [',', ' ', "\t", "\r", "\n"], true)) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }

            if (strtoupper(substr($raw, $i, 4)) === 'NULL') {
                $values[] = null;
                $i       += 4;
            } elseif ($raw[$i] === "'") {
                $i++;   // skip opening quote
                $val = '';
                while ($i < $len) {
                    if ($raw[$i] === '\\' && $i + 1 < $len) {
                        // backslash escape
                        $val .= $raw[$i + 1];
                        $i   += 2;
                    } elseif ($raw[$i] === "'" && $i + 1 < $len && $raw[$i + 1] === "'") {
                        // doubled-quote escape  ''
                        $val .= "'";
                        $i   += 2;
                    } elseif ($raw[$i] === "'") {
                        $i++;   // skip closing quote
                        break;
                    } else {
                        $val .= $raw[$i];
                        $i++;
                    }
                }
                $values[] = $val;
            } else {
                // unquoted token (number or keyword)
                $start = $i;
                while ($i < $len && $raw[$i] !== ',') {
                    $i++;
                }
                $values[] = trim(substr($raw, $start, $i - $start));
            }
        }

        return $values;
    }
}
