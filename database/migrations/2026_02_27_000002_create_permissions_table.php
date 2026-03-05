<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    public function up()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // e.g. "Item - Insert"
            $table->string('slug')->unique(); // e.g. "item.insert"
            $table->string('module');         // item, location, inbound, putaway, stock, report, user
            $table->string('action');         // view, insert, update, delete
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('permissions');
    }
}
