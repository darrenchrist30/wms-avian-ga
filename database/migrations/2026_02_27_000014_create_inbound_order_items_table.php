<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInboundOrderItemsTable extends Migration
{
    public function up()
    {
        Schema::create('inbound_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('restrict');
            $table->string('lpn')->nullable();             // License Plate Number (pallet/carton ID)
            $table->unsignedInteger('quantity_ordered');   // qty di surat jalan
            $table->unsignedInteger('quantity_received')->default(0); // qty aktual diterima
            $table->enum('status', [
                'pending',      // belum diproses
                'recommended',  // sudah dapat rekomendasi lokasi
                'put_away',     // sudah dikonfirmasi put-away
                'partial'       // sebagian sudah put-away
            ])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inbound_order_items');
    }
}
