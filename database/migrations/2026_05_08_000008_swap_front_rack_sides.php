<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tukar sisi kiri-kanan area depan:
 *   Kiri (+X, ujung X=10): Rak Ban (depan, Z=-8) + V-Belt (belakang, Z=-4)
 *   Kanan (-X, ujung X=-4): Lemari (X=-2) + O2 & Argon (X=-4), sejajar di Z=-6
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x = 10, pos_z = -8 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = 10, pos_z = -4 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x = -2, pos_z = -6 WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x = -4, pos_z = -6 WHERE code = '17'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x = -2, pos_z = -6 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = -4, pos_z = -6 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x =  6, pos_z = -6 WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x = 10, pos_z = -6 WHERE code = '17'");
    }
};
