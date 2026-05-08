<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Beri jarak lebih lebar antara rak 1 (Z=0) dan area depan (rak 17–20).
 *
 * Sebelum: rak 20/18/17 di Z=-2, rak 19 di Z=-4  → gap ~1 m dari rak 1
 * Sesudah: rak 20/18/17 di Z=-6, rak 19 di Z=-8  → gap ~5 m dari rak 1
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x = 0, pos_z = -6 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x = 0, pos_z = -8 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = 4, pos_z = -6 WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x = 6, pos_z = -6 WHERE code = '17'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x = 0, pos_z = -2 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x = 0, pos_z = -4 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = 4, pos_z = -2 WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x = 6, pos_z = -2 WHERE code = '17'");
    }
};
