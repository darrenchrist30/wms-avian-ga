<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susun rak 12–15 dalam kolom vertikal (arah Z) di sisi kiri layar (X positif).
 *
 * Sebelumnya: X=4,6,8,10 semua Z=36 → saling menempel, terlihat 1 blok horizontal.
 * Sekarang  : X=9 semua, Z=36/38.4/40.8/43.2 → kolom vertikal terpisah di pojok kiri-belakang.
 *
 * Urutan: rak 12 paling dekat (Z=36), rak 15 paling jauh (Z=43.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 36.0 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 38.4 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 40.8 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 9, pos_z = 43.2 WHERE code = '15'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 36 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x =  6, pos_z = 36 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x =  8, pos_z = 36 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 10, pos_z = 36 WHERE code = '15'");
    }
};
