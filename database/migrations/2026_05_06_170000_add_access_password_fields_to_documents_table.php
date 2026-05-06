<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('access_password_hash')->nullable()->after('file_path');
            $table->string('access_password_hint')->nullable()->after('access_password_hash');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'access_password_hash',
                'access_password_hint',
            ]);
        });
    }
};
