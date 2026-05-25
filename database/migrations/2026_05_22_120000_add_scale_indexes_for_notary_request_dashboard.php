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
            $table->index(['notary_request_id', 'status'], 'documents_request_status_idx');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->index(['document_id', 'role_type'], 'document_signers_document_role_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropIndex('document_signers_document_role_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_request_status_idx');
        });
    }
};
