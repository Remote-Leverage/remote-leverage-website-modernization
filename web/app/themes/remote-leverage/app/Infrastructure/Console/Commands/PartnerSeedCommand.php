<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;

/**
 * Applies `resources/partners/partners.php` to the `rl_partner` CPT.
 *
 * The partner hub's content was database-only until 2026-09-15, which meant a
 * database refresh silently emptied `/partners/` and 404'd every co-branded
 * hub. Seeding from a file in git makes the entries reproducible on a fresh
 * clone, the same trade the theme makes by keeping page content in `patterns/`.
 *
 * Idempotent: matches on `post_name` and updates in place, so re-running never
 * duplicates a partner and never changes an existing post ID (attribution and
 * any linked media keep pointing at the same entry).
 */
class PartnerSeedCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'partners:seed
        {--prune : Trash published rl_partner entries that this file does not define}
        {--dry-run : Report what would change without writing}';

    /**
     * @var string
     */
    protected $description = 'Create or update the rl_partner entries defined in resources/partners/partners.php';

    public function handle(): int
    {
        $path = get_theme_file_path('resources/partners/partners.php');

        if (! is_readable($path)) {
            $this->error("Partner definitions not found at {$path}");

            return self::FAILURE;
        }

        $definitions = require $path;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($definitions as $slug => $definition) {
            $this->seedPartner((string) $slug, $definition, $dryRun);
        }

        if ($this->option('prune')) {
            $this->prune(array_keys($definitions), $dryRun);
        }

        if ($dryRun) {
            $this->warn('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{post_title: string, meta: array<string, string>}  $definition
     */
    private function seedPartner(string $slug, array $definition, bool $dryRun): void
    {
        $existing = get_page_by_path($slug, OBJECT, 'rl_partner');

        if ($dryRun) {
            $this->line($existing ? "Would update: {$slug}" : "Would create: {$slug}");

            return;
        }

        $postData = [
            'post_type' => 'rl_partner',
            'post_name' => $slug,
            'post_title' => $definition['post_title'],
            'post_status' => 'publish',
        ];

        if ($existing) {
            $postData['ID'] = $existing->ID;
            $postId = wp_update_post($postData, true);
        } else {
            $postId = wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            $this->error("{$slug}: ".$postId->get_error_message());

            return;
        }

        foreach ($definition['meta'] as $key => $value) {
            update_post_meta($postId, $key, $value);
        }

        $this->info(($existing ? 'Updated' : 'Created')." {$slug} (ID {$postId})");
    }

    /**
     * @param  array<int, string>  $knownSlugs
     */
    private function prune(array $knownSlugs, bool $dryRun): void
    {
        $all = get_posts([
            'post_type' => 'rl_partner',
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);

        foreach ($all as $post) {
            if (in_array($post->post_name, $knownSlugs, true)) {
                continue;
            }

            if ($dryRun) {
                $this->line("Would trash: {$post->post_name}");

                continue;
            }

            wp_trash_post($post->ID);
            $this->warn("Trashed {$post->post_name} (ID {$post->ID}) — not in partners.php");
        }
    }
}
