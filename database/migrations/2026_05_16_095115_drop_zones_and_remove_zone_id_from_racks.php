<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Drop FK + zone_id column from racks; warehouse_id is already backfilled
        Schema::table('racks', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn('zone_id');
        });

        // Drop zones table — no longer part of the hierarchy
        Schema::dropIfExists('zones');
    }

    public function down(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('racks', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->constrained('zones')->cascadeOnDelete();
        });
    }
};
