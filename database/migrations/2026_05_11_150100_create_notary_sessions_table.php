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
        Schema::create('notary_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_request_id')->constrained('notary_requests')->cascadeOnDelete();
            $table->string('provider_name')->default('manual');
            $table->string('status')->default('scheduled');
            $table->string('room_name')->nullable();
            $table->text('meeting_url')->nullable();
            $table->string('host_reference')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->index(['notary_request_id', 'status'], 'notary_sessions_request_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notary_sessions');
    }
};
