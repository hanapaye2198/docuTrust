<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notary_sessions', function (Blueprint $table) {
            $table->foreignId('notary_signer_id')
                ->nullable()
                ->after('notary_request_id')
                ->constrained('notary_signers')
                ->nullOnDelete();
            $table->string('access_token', 64)->nullable()->after('notary_signer_id');
            $table->timestamp('invitation_sent_at')->nullable()->after('scheduled_for');

            $table->unique('access_token');
            $table->index(['notary_request_id', 'notary_signer_id'], 'notary_sessions_request_signer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notary_sessions', function (Blueprint $table) {
            $table->dropIndex('notary_sessions_request_signer_idx');
            $table->dropUnique(['access_token']);
            $table->dropConstrainedForeignId('notary_signer_id');
            $table->dropColumn(['access_token', 'invitation_sent_at']);
        });
    }
};
