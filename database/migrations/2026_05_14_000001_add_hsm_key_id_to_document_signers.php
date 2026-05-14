<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add HSM key ID column to document_signers table
     * 
     * Required for CSC compliance to track HSM-backed keys.
     */
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('hsm_key_id')->nullable()->after('remote_credential_id');
            $table->string('public_key_fingerprint')->nullable()->after('hsm_key_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn(['hsm_key_id', 'public_key_fingerprint']);
        });
    }
};
