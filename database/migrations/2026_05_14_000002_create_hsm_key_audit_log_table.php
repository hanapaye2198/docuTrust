<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create HSM key audit log table
     * 
     * Required for CSC compliance to maintain audit trail of HSM operations.
     */
    public function up(): void
    {
        Schema::create('hsm_key_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('operation'); // generate, sign, verify, destroy
            $table->string('key_id')->nullable();
            $table->string('object_type')->nullable(); // signer_certificate, ca_certificate
            $table->unsignedBigInteger('object_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsm_key_audit_log');
    }
};
