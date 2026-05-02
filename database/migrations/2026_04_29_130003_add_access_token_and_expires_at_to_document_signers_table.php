<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('access_token')->nullable()->unique()->after('email');
            $table->timestamp('expires_at')->nullable()->after('signed_at');
        });

        DB::table('document_signers')->orderBy('id')->each(function (object $signer): void {
            DB::table('document_signers')
                ->where('id', $signer->id)
                ->update(['access_token' => (string) Str::uuid()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropUnique(['access_token']);
            $table->dropColumn(['access_token', 'expires_at']);
        });
    }
};
