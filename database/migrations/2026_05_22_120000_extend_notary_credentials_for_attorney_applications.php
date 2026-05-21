<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notary_credentials', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->timestamp('submitted_at')->nullable()->after('reviewed_at');
            $table->boolean('is_renewal')->default(false)->after('submitted_at');
            $table->string('commission_document_path')->nullable()->after('signature_image_path');
            $table->string('ibp_document_path')->nullable()->after('commission_document_path');
            $table->string('ptr_document_path')->nullable()->after('ibp_document_path');
            $table->string('mcle_document_path')->nullable()->after('ptr_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('notary_credentials', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropColumn([
                'rejection_reason',
                'reviewed_by_user_id',
                'reviewed_at',
                'submitted_at',
                'is_renewal',
                'commission_document_path',
                'ibp_document_path',
                'ptr_document_path',
                'mcle_document_path',
            ]);
        });
    }
};
