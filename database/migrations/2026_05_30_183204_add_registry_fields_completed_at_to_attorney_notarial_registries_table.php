<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attorney_notarial_registries', function (Blueprint $table) {
            $table->timestamp('registry_fields_completed_at')->nullable()->after('notary_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('attorney_notarial_registries', function (Blueprint $table) {
            $table->dropColumn('registry_fields_completed_at');
        });
    }
};
