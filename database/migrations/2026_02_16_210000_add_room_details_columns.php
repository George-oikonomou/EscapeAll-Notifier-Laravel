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
            $table->string('slug')->nullable()->after('provider');
            $table->text('short_description')->nullable()->after('slug');
            $table->text('description')->nullable()->after('short_description');
            $table->float('rating')->nullable()->after('description');
            $table->unsignedInteger('reviews_count')->nullable()->after('rating');
            $table->unsignedInteger('duration_minutes')->nullable()->after('reviews_count');
            $table->unsignedInteger('min_players')->nullable()->after('duration_minutes');
            $table->unsignedInteger('max_players')->nullable()->after('min_players');
            $table->float('escape_rate')->nullable()->after('max_players');
            $table->string('image_url')->nullable()->after('escape_rate');
            $table->json('categories')->nullable()->after('image_url');
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
