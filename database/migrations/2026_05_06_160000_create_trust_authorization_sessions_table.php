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
        Schema::create('trust_authorization_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_signer_id')->constrained('document_signers')->cascadeOnDelete();
            $table->string('provider_name');
            $table->string('credential_id')->nullable();
            $table->string('authorization_mode')->default('explicit');
            $table->string('status')->default('pending');
            $table->string('authorization_reference')->nullable();
            $table->text('sad')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['document_signer_id', 'provider_name', 'status'], 'trust_auth_signer_provider_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trust_authorization_sessions');
    }
};
