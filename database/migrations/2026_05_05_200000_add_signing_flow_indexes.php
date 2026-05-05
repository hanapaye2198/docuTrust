<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('signature_fields', function (Blueprint $table) {
            $table->index('document_id', 'signature_fields_document_id_idx');
            $table->index('signer_id', 'signature_fields_signer_id_idx');
            $table->index(['document_id', 'signer_id'], 'signature_fields_document_signer_idx');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->index(['document_id', 'signing_order'], 'document_signers_document_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropIndex('document_signers_document_order_idx');
        });

        Schema::table('signature_fields', function (Blueprint $table) {
            $table->dropIndex('signature_fields_document_signer_idx');
            $table->dropIndex('signature_fields_signer_id_idx');
            $table->dropIndex('signature_fields_document_id_idx');
        });
    }
};
