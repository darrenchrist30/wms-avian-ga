<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePutAwayConfirmationsTable extends Migration
{
    public function up()
    {
        Schema::create('put_away_confirmations', function (Blueprint $table) {
            $table->id();

            // Item inbound yang dikonfirmasi put-away-nya
            $table->foreignId('inbound_order_item_id')
                ->constrained('inbound_details')
                ->onDelete('cascade');

            // Cell tujuan aktual (bisa berbeda dari rekomendasi GA jika di-override)
            $table->foreignId('cell_id')
                ->constrained('cells')
                ->onDelete('restrict');

            // Detail rekomendasi GA yang jadi acuan (nullable jika manual/override)
            $table->foreignId('ga_recommendation_detail_id')->nullable()
                ->constrained('ga_recommendation_details')
                ->onDelete('set null');

            // Operator yang melakukan konfirmasi (scan QR / input manual)
            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Qty aktual yang diletakkan di cell ini
            $table->unsignedInteger('quantity_stored');

            // Apakah operator mengikuti rekomendasi GA atau tidak
            $table->boolean('follow_recommendation')->default(true);

            $table->dateTime('confirmed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['inbound_order_item_id', 'cell_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('put_away_confirmations');
    }
}
