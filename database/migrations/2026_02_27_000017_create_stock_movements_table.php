<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockMovementsTable extends Migration
{
    public function up()
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->onDelete('restrict');
            $table->foreignId('from_cell_id')->nullable()
                ->constrained('cells')->onDelete('set null'); // null = barang baru masuk
            $table->foreignId('to_cell_id')->nullable()
                ->constrained('cells')->onDelete('set null'); // null = barang keluar
            $table->foreignId('performed_by')->nullable()
                ->constrained('users')->onDelete('set null');
            $table->string('lpn')->nullable();
            $table->string('batch_no')->nullable();
            $table->unsignedInteger('quantity');
            $table->enum('movement_type', [
                'inbound',        // barang masuk dari supplier
                'outbound',       // barang keluar ke customer
                'transfer',       // pindah antar cell / zona
                'adjustment',     // penyesuaian stok (opname)
                'return_inbound', // retur dari customer
                'return_outbound' // retur ke supplier
            ]);
            // Referensi ke dokumen sumber
            $table->string('reference_type')->nullable(); // InboundOrder, OutboundOrder, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_movements');
    }
}
