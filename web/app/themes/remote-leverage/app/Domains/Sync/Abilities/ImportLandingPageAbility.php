<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncCapability;
use RuntimeException;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * REST-only (no meta.mcp.public) — invoked exclusively by `wp rl:sync:page`.
 * Creates or updates (matched by slug) a page from an ExportLandingPageAbility
 * payload, including postmeta. Uses wp_slash() per
 * docs/page-migration-and-design-system-workflow.md § 8.
 */
class ImportLandingPageAbility extends Ability
{
    public function label(): string
    {
        return 'Import Landing Page';
    }

    public function description(): string
    {
        return 'Creates or updates (matched by slug) a page from exported title/slug/status/content/meta. '.
            'Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $existing = get_page_by_path($input['slug'], OBJECT, 'page');

        $postArgs = [
            'post_type' => 'page',
            'post_title' => $input['title'],
            'post_name' => $input['slug'],
            'post_status' => $input['status'],
            'post_content' => wp_slash($input['content']),
        ];

        if ($existing !== null) {
            $postArgs['ID'] = $existing->ID;
            $result = wp_update_post($postArgs, true);
        } else {
            $result = wp_insert_post($postArgs, true);
        }

        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        $postId = $existing?->ID ?? $result;

        foreach ($input['meta'] ?? [] as $key => $values) {
            delete_post_meta($postId, $key);

            foreach ((array) $values as $value) {
                add_post_meta($postId, $key, maybe_unserialize($value));
            }
        }

        return [
            'post_id' => $postId,
            'created' => $existing === null,
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
                'title' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'meta' => [
                    'type' => 'object',
                    'description' => 'Map of meta_key to an array of raw meta values, as returned by '.
                        'get_post_meta($id) / ExportLandingPageAbility.',
                ],
            ],
            'required' => ['title', 'slug', 'status', 'content'],
        ];
    }

    public function category(): ?string
    {
        return 'site';
    }
}
