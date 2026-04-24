<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGaRecommendationDetailsTable extends Migration
{
    public function up()
    {
        Schema::create('ga_recommendation_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ga_recommendation_id')
                ->constrained('ga_recommendations')
                ->onDelete('cascade');

            // Item/LPN spesifik yang direkomendasikan ke cell ini
            $table->foreignId('inbound_order_item_id')
                ->constrained('inbound_details')
                ->onDelete('cascade');

            $table->foreignId('cell_id')
                ->constrained('cells')
                ->onDelete('restrict');

            // Jumlah yang ditempatkan di cell ini
            $table->unsignedInteger('quantity');

            // Fitness breakdown per gene (skala 0-100 masing-masing)
            // gene_fitness = gabungan weighted dari 4 komponen
            $table->decimal('gene_fitness', 8, 4)->nullable();   // total fitness gene ini

            // FC_CAP: fitness kapasitas (40 poin maks) — apakah kapasitas cell cukup
            $table->decimal('fc_cap_score', 8, 4)->nullable();

            // FC_CAT: fitness kategori (30 poin maks) — apakah kategori item sesuai zone_category cell
            $table->decimal('fc_cat_score', 8, 4)->nullable();

            // FC_AFF: fitness afinitas (20 poin maks) — apakah item sering berdampingan
            $table->decimal('fc_aff_score', 8, 4)->nullable();

            // FC_SPLIT: penalti split (10 poin maks) — apakah item yang sama tidak terpecah di banyak cell
            $table->decimal('fc_split_score', 8, 4)->nullable();

            $table->timestamps();

            $table->index(['ga_recommendation_id', 'cell_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ga_recommendation_details');
    }
}
