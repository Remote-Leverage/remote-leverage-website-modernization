<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Commands;

use App\Domains\ContentAudit\Services\ElementorProseExtractor;
use Illuminate\Console\Command;

/**
 * Imports blog posts captured from production's REST API, converting each one's
 * Elementor markup into clean Gutenberg blocks on the way in.
 *
 * Idempotent: posts are matched on slug, so re-running updates rather than
 * duplicating. Featured images are sideloaded separately (--with-images) because
 * this theme's image pipeline is slow enough to dominate the run.
 */
class ImportBlogPostsCommand extends Command
{
    protected $signature = 'content:import-posts
        {--dir= : Directory holding the fetched p-*.json files}
        {--limit=0 : Maximum posts to import this run (0 = all)}
        {--with-images : Also sideload featured images}
        {--dry-run : Report what would happen without writing}';

    protected $description = 'Import blog posts from captured production JSON, converting Elementor markup to Gutenberg';

    public function handle(ElementorProseExtractor $extractor): int
    {
        $dir = rtrim((string) $this->option('dir'), '/');

        if ($dir === '' || ! is_dir($dir)) {
            $this->error('Pass --dir pointing at the fetched JSON.');

            return self::FAILURE;
        }

        $files = glob($dir.'/p-*.json') ?: [];
        $posts = [];

        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);

            if (! is_array($decoded)) {
                $this->warn('Skipping unreadable '.basename($file));

                continue;
            }

            foreach ($decoded as $post) {
                $posts[$post['id']] = $post;
            }
        }

        if (! $posts) {
            $this->error('No posts found in '.$dir);

            return self::FAILURE;
        }

        $catMap = $this->map($dir.'/map-category.json');
        $tagMap = $this->map($dir.'/map-post_tag.json');

        $limit = (int) $this->option('limit');
        $dry = (bool) $this->option('dry-run');
        $withImages = (bool) $this->option('with-images');

        $created = $updated = $skipped = $failed = 0;
        $done = 0;

        foreach ($posts as $post) {
            if ($limit > 0 && $done >= $limit) {
                break;
            }
            $done++;

            $slug = $post['slug'];
            $content = $extractor->extract($post['content']['rendered'] ?? '');

            if (trim($content) === '') {
                $this->warn("  {$slug}: no prose extracted, skipped");
                $skipped++;

                continue;
            }

            $existing = get_page_by_path($slug, OBJECT, 'post');

            $payload = [
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_name' => $slug,
                'post_title' => wp_strip_all_tags($post['title']['rendered'] ?? $slug),
                'post_content' => $content,
                'post_excerpt' => wp_strip_all_tags($post['excerpt']['rendered'] ?? ''),
                'post_date' => str_replace('T', ' ', (string) ($post['date'] ?? '')),
                'post_modified' => str_replace('T', ' ', (string) ($post['modified'] ?? '')),
            ];

            if ($dry) {
                $this->line(sprintf('  %-44s %s (%d blocks)', substr($slug, 0, 42),
                    $existing ? 'would update' : 'would create', count(parse_blocks($content))));

                continue;
            }

            if ($existing) {
                $payload['ID'] = $existing->ID;
            }

            $id = wp_insert_post(wp_slash($payload), true);

            if (is_wp_error($id)) {
                $this->error("  {$slug}: ".$id->get_error_message());
                $failed++;

                continue;
            }

            $this->assignTerms($id, $post, $catMap, $tagMap);

            if ($withImages && ! empty($post['featured_media'])) {
                $this->attachFeaturedImage($id, (int) $post['featured_media']);
            }

            $existing ? $updated++ : $created++;
        }

        $this->newLine();
        $this->info($dry
            ? "Dry run over {$done} posts."
            : "Created {$created}, updated {$updated}, skipped {$skipped}, failed {$failed}.");

        return self::SUCCESS;
    }

    /** @return array<int,int> */
    private function map(string $path): array
    {
        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    /**
     * @param  array<int,int>  $catMap
     * @param  array<int,int>  $tagMap
     */
    private function assignTerms(int $id, array $post, array $catMap, array $tagMap): void
    {
        foreach ([['categories', 'category', $catMap], ['tags', 'post_tag', $tagMap]] as [$key, $tax, $map]) {
            $ids = [];

            foreach ($post[$key] ?? [] as $remote) {
                if (isset($map[$remote])) {
                    $ids[] = (int) $map[$remote];
                }
            }

            if ($ids) {
                wp_set_object_terms($id, $ids, $tax);
            }
        }

        // Uncategorized is WordPress's default, not an editorial choice — drop it
        // once the post has a real category.
        $cats = wp_get_object_terms($id, 'category', ['fields' => 'slugs']);

        if (count($cats) > 1 && in_array('uncategorized', $cats, true)) {
            $keep = array_values(array_diff($cats, ['uncategorized']));
            wp_set_object_terms($id, $keep, 'category');
        }
    }

    private function attachFeaturedImage(int $postId, int $remoteMediaId): void
    {
        if (get_post_thumbnail_id($postId)) {
            return;
        }

        $response = wp_remote_get("https://remoteleverage.com/wp-json/wp/v2/media/{$remoteMediaId}?_fields=source_url", ['timeout' => 30]);

        if (is_wp_error($response)) {
            return;
        }

        $url = json_decode((string) wp_remote_retrieve_body($response), true)['source_url'] ?? null;

        if (! $url) {
            return;
        }

        $existing = attachment_url_to_postid(str_replace('https://remoteleverage.com', home_url(), $url));

        if ($existing) {
            set_post_thumbnail($postId, $existing);

            return;
        }

        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        $attachmentId = media_sideload_image($url, $postId, null, 'id');

        if (! is_wp_error($attachmentId)) {
            set_post_thumbnail($postId, $attachmentId);
        }
    }
}
