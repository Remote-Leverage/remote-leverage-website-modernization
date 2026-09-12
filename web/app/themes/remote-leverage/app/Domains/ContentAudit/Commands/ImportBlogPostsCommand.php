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
        {--author= : User ID or login to attribute posts to (default: the Remote Leverage house account)}
        {--dry-run : Report what would happen without writing}';

    private const HOUSE_AUTHOR_LOGIN = 'remote-leverage';

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
        $author = $this->resolveAuthor($dry);

        if ($author === 0 && ! $dry) {
            $this->error('Could not resolve an author. Pass --author=<id|login>.');

            return self::FAILURE;
        }

        // Production's "Also read" cross-links have no href, so titles are matched
        // against the imported set to give them a real destination.
        $linkMap = [];

        foreach ($posts as $candidate) {
            $key = ElementorProseExtractor::normalise($candidate['title']['rendered'] ?? '');

            if ($key !== '') {
                $linkMap[$key] = home_url('/blog/'.$candidate['slug'].'/');
            }
        }

        $created = $updated = $skipped = $failed = 0;
        $done = 0;

        foreach ($posts as $post) {
            if ($limit > 0 && $done >= $limit) {
                break;
            }
            $done++;

            $slug = $post['slug'];
            $rendered = $post['content']['rendered'] ?? '';
            $content = $extractor->extract($rendered, $linkMap);

            if (trim($content) === '') {
                $this->warn("  {$slug}: no prose extracted, skipped");
                $skipped++;

                continue;
            }

            $existing = get_page_by_path($slug, OBJECT, 'post');

            $payload = [
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_author' => $author,
                'post_name' => $slug,
                'post_title' => $this->title($post['title']['rendered'] ?? $slug),
                'post_content' => $content,
                'post_excerpt' => $this->excerpt($extractor->summary($rendered), $content),
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

            // Always terminal on production, so it is meta the template renders
            // rather than markup frozen into post_content.
            $faqs = $extractor->faqs($rendered);

            if ($faqs) {
                update_post_meta($id, 'rl_faqs', $faqs);
            } else {
                delete_post_meta($id, 'rl_faqs');
            }

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

    /**
     * Posts imported without an author land on user 0, which leaves the byline
     * blank and the author archive empty.
     *
     * Production's Elementor header carries per-writer bylines, but they are
     * published under one house voice here, so everything is attributed to a
     * single "Remote Leverage" account — created on first run, reused after.
     */
    private function resolveAuthor(bool $dry): int
    {
        $option = (string) $this->option('author');

        if ($option !== '') {
            $user = is_numeric($option) ? get_user_by('id', (int) $option) : get_user_by('login', $option);

            return $user ? (int) $user->ID : 0;
        }

        if ($house = get_user_by('login', self::HOUSE_AUTHOR_LOGIN)) {
            return (int) $house->ID;
        }

        if ($dry) {
            $this->line('  Would create house author "Remote Leverage".');

            return 0;
        }

        $id = wp_insert_user([
            'user_login' => self::HOUSE_AUTHOR_LOGIN,
            'user_nicename' => self::HOUSE_AUTHOR_LOGIN,
            'display_name' => 'Remote Leverage',
            'first_name' => 'Remote Leverage',
            'user_email' => 'editorial@remoteleverage.com',
            'user_pass' => wp_generate_password(32),
            'role' => 'author',
            'description' => 'Our research and staffing team places pre-vetted bilingual talent across Latin America and Europe. Every guide is checked against live market hiring rates, compliance requirements, and the workflows our clients actually run.',
        ]);

        if (is_wp_error($id)) {
            $this->error('  house author: '.$id->get_error_message());

            return 0;
        }

        $this->line('  Created house author "Remote Leverage".');

        return (int) $id;
    }

    /**
     * REST returns titles HTML-encoded ("Cold Calling Script &#8211; Free
     * Download"). Stored as-is, WordPress escapes the ampersand again on output
     * and the reader sees the entity itself, so decode before saving.
     */
    private function title(string $rendered): string
    {
        return html_entity_decode(wp_strip_all_tags($rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Production's REST excerpts are auto-generated from the whole Elementor
     * template, so they open with the header furniture ("Written by: … 10 MIN
     * READ Copy Link …"). The hand-written Quick Summary is the real standfirst;
     * posts without one fall back to the opening paragraph of the article.
     *
     * @param  string[]  $summary
     */
    private function excerpt(array $summary, string $content): string
    {
        $source = $summary[0] ?? '';

        if ($source === '') {
            preg_match('/<p>(.*?)<\/p>/s', $content, $match);
            $source = $match[1] ?? '';
        }

        return wp_trim_words(wp_strip_all_tags($source), 40, '…');
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
            $this->warn('  media '.$remoteMediaId.': '.$response->get_error_message());

            return;
        }

        $url = json_decode((string) wp_remote_retrieve_body($response), true)['source_url'] ?? null;

        if (! $url) {
            $this->warn('  media '.$remoteMediaId.': not readable on production (unpublished?)');

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

        // Downloads fail transiently often enough that silence here hides real
        // gaps — a re-run picks them up, but only if the run said something.
        if (is_wp_error($attachmentId)) {
            $this->warn('  media '.$remoteMediaId.': '.$attachmentId->get_error_message());

            return;
        }

        set_post_thumbnail($postId, $attachmentId);
    }
}
