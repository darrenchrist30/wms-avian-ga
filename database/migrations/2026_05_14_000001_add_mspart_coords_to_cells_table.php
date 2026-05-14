<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->unsignedTinyInteger('blok')->nullable()->after('column');
            $table->char('grup', 1)->nullable()->after('blok');
            $table->unsignedTinyInteger('kolom')->nullable()->after('grup');
            $table->unsignedTinyInteger('baris')->nullable()->after('kolom');
        });
    }

    public function down(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->dropColumn(['blok', 'grup', 'kolom', 'baris']);
        });
    }
};
