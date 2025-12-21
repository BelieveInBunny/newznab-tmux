<?php

namespace App\Services\Releases;

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for browsing and ordering releases on the frontend.
 */
class ReleaseBrowseService
{
    private const CACHE_VERSION_KEY = 'releases:cache_version';

    // RAR/ZIP Password indicator.
    public const PASSWD_NONE = 0; // No password.
    public const PASSWD_RAR = 1; // Definitely passworded.

    public function __construct()
    {
    }

    /**
     * Used for Browse results.
     * Optimized query - only fetches fields actually used in views.
     *
     * @return Collection|mixed
     */
    public function getBrowseRange($page, $cat, $start, $num, $orderBy, int $maxAge = -1, array $excludedCats = [], int|string $groupName = -1, int $minSize = 0): mixed
    {
        $cacheVersion = $this->getCacheVersion();
        $page = max(1, $page);
        $start = max(0, $start);

        $orderBy = $this->getBrowseOrder($orderBy);

        // Build WHERE conditions once
        $categorySearch = Category::getCategorySearch($cat);
        $ageCondition = $maxAge > 0 ? ' AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ' : '';
        $excludeCondition = \count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : '';
        $sizeCondition = $minSize > 0 ? sprintf(' AND r.size >= %d', $minSize) : '';
        $limitClause = ' LIMIT '.$num.($start > 0 ? ' OFFSET '.$start : '');
        $needsGroupJoin = (int) $groupName !== -1;
        $groupCondition = $needsGroupJoin ? sprintf(' AND g.name = %s ', escapeString($groupName)) : '';

        // Optimized query: fetch only required fields, minimize JOINs
        // Uses STRAIGHT_JOIN for categories (small tables), LEFT JOIN for optional data
        $qry = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart,
                r.fromname, r.grabs, r.comments, r.adddate, r.videos_id, r.haspreview,
                r.jpgstatus, r.nfostatus,
                CONCAT(cp.title, ' > ', c.title) AS category_name,
                %s AS group_name,
                m.imdbid,
                (SELECT COUNT(*) FROM dnzb_failures df WHERE df.release_id = r.id) AS failed,
                EXISTS(SELECT 1 FROM video_data vd WHERE vd.releases_id = r.id) AS reid
            FROM releases r
            %s
            STRAIGHT_JOIN categories c ON c.id = r.categories_id
            STRAIGHT_JOIN root_categories cp ON cp.id = c.root_categories_id
            LEFT JOIN movieinfo m ON m.id = r.movieinfo_id
            WHERE r.passwordstatus %s
            %s %s %s %s %s
            ORDER BY r.%s %s
            %s",
            $needsGroupJoin ? 'g.name' : '(SELECT name FROM usenet_groups WHERE id = r.groups_id)',
            $needsGroupJoin ? 'INNER JOIN usenet_groups g ON g.id = r.groups_id' : '',
            $this->showPasswords(),
            $categorySearch,
            $ageCondition,
            $excludeCondition,
            $sizeCondition,
            $groupCondition,
            $orderBy[0],
            $orderBy[1],
            $limitClause
        );

        $cacheKey = 'browse_'.md5($cacheVersion.$qry);
        $releases = Cache::get($cacheKey);
        if ($releases !== null) {
            return $releases;
        }

        $sql = DB::select($qry);
        if (\count($sql) > 0) {
            $possibleRows = $this->getBrowseCount($cat, $maxAge, $excludedCats, $groupName);
            $sql[0]->_totalcount = $sql[0]->_totalrows = $possibleRows;
        }

        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        Cache::put($cacheKey, $sql, $expiresAt);

