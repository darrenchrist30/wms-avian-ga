<?php

namespace App\Console\Commands;

use App\Models\Cell;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateColumnCells extends Command
{
    protected $signature   = 'cells:generate-columns {--dry-run : Tampilkan tanpa menyimpan}';
    protected $description = 'Generate column-level cell records (blok-grup-kolom, baris=null) dari sel yang sudah ada';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $columns = Cell::whereNotNull('blok')
            ->whereNotNull('grup')
            ->whereNotNull('kolom')
            ->whereNotNull('baris')
            ->where('is_active', true)
            ->selectRaw('blok, UPPER(grup) as grup, kolom, rack_id, MIN(id) as sample_id')
            ->groupBy('blok', 'grup', 'kolom', 'rack_id')
            ->orderBy('blok')
            ->orderByRaw('UPPER(grup)')
            ->orderBy('kolom')
            ->get();

        $this->info("Ditemukan {$columns->count()} kombinasi kolom unik.");

        if ($dryRun) {
            $this->table(['Kode Kolom', 'rack_id'], $columns->map(fn($c) =>
                [$c->blok . '-' . $c->grup . '-' . $c->kolom, $c->rack_id]
            )->toArray());
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($columns as $col) {
                $columnCode = $col->blok . '-' . strtoupper($col->grup) . '-' . $col->kolom;

                $exists = Cell::where('blok', $col->blok)
                    ->whereRaw('UPPER(grup) = ?', [strtoupper($col->grup)])
                    ->where('kolom', $col->kolom)
                    ->whereNull('baris')
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Cell::create([
                    'rack_id'       => $col->rack_id,
                    'code'          => $columnCode,
                    'qr_code'       => $columnCode,
                    'blok'          => $col->blok,
                    'grup'          => strtoupper($col->grup),
                    'kolom'         => (int) $col->kolom,
                    'baris'         => null,
                    'level'         => 0,
                    'column'        => (int) $col->kolom,
                    'capacity_max'  => 0,
                    'capacity_used' => 0,
                    'status'        => 'available',
                    'is_active'     => true,
                ]);

                $created++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Gagal: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Selesai. Dibuat: {$created}, Dilewati (sudah ada): {$skipped}.");
        return self::SUCCESS;
    }
}
