<?php

use App\Enums\SigningMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table): void {
            $table->string('signing_method')->default(SigningMethod::EmailLink->value)->after('email');
            $table->foreignId('user_id')->nullable()->after('signing_method')->constrained()->nullOnDelete();
            $table->index(['document_id', 'signing_method']);
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table): void {
            $table->dropIndex(['document_id', 'signing_method']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('signing_method');
        });
    }
};