        return $sql;
    }

    /**
     * Used for pager on browse page.
     * Optimized to avoid unnecessary JOINs and use faster counting.
     */
    public function getBrowseCount(array $cat, int $maxAge = -1, array $excludedCats = [], int|string $groupName = ''): int
    {
        $needsGroupJoin = $groupName !== '' && $groupName !== -1;

        return $this->getPagerCount(sprintf(
            'SELECT COUNT(*) AS count
                FROM releases r
                %s
                WHERE r.passwordstatus %s
                %s
                %s %s %s',
            $needsGroupJoin ? 'INNER JOIN usenet_groups g ON g.id = r.groups_id' : '',
            $this->showPasswords(),
            $needsGroupJoin ? sprintf(' AND g.name = %s', escapeString($groupName)) : '',
            Category::getCategorySearch($cat),
            $maxAge > 0 ? ' AND r.postdate > NOW() - INTERVAL '.$maxAge.' DAY ' : '',
            \count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''
        ));
    }

    /**
     * Get the passworded releases clause.
     */
    public function showPasswords(): string
    {
        $show = (int) Settings::settingValue('showpasswordedrelease');
        $setting = $show ?? 0;

        return match ($setting) {
            1 => '<= '.self::PASSWD_RAR,
            default => '= '.self::PASSWD_NONE,
        };
    }

    /**
     * Use to order releases on site.
     */
    public function getBrowseOrder(array|string $orderBy): array
    {
        $orderArr = explode('_', ($orderBy === '' ? 'posted_desc' : $orderBy));
        $orderField = match ($orderArr[0]) {
            'cat' => 'categories_id',
            'name' => 'searchname',
            'size' => 'size',
            'files' => 'totalpart',
            'stats' => 'grabs',
            default => 'postdate',
        };

        return [$orderField, isset($orderArr[1]) && preg_match('/^(asc|desc)$/i', $orderArr[1]) ? $orderArr[1] : 'desc'];
    }

    /**
     * Return ordering types usable on site.
     *
     * @return string[]
     */
    public function getBrowseOrdering(): array
    {
        return [
            'name_asc',
            'name_desc',
            'cat_asc',
            'cat_desc',
            'posted_asc',
            'posted_desc',
            'size_asc',
            'size_desc',
            'files_asc',
            'files_desc',
            'stats_asc',
            'stats_desc',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
    public function getShowsRange($userShows, $offset, $limit, $orderBy, int $maxAge = -1, array $excludedCats = [])
    {
        $cacheVersion = $this->getCacheVersion();
        $orderBy = $this->getBrowseOrder($orderBy);
        $sql = sprintf(
            "SELECT r.id, r.searchname, r.guid, r.postdate, r.categories_id, r.size, r.totalpart,
                r.fromname, r.grabs, r.comments, r.adddate, r.videos_id, r.haspreview, r.jpgstatus,
                CONCAT(cp.title, ' > ', c.title) AS category_name
            FROM releases r
            STRAIGHT_JOIN categories c ON c.id = r.categories_id
            STRAIGHT_JOIN root_categories cp ON cp.id = c.root_categories_id
            WHERE %s %s
                AND r.categories_id BETWEEN %d AND %d
                AND r.passwordstatus %s
                %s
            ORDER BY r.%s %s %s",
            $this->uSQL($userShows, 'videos_id'),
            (! empty($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
            Category::TV_ROOT,
            Category::TV_OTHER,
            $this->showPasswords(),
            ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : ''),
            $orderBy[0],
            $orderBy[1],
            ($offset === false ? '' : (' LIMIT '.$limit.' OFFSET '.$offset))
        );
        $cacheKey = 'shows_'.md5($cacheVersion.$sql);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_long'));
        $result = Cache::get($cacheKey);
        if ($result !== null) {
            return $result;
        }
        $result = Release::fromQuery($sql);
        Cache::put($cacheKey, $result, $expiresAt);

        return $result;
    }

    public function getShowsCount($userShows, int $maxAge = -1, array $excludedCats = []): int
    {
        return $this->getPagerCount(
            sprintf(
                'SELECT COUNT(*) AS count
				FROM releases r
				WHERE %s %s
				AND r.categories_id BETWEEN %d AND %d
				AND r.passwordstatus %s
				%s',
                $this->uSQL($userShows, 'videos_id'),
                (\count($excludedCats) ? ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')' : ''),
                Category::TV_ROOT,
                Category::TV_OTHER,
                $this->showPasswords(),
                ($maxAge > 0 ? sprintf(' AND r.postdate > NOW() - INTERVAL %d DAY ', $maxAge) : '')
            )
        );
    }

    /**
     * Creates part of a query for some functions.
     */
    public function uSQL(Collection|array|\Illuminate\Support\Collection $userQuery, string $type): string
    {
        $sql = '(1=2 ';
        foreach ($userQuery as $query) {
            $sql .= sprintf('OR (r.%s = %d', $type, $query->$type);
            if (! empty($query->categories)) {
                $catsArr = explode('|', $query->categories);
                if (\count($catsArr) > 1) {
                    $sql .= sprintf(' AND r.categories_id IN (%s)', implode(',', $catsArr));
                } else {
                    $sql .= sprintf(' AND r.categories_id = %d', $catsArr[0]);
                }
            }
            $sql .= ') ';
        }
        $sql .= ') ';

        return $sql;
    }

    public static function bumpCacheVersion(): void
    {
        $current = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
    }

    private function getCacheVersion(): int
    {
        return Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    /**
     * Get the count of releases for pager.
     * Optimized: expects COUNT(*) queries directly for best performance.
     *
     * @param  string  $query  The COUNT query to execute.
     */
    private function getPagerCount(string $query): int
    {
        $maxResults = (int) config('nntmux.max_pager_results');
        $cacheExpiry = (int) config('nntmux.cache_expiry_short', 5);

        $cacheKey = 'pager_count_'.md5($query);

        $count = Cache::get($cacheKey);
        if ($count !== null) {
            return (int) $count;
        }

        try {
            $result = DB::select($query);
            $count = 0;

            if (isset($result[0])) {
                // Handle the count result
                $count = $result[0]->count ?? 0;
                if ($count === 0) {
                    // Fallback: get first property value
                    foreach ($result[0] as $value) {
                        $count = (int) $value;
                        break;
                    }
                }
            }

            // Cap at max results if configured
            if ($maxResults > 0 && $count > $maxResults) {
                $count = $maxResults;
            }

            Cache::put($cacheKey, $count, now()->addMinutes($cacheExpiry));

            return (int) $count;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('getPagerCount failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}

