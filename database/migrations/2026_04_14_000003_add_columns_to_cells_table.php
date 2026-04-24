<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToCellsTable extends Migration
{
    public function up()
    {
        Schema::table('cells', function (Blueprint $table) {
            // Label fisik sel (format thesis: "1-D", "2-A", dll.)
            $table->string('label')->nullable()->after('code');

            // Kategori zona yang di-assign GA untuk sel ini
            $table->string('zone_category')->nullable()->after('dominant_category_id');

            // QR code untuk scanning oleh operator saat put-away
            $table->string('qr_code')->nullable()->unique()->after('status');
        });
    }

    public function down()
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->dropColumn(['label', 'zone_category', 'qr_code']);
        });
    }
}
