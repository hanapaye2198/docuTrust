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
        Schema::table('notary_sessions', function (Blueprint $table) {
            $table->json('verification_checklist')->nullable()->after('evidence');
            $table->string('recording_path')->nullable()->after('verification_checklist');
            $table->boolean('signer_confirmed')->default(false)->after('recording_path');
            $table->timestamp('signer_confirmed_at')->nullable()->after('signer_confirmed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notary_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'verification_checklist',
                'recording_path',
                'signer_confirmed',
                'signer_confirmed_at',
            ]);
        });
    }
};
