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
        Schema::table('signer_certificates', function (Blueprint $table) {
            if (! Schema::hasColumn('signer_certificates', 'csc_credential_id')) {
                $table->string('csc_credential_id', 500)->nullable()->after('provider_reference');
            }

            if (! Schema::hasColumn('signer_certificates', 'certificate_chain')) {
                $table->longText('certificate_chain')->nullable()->after('issuer_certificate_pem');
            }

            if (! Schema::hasColumn('signer_certificates', 'valid_until')) {
                $table->timestamp('valid_until')->nullable()->after('valid_to');
            }

            if (! Schema::hasColumn('signer_certificates', 'key_algorithm')) {
                $table->string('key_algorithm', 50)->nullable()->after('valid_until');
            }

            if (! Schema::hasColumn('signer_certificates', 'key_size')) {
                $table->integer('key_size')->nullable()->after('key_algorithm');
            }

            if (! Schema::hasColumn('signer_certificates', 'ocsp_url')) {
                $table->string('ocsp_url', 500)->nullable()->after('key_size');
            }

            if (! Schema::hasColumn('signer_certificates', 'crl_url')) {
                $table->string('crl_url', 500)->nullable()->after('ocsp_url');
            }

            if (! Schema::hasColumn('signer_certificates', 'ocsp_staple')) {
                $table->longText('ocsp_staple')->nullable()->after('crl_url');
            }

            if (! Schema::hasColumn('signer_certificates', 'ocsp_checked_at')) {
                $table->timestamp('ocsp_checked_at')->nullable()->after('ocsp_staple');
            }

            if (! Schema::hasColumn('signer_certificates', 'revocation_status')) {
                $table->string('revocation_status', 50)->nullable()->after('ocsp_checked_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signer_certificates', function (Blueprint $table) {
            foreach ([
                'csc_credential_id',
                'certificate_chain',
                'valid_until',
                'key_algorithm',
                'key_size',
                'ocsp_url',
                'crl_url',
                'ocsp_staple',
                'ocsp_checked_at',
                'revocation_status',
            ] as $column) {
                if (Schema::hasColumn('signer_certificates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
