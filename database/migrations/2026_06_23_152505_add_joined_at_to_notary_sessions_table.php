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
            $table->timestamp('joined_at')->nullable()->after('access_token');
            $table->timestamp('left_at')->nullable()->after('joined_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notary_sessions', function (Blueprint $table) {
            $table->dropColumn(['joined_at', 'left_at']);
        });
    }
};
