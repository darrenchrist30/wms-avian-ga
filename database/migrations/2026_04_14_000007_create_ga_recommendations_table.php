<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGaRecommendationsTable extends Migration
{
    public function up()
    {
        Schema::create('ga_recommendations', function (Blueprint $table) {
            $table->id();

            // Inbound order yang di-proses GA
            $table->foreignId('inbound_order_id')
                ->constrained('inbound_transactions')
                ->onDelete('cascade');

            // Siapa yang men-trigger GA (biasanya supervisor)
            $table->foreignId('generated_by')->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Hasil kromosom terbaik: array [sku_index => cell_id]
            $table->json('chromosome_json');

            // Fitness score keseluruhan (rata-rata dari semua gene)
            $table->decimal('fitness_score', 8, 4)->nullable();

            // Statistik eksekusi GA
            $table->unsignedInteger('generations_run')->default(0);
            $table->unsignedInteger('execution_time_ms')->nullable();

            // Parameter GA yang dipakai saat run ini
            // { population: 100, max_gen: 150, tournament_size: 3, ... }
            $table->json('parameters_json')->nullable();

            $table->timestamp('generated_at')->nullable();

            // Status apakah rekomendasi diterima atau ditolak supervisor
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ga_recommendations');
    }
}
