<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ga_recommendation_details', function (Blueprint $table) {
            // FC_MOV: fitness slotting FSN (maks 10 poin)
            $table->decimal('fc_mov_score', 8, 4)->nullable()->after('fc_split_score');
        });
    }

    public function down(): void
    {
        Schema::table('ga_recommendation_details', function (Blueprint $table) {
            $table->dropColumn('fc_mov_score');
        });
    }
};
