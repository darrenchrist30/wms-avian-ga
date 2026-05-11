<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inbound_details MODIFY COLUMN status ENUM('pending','recommended','put_away','partial','partial_put_away') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE inbound_details SET status = 'partial' WHERE status = 'partial_put_away'");
        DB::statement("ALTER TABLE inbound_details MODIFY COLUMN status ENUM('pending','recommended','put_away','partial') NOT NULL DEFAULT 'pending'");
    }
};
