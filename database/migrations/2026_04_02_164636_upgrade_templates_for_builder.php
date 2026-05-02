<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->json('files')->nullable()->after('name');
        });

        DB::table('templates')->orderBy('id')->each(function (object $row): void {
            $path = $row->file_path ?? null;
            if ($path !== null) {
                DB::table('templates')->where('id', $row->id)->update([
                    'files' => json_encode([$path]),
                ]);
            }
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->boolean('document_workflow')->default(false)->after('files');
            $table->string('email_subject')->nullable()->after('document_workflow');
            $table->text('email_message')->nullable()->after('email_subject');
            $table->string('signing_method')->default('docutrust_sign')->after('email_message');
            $table->boolean('audit_enabled')->default(true)->after('signing_method');
            $table->json('audit_settings')->nullable()->after('audit_enabled');
        });

        DB::table('template_signers')->where('role_type', 'viewer')->update(['role_type' => 'recipient']);

        Schema::table('template_signers', function (Blueprint $table) {
            $table->unsignedInteger('signing_order')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_signers', function (Blueprint $table) {
            $table->unsignedInteger('signing_order')->default(0)->nullable(false)->change();
        });

        DB::table('template_signers')->where('role_type', 'recipient')->update(['role_type' => 'viewer']);

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn([
                'audit_settings',
                'audit_enabled',
                'signing_method',
                'email_message',
                'email_subject',
                'document_workflow',
            ]);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->string('file_path')->after('name');
        });

        DB::table('templates')->orderBy('id')->each(function (object $row): void {
            $files = $row->files ? json_decode($row->files, true) : [];
            $first = is_array($files) && $files !== [] ? $files[0] : 'templates/placeholder.pdf';
            DB::table('templates')->where('id', $row->id)->update([
                'file_path' => $first,
            ]);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('files');
        });
    }
};
