<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Geser Lemari (rak 18) dari X=8 ke X=6 agar ada gap 4 m dari O2 & Argon (rak 17, X=10).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE racks SET pos_x = 6, pos_z = -6 WHERE code = '18'");
    }

    public function down(): void
    {
        DB::statement("UPDATE racks SET pos_x = 8, pos_z = -6 WHERE code = '18'");
    }
};
