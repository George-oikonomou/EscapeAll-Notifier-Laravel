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
            $table->float('difficulty')->nullable()->after('escape_rate');
            $table->json('languages')->nullable()->after('difficulty');
            $table->string('video_url', 500)->nullable()->after('languages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['difficulty', 'languages', 'video_url']);
        });
    }
};
