<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Redesign posisi semua rak agar cocok dengan denah Excel client (tampak atas):
 *
 * Layout baru (koordinat absolut, semua zona di origin 0,0):
 *   • Rak 1–11 : rak wide (8 m) berpasangan depan-belakang, berderet dalam arah Z
 *       Pasangan: (1,2), (3,4), (5,6), (7,8), (9,10), lalu 11 sendiri
 *       Semua berada di pos_x = 3 (tengah dari lebar 8 m)
 *   • Rak 12–15: di belakang (Z=36), berderet ke kiri mulai X=-1
 *   • Rak 16   : di sebelah kanan rak 11 (X=9, Z=32)
 *   • Rak 19,20: di sebelah kiri rak 1 (X=-3,-5 ; Z=0)
 *   • Rak 17,18: di depan rak 1 (Z=-3), di sebelah 19,20
 */
return new class extends Migration
{
    public function up(): void
    {
        // Semua zona ke origin agar pos_x/pos_z rak menjadi koordinat absolut
        DB::statement("UPDATE zones SET pos_x = 0, pos_z = 0");

        // ── Rak wide 1–11 (pos_x = 3 = tengah lebar 8 m) ────────────────────
        DB::statement("UPDATE racks SET pos_x =  3, pos_z =  0   WHERE code = '1'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z =  2.4 WHERE code = '2'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z =  6.4 WHERE code = '3'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z =  8.8 WHERE code = '4'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z = 12.8 WHERE code = '5'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z = 15.2 WHERE code = '6'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z = 19.2 WHERE code = '7'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z = 21.6 WHERE code = '8'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z = 25.6 WHERE code = '9'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z = 28.0 WHERE code = '10'");
        DB::statement("UPDATE racks SET pos_x =  3, pos_z = 32.0 WHERE code = '11'");

        // ── Rak belakang 12–15 (di belakang rak 11, berderet ke kiri) ───────
        DB::statement("UPDATE racks SET pos_x = -1, pos_z = 36   WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x = -3, pos_z = 36   WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = -5, pos_z = 36   WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = -7, pos_z = 36   WHERE code = '15'");

        // ── Rak 16 di sebelah kanan rak 11 ──────────────────────────────────
        DB::statement("UPDATE racks SET pos_x =  9, pos_z = 32   WHERE code = '16'");

        // ── Rak 19,20 di sebelah kiri rak 1 (Z sama dengan rak 1) ──────────
        DB::statement("UPDATE racks SET pos_x = -3, pos_z =  0   WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = -5, pos_z =  0   WHERE code = '20'");

        // ── Rak 17,18 di depan rak 1 (agak lebih depan Z=-3) ────────────────
        DB::statement("UPDATE racks SET pos_x = -3, pos_z = -3   WHERE code = '17'");
        DB::statement("UPDATE racks SET pos_x = -5, pos_z = -3   WHERE code = '18'");
    }

    public function down(): void
    {
        // Kembalikan ke layout zona sebelumnya (setelah migration 000001)
        DB::statement("UPDATE zones SET pos_x = 0, pos_z = 0  WHERE code = 'A'");
        DB::statement("UPDATE zones SET pos_x = 0, pos_z = 8  WHERE code = 'B'");
        DB::statement("UPDATE zones SET pos_x = 0, pos_z = 16 WHERE code = 'C'");

        // Zona A racks (relative to zone at 0,0)
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = 0   WHERE code = '1'");
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 0   WHERE code = '2'");
        DB::statement("UPDATE racks SET pos_x =  8, pos_z = 0   WHERE code = '3'");
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 0   WHERE code = '4'");
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = 2.4 WHERE code = '5'");
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 2.4 WHERE code = '6'");
        DB::statement("UPDATE racks SET pos_x =  8, pos_z = 2.4 WHERE code = '7'");
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 2.4 WHERE code = '8'");

        // Zona B racks (relative to zone at 0, 8)
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = 0   WHERE code = '9'");
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 0   WHERE code = '10'");
        DB::statement("UPDATE racks SET pos_x =  8, pos_z = 0   WHERE code = '11'");
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 0   WHERE code = '12'");
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = 2.4 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 2.4 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x =  8, pos_z = 2.4 WHERE code = '15'");
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 2.4 WHERE code = '16'");

        // Zona C racks (relative to zone at 0, 16)
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = 0   WHERE code = '17'");
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 0   WHERE code = '18'");
        DB::statement("UPDATE racks SET pos_x =  0, pos_z = 2.4 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x =  4, pos_z = 2.4 WHERE code = '20'");
    }
};
