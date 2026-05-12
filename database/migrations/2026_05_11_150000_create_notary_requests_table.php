<?php

use App\Enums\NotaryRequestStatus;
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
        Schema::create('notary_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('request_type')->default('acknowledgment');
            $table->string('status')->default(NotaryRequestStatus::Draft->value);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'notary_requests_org_status_idx');
            $table->index(['user_id', 'status'], 'notary_requests_user_status_idx');
            $table->index(['notary_user_id', 'status'], 'notary_requests_notary_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notary_requests');
    }
};
