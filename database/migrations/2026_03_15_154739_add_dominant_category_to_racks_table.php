<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->foreignId('dominant_category_id')
                  ->nullable()
                  ->after('zone_id')
                  ->constrained('item_categories')
                  ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropForeign(['dominant_category_id']);
            $table->dropColumn('dominant_category_id');
        });
    }
};
