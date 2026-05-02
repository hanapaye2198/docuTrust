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
            $table->timestamp('sent_at')->nullable()->after('status');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->unsignedInteger('signing_order')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->unsignedInteger('signing_order')->default(0)->nullable(false)->change();
        });
    }
};
