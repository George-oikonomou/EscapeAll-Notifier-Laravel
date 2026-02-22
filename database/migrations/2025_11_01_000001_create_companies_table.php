<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique(); // CompanyId from source
            $table->string('name'); // DisplayName
            $table->string('logo_url')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('full_address', 500)->nullable();
            $table->uuid('municipality_external_id')->nullable()->index();
            $table->timestamps();

            $table->index(['name']);

            // FK to municipalities.external_id (both UUID). Since it's not the PK, add a manual foreign key.
            $table->foreign('municipality_external_id')
                  ->references('external_id')
                  ->on('municipalities')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
