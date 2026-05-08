<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Posisikan rak 17–20 tepat di depan rak 1 (pos_z negatif), bukan di samping.
 *
 * Layout baru (tampak atas, Z semakin kecil = semakin depan):
 *
 *   ← kiri                                  kanan →
 *   [ Rak 1 – wide 8m, pos_x=3, pos_z=0           ]
 *   [ Rak 2 – wide 8m, pos_x=3, pos_z=2.4         ]
 *
 *   [20/Lemari x=0,z=-2]        [18/Lemari x=4,z=-2] [17/O2 x=6,z=-2]
 *   [19/RakBan x=0,z=-4]
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kiri: rak 20 (Lemari) depan-dekat, rak 19 (Rak Ban) depan-jauh
        DB::statement("UPDATE racks SET pos_x = 0, pos_z = -2 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x = 0, pos_z = -4 WHERE code = '19'");

        // Kanan: rak 18 (Lemari) & rak 17 (O2 & Argon) bersebelahan
        DB::statement("UPDATE racks SET pos_x = 4, pos_z = -2 WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x = 6, pos_z = -2 WHERE code = '17'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x = -3, pos_z =  0 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = -5, pos_z =  0 WHERE code = '20'");
        DB::statement("UPDATE racks SET pos_x = -3, pos_z = -3 WHERE code = '17'");
        DB::statement("UPDATE racks SET pos_x = -5, pos_z = -3 WHERE code = '18'");
    }
};
