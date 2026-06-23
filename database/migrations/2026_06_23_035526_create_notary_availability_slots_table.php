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
        Schema::create('notary_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notary_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_booked')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->integer('duration_minutes')->default(60);
            $table->timestamps();

            $table->index(['notary_user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notary_availability_slots');
    }
};
