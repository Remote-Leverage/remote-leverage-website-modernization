<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

use Illuminate\Support\Facades\Cache;

/**
 * The numbers behind the SEO dashboard widget and overview screen.
 *
 * Reads Yoast's own storage rather than recomputing anything: the analysis scores it writes to
 * postmeta, and the indexable table it builds. The meta keys and score bands are Yoast's, taken
 * from `WPSEO_Meta::$meta_prefix` and `WPSEO_Rank::$ranges` — they are restated here as
 * constants because the plugin's classes are not autoloadable from the theme, so this file is
 * the seam that has to be checked when Yoast changes them.
 *
 * Bucketing and severity are static and pure so they can be tested without a database; only
 * the three `*Raw` readers touch $wpdb.
 */
class SeoStats
{
    /** Yoast's postmeta prefix. */
    private const META_PREFIX = '_yoast_wpseo_';

    /** Analysis score, 0-100. */
    private const META_SCORE = self::META_PREFIX.'linkdex';

    private const META_METADESC = self::META_PREFIX.'metadesc';

    private const META_TITLE = self::META_PREFIX.'title';

    private const META_NOINDEX = self::META_PREFIX.'meta-robots-noindex';

    /** How long the widget's figures may be stale. Matches the other dashboard widgets. */
    private const CACHE_TTL = 180;

    /** The content types the widget reports on. */
    private const POST_TYPES = ['post', 'page'];

    /**
     * Yoast's score bands, from WPSEO_Rank::$ranges.
     *
     * A score of 0 means "never analysed" rather than "scored zero", which is why `na` is a
     * band in its own right and not folded into `bad`.
     *
     * @var array<string, array{label: string, min: int, max: int}>
     */
    public const BANDS = [
        'good' => ['label' => 'Good', 'min' => 71, 'max' => 100],
        'ok' => ['label' => 'OK', 'min' => 41, 'max' => 70],
        'bad' => ['label' => 'Needs work', 'min' => 1, 'max' => 40],
        'na' => ['label' => 'Not analysed', 'min' => 0, 'max' => 0],
    ];

    /**
     * Everything the widget renders, in one cached read.
     *
     * @return array{
     *     scores: array<string, int>,
     *     total: int,
     *     gaps: array{missing_metadesc: int, missing_title: int, total: int},
     *     indexing: array{noindex: int, discouraged: bool, sitemap: bool, indexables: int, indexable_gap: int}
     * }
     */
    public static function all(): array
    {
        return Cache::remember('rl_seo_widget_stats', self::CACHE_TTL, static function (): array {
            $scores = self::scoreBuckets();

            return [
                'scores' => $scores,
                'total' => array_sum($scores),
                'gaps' => self::contentGaps(),
                'indexing' => self::indexingHealth(),
            ];
        });
    }

    /**
     * Published content counted into Yoast's four score bands.
     *
     * @return array<string, int> band key => count, always with every band present
     */
    public static function scoreBuckets(): array
    {
        global $wpdb;

        $empty = array_fill_keys(array_keys(self::BANDS), 0);

        if (! isset($wpdb)) {
            return $empty;
        }

        $types = self::postTypePlaceholders();

        // The CASE mirrors WPSEO_Rank::$ranges. A missing row and a stored '0' both mean the
        // post has never been analysed, so they collapse into the same band.
        $sql = $wpdb->prepare(
            "SELECT CASE
                    WHEN m.meta_value IS NULL OR m.meta_value = '' OR m.meta_value = '0' THEN 'na'
                    WHEN CAST(m.meta_value AS UNSIGNED) BETWEEN 1 AND 40 THEN 'bad'
                    WHEN CAST(m.meta_value AS UNSIGNED) BETWEEN 41 AND 70 THEN 'ok'
                    ELSE 'good'
                END AS band,
                COUNT(*) AS tally
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m
                    ON m.post_id = p.ID AND m.meta_key = %s
             WHERE p.post_status = 'publish'
               AND p.post_type IN ({$types})
             GROUP BY band",
            array_merge([self::META_SCORE], self::POST_TYPES)
        );

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        foreach ($rows as $row) {
            $band = (string) ($row['band'] ?? '');

            if (isset($empty[$band])) {
                $empty[$band] = (int) $row['tally'];
            }
        }

        return $empty;
    }

