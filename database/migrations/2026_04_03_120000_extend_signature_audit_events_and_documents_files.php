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
        Schema::table('signature_audit_events', function (Blueprint $table) {
            $table->foreignId('document_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->nullable()->after('document_id')->constrained('document_signers')->nullOnDelete();
            $table->string('action', 32)->after('signer_id');
            $table->string('ip_address', 45)->nullable()->after('action');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->json('files')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('files');
        });

        Schema::table('signature_audit_events', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropForeign(['signer_id']);
            $table->dropColumn(['document_id', 'signer_id', 'action', 'ip_address']);
        });
    }
};
