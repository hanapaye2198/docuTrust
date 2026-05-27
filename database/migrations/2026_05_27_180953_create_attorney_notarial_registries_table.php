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
        Schema::create('attorney_notarial_registries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_request_id')->constrained()->cascadeOnDelete();
            $table->string('entry_no')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('parties');
            $table->json('witnesses')->nullable();
            $table->json('competent_evidence');
            $table->json('notarization_timestamps')->nullable();
            $table->string('notarial_act_type');
            $table->decimal('fees', 12, 2)->default(0);
            $table->string('official_receipt_no')->nullable();
            $table->string('notary_signature_path')->nullable();
            $table->timestamps();

            $table->unique('notary_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attorney_notarial_registries');
    }
};
