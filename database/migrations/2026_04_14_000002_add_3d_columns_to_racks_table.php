<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Add3dColumnsToRacksTable extends Migration
{
    public function up()
    {
        Schema::table('racks', function (Blueprint $table) {
            // Referensi langsung ke warehouse (denormalized dari zone → warehouse)
            $table->foreignId('warehouse_id')->nullable()
                ->after('zone_id')
                ->constrained('warehouses')
                ->onDelete('set null');

            // Nomor urut rak di dalam gudang (untuk label fisik: Rack 1, Rack 2, ...)
            $table->unsignedSmallInteger('rack_number')->nullable()->after('name');

            // Posisi 3D tambahan (pos_x dan pos_z sudah ada)
            $table->float('pos_y')->default(0)->after('pos_x');

            // Dimensi 3D untuk visualisasi Three.js (dalam meter)
            $table->float('width_3d')->default(2.0)->after('pos_y');
            $table->float('height_3d')->default(3.0)->after('width_3d');
            $table->float('depth_3d')->default(1.0)->after('height_3d');
        });
    }

    public function down()
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn([
                'warehouse_id', 'rack_number',
                'pos_y', 'width_3d', 'height_3d', 'depth_3d',
            ]);
        });
    }
}
