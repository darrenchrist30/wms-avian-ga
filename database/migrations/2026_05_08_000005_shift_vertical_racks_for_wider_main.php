<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Geser rak vertikal 12–15 lebih jauh ke kanan karena rak utama diperlebar (WW=14 m).
 * Rak utama sekarang span X=-4 s/d X=10 → beri gap 3 m → rak 12 mulai di X=14.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x = 15, pos_z = 16 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = 17, pos_z = 16 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 19, pos_z = 16 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 21, pos_z = 16 WHERE code = '15'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 16 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = 14, pos_z = 16 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 16, pos_z = 16 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 18, pos_z = 16 WHERE code = '15'");
    }
};
