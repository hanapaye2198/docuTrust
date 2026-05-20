<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einvoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('einvoice_id')->constrained('einvoices')->cascadeOnDelete();
            $table->string('status', 32)->default('queued');
            $table->string('submit_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['einvoice_id', 'status'], 'einvoice_submissions_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einvoice_submissions');
    }
};
