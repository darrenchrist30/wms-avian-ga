<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: expand ENUM to include 'inbound' alongside old values
        DB::statement("ALTER TABLE inbound_transactions MODIFY COLUMN status ENUM(
            'draft','processing','recommended','put_away','completed','cancelled','inbound'
        ) NOT NULL DEFAULT 'inbound'");

        // Step 2: migrate old statuses to simplified ones
        DB::table('inbound_transactions')
            ->whereIn('status', ['draft', 'processing', 'recommended'])
            ->update(['status' => 'inbound']);

        // Step 3: shrink ENUM to only the three valid statuses
        DB::statement("ALTER TABLE inbound_transactions MODIFY COLUMN status ENUM(
            'inbound','put_away','completed'
        ) NOT NULL DEFAULT 'inbound'");
    }

    public function down(): void
    {
        // Restore old ENUM with all statuses (data cannot be fully reverted)
        DB::statement("ALTER TABLE inbound_transactions MODIFY COLUMN status ENUM(
            'draft','processing','recommended','put_away','completed','cancelled','inbound'
        ) NOT NULL DEFAULT 'draft'");
    }
};
