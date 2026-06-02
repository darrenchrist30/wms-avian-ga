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
        Schema::table('put_away_confirmations', function (Blueprint $table) {
            // Mencegah GA recommendation detail yang sama dikonfirmasi dua kali di level DB.
            // NULL diperbolehkan lebih dari satu (untuk override yang tidak mengikuti GA).
            $table->unique('ga_recommendation_detail_id', 'uq_pac_ga_recommendation_detail_id');
        });
    }

    public function down(): void
    {
        Schema::table('put_away_confirmations', function (Blueprint $table) {
            $table->dropUnique('uq_pac_ga_recommendation_detail_id');
        });
    }
};
