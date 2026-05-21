<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enotary_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('email');
            $table->string('full_name');
            $table->foreignId('notary_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notary_signer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['email', 'notary_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enotary_invitations');
    }
};
