<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signer_certificates', function (Blueprint $table) {
            $table->foreignId('certificate_authority_id')->nullable()->change();
            $table->string('certificate_source')->default('app_managed')->after('certificate_authority_id');
            $table->string('provider_name')->nullable()->after('certificate_source');
            $table->string('provider_reference')->nullable()->after('provider_name');
            $table->longText('issuer_certificate_pem')->nullable()->after('certificate_pem');
        });
    }

    public function down(): void
    {
        Schema::table('signer_certificates', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_source',
                'provider_name',
                'provider_reference',
                'issuer_certificate_pem',
            ]);

            $table->foreignId('certificate_authority_id')->nullable(false)->change();
        });
    }
};
