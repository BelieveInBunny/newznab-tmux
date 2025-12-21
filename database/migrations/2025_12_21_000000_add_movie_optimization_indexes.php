<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes to optimize movie-related queries for large datasets.
 *
 * Key optimizations:
 * - Composite index on releases for movie lookups (imdbid, postdate, passwordstatus)
 * - Index on movieinfo for title searches with year filtering
 * - Index on releases for movie processing (categories_id, imdbid)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check and add indexes on releases table
        Schema::table('releases', function (Blueprint $table) {
            // Composite index for movie range queries - helps with:
            // - Getting latest release per movie
            // - Filtering by password status
            // - Ordering by postdate
            if (! $this->hasIndex('releases', 'ix_releases_imdbid_postdate_passwordstatus')) {
                $table->index(['imdbid', 'postdate', 'passwordstatus'], 'ix_releases_imdbid_postdate_passwordstatus');
            }

            // Index for movie processing - unprocessed releases lookup
            if (! $this->hasIndex('releases', 'ix_releases_categories_imdbid')) {
                $table->index(['categories_id', 'imdbid'], 'ix_releases_categories_imdbid');
            }
        });

        // Check and add indexes on movieinfo table
        Schema::table('movieinfo', function (Blueprint $table) {
            // Composite index for local IMDB search with year filtering
            if (! $this->hasIndex('movieinfo', 'ix_movieinfo_year_title')) {
                $table->index(['year', 'title'], 'ix_movieinfo_year_title');
            }

            // Index for unique imdbid lookups (should already exist, but ensure it's there)
            if (! $this->hasIndex('movieinfo', 'ix_movieinfo_imdbid')) {
                $table->unique('imdbid', 'ix_movieinfo_imdbid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            if ($this->hasIndex('releases', 'ix_releases_imdbid_postdate_passwordstatus')) {
                $table->dropIndex('ix_releases_imdbid_postdate_passwordstatus');
            }
            if ($this->hasIndex('releases', 'ix_releases_categories_imdbid')) {
                $table->dropIndex('ix_releases_categories_imdbid');
            }
        });

        Schema::table('movieinfo', function (Blueprint $table) {
            if ($this->hasIndex('movieinfo', 'ix_movieinfo_year_title')) {
                $table->dropIndex('ix_movieinfo_year_title');
            }
            // Don't drop the unique index on imdbid as it may have existed before
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $result = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = ?
            AND table_name = ?
            AND index_name = ?
        ", [$databaseName, $table, $indexName]);

        return isset($result[0]) && $result[0]->count > 0;
    }
};

