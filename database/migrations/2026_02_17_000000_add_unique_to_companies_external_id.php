<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The unique constraint is already defined in the original migration
        // This migration is kept for compatibility but does nothing if index exists
        // Laravel's schema builder handles this cross-database
        if (!$this->indexExists('companies', 'companies_external_id_unique')) {
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

    /**
     * Check if an index exists (cross-database compatible)
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $result = $connection->select(
                "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, $indexName]
            );
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $result = $connection->select(
                "SHOW INDEX FROM {$table} WHERE Key_name = ?",
                [$indexName]
            );
        } else {
            // SQLite or other - just try to add and catch exception
            return false;
        }

        return count($result) > 0;
    }
};

