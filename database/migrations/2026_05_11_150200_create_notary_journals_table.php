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
        Schema::create('notary_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_request_id')->constrained('notary_requests')->cascadeOnDelete();
            $table->foreignId('notary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type')->default('note');
            $table->text('summary');
            $table->json('legal_assertions')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['notary_request_id', 'entry_type'], 'notary_journals_request_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notary_journals');
    }
};
