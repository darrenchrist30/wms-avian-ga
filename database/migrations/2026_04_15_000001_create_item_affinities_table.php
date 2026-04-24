<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemAffinitiesTable extends Migration
{
    public function up()
    {
        Schema::create('item_affinities', function (Blueprint $table) {
            $table->id();

            // Pasangan SKU yang sering berdampingan
            $table->foreignId('item_id')
                ->constrained('items')
                ->onDelete('cascade');

            $table->foreignId('related_item_id')
                ->constrained('items')
                ->onDelete('cascade');

            // Skor afinitas 0.0 – 1.0
            // 1.0 = selalu keluar/masuk bersamaan, 0.0 = tidak pernah
            // Dipakai GA untuk menghitung FC_AFF (20 poin maks)
            $table->decimal('affinity_score', 5, 4)->default(0.0000);

            // Berapa kali kedua item ini muncul dalam 1 inbound order yang sama
            // Diperbarui otomatis oleh command: php artisan ga:recalculate-affinity
            $table->unsignedInteger('co_occurrence_count')->default(0);

            $table->timestamps();

            // Satu pasang item hanya boleh ada satu record (A→B, bukan A→B + B→A)
            $table->unique(['item_id', 'related_item_id']);

            // Index untuk query cepat saat GA menghitung FC_AFF
            $table->index(['item_id', 'affinity_score']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('item_affinities');
    }
}
