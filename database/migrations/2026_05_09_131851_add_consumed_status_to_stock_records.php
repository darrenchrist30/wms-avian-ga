<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_records MODIFY COLUMN status ENUM('available','reserved','quarantine','expired','consumed') NOT NULL DEFAULT 'available'");
    }

    public function down(): void
    {
        // Move any consumed records back to expired before shrinking the enum
        DB::statement("UPDATE stock_records SET status = 'expired' WHERE status = 'consumed'");
        DB::statement("ALTER TABLE stock_records MODIFY COLUMN status ENUM('available','reserved','quarantine','expired') NOT NULL DEFAULT 'available'");
    }
};
