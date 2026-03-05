<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZonesTable extends Migration
{
    public function up()
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->string('code');            // A, B, C, D, E
            $table->string('name');            // Zone A - Fast Moving, Zone B - Slow Moving
            $table->string('description')->nullable();
            // Posisi zona di layout 3D
            $table->float('pos_x')->default(0);
            $table->float('pos_z')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['warehouse_id', 'code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('zones');
    }
}
