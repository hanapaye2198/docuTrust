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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('notary_request_id')->constrained('notary_requests')->cascadeOnDelete();
            $table->foreignId('notarial_register_entry_id')->nullable()->constrained('notarial_register_entries')->nullOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->default('gatewayhub');
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('gateway', 32);
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 32)->default('pending');
            $table->text('qr_data')->nullable();
            $table->text('redirect_url')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_payment_id'], 'payments_provider_payment_unique');
            $table->index(['notary_request_id', 'status'], 'payments_request_status_idx');
            $table->index(['organization_id', 'status'], 'payments_org_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
