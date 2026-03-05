<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCellsTable extends Migration
{
    public function up()
    {
        Schema::create('cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained()->onDelete('cascade');
            $table->foreignId('dominant_category_id')->nullable()
                ->constrained('item_categories')->onDelete('set null');
            $table->string('code');                   // R-A01-L1-C1 (Rack-Level-Column)
            $table->unsignedTinyInteger('level');     // 1 = bawah, 4 = atas
            $table->unsignedTinyInteger('column');    // 1, 2, 3
            $table->unsignedInteger('capacity_max');  // kapasitas maksimum (unit/pcs)
            $table->unsignedInteger('capacity_used')->default(0); // kapasitas terpakai
            $table->enum('status', ['available', 'partial', 'full', 'blocked', 'reserved'])
                ->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['rack_id', 'level', 'column']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cells');
    }
}
