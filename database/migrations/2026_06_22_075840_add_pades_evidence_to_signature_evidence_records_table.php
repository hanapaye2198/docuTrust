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
        Schema::table('signature_evidence_records', function (Blueprint $table) {
            if (! Schema::hasColumn('signature_evidence_records', 'pades_profile')) {
                $table->string('pades_profile', 20)->nullable()->after('audit_trail_snapshot');
            }

            if (! Schema::hasColumn('signature_evidence_records', 'cms_signature_hash')) {
                $table->string('cms_signature_hash', 200)->nullable()->after('pades_profile');
            }

            if (! Schema::hasColumn('signature_evidence_records', 'tsr_hash')) {
                $table->string('tsr_hash', 200)->nullable()->after('cms_signature_hash');
            }

            if (! Schema::hasColumn('signature_evidence_records', 'ltv_applied')) {
                $table->boolean('ltv_applied')->default(false)->after('tsr_hash');
            }

            if (! Schema::hasColumn('signature_evidence_records', 'csc_provider')) {
                $table->string('csc_provider', 200)->nullable()->after('ltv_applied');
            }

            if (! Schema::hasColumn('signature_evidence_records', 'csc_transaction_id')) {
                $table->string('csc_transaction_id', 500)->nullable()->after('csc_provider');
            }

            if (! Schema::hasColumn('signature_evidence_records', 'validation_snapshot')) {
                $table->json('validation_snapshot')->nullable()->after('csc_transaction_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signature_evidence_records', function (Blueprint $table) {
            foreach ([
                'pades_profile',
                'cms_signature_hash',
                'tsr_hash',
                'ltv_applied',
                'csc_provider',
                'csc_transaction_id',
                'validation_snapshot',
            ] as $column) {
                if (Schema::hasColumn('signature_evidence_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
