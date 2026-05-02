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
        Schema::table('documents', function (Blueprint $table) {
            $table->index('status', 'documents_status_idx');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->index('document_id', 'document_signers_document_id_idx');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->index('document_id', 'signatures_document_id_idx');
        });

        Schema::table('document_hashes', function (Blueprint $table) {
            $table->index('document_id', 'document_hashes_document_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_hashes', function (Blueprint $table) {
            $table->dropIndex('document_hashes_document_id_idx');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->dropIndex('signatures_document_id_idx');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropIndex('document_signers_document_id_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_status_idx');
        });
    }
};
