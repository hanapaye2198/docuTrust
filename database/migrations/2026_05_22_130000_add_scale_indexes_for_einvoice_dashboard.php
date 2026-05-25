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
        Schema::table('einvoices', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'einvoices_org_created_idx');
            $table->index(['status', 'created_at'], 'einvoices_status_created_idx');
        });

        Schema::table('einvoice_submissions', function (Blueprint $table) {
            $table->index(['einvoice_id', 'created_at'], 'einvoice_submissions_invoice_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('einvoice_submissions', function (Blueprint $table) {
            $table->dropIndex('einvoice_submissions_invoice_created_idx');
        });

        Schema::table('einvoices', function (Blueprint $table) {
            $table->dropIndex('einvoices_status_created_idx');
            $table->dropIndex('einvoices_org_created_idx');
        });
    }
};
