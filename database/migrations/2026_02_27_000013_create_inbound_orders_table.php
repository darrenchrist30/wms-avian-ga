<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inbound_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('received_by')->nullable()
                ->constrained('users')->onDelete('set null');
            $table->string('do_number')->unique();         // nomor surat jalan / delivery order
            $table->string('erp_reference')->nullable();   // referensi dokumen di ERP
            $table->date('do_date');                       // tanggal surat jalan
            $table->dateTime('received_at')->nullable();   // tanggal & waktu diterima di gudang
            $table->enum('status', [
                'draft',        // baru masuk dari ERP
                'processing',   // sedang diproses GA
                'recommended',  // rekomendasi GA sudah keluar
                'put_away',     // sedang proses put-away
                'completed',    // semua item sudah di-put-away
                'cancelled'
            ])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inbound_transactions');
    }
};
