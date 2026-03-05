<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRacksTable extends Migration
{
    public function up()
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->onDelete('cascade');
            $table->string('code');             // R-A01, R-A02, ...
            $table->string('name')->nullable(); // Rack A-01
            $table->unsignedTinyInteger('total_levels')->default(4);  // jumlah level (baris)
            $table->unsignedTinyInteger('total_columns')->default(3); // jumlah kolom
            // Posisi fisik rak di layout 3D
            $table->float('pos_x')->default(0);
            $table->float('pos_z')->default(0);
            $table->float('rotation_y')->default(0); // rotasi rak di denah
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['zone_id', 'code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('racks');
    }
}
