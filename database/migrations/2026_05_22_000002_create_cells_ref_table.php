<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cells_ref')) {
            return;
        }
        Schema::create('cells_ref', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 150)->index();    // item ERP code (mspart.kode)
            $table->string('nama', 300)->nullable(); // item name
            $table->double('stok')->default(0);      // stock qty in this location
            $table->string('sat', 150)->nullable();  // unit
            $table->double('minstok')->default(0);
            $table->double('maxstok')->default(0);
            $table->string('blok', 300)->nullable(); // physical location coords
            $table->string('grup', 300)->nullable();
            $table->string('kolom', 300)->nullable();
            $table->string('baris', 300)->nullable();
            $table->string('note', 750)->nullable();
            $table->tinyInteger('isdel')->default(0);
            $table->timestamps();

            $table->index(['blok', 'grup', 'kolom', 'baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cells_ref');
    }
};
