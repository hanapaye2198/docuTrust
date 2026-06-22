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
        Schema::table('signatures', function (Blueprint $table) {
            if (! Schema::hasColumn('signatures', 'pades_profile')) {
                $table->string('pades_profile', 20)->nullable()->after('position_data');
            }

            if (! Schema::hasColumn('signatures', 'cms_signature')) {
                $table->longText('cms_signature')->nullable()->after('pades_profile');
            }

            if (! Schema::hasColumn('signatures', 'byte_range')) {
                $table->json('byte_range')->nullable()->after('cms_signature');
            }

            if (! Schema::hasColumn('signatures', 'digest_algorithm')) {
                $table->string('digest_algorithm', 20)->nullable()->after('byte_range');
            }

            if (! Schema::hasColumn('signatures', 'signing_time')) {
                $table->timestamp('signing_time')->nullable()->after('digest_algorithm');
            }

            if (! Schema::hasColumn('signatures', 'tsa_timestamp')) {
                $table->longText('tsa_timestamp')->nullable()->after('signing_time');
            }

            if (! Schema::hasColumn('signatures', 'tsa_url')) {
                $table->string('tsa_url', 500)->nullable()->after('tsa_timestamp');
            }

            if (! Schema::hasColumn('signatures', 'ltv_applied')) {
                $table->boolean('ltv_applied')->default(false)->after('tsa_url');
            }

            if (! Schema::hasColumn('signatures', 'ltv_dss_path')) {
                $table->string('ltv_dss_path', 500)->nullable()->after('ltv_applied');
            }

            if (! Schema::hasColumn('signatures', 'csc_credential_id')) {
                $table->string('csc_credential_id', 500)->nullable()->after('ltv_dss_path');
            }

            if (! Schema::hasColumn('signatures', 'csc_transaction_id')) {
                $table->string('csc_transaction_id', 500)->nullable()->after('csc_credential_id');
            }

            if (! Schema::hasColumn('signatures', 'validation_status')) {
                $table->string('validation_status', 50)->nullable()->after('csc_transaction_id');
            }

            if (! Schema::hasColumn('signatures', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('validation_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            foreach ([
                'pades_profile',
                'cms_signature',
                'byte_range',
                'digest_algorithm',
                'signing_time',
                'tsa_timestamp',
                'tsa_url',
                'ltv_applied',
                'ltv_dss_path',
                'csc_credential_id',
                'csc_transaction_id',
                'validation_status',
                'validated_at',
            ] as $column) {
                if (Schema::hasColumn('signatures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
