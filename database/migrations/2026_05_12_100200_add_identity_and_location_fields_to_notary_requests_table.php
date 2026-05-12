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
            $table->string('id_document_type')->nullable()->after('metadata');
            $table->string('id_document_number')->nullable()->after('id_document_type');
            $table->string('id_document_path')->nullable()->after('id_document_number');
            $table->string('selfie_path')->nullable()->after('id_document_path');
            $table->timestamp('identity_verified_at')->nullable()->after('selfie_path');
            $table->timestamp('location_verified_at')->nullable()->after('identity_verified_at');
            $table->string('location_ip_address')->nullable()->after('location_verified_at');
            $table->string('location_country_code')->nullable()->after('location_ip_address');
            $table->decimal('location_latitude', 10, 7)->nullable()->after('location_country_code');
            $table->decimal('location_longitude', 10, 7)->nullable()->after('location_latitude');
            $table->boolean('location_vpn_detected')->nullable()->after('location_longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notary_requests', function (Blueprint $table) {
            $table->dropColumn([
                'id_document_type',
                'id_document_number',
                'id_document_path',
                'selfie_path',
                'identity_verified_at',
                'location_verified_at',
                'location_ip_address',
                'location_country_code',
                'location_latitude',
                'location_longitude',
                'location_vpn_detected',
            ]);
        });
    }
};
