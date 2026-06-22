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
        Schema::table('document_signers', function (Blueprint $table) {
            if (! Schema::hasColumn('document_signers', 'csc_access_token')) {
                $table->text('csc_access_token')->nullable()->after('remote_credential_id');
            }

            if (! Schema::hasColumn('document_signers', 'csc_token_expires_at')) {
                $table->timestamp('csc_token_expires_at')->nullable()->after('csc_access_token');
            }

            if (! Schema::hasColumn('document_signers', 'csc_signing_completed')) {
                $table->boolean('csc_signing_completed')->default(false)->after('csc_token_expires_at');
            }

            if (! Schema::hasColumn('document_signers', 'csc_signing_completed_at')) {
                $table->timestamp('csc_signing_completed_at')->nullable()->after('csc_signing_completed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            foreach ([
                'csc_access_token',
                'csc_token_expires_at',
                'csc_signing_completed',
                'csc_signing_completed_at',
            ] as $column) {
                if (Schema::hasColumn('document_signers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
