<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tempatkan area khusus depan di ujung rak 1 (WW=14 m, center X=3 → ujung X=-4 dan X=10).
 *
 * Kiri layar (+X, ujung X=10):
 *   Rak 17 (O2 & Argon) → X=10, Z=-6
 *   Rak 18 (Lemari)     → X= 8, Z=-6
 *
 * Kanan layar (−X, ujung X=-4):
 *   Rak 20 (V-Belt)  → X=-4, Z=-6
 *   Rak 19 (Rak Ban) → X=-2, Z=-6
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x = 10, pos_z = -6 WHERE code = '17'");
        DB::statement("UPDATE racks SET pos_x =  8, pos_z = -6 WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x = -4, pos_z = -6 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x = -2, pos_z = -6 WHERE code = '19'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x =  6, pos_z = -6 WHERE code = '17'");
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = -6 WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = -6 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = -8 WHERE code = '19'");
    }
};
