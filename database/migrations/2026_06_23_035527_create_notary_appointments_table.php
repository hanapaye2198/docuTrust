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
        Schema::create('notary_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_availability_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notary_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notary_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->string('meeting_link')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['notary_user_id', 'status']);
            $table->index(['client_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notary_appointments');
    }
};
