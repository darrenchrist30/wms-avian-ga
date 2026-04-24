<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill:
     *  1. racks.warehouse_id  ← via zone → warehouse
     *  2. cells.qr_code       ← cells.code (if null)
     */
    public function up(): void
    {
        // 1. racks: set warehouse_id via zone
        DB::statement('
            UPDATE racks
            INNER JOIN zones ON zones.id = racks.zone_id
            SET racks.warehouse_id = zones.warehouse_id
            WHERE racks.warehouse_id IS NULL
              AND racks.deleted_at IS NULL
        ');

        // 2. cells: set qr_code = code if not yet set
        DB::statement('
            UPDATE cells
            SET qr_code = code
            WHERE qr_code IS NULL
              AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        // Intentionally left blank — data backfill rollback not needed
    }
};
