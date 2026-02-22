<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // Stable slug key, aligned with lang files (e.g. "actor", "horror").
            $table->string('slug')->unique();
            // Optional stable numeric code from external systems; keep nullable for flexibility.
            $table->unsignedInteger('code')->nullable()->unique();
            // Icon CSS classes (language-independent).
            $table->string('icon')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
