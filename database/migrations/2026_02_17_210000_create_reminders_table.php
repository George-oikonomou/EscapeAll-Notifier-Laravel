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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('type')->default('coming_soon'); // this_month, specific_day, coming_soon
            $table->date('remind_at')->nullable(); // For specific_day type
            $table->boolean('notified')->default(false); // Track if reminder was sent
            $table->timestamps();

            // Ensure a user can only have one reminder per room
            $table->unique(['user_id', 'room_id']);

            // Indexes for efficient lookups
            $table->index('user_id');
            $table->index('room_id');
            $table->index('type');
            $table->index('remind_at');
            $table->index('notified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};

