<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Commands;

use App\Support\BlockDefaults;
use Illuminate\Console\Command;

/**
 * Repoint Yoast's social-image postmeta off the legacy site and onto this environment.
 *
 * `content:import-seo` carried production's Yoast meta across faithfully, including the
 * OpenGraph image URLs, which are absolute and therefore still resolve against
 * `remoteleverage.com`. Rewriting canonicals was part of that import; rewriting media URLs
 * was not, because the media library was deliberately never ported — so there was nothing
 * to point them at until local attachments existed.
 *
 * Measured on 2026-09-17: 171 `_yoast_wpseo_opengraph-image` rows across 149 distinct
 * filenames, of which 124 have a local attachment with the same basename and 25 do not.
 * Harmless while the legacy site is up; it means the new site's social cards depend on the
 * old one, which is not a dependency that survives the apex cutover.
 *
 * Matching is by basename through {@see BlockDefaults::mediaUrl()}, the same resolver the
 * migrated presets use — so a file re-uploaded under a different year/month folder still
 * resolves, and a `-scaled` variant resolves to its original.
 *
 * **What happens to the 25 with no local file** is the one real decision here, and it is why
 * `--unmatched` exists. The default is to delete the row: Yoast then falls back to the
 * site-wide default OG image, which is a picture from this site. Keeping the row leaves the
 * card pointing at the legacy host, which is the defect being fixed. `--unmatched=keep`
 * is there for a run where you would rather see the full report first and upload the
 * missing media before deciding.
 *
 * Idempotent — a second run reports nothing to do.
 */
class RehostSeoImagesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'content:rehost-seo-images
        {--source=https://remoteleverage.com : Origin whose media URLs should be repointed}
        {--meta-keys=_yoast_wpseo_opengraph-image,_yoast_wpseo_twitter-image : Postmeta keys to rewrite}
        {--unmatched=drop : What to do when no local media matches — drop or keep}
        {--dry-run : Report what would change without writing}';

    /**
     * @var string
     */
    protected $description = 'Repoint Yoast OpenGraph/Twitter image postmeta from the legacy host onto local media';

    /**
     * Yoast option fields that can also hold an absolute image URL.
     *
     * Reported rather than rewritten: each is half of a URL/attachment-id pair, and writing
     * the URL without its `_id` companion leaves Yoast's settings internally inconsistent in
     * a way the admin screen will not show. A human fixing these in Search Appearance takes a
     * minute and keeps both halves in step.
     *
     * @var list<string>
     */
    private const REPORT_ONLY_OPTION_FIELDS = [
        'company_logo',
        'person_logo',
        'og_default_image',
    ];

    public function handle(): int
    {
        $source = rtrim((string) $this->option('source'), '/');
        $host = parse_url($source, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $this->error('--source must be an origin such as https://example.com.');

            return self::FAILURE;
        }

        $unmatched = (string) $this->option('unmatched');

        if (! in_array($unmatched, ['drop', 'keep'], true)) {
            $this->error('--unmatched must be either "drop" or "keep".');

            return self::FAILURE;
        }

        $keys = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('meta-keys'))
        )));

        if ($keys === []) {
            $this->error('--meta-keys must name at least one postmeta key.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            $this->error('WordPress is not loaded; run this through `wp acorn`.');

            return self::FAILURE;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '%s'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta}
             WHERE meta_key IN ({$placeholders}) AND meta_value LIKE %s
             ORDER BY post_id ASC",
            [...$keys, '%//'.$wpdb->esc_like($host).'/%']
        ));

        if ($rows === []) {
            $this->info("Nothing to do — no rows on {$host} for: ".implode(', ', $keys));
            $this->reportOptionFields($host);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d row(s) on %s%s.',
            count($rows),
            $host,
            $dryRun ? ' (dry run, nothing will be written)' : ''
        ));

        $rewritten = 0;
        $dropped = 0;
        $kept = 0;
        $missing = [];

        foreach ($rows as $row) {
            $postId = (int) $row->post_id;
            $key = (string) $row->meta_key;
            $value = (string) $row->meta_value;
            $local = BlockDefaults::mediaUrl($value);

            if ($local !== '' && $local !== $value) {
                $this->line(sprintf('  #%d %s -> %s', $postId, $key, $local));

                if (! $dryRun) {
                    update_post_meta($postId, $key, $local);
                }

                $rewritten++;

                continue;
            }

            $missing[basename(parse_url($value, PHP_URL_PATH) ?: $value)] = true;

            if ($unmatched === 'keep') {
                $kept++;

                continue;
            }

            $this->line(sprintf('  #%d %s -> dropped (no local media)', $postId, $key));

            if (! $dryRun) {
                delete_post_meta($postId, $key);
            }

            $dropped++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%d rewritten, %d %s, across %d filename(s) with no local media.',
            $rewritten,
            $unmatched === 'keep' ? $kept : $dropped,
            $unmatched === 'keep' ? 'left on the legacy host' : 'dropped',
            count($missing)
        ));

        if ($missing !== []) {
            $this->newLine();
            $this->warn('No local media matches these filenames:');

            foreach (array_keys($missing) as $filename) {
                $this->line('  '.$filename);
            }

            $this->line('Upload them and re-run to rewrite instead of dropping.');
        }

        $this->reportOptionFields($host);

        return self::SUCCESS;
    }

    /**
     * Flag Yoast settings that still hold a legacy-host image, without touching them.
     */
    private function reportOptionFields(string $host): void
    {
        if (! function_exists('get_option')) {
            return;
        }

        $titles = get_option('wpseo_titles');

        if (! is_array($titles)) {
            return;
        }

        $stale = [];

        foreach (self::REPORT_ONLY_OPTION_FIELDS as $field) {
            $value = $titles[$field] ?? '';

            if (is_string($value) && $value !== '' && str_contains($value, '//'.$host.'/')) {
                $stale[$field] = $value;
            }
        }

        if ($stale === []) {
            return;
        }

        $this->newLine();
        $this->warn('Yoast Search Appearance still holds images on '.$host.':');

        foreach ($stale as $field => $value) {
            $this->line("  {$field}: {$value}");
        }

        $this->line('Re-pick these in wp-admin so the URL and its attachment id stay in step.');
    }
}
