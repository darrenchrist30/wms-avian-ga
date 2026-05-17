<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            // MSpart home location: blok/grup/kolom/baris mapped to a cell
            $table->foreignId('home_cell_id')
                ->nullable()
                ->after('is_active')
                ->constrained('cells')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['home_cell_id']);
            $table->dropColumn('home_cell_id');
        });
    }
};
