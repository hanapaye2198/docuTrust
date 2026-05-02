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
        Schema::table('signatures', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->change();
            $table->longText('signature_value')->nullable()->after('signature_path');
            $table->string('signature_hash', 64)->nullable()->after('signature_value');
            $table->string('public_key_fingerprint', 64)->nullable()->after('signature_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->string('signature_path')->nullable(false)->change();
            $table->dropColumn([
                'signature_value',
                'signature_hash',
                'public_key_fingerprint',
            ]);
        });
    }
};
