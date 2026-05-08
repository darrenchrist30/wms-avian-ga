<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Redesign posisi zona dan rak supaya cocok dengan layout gudang nyata:
 *
 * Sebelumnya (salah): zona A/B/C di-spread dalam arah X (pos_x = 0, 40, 80)
 * Sesudah (benar)   : zona di-stack dalam arah Z (depan ke belakang),
 *                     setiap zona punya 2 baris rak berpasangan:
 *                       - baris depan  : pos_z = 0   (relative to zone)
 *                       - baris belakang: pos_z = 2.4 (relative to zone)
 *
 * Zone layout (absolute Z):
 *   Zona A – Fast Moving : Z =  0  dan  2.4
 *   Zona B – Slow Moving : Z =  8  dan 10.4
 *   Zona C – Heavy       : Z = 16  dan 18.4
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Zone positions ────────────────────────────────────────────────────
        DB::statement("UPDATE zones SET pos_x = 0, pos_z = 8  WHERE code = 'B'");
        DB::statement("UPDATE zones SET pos_x = 0, pos_z = 16 WHERE code = 'C'");

        // ── Zone A back row (racks 5–8): pos_x 16,20,24,28 → 0,4,8,12 ; pos_z 0→2.4 ──
        DB::statement("UPDATE racks SET pos_x = 0,  pos_z = 2.4 WHERE code = '5'");
        DB::statement("UPDATE racks SET pos_x = 4,  pos_z = 2.4 WHERE code = '6'");
        DB::statement("UPDATE racks SET pos_x = 8,  pos_z = 2.4 WHERE code = '7'");
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 2.4 WHERE code = '8'");

        // ── Zone B back row (racks 13–16): sama ──────────────────────────────
        DB::statement("UPDATE racks SET pos_x = 0,  pos_z = 2.4 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 4,  pos_z = 2.4 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 8,  pos_z = 2.4 WHERE code = '15'");
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 2.4 WHERE code = '16'");

        // ── Zone C back row (racks 19–20): pos_x 8,12 → 0,4 ; pos_z 0→2.4 ──
        DB::statement("UPDATE racks SET pos_x = 0, pos_z = 2.4 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = 4, pos_z = 2.4 WHERE code = '20'");
    }

    public function down(): void
    {
        DB::statement("UPDATE zones SET pos_x = 40, pos_z = 0 WHERE code = 'B'");
        DB::statement("UPDATE zones SET pos_x = 80, pos_z = 0 WHERE code = 'C'");

        DB::statement("UPDATE racks SET pos_x = 16, pos_z = 0 WHERE code = '5'");
        DB::statement("UPDATE racks SET pos_x = 20, pos_z = 0 WHERE code = '6'");
        DB::statement("UPDATE racks SET pos_x = 24, pos_z = 0 WHERE code = '7'");
        DB::statement("UPDATE racks SET pos_x = 28, pos_z = 0 WHERE code = '8'");

        DB::statement("UPDATE racks SET pos_x = 16, pos_z = 0 WHERE code = '13'");
        DB::statement("UPDATE racks SET pos_x = 20, pos_z = 0 WHERE code = '14'");
        DB::statement("UPDATE racks SET pos_x = 24, pos_z = 0 WHERE code = '15'");
        DB::statement("UPDATE racks SET pos_x = 28, pos_z = 0 WHERE code = '16'");

        DB::statement("UPDATE racks SET pos_x = 8,  pos_z = 0 WHERE code = '19'");
        DB::statement("UPDATE racks SET pos_x = 12, pos_z = 0 WHERE code = '20'");
    }
};
