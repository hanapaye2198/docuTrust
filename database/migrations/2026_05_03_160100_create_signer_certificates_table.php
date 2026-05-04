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
        Schema::create('signer_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_signer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_authority_id')->constrained()->cascadeOnDelete();
            $table->string('subject_dn');
            $table->string('issuer_dn');
            $table->string('serial_number');
            $table->longText('public_key_pem');
            $table->longText('certificate_pem');
            $table->string('fingerprint_sha256', 64)->unique();
            $table->timestamp('valid_from');
            $table->timestamp('valid_to');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['document_signer_id', 'certificate_authority_id'], 'signer_ca_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signer_certificates');
    }
};
