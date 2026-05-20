<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->string('certificate_path')->nullable();
            $table->longText('public_key')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('status')->default('placeholder');
            $table->timestamps();
        });

        Schema::create('certificate_revocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certificate_id');
            $table->string('reason')->nullable();
            $table->timestamp('revoked_at');
            $table->timestamps();

            $table->index('certificate_id');
        });

        Schema::create('timestamp_authorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('endpoint_url')->nullable();
            $table->string('status')->default('placeholder');
            $table->string('algorithm')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ocsp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->nullable();
            $table->string('status')->nullable();
            $table->string('responder_url')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('signature_validation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('signature_id')->nullable()->constrained()->nullOnDelete();
            $table->string('validation_type');
            $table->string('result');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('signature_evidence_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('document_signers')->nullOnDelete();
            $table->foreignId('signature_id')->nullable()->constrained()->nullOnDelete();
            $table->json('signer_identity')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->string('signature_hash', 64)->nullable();
            $table->string('signature_algorithm')->nullable();
            $table->string('blockchain_txn')->nullable();
            $table->boolean('otp_verified')->default(false);
            $table->string('otp_method')->nullable();
            $table->string('signing_provider')->nullable();
            $table->json('signing_provider_payload')->nullable();
            $table->json('audit_trail_snapshot')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'signer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_evidence_records');
        Schema::dropIfExists('signature_validation_logs');
        Schema::dropIfExists('ocsp_logs');
        Schema::dropIfExists('timestamp_authorities');
        Schema::dropIfExists('certificate_revocations');
        Schema::dropIfExists('digital_certificates');
    }
};
