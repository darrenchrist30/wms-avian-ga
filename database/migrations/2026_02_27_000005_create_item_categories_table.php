<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();        // e.g. CAT-001
            $table->string('name');                  // e.g. Spare Part Engine
            $table->string('description')->nullable();
            $table->string('color_code')->nullable(); // untuk color coding di 3D view
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('item_categories');
    }
}
