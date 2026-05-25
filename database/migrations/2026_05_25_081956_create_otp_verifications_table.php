<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('otps') && ! Schema::hasTable('otp_verifications')) {
            Schema::rename('otps', 'otp_verifications');
        }

        if (! Schema::hasTable('otp_verifications')) {
            Schema::create('otp_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('email')->nullable()->index();
                $table->string('mobile_number', 32)->nullable()->index();
                $table->string('purpose', 64)->default('verification');
                $table->string('channel', 16)->default('sms');
                $table->string('otp_code');
                $table->timestamp('expires_at');
                $table->timestamp('verified_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('otp_verifications', function (Blueprint $table) {
            if (! Schema::hasColumn('otp_verifications', 'purpose')) {
                $table->string('purpose', 64)->default('verification')->after('mobile_number');
            }
            if (! Schema::hasColumn('otp_verifications', 'channel')) {
                $table->string('channel', 16)->default('sms')->after('purpose');
            }
            if (! Schema::hasColumn('otp_verifications', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('attempts');
            }
            if (! Schema::hasColumn('otp_verifications', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('otp_verifications') && ! Schema::hasTable('otps')) {
            Schema::rename('otp_verifications', 'otps');

            return;
        }

        Schema::dropIfExists('otp_verifications');
    }
};
