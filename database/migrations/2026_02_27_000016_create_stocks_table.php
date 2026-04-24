<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->onDelete('restrict');
            $table->foreignId('cell_id')->constrained()->onDelete('restrict');
            $table->foreignId('inbound_order_item_id')->nullable()
                ->constrained('inbound_details')->onDelete('set null');
            $table->string('lpn')->nullable();              // License Plate Number
            $table->string('batch_no')->nullable();         // nomor batch / lot
            $table->unsignedInteger('quantity');            // qty stok di cell ini
            $table->date('inbound_date');                   // tanggal masuk (untuk FIFO)
            $table->date('expiry_date')->nullable();
            $table->timestamp('last_moved_at')->nullable(); // terakhir bergerak (untuk deteksi deadstock)
            $table->enum('status', [
                'available',   // bisa diambil
                'reserved',    // sudah dipesan untuk outbound
                'quarantine',  // karantina / hold
                'expired'      // kadaluarsa
            ])->default('available');
            $table->timestamps();
            $table->index(['item_id', 'cell_id']);
            $table->index('inbound_date'); // untuk FIFO query
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_records');
    }
};
