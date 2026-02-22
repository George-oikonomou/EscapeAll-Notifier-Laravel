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
        Schema::create('room_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('available_date');
            $table->time('available_time');
            $table->timestamps();

            // Unique constraint: one row per room/date/time combination
            $table->unique(['room_id', 'available_date', 'available_time'], 'room_availability_unique');

            // Index for efficient lookups
            $table->index(['available_date', 'available_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_availabilities');
    }
};

