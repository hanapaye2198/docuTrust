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
        Schema::create('notary_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('commission_number');
            $table->string('commission_jurisdiction')->default('Philippines');
            $table->date('commission_issued_at');
            $table->date('commission_expires_at');
            $table->string('roll_number')->nullable();
            $table->string('ibp_number')->nullable();
            $table->string('ptr_number')->nullable();
            $table->string('mcle_compliance_number')->nullable();
            $table->string('seal_image_path')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'commission_number'], 'notary_credentials_user_commission_unique');
            $table->index(['user_id', 'status'], 'notary_credentials_user_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notary_credentials');
    }
};
