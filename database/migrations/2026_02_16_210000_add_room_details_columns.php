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
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('slug')->nullable();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->float('rating')->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('min_players')->nullable();
            $table->unsignedInteger('max_players')->nullable();
            $table->float('escape_rate')->nullable();
            $table->string('image_url')->nullable();
            $table->json('categories')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'short_description',
                'description',
                'rating',
                'reviews_count',
                'duration_minutes',
                'min_players',
                'max_players',
                'escape_rate',
                'image_url',
                'categories',
            ]);
        });
    }
};
