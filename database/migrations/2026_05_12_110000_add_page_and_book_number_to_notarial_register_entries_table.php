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
        Schema::table('notarial_register_entries', function (Blueprint $table) {
            $table->unsignedInteger('page_number')->nullable()->after('entry_year');
            $table->string('book_number')->nullable()->after('page_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notarial_register_entries', function (Blueprint $table) {
            $table->dropColumn(['page_number', 'book_number']);
        });
    }
};
