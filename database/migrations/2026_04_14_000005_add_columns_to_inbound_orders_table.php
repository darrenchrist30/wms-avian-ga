<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            $table->string('no_bukti_manual')->nullable()->after('do_number');
            $table->string('ref_doc_spk')->nullable()->after('erp_reference');
            $table->string('batch_header')->nullable()->after('ref_doc_spk');
            $table->dateTime('processed_at')->nullable()->after('received_at');
        });
    }

    public function down()
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            $table->dropColumn(['no_bukti_manual', 'ref_doc_spk', 'batch_header', 'processed_at']);
        });
    }
};
