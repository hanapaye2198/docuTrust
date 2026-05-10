<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('audit_enabled')->nullable()->after('email_message');
            $table->json('audit_settings')->nullable()->after('audit_enabled');
        });

        DB::table('documents')->update([
            'audit_enabled' => true,
            'audit_settings' => json_encode(Document::defaultAuditSettings(), JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['audit_enabled', 'audit_settings']);
        });
    }
};
