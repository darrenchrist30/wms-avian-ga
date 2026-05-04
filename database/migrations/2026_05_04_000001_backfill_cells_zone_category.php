<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill kolom zone_category pada tabel cells.
 *
 * Kolom zone_category ditambahkan di migration 2026_04_14 tetapi seeder
 * tidak mengisinya. Akibatnya FC_CAT di GA engine selalu jatuh ke skor 0
 * untuk cells yang zone_category-nya kosong (fallback GaService ke
 * rack.zone.code hanya berlaku di payload Python, bukan di tabel).
 *
 * Migration ini mengisi zone_category dari kode zona rack masing-masing cell.
 */
class BackfillCellsZoneCategory extends Migration
{
    public function up(): void
    {
        // Update semua cell yang zone_category-nya masih null
        // dengan kode zona dari rack → zone (single query via JOIN)
        DB::statement("
            UPDATE cells
            INNER JOIN racks  ON racks.id  = cells.rack_id
            INNER JOIN zones  ON zones.id  = racks.zone_id
            SET cells.zone_category = zones.code
            WHERE cells.zone_category IS NULL
        ");
    }

    public function down(): void
    {
        DB::table('cells')->update(['zone_category' => null]);
    }
}
