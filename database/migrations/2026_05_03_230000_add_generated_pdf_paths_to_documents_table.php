<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('prepared_pdf_path')->nullable()->after('file_path');
            $table->string('final_pdf_path')->nullable()->after('prepared_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['prepared_pdf_path', 'final_pdf_path']);
        });
    }
};
