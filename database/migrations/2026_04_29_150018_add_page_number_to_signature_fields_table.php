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
        Schema::table('signature_fields', function (Blueprint $table) {
            $table->unsignedInteger('page_number')->default(1)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signature_fields', function (Blueprint $table) {
            $table->dropColumn('page_number');
        });
    }
};
