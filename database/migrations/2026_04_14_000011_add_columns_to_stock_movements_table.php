<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToStockMovementsTable extends Migration
{
    public function up()
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Denormalized FK ke warehouse
            $table->foreignId('warehouse_id')->nullable()
                ->after('item_id')
                ->constrained('warehouses')
                ->onDelete('set null');

            // Timestamp eksplisit saat pergerakan terjadi (thesis: moved_at)
            // created_at bisa dipakai, tapi moved_at lebih eksplisit untuk laporan
            $table->timestamp('moved_at')->nullable()->after('notes');

            $table->index('warehouse_id');
        });
    }

    public function down()
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex(['warehouse_id']);
            $table->dropColumn(['warehouse_id', 'moved_at']);
        });
    }
}
