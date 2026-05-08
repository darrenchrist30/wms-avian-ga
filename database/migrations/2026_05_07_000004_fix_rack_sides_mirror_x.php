<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaiki orientasi kiri-kanan rak agar sesuai tampilan perspektif kamera.
 *
 * Kamera default ada di Z=-8 memandang ke +Z → sumbu X terbalik di layar:
 *   - layar KIRI  = +X (positif)
 *   - layar KANAN = -X (negatif)
 *
 * Rak 12–15 harus di KIRI layar  → pindah ke X positif (x=4,6,8,10)
 * Rak 16 (Lemari) harus di KANAN → pindah ke X negatif (x=-5, z=32)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rak belakang 12–15: pindah ke sisi +X (kiri layar perspektif)
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 36 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x =  6, pos_z = 36 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x =  8, pos_z = 36 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 10, pos_z = 36 WHERE code = '15'");

        // Rak 16 (Lemari): pindah ke sisi -X (kanan layar perspektif), sejajar rak 11
        DB::statement("UPDATE racks SET pos_x = -5, pos_z = 32 WHERE code = '16'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x = -1, pos_z = 36 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = -3, pos_z = 36 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = -5, pos_z = 36 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = -7, pos_z = 36 WHERE code = '15'");
        DB::statement("UPDATE racks SET pos_x =  9, pos_z = 32 WHERE code = '16'");
    }
};
