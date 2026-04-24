<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('opname_number', 50)->unique(); // SO-2026-001
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->date('opname_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('cell_id')->nullable()->constrained('cells')->nullOnDelete();
            $table->integer('system_qty')->default(0);  // qty di sistem saat scan
            $table->integer('physical_qty')->nullable(); // qty hasil hitung fisik
            $table->integer('difference')->nullable();   // physical - system (negatif = kurang)
            $table->enum('status', ['pending', 'counted'])->default('pending');
            $table->foreignId('scanned_by')->nullable()->constrained('users');
            $table->timestamp('scanned_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // Satu item bisa dihitung beberapa kali di opname berbeda
            $table->index(['stock_opname_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
