<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('signing_workflow')->default('sequential')->after('access_password_hint');
            $table->string('archive_storage_disk')->nullable()->after('certificate_path');
            $table->string('archive_document_path')->nullable()->after('archive_storage_disk');
            $table->string('archive_certificate_path')->nullable()->after('archive_document_path');
            $table->timestamp('archived_at')->nullable()->after('archive_certificate_path');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn([
                'signing_workflow',
                'archive_storage_disk',
                'archive_document_path',
                'archive_certificate_path',
                'archived_at',
            ]);
        });
    }
};
