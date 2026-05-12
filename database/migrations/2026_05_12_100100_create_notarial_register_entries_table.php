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
        Schema::create('notarial_register_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_request_id')->constrained('notary_requests')->cascadeOnDelete();
            $table->foreignId('notary_credential_id')->constrained('notary_credentials')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->unsignedInteger('entry_number');
            $table->unsignedSmallInteger('entry_year');
            $table->string('document_title');
            $table->text('document_description')->nullable();
            $table->json('parties');
            $table->json('witnesses')->nullable();
            $table->json('competent_evidence');
            $table->timestamp('notarized_at');
            $table->string('notarial_act_type');
            $table->decimal('fees', 10, 2)->default(0);
            $table->string('official_receipt_number')->nullable();
            $table->string('notary_signature_path')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->string('qr_verification_token')->nullable();
            $table->timestamps();

            $table->unique(['notary_credential_id', 'entry_number', 'entry_year'], 'notarial_register_entry_unique');
            $table->index(['notary_request_id'], 'notarial_register_entries_request_idx');
            $table->index(['qr_verification_token'], 'notarial_register_entries_qr_token_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notarial_register_entries');
    }
};
