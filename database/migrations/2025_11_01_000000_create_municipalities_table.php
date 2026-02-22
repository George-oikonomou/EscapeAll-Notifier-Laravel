<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('municipalities')) {
            Schema::create('municipalities', function (Blueprint $table) {
                $table->id();
                $table->uuid('external_id')->unique();
                $table->string('name');
                $table->timestamps();

                $table->index(['name']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('municipalities')) {
            Schema::dropIfExists('municipalities');
        }
    }
};

