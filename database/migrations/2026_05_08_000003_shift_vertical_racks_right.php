<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Geser rak 12–15 lebih jauh ke kanan (+X) untuk memberi jarak ~3 m
 * dari ujung kanan rak utama 1–11 (pos_x = 3, lebar WW = 8 m → ujung kanan X = 7).
 *
 * Sebelum : R12 @X=9  → gap 1 m (X=7 ke X=8)
 * Sesudah : R12 @X=12 → gap 4 m (X=7 ke X=11)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 16 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = 14, pos_z = 16 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 16, pos_z = 16 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 18, pos_z = 16 WHERE code = '15'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x =  9, pos_z = 16 WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = 11, pos_z = 16 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 13, pos_z = 16 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 15, pos_z = 16 WHERE code = '15'");
    }
};
