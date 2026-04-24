<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropPutAwayRecommendationsTable extends Migration
{
    /**
     * Tabel ini digantikan oleh sistem yang lebih lengkap:
     *   - ga_recommendations          → header per GA run (chromosome, fitness, parameters)
     *   - ga_recommendation_details   → per-SKU placement dengan breakdown FC_CAP/CAT/AFF/SPLIT
     *   - put_away_confirmations      → konfirmasi aktual operator (dipisah dari rekomendasi)
     */
    public function up()
    {
        Schema::dropIfExists('put_away_recommendations');
    }

    public function down()
    {
        // Recreate jika rollback diperlukan
        Schema::create('put_away_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_order_id')->constrained('inbound_transactions')->onDelete('cascade');
            $table->foreignId('inbound_order_item_id')->constrained('inbound_details')->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('restrict');
            $table->foreignId('cell_id')->constrained()->onDelete('restrict');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('fitness_score', 10, 6)->nullable();
            $table->integer('generation')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('chromosome_index')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'override'])->default('pending');
            $table->foreignId('override_cell_id')->nullable()->constrained('cells')->onDelete('set null');
            $table->dateTime('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
