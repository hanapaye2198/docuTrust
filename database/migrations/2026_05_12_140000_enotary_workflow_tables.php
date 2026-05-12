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
        Schema::table('notary_requests', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('metadata');
            $table->text('remarks')->nullable()->after('document_path');
            $table->timestamp('verified_at')->nullable()->after('identity_verified_at');
            $table->timestamp('notarized_at')->nullable()->after('approved_at');
        });

        Schema::create('notary_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_request_id')->constrained('notary_requests')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('role')->default('signer');
            $table->timestamps();

            $table->index(['notary_request_id', 'email'], 'notary_signers_request_email_idx');
        });

        Schema::create('notary_identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_request_id')->constrained('notary_requests')->cascadeOnDelete();
            $table->foreignId('notary_signer_id')->constrained('notary_signers')->cascadeOnDelete();
            $table->string('id_type');
            $table->string('id_number');
            $table->string('id_image_path');
            $table->string('selfie_image_path')->nullable();
            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['notary_request_id', 'verification_status'], 'notary_identity_request_status_idx');
        });

        Schema::create('notary_geo_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_request_id')->constrained('notary_requests')->cascadeOnDelete();
            $table->foreignId('notary_signer_id')->nullable()->constrained('notary_signers')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('vpn_detected')->default(false);
            $table->string('verification_status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['notary_request_id', 'verification_status'], 'notary_geo_request_status_idx');
        });

        Schema::table('notary_sessions', function (Blueprint $table) {
            $table->foreignId('notary_user_id')->nullable()->after('notary_request_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notary_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notary_user_id');
        });

        Schema::dropIfExists('notary_geo_logs');
        Schema::dropIfExists('notary_identity_verifications');
        Schema::dropIfExists('notary_signers');

        Schema::table('notary_requests', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'remarks', 'verified_at', 'notarized_at']);
        });
    }
};
