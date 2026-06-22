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
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('csc_signed')->default(false)->after('sent_at');
            $table->json('pades_byte_range')->nullable()->after('csc_signed');
            $table->longText('pades_cms_signature')->nullable()->after('pades_byte_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'csc_signed',
                'pades_byte_range',
                'pades_cms_signature',
            ]);
        });
    }
};
