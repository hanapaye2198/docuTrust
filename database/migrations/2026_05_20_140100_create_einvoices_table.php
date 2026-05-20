<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einvoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('billing_profile_id')->nullable()->constrained('billing_profiles')->nullOnDelete();
            $table->foreignId('notary_request_id')->nullable()->constrained('notary_requests')->nullOnDelete();
            $table->foreignId('notarial_register_entry_id')->nullable()->constrained('notarial_register_entries')->nullOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('invoice_number')->unique();
            $table->string('currency', 3)->default('PHP');
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('issue_date');
            $table->string('official_receipt_number')->nullable();
            $table->string('document_title')->nullable();
            $table->string('seller_name')->nullable();
            $table->string('seller_tin')->nullable();
            $table->string('seller_branch_code', 16)->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_email')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_tin')->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_email')->nullable();
            $table->json('source_payload')->nullable();
            $table->string('eis_unique_id')->nullable();
            $table->string('submit_id')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique('payment_id');
            $table->index(['organization_id', 'status'], 'einvoices_org_status_idx');
            $table->index(['notary_request_id', 'status'], 'einvoices_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einvoices');
    }
};
