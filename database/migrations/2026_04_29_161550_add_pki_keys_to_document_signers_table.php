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
            $table->text('signing_public_key')->nullable()->after('expires_at');
            $table->text('signing_private_key')->nullable()->after('signing_public_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn([
                'signing_public_key',
                'signing_private_key',
            ]);
        });
    }
};
