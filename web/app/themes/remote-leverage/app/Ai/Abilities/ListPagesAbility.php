<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use Roots\AcornAi\Abilities\Ability;
use WP_Error;
use WP_Query;

/**
 * The entry point for every "work on the page that already exists" task —
 * without it an MCP client has no way to turn "the Wing comparison page" into
 * a post ID it can pass to describe-page or clone-page.
 */
class ListPagesAbility extends Ability
{
    public function label(): string
    {
        return 'List Pages';
    }

    public function description(): string
    {
        return 'Lists pages on this WordPress site with their post ID, title, slug, status and URL. '.
            'Call this first to resolve a page a human referred to by name (e.g. "the Wing comparison page") '.
            'into the post_id that describe-page, clone-page and update-page-sections all take. '.
            'Supports a search term and a status filter.';
    }

    public function execute(array $input): mixed
    {
        $query = new WP_Query([
            'post_type' => 'page',
            'post_status' => $input['status'] ?? ['publish', 'draft', 'pending', 'private'],
            's' => $input['search'] ?? '',
            'posts_per_page' => min((int) ($input['limit'] ?? 50), 200),
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $pages = [];

        foreach ($query->posts as $post) {
            $pages[] = [
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'status' => $post->post_status,
                'url' => get_permalink($post),
                'modified' => $post->post_modified_gmt,
            ];
        }

        return $pages;
    }

    public function permission(): bool|WP_Error
    {
        return current_user_can('edit_pages');
    }

    public function category(): ?string
    {
        return 'site';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional search term matched against page title and content.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private', 'any'],
                    'description' => 'Optional post status filter. Defaults to every status except trashed.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 50,
                    'description' => 'Maximum pages to return (capped at 200).',
                ],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'modified' => ['type' => 'string'],
                ],
            ],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
