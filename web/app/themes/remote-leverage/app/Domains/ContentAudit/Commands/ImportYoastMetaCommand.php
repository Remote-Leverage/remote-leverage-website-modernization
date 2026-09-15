<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Commands;

use App\Domains\ContentAudit\Services\YoastMetaMapper;
use Illuminate\Console\Command;
use WP_Post;

/**
 * Carries production's Yoast SEO meta onto the migrated v2 content.
 *
 * Production runs Yoast, so every page and post exposes a `yoast_head_json`
 * object over REST. There is no database access to production, so that rendered
 * head is the source and {@see YoastMetaMapper} turns it back into
 * `_yoast_wpseo_*` postmeta.
 *
 * Matching is by slug. Production has no `case_study` post type — its case
 * studies are pages served under `/case-study/<slug>/`, the same path v2's CPT
 * uses — so a local post falls back to any production type with the same slug
 * unless `--strict-types` is passed.
 *
 * Idempotent: the mapper owns a fixed key set, and any key it does not produce
 * for a post is deleted, so a re-run converges instead of leaving stale
 * overrides behind. That is also why the force-index exclusion lives here as an
 * option with a default rather than as a manual `wp post meta delete` after the
 * fact: a hand-edit would be undone by the next run, and this command is meant
 * to be re-runnable.
 */
class ImportYoastMetaCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'content:import-seo
        {--source=https://remoteleverage.com : Production origin to read yoast_head_json from}
        {--target= : Origin to rewrite canonicals onto (default: this site\'s home URL)}
        {--post-types=page,post,case_study : Local post types to carry meta onto}
        {--dir= : Read captured seo-<type>-<n>.json from this directory instead of fetching}
        {--cache= : Write the fetched REST payloads into this directory}
        {--strict-types : Only match a local post against the same production post type}
        {--all-social : Carry OpenGraph/Twitter values even when they look like a post-type template}
        {--force-index=referral-program,ecommerce-virtual-assistant : Slugs that stay indexable whatever production says; blank to carry every directive}
        {--threshold=0.05 : Share of the corpus at which a repeated social value counts as a template}
        {--dry-run : Report what would change without writing}';

    /**
     * @var string
     */
    protected $description = 'Import Yoast SEO postmeta from production, matching local content by slug';

    /**
     * WordPress only guarantees a REST base equal to the post type name; the two
     * core types are the exceptions.
     *
     * @var array<string,string>
     */
    private const REST_BASES = ['page' => 'pages', 'post' => 'posts'];

    /**
     * Statuses worth carrying meta onto. `auto-draft` and `trash` are noise.
     *
     * @var list<string>
     */
    private const LOCAL_STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    public function handle(): int
    {
        $source = rtrim((string) $this->option('source'), '/');
        $target = rtrim((string) ($this->option('target') ?: home_url()), '/');

        if ($source === '' || $target === '') {
            $this->error('Both --source and --target must be an origin such as https://example.com.');

            return self::FAILURE;
        }

        $types = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('post-types'))
        )));

        $remote = $this->fetchRemote($source, $types);

        if ($remote === []) {
            $this->error('No production content fetched — nothing to import.');

            return self::FAILURE;
        }

        $defaults = $this->option('all-social')
            ? []
            : YoastMetaMapper::detectSiteDefaults(
                array_map(static fn (array $item): array => $item['head'], $remote),
                (float) $this->option('threshold'),
            );

        $this->reportSiteDefaults($defaults);

        $forceIndex = $this->forceIndexSlugs();
        $mapper = new YoastMetaMapper($source, $target, $defaults, $forceIndex);
        $dryRun = (bool) $this->option('dry-run');
        $strict = (bool) $this->option('strict-types');

        $this->reportForceIndex($forceIndex);

        $forcedSeen = [];

        // Production types keyed by slug, so a local case study can find the
        // production *page* that carries its SEO meta.
        $bySlug = [];

        foreach ($remote as $item) {
            $bySlug[$item['slug']] ??= $item['type'];
        }

        $matched = $crossType = $changed = $unchanged = 0;
        $localUnmatched = [];
        $seen = [];

        foreach ($types as $type) {
            foreach ($this->localPosts($type) as $post) {
                $slug = (string) $post->post_name;

                if ($slug === '') {
                    continue;
                }

                if ($mapper->indexForced($slug)) {
                    $forcedSeen[YoastMetaMapper::normaliseSlug($slug)] = true;
                }

                $remoteType = isset($remote[$type.'|'.$slug])
                    ? $type
                    : ($strict ? null : ($bySlug[$slug] ?? null));

                if ($remoteType === null) {
                    $localUnmatched[] = $type.' '.$slug;

                    continue;
                }

                $item = $remote[$remoteType.'|'.$slug];
                $seen[$remoteType.'|'.$slug] = true;
                $matched++;

                if ($remoteType !== $type) {
                    $crossType++;
                }

                $desired = $mapper->map($item['head'], $item['title'], $slug);
                $diff = $this->diff((int) $post->ID, $desired);

                if ($diff === []) {
                    $unchanged++;

                    continue;
                }

                $changed++;
                $this->line(sprintf(
                    '  %-46s %s %s',
                    substr($type.'/'.$slug, 0, 46),
                    $dryRun ? 'would set' : 'set',
                    implode(', ', $diff),
                ));

                if (! $dryRun) {
                    $this->write((int) $post->ID, $desired);
                }
            }
        }

        $remoteUnmatched = array_values(array_diff(array_keys($remote), array_keys($seen)));

        $this->summarise($remote, $matched, $crossType, $changed, $unchanged, $localUnmatched, $remoteUnmatched, $dryRun);
        $this->warnUnusedForceIndex($forceIndex, $forcedSeen);

        return self::SUCCESS;
    }

    /**
     * Pulls every production item of the requested types, keyed `type|slug`.
     *
     * @param  list<string>  $types
     * @return array<string,array{type:string,slug:string,head:array<string,mixed>,title:string}>
     */
    private function fetchRemote(string $source, array $types): array
    {
        $dir = rtrim((string) $this->option('dir'), '/');
        $cache = rtrim((string) $this->option('cache'), '/');
        $items = [];

        foreach ($types as $type) {
            $base = self::REST_BASES[$type] ?? $type;
            $page = 1;

            while (true) {
                $payload = $dir !== ''
                    ? $this->readCaptured($dir, $base, $page)
                    : $this->request($source, $base, $page);

                if ($payload === null) {
                    break;
                }

                if ($cache !== '' && $dir === '') {
                    @mkdir($cache, 0775, true);
                    file_put_contents(
                        $cache."/seo-{$base}-{$page}.json",
                        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    );
                }

                foreach ($payload as $item) {
                    $slug = (string) ($item['slug'] ?? '');
                    $head = $item['yoast_head_json'] ?? null;

                    if ($slug === '' || ! is_array($head)) {
                        continue;
                    }

                    $remoteType = (string) ($item['type'] ?? $type);

                    $items[$remoteType.'|'.$slug] = [
                        'type' => $remoteType,
                        'slug' => $slug,
                        'head' => $head,
                        'title' => html_entity_decode(
                            wp_strip_all_tags((string) ($item['title']['rendered'] ?? '')),
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        ),
                    ];
                }

                if (count($payload) < 100) {
                    break;
                }

                $page++;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string,mixed>>|null Null ends the pagination loop.
     */
    private function request(string $source, string $base, int $page): ?array
    {
        $url = sprintf(
            '%s/wp-json/wp/v2/%s?per_page=100&page=%d&status=publish&_fields=slug,type,title,yoast_head_json',
            $source,
            rawurlencode($base),
            $page,
        );

        $response = wp_remote_get($url, ['timeout' => 60]);

        if (is_wp_error($response)) {
            $this->warn("  {$base} page {$page}: ".$response->get_error_message());

            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 404) {
            // Expected for `case_study`: production has no such post type, its
            // case studies are pages. The slug fallback picks them up.
            $this->line("  {$base}: no REST route on production, falling back to slug matching");

            return null;
        }

        if ($code !== 200) {
            $this->warn("  {$base} page {$page}: HTTP {$code}");

            return null;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    /**
     * @return list<array<string,mixed>>|null
     */
    private function readCaptured(string $dir, string $base, int $page): ?array
    {
        $path = $dir."/seo-{$base}-{$page}.json";

        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    /**
     * @return list<WP_Post>
     */
    private function localPosts(string $type): array
    {
        if (! post_type_exists($type)) {
            $this->warn("  {$type}: not registered locally, skipped");

            return [];
        }

        /** @var list<WP_Post> $posts */
        $posts = get_posts([
            'post_type' => $type,
            'post_status' => self::LOCAL_STATUSES,
            'numberposts' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        return $posts;
    }

    /**
     * Describes the writes and deletes a post needs, as short `key=action`
     * fragments. An empty result means the post is already in parity.
     *
     * @param  array<string,string>  $desired
     * @return list<string>
     */
    private function diff(int $postId, array $desired): array
    {
        $diff = [];

        foreach (YoastMetaMapper::META_KEYS as $key) {
            $current = get_post_meta($postId, $key, true);
            $current = is_string($current) ? $current : '';
            $want = $desired[$key] ?? '';
            $short = str_replace('_yoast_wpseo_', '', $key);

            if ($current === $want) {
                continue;
            }

            $diff[] = $want === '' ? $short.'=clear' : ($current === '' ? $short : $short.'=changed');
        }

        return $diff;
    }

    /**
     * @param  array<string,string>  $desired
     */
    private function write(int $postId, array $desired): void
    {
        foreach (YoastMetaMapper::META_KEYS as $key) {
            if (isset($desired[$key]) && $desired[$key] !== '') {
                update_post_meta($postId, $key, $desired[$key]);

                continue;
            }

            delete_post_meta($postId, $key);
        }
    }

    /**
     * Slugs whose production `noindex` is stale and must not be carried.
     *
     * @return list<string>
     */
    private function forceIndexSlugs(): array
    {
        return array_values(array_filter(array_map(
            [YoastMetaMapper::class, 'normaliseSlug'],
            explode(',', (string) $this->option('force-index')),
        )));
    }

    /**
     * @param  list<string>  $slugs
     */
    private function reportForceIndex(array $slugs): void
    {
        if ($slugs === []) {
            return;
        }

        $this->line('Kept indexable regardless of production\'s robots directives: '.implode(', ', $slugs));
        $this->newLine();
    }

    /**
     * A force-index slug that matched nothing is almost always a typo, and it
     * fails silently — the page it was meant to protect keeps its noindex.
     *
     * @param  list<string>  $slugs
     * @param  array<string,true>  $seen
     */
    private function warnUnusedForceIndex(array $slugs, array $seen): void
    {
        $unused = array_values(array_diff($slugs, array_keys($seen)));

        if ($unused !== []) {
            $this->warn('--force-index matched no local post: '.implode(', ', $unused));
        }
    }

    /**
     * @param  array<string,list<string>>  $defaults
     */
    private function reportSiteDefaults(array $defaults): void
    {
        if ($defaults === []) {
            return;
        }

        $this->newLine();
        $this->warn('Values that repeat across production and read as Yoast Search Appearance templates, not per-post overrides — skipped:');

        foreach ($defaults as $field => $values) {
            foreach ($values as $value) {
                $this->line(sprintf('  %-22s %s', $field, '"'.mb_strimwidth($value, 0, 78, '…').'"'));
            }
        }

        $this->line('  Configure these in Yoast → Search Appearance rather than carrying them onto every post, or pass --all-social.');
        $this->newLine();
    }

    /**
     * @param  array<string,mixed>  $remote
     * @param  list<string>  $localUnmatched
     * @param  list<string>  $remoteUnmatched
     */
    private function summarise(
        array $remote,
        int $matched,
        int $crossType,
        int $changed,
        int $unchanged,
        array $localUnmatched,
        array $remoteUnmatched,
        bool $dryRun,
    ): void {
        $this->newLine();
        $this->table(['', 'Count'], [
            ['Production items fetched', count($remote)],
            ['Matched by slug', $matched],
            ['  …of which matched across post types', $crossType],
            [$dryRun ? 'Would change' : 'Changed', $changed],
            ['Already in parity', $unchanged],
            ['Production items with no local counterpart', count($remoteUnmatched)],
            ['Local items with no production counterpart', count($localUnmatched)],
        ]);

        if ($localUnmatched !== []) {
            $this->line('Local without production counterpart: '.implode(', ', array_slice($localUnmatched, 0, 30))
                .(count($localUnmatched) > 30 ? ' …' : ''));
        }

        if ($dryRun) {
            $this->warn('Dry run — nothing was written.');
        }
    }
}
