<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\Support\LandingPageComposer;
use App\Ai\Support\PageSectionEditor;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Edits the copy on a page in place, leaving every section it was not told to
 * touch byte-identical.
 *
 * The sibling update-landing-page-content replaces a page's whole content from
 * a list of pattern slugs, which throws away any per-page copy. This is the one
 * to reach for when iterating on a page that has already been customised.
 */
class UpdatePageSectionsAbility extends Ability
{
    public function __construct(
        private PageSectionEditor $editor,
        private LandingPageComposer $composer,
    ) {}

    public function label(): string
    {
        return 'Update Page Sections';
    }

    public function description(): string
    {
        return 'Replaces the copy in named sections of an existing page, leaving all other sections untouched. '.
            'Use this to iterate on a page — including one made by clone-page — without rebuilding it. '.
            'Call describe-page first for valid section and field names. Editing a page that is already '.
            'published changes the live site immediately and requires the edit_published_pages capability. '.
            'Overrides naming a section or field that does not exist are reported in "skipped", not applied.';
    }

    public function execute(array $input): mixed
    {
        $postId = (int) $input['post_id'];
        $post = get_post($postId);

        if ($post === null || $post->post_type !== 'page') {
            return new WP_Error('not_found', "Page {$postId} not found.");
        }

        if ($post->post_status === 'publish' && ! current_user_can('edit_published_pages')) {
            return new WP_Error(
                'forbidden',
                "Page {$postId} is published; editing it requires the edit_published_pages capability. ".
                'The page was not changed.'
            );
        }

        $overrides = $input['overrides'] ?? [];

        if ($overrides === []) {
            return new WP_Error('no_overrides', 'No overrides were supplied; the page was not changed.');
        }

        $result = $this->editor->apply($post->post_content, $overrides);

        if ($result['applied'] === []) {
            return new WP_Error(
                'nothing_applied',
                'None of the supplied overrides matched a section and field on this page, so it was not '.
                'changed. Call describe-page for valid names. Details: '.implode('; ', $result['skipped'])
            );
        }

        $this->composer->updatePage($postId, $result['content']);

        return [
            'post_id' => $postId,
            'status' => $post->post_status,
            'edit_url' => admin_url("post.php?post={$postId}&action=edit"),
            'preview_url' => get_permalink($postId),
            'applied' => $result['applied'],
            'skipped' => $result['skipped'],
            'audit' => $this->composer->auditBlocks($result['content']),
        ];
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
                'post_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the page to edit, as returned by list-pages.',
                ],
                'overrides' => [
                    'type' => 'array',
                    'description' => 'Per-section content replacements. Sections not named here are untouched.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => [
                                'type' => 'string',
                                'description' => 'A section name from describe-page, e.g. "hero".',
                            ],
                            'fields' => [
                                'type' => 'object',
                                'description' => 'Field name to new value, e.g. {"headline": "New headline"}.',
                            ],
                        ],
                        'required' => ['section', 'fields'],
                    ],
                ],
            ],
            'required' => ['post_id', 'overrides'],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
