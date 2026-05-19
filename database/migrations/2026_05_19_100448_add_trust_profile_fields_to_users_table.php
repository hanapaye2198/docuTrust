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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('suffix');
            $table->text('address')->nullable()->after('mobile_verified_at');
            $table->string('nationality', 100)->nullable()->after('address');
            $table->date('date_of_birth')->nullable()->after('nationality');
            $table->string('government_id_type', 50)->nullable()->after('date_of_birth');
            $table->string('government_id_number', 100)->nullable()->after('government_id_type');
            $table->timestamp('selfie_verified_at')->nullable()->after('kyc_verified_at');
            $table->timestamp('gps_permission_granted_at')->nullable()->after('selfie_verified_at');
            $table->string('signature_image_path')->nullable()->after('gps_permission_granted_at');
            $table->string('signature_initials', 10)->nullable()->after('signature_image_path');
            $table->string('signature_type', 20)->nullable()->after('signature_initials');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_path',
                'address',
                'nationality',
                'date_of_birth',
                'government_id_type',
                'government_id_number',
                'selfie_verified_at',
                'gps_permission_granted_at',
                'signature_image_path',
                'signature_initials',
                'signature_type',
            ]);
        });
    }
};
