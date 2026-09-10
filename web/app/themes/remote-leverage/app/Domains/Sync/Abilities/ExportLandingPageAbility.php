<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncCapability;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * REST-only (no meta.mcp.public) — invoked exclusively by `wp rl:sync:page`.
 * Exports a page's post fields and full postmeta (including serialized ACF
 * field data) so it can be recreated identically on another environment.
 */
class ExportLandingPageAbility extends Ability
{
    public function label(): string
    {
        return 'Export Landing Page';
    }

    public function description(): string
    {
        return 'Returns a page\'s title, slug, status, content, and full postmeta (ACF field data included) '.
            'so it can be imported on another environment. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $postId = (int) $input['post_id'];
        $post = get_post($postId);

        if ($post === null || $post->post_type !== 'page') {
            return new WP_Error('not_found', "Page {$postId} not found.");
        }

        return [
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'content' => $post->post_content,
            'meta' => get_post_meta($postId),
        ];
    }

    public function permission(): bool|WP_Error
    {
        if (! SyncCapability::currentUserCan()) {
            return new WP_Error('forbidden', 'The '.SyncCapability::NAME.' capability is required.');
        }

        return true;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the page to export, on this environment.',
                ],
            ],
            'required' => ['post_id'],
        ];
    }

    public function category(): ?string
    {
        return 'site';
    }

    /**
     * See ExportSyncableSettingsAbility::meta() — show_in_rest (not `public`)
     * is what unlocks the REST run endpoint on WordPress 7.1+.
     */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
