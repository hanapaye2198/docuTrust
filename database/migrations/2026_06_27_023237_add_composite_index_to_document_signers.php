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
        Schema::table('document_signers', function (Blueprint $table): void {
            // Covers sidebar badge count and sign-requests page:
            // WHERE user_id = ? AND status = ?
            $table->index(['user_id', 'status'], 'document_signers_user_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table): void {
            $table->dropIndex('document_signers_user_id_status_index');
        });
    }
};
