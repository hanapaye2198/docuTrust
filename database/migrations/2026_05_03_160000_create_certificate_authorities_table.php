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
        Schema::create('certificate_authorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject_dn');
            $table->string('issuer_dn');
            $table->string('serial_number');
            $table->longText('public_key_pem');
            $table->longText('private_key_pem');
            $table->longText('certificate_pem');
            $table->string('fingerprint_sha256', 64)->unique();
            $table->timestamp('valid_from');
            $table->timestamp('valid_to');
            $table->boolean('is_root')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_authorities');
    }
};
