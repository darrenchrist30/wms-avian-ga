<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsTable extends Migration
{
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('item_categories')->onDelete('restrict');
            $table->foreignId('unit_id')->constrained('units')->onDelete('restrict');
            $table->string('sku')->unique();                   // kode SKU unik
            $table->string('erp_item_code')->nullable();       // kode di ERP (untuk mapping)
            $table->string('name');                            // nama barang
            $table->string('barcode')->nullable()->unique();   // barcode / QR code
            $table->text('description')->nullable();
            $table->unsignedInteger('min_stock')->default(0);  // stok minimum (trigger notifikasi)
            $table->unsignedInteger('max_stock')->default(0);  // stok maksimum
            $table->unsignedInteger('reorder_point')->default(0);
            // Klasifikasi pergerakan untuk color coding di 3D
            $table->enum('movement_type', ['fast_moving', 'slow_moving', 'non_moving'])
                ->default('slow_moving');
            $table->decimal('weight_kg', 8, 3)->nullable();   // berat per unit (kg)
            $table->decimal('volume_m3', 8, 4)->nullable();   // volume per unit (m3)
            $table->string('image')->nullable();               // path foto barang
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('items');
    }
}
