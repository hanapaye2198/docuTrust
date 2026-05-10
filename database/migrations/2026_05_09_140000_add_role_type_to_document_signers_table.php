<?php

use App\Enums\TemplateRoleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table): void {
            $table->string('role_type')->default(TemplateRoleType::Signer->value)->after('role_name');
        });

        DB::table('document_signers')
            ->whereNull('role_type')
            ->update(['role_type' => TemplateRoleType::Signer->value]);
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table): void {
            $table->dropColumn('role_type');
        });
    }
};
