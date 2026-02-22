<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only add unique constraint if it doesn't already exist
        $indexExists = collect(DB::select("SHOW INDEX FROM companies WHERE Key_name = 'companies_external_id_unique'"))
            ->isNotEmpty();

        if (!$indexExists) {
            Schema::table('companies', function (Blueprint $table) {
                $table->unique('external_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
        });
    }
};
