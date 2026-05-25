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
        Schema::table('ekyc_records', function (Blueprint $table) {
            $table->string('provider', 32)->default('tesseract')->after('document_type');
            $table->string('provider_reference')->nullable()->after('provider');
            $table->json('provider_payload')->nullable()->after('rejection_reason');

            $table->index('provider_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ekyc_records', function (Blueprint $table) {
            $table->dropIndex(['provider_reference']);
            $table->dropColumn(['provider', 'provider_reference', 'provider_payload']);
        });
    }
};
