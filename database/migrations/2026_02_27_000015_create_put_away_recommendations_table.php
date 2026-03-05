<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePutAwayRecommendationsTable extends Migration
{
    public function up()
    {
        Schema::create('put_away_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('inbound_order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('restrict');
            $table->foreignId('cell_id')->constrained()->onDelete('restrict');
            $table->foreignId('confirmed_by')->nullable()
                ->constrained('users')->onDelete('set null');
            // Data hasil GA
            $table->decimal('fitness_score', 10, 6)->nullable(); // skor fitness GA
            $table->integer('generation')->nullable();           // pada generasi ke-berapa
            $table->unsignedInteger('quantity');                 // qty yang ditempatkan di cell ini
            $table->string('chromosome_index')->nullable();      // posisi kromosom
            $table->enum('status', [
                'pending',    // menunggu konfirmasi operator
                'confirmed',  // dikonfirmasi via barcode scan
                'rejected',   // ditolak operator, akan di-rerun GA
                'override'    // lokasi diganti manual oleh supervisor
            ])->default('pending');
            $table->foreignId('override_cell_id')->nullable()
                ->constrained('cells')->onDelete('set null'); // jika di-override
            $table->dateTime('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('put_away_recommendations');
    }
}
