<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\SyncClient;
use Illuminate\Console\Command;

class SyncPageCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:page {post_id : The local post ID to push, or (with --pull) unused}
        {--push : Push a local page to the remote environment}
        {--pull : Pull a page down from the remote environment into this (local) one}
        {--remote-post-id= : Required with --pull; the post ID on the remote environment to export}
        {--env=staging : Target environment key from config/rl-sync.php}';

    /**
     * @var string
     */
    protected $description = 'Push a local landing page (post + postmeta) to a remote environment, '.
        'or pull one down from it, matched/created by slug.';

    public function handle(): int
    {
        $push = (bool) $this->option('push');
        $pull = (bool) $this->option('pull');
        $env = (string) $this->option('env');
        $postId = (int) $this->argument('post_id');

        if ($push === $pull) {
            $this->error('Pass exactly one of --push or --pull.');

            return self::FAILURE;
        }

        $client = new SyncClient($env);

        if ($push) {
            $post = get_post($postId);

            if ($post === null || $post->post_type !== 'page') {
                $this->error("No local page found with ID {$postId}.");

                return self::FAILURE;
            }

            $payload = [
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'status' => $post->post_status,
                'content' => $post->post_content,
                'meta' => get_post_meta($postId),
            ];

            $result = $client->run('app/import-landing-page', $payload);
            $verb = $result['created'] ? 'Created' : 'Updated';
            $this->info("{$verb} \"{$post->post_title}\" on {$env} (post ID {$result['post_id']}).");

            return self::SUCCESS;
        }

        $remotePostId = $this->option('remote-post-id');

        if (! $remotePostId) {
            $this->error('--remote-post-id is required with --pull.');

            return self::FAILURE;
        }

        $payload = $client->run('app/export-landing-page', ['post_id' => (int) $remotePostId]);

        $existing = get_page_by_path($payload['slug'], OBJECT, 'page');

        $postArgs = [
            'post_type' => 'page',
            'post_title' => $payload['title'],
            'post_name' => $payload['slug'],
            'post_status' => $payload['status'],
            'post_content' => wp_slash($payload['content']),
        ];

        if ($existing !== null) {
            $postArgs['ID'] = $existing->ID;
            wp_update_post($postArgs);
            $localPostId = $existing->ID;
        } else {
            $localPostId = wp_insert_post($postArgs);
        }

        foreach ($payload['meta'] ?? [] as $key => $values) {
            delete_post_meta($localPostId, $key);

            foreach ((array) $values as $value) {
                add_post_meta($localPostId, $key, maybe_unserialize($value));
            }
        }

        $verb = $existing === null ? 'Created' : 'Updated';
        $this->info("{$verb} \"{$payload['title']}\" locally (post ID {$localPostId}) from {$env}.");

        return self::SUCCESS;
    }
}
