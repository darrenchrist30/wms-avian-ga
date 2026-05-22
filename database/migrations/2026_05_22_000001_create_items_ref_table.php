<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_ref', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 150)->index();   // ERP item code (Qspart.kode)
            $table->string('nama', 300);            // item name
            $table->string('sat', 150)->nullable(); // unit (satuan)
            $table->double('stock')->default(0);    // ERP stock qty
            $table->double('min_stock')->default(0);
            $table->double('max_stock')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_ref');
    }
};