    /**
     * Published content with no meta description or no SEO title override.
     *
     * @return array{missing_metadesc: int, missing_title: int, total: int}
     */
    public static function contentGaps(): array
    {
        global $wpdb;

        $empty = ['missing_metadesc' => 0, 'missing_title' => 0, 'total' => 0];

        if (! isset($wpdb)) {
            return $empty;
        }

        $types = self::postTypePlaceholders();

        $sql = $wpdb->prepare(
            "SELECT
                SUM(CASE WHEN md.meta_value IS NULL OR md.meta_value = '' THEN 1 ELSE 0 END) AS missing_metadesc,
                SUM(CASE WHEN t.meta_value IS NULL OR t.meta_value = '' THEN 1 ELSE 0 END) AS missing_title,
                COUNT(*) AS total
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} md ON md.post_id = p.ID AND md.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} t  ON t.post_id  = p.ID AND t.meta_key  = %s
             WHERE p.post_status = 'publish'
               AND p.post_type IN ({$types})",
            array_merge([self::META_METADESC, self::META_TITLE], self::POST_TYPES)
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! is_array($row)) {
            return $empty;
        }

        return [
            'missing_metadesc' => (int) ($row['missing_metadesc'] ?? 0),
            'missing_title' => (int) ($row['missing_title'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    /**
     * The things that silently stop content ranking.
     *
     * `discouraged` is the one that matters most and is the cheapest to get wrong: a staging
     * database restored over production leaves `blog_public` at 0 and every other number on
     * this widget stays green while the whole site is noindexed.
     *
     * @return array{noindex: int, discouraged: bool, sitemap: bool, indexables: int, indexable_gap: int}
     */
    public static function indexingHealth(): array
    {
        global $wpdb;

        $wpseo = get_option('wpseo', []);

        $health = [
            'noindex' => 0,
            'discouraged' => get_option('blog_public') === '0',
            'sitemap' => ! is_array($wpseo) || ($wpseo['enable_xml_sitemap'] ?? true) !== false,
            'indexables' => 0,
            'indexable_gap' => 0,
        ];

        if (! isset($wpdb)) {
            return $health;
        }

        $types = self::postTypePlaceholders();

        $noindex = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
             WHERE m.meta_key = %s
               AND m.meta_value = '1'
               AND p.post_status = 'publish'
               AND p.post_type IN ({$types})",
            array_merge([self::META_NOINDEX], self::POST_TYPES)
        ));

        $health['noindex'] = (int) $noindex;

        // The indexable table is created by a Yoast migration, so it can legitimately be
        // absent on a fresh install or a partially-migrated database. Treat that as "no data"
        // rather than letting a missing-table error surface on the dashboard.
        $table = $wpdb->prefix.'yoast_indexable';

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists !== $table) {
            return $health;
        }

        $indexables = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE object_type = 'post' AND post_status = 'publish' AND object_sub_type IN ({$types})",
            self::POST_TYPES
        ));

        $published = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$types})",
            self::POST_TYPES
        ));

        $health['indexables'] = $indexables;
        $health['indexable_gap'] = max(0, $published - $indexables);

        return $health;
    }

    /**
     * The published content most worth opening, worst first.
     *
     * "Worst" is ordered by consequence rather than by score alone: a page with no meta
     * description at all outranks a merely low-scoring one, because the fix is concrete and
     * the effect is visible in the search result. Never-analysed content sorts last — a 0 there
     * means Yoast has not looked at it, not that it is bad.
     *
     * @return list<array{id: int, title: string, type: string, score: int, has_metadesc: bool}>
     */
    public static function needsAttention(int $limit = 8): array
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return [];
        }

        $types = self::postTypePlaceholders();

        $sql = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type,
                    COALESCE(CAST(s.meta_value AS UNSIGNED), 0) AS score,
                    CASE WHEN md.meta_value IS NULL OR md.meta_value = '' THEN 0 ELSE 1 END AS has_metadesc
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} s  ON s.post_id  = p.ID AND s.meta_key  = %s
             LEFT JOIN {$wpdb->postmeta} md ON md.post_id = p.ID AND md.meta_key = %s
             WHERE p.post_status = 'publish'
               AND p.post_type IN ({$types})
               AND (
                    (md.meta_value IS NULL OR md.meta_value = '')
                    OR (s.meta_value IS NOT NULL AND s.meta_value <> '' AND CAST(s.meta_value AS UNSIGNED) BETWEEN 1 AND 40)
               )
             ORDER BY has_metadesc ASC,
                      (CASE WHEN COALESCE(CAST(s.meta_value AS UNSIGNED), 0) = 0 THEN 1 ELSE 0 END) ASC,
                      score ASC
             LIMIT %d",
            array_merge([self::META_SCORE, self::META_METADESC], self::POST_TYPES, [$limit])
        );

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['ID'],
            'title' => (string) $row['post_title'],
            'type' => (string) $row['post_type'],
            'score' => (int) $row['score'],
            'has_metadesc' => (bool) $row['has_metadesc'],
        ], $rows);
    }

    /**
     * The band a raw analysis score falls into, by WPSEO_Rank's boundaries.
     */
    public static function bandFor(int $score): string
    {
        foreach (self::BANDS as $band => $range) {
            if ($score >= $range['min'] && $score <= $range['max']) {
                return $band;
            }
        }

        return 'na';
    }

    /**
     * Turn raw band counts into rounded percentages that still add up to 100.
     *
     * Rounding each band independently drifts — four bands rounding up puts the stacked bar
     * past full width and leaves a visible overflow. The largest band absorbs the remainder.
     *
     * @param  array<string, int>  $counts
     * @return array<string, float>
     */
    public static function percentages(array $counts): array
    {
        $total = array_sum($counts);

        if ($total <= 0) {
            return array_fill_keys(array_keys($counts), 0.0);
        }

        $percentages = [];

        foreach ($counts as $band => $count) {
            $percentages[$band] = round(($count / $total) * 100, 1);
        }

        $drift = round(100 - array_sum($percentages), 1);

        if (abs($drift) >= 0.1) {
            $largest = array_search(max($counts), $counts, true);

            if ($largest !== false) {
                $percentages[$largest] = round($percentages[$largest] + $drift, 1);
            }
        }

        return $percentages;
    }

    /**
     * How loudly to present a gap count, as a share of the content it was measured against.
     *
     * Returns one of 'ok', 'warn', 'bad' — the suffixes of the .rl-seo-pill modifiers.
     */
    public static function severity(int $count, int $total): string
    {
        if ($count === 0 || $total <= 0) {
            return 'ok';
        }

        $share = ($count / $total) * 100;

        if ($share >= 25.0) {
            return 'bad';
        }

        return 'warn';
    }

    /**
     * A `%s, %s` placeholder run matching self::POST_TYPES, for interpolation into prepare().
     *
     * The types are bound as parameters rather than inlined; this only builds the run of
     * placeholders, because the count has to be known before prepare() is called.
     */
    private static function postTypePlaceholders(): string
    {
        return implode(', ', array_fill(0, count(self::POST_TYPES), '%s'));
    }

    /**
     * Drop the cached figures. Called when content is saved so the widget does not lag a save.
     */
    public static function flush(): void
    {
        Cache::forget('rl_seo_widget_stats');
    }
}
