<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL does not support enum column change via ->change() reliably;
        // use raw SQL to redefine the enum with the new value.
        DB::statement("ALTER TABLE ga_recommendations MODIFY COLUMN status ENUM('pending','pending_review','accepted','rejected') NOT NULL DEFAULT 'pending'");

        Schema::table('ga_recommendations', function (Blueprint $table) {
            $table->boolean('review_required')->default(false)->after('status');
            $table->string('review_reason')->nullable()->after('review_required');

            $table->foreignId('accepted_by')->nullable()->after('review_reason')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');

            $table->foreignId('rejected_by')->nullable()->after('accepted_at')
                ->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->string('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('ga_recommendations', function (Blueprint $table) {
            $table->dropForeign(['accepted_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'review_required',
                'review_reason',
                'accepted_by',
                'accepted_at',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
            ]);
        });

        DB::statement("ALTER TABLE ga_recommendations MODIFY COLUMN status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending'");
    }
};
