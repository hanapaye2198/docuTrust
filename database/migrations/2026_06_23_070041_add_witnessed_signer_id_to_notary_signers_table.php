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
        Schema::table('notary_signers', function (Blueprint $table) {
            $table->foreignId('witnessed_signer_id')
                ->nullable()
                ->after('role')
                ->constrained('notary_signers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notary_signers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('witnessed_signer_id');
        });
    }
};
