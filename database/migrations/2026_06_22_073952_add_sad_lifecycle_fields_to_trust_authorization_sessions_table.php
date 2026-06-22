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
        Schema::table('trust_authorization_sessions', function (Blueprint $table) {
            $table->foreignId('document_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('consumed_at')->nullable()->after('completed_at');
            $table->index(
                ['document_id', 'document_signer_id', 'status', 'consumed_at'],
                'trust_auth_sad_lookup_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trust_authorization_sessions', function (Blueprint $table) {
            $table->dropIndex('trust_auth_sad_lookup_idx');
            $table->dropConstrainedForeignId('document_id');
            $table->dropColumn('consumed_at');
        });
    }
};
