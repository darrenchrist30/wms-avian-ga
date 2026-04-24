<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemSizeToItemsTable extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            // Ukuran fisik item — dipakai GA untuk FC_CAP scoring
            $table->enum('item_size', ['small', 'medium', 'large', 'extra_large'])
                ->nullable()
                ->after('name');

            // Threshold hari tanpa pergerakan → dianggap deadstock
            $table->unsignedSmallInteger('deadstock_threshold_days')
                ->default(90)
                ->after('item_size')
                ->comment('Hari tanpa pergerakan sebelum dianggap deadstock');
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['item_size', 'deadstock_threshold_days']);
        });
    }
}
