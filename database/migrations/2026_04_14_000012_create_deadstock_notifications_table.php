<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeadstockNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('deadstock_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')
                ->constrained('items')
                ->onDelete('cascade');

            $table->foreignId('cell_id')
                ->constrained('cells')
                ->onDelete('cascade');

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->onDelete('cascade');

            // Jumlah hari tanpa pergerakan saat notifikasi di-generate
            $table->unsignedInteger('days_no_movement')->default(0);

            // Kapan terakhir kali ada pergerakan stok item ini di cell ini
            $table->timestamp('last_movement_at')->nullable();

            // Status penanganan notifikasi
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active');

            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['item_id', 'cell_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('deadstock_notifications');
    }
}
