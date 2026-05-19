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
            $table->text('ocr_text')->nullable()->after('document_path');
            $table->string('rejection_reason', 500)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ekyc_records', function (Blueprint $table) {
            $table->dropColumn(['ocr_text', 'rejection_reason']);
        });
    }
};
