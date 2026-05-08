<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Posisikan rak 12–15 sebagai kolom vertikal di SAMPING KIRI rak 1–11.
 *
 * Orientasi: perpendicular terhadap rak utama (memanjang ke arah Z, bukan X).
 * Posisi Z = 16 (tengah dari span rak 1–11: Z=0 s/d Z=32).
 * Posisi X berbeda tiap kolom, sisi +X (kiri layar perspektif).
 *
 * Layout tampak atas:
 *   X=15 → Rak 15 │ X=13 → Rak 14 │ X=11 → Rak 13 │ X=9 → Rak 12 │ [Rak 1–11 di X=3]
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x =  9, pos_z = 16 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = 11, pos_z = 16 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 13, pos_z = 16 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 15, pos_z = 16 WHERE code = '15'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 36.0 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 38.4 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 40.8 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 43.2 WHERE code = '15'");
    }
};
