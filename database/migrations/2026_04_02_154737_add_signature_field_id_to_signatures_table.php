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
        Schema::table('signatures', function (Blueprint $table) {
            $table->foreignId('signature_field_id')
                ->nullable()
                ->after('signer_id')
                ->constrained('signature_fields')
                ->nullOnDelete();
            $table->unique(['signature_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->dropUnique(['signature_field_id']);
            $table->dropConstrainedForeignId('signature_field_id');
        });
    }
};
