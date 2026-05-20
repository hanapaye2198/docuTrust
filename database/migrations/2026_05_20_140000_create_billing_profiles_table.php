<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('registered_name');
            $table->string('tin')->nullable();
            $table->string('branch_code', 16)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2)->default('PH');
            $table->string('eis_environment', 16)->default('sandbox');
            $table->string('eis_accreditation_id')->nullable();
            $table->string('eis_application_id')->nullable();
            $table->string('eis_username')->nullable();
            $table->text('eis_password')->nullable();
            $table->string('eis_certificate_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'billing_profiles_org_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};
