<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->string('signing_provider')->nullable()->after('signature_algorithm');
            $table->string('signing_provider_reference')->nullable()->after('signing_provider');
        });
    }

    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->dropColumn([
                'signing_provider',
                'signing_provider_reference',
            ]);
        });
    }
};
