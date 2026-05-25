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
        Schema::table('einvoice_submissions', function (Blueprint $table) {
            $table->timestamp('payload_pruned_at')->nullable()->after('resolved_at');
            $table->index(['payload_pruned_at', 'resolved_at'], 'einvoice_submissions_prune_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('einvoice_submissions', function (Blueprint $table) {
            $table->dropIndex('einvoice_submissions_prune_idx');
            $table->dropColumn('payload_pruned_at');
        });
    }
};
