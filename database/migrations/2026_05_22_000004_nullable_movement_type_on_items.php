<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint; // kept for down() if needed
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buat kolom nullable — data update dijalankan manual via DBeaver
        // karena cells_ref mungkin belum ada di semua environment.
        DB::statement("ALTER TABLE items MODIFY COLUMN movement_type ENUM('fast_moving','slow_moving','non_moving') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE items SET movement_type = 'slow_moving' WHERE movement_type IS NULL");
        DB::statement("ALTER TABLE items MODIFY COLUMN movement_type ENUM('fast_moving','slow_moving','non_moving') NOT NULL DEFAULT 'slow_moving'");
    }
};
