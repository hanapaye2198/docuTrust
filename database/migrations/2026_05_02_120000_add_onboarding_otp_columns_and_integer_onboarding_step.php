<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_otp')) {
                $table->string('email_otp', 6)->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'email_otp_expires_at')) {
                $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp');
            }
            if (! Schema::hasColumn('users', 'mobile_number')) {
                $table->string('mobile_number', 32)->nullable()->after('email_otp_expires_at');
            }
            if (! Schema::hasColumn('users', 'mobile_verified_at')) {
                $table->timestamp('mobile_verified_at')->nullable()->after('mobile_number');
            }
            if (! Schema::hasColumn('users', 'kyc_id_type')) {
                $table->string('kyc_id_type', 64)->nullable()->after('mobile_verified_at');
            }
            if (! Schema::hasColumn('users', 'kyc_file_path')) {
                $table->string('kyc_file_path', 500)->nullable()->after('kyc_id_type');
            }
            if (! Schema::hasColumn('users', 'kyc_verified_at')) {
                $table->timestamp('kyc_verified_at')->nullable()->after('kyc_file_path');
            }
            if (! Schema::hasColumn('users', 'mfa_enabled')) {
                $table->boolean('mfa_enabled')->default(false)->after('kyc_verified_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('onboarding_step_int')->default(1);
        });

        $map = [
            'registered' => 1,
            'email_verified' => 2,
            'phone_verified' => 3,
            'ekyc_pending' => 3,
            'ekyc_verified' => 4,
            'mfa_setup' => 4,
            'completed' => 5,
        ];

        foreach ($map as $legacy => $value) {
            DB::table('users')
                ->where('onboarding_step', $legacy)
                ->update(['onboarding_step_int' => $value]);
        }

        DB::table('users')
            ->whereNotIn('onboarding_step', array_keys($map))
            ->update(['onboarding_step_int' => 1]);

        DB::table('users')
            ->where('onboarding_step_int', 5)
            ->update(['mfa_enabled' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_step');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('onboarding_step_int', 'onboarding_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
