<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\Support\LandingPageComposer;
use App\Ai\Support\PageSectionEditor;
use App\Infrastructure\WordPress\PostDuplicator;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * The ability the marketing workflow is built around: take a landing page that
 * already works, keep its layout, and swap the copy.
 *
 * Deliberately a clone-and-override rather than a compose-from-scratch. The
 * source page's block tree already carries the right blocks with the right
 * options, so copying it and rewriting attrs.data keeps a new page on the
 * design system — and means new copy no longer requires a new patterns/*.php
 * file and a deploy.
 */
class ClonePageAbility extends Ability
{
    public function __construct(
        private PageSectionEditor $editor,
        private LandingPageComposer $composer,
        private PostDuplicator $duplicator,
    ) {}

    public function label(): string
    {
        return 'Clone Page With New Content';
    }

    public function description(): string
    {
        return 'Creates a new page by copying an existing one and replacing the copy in named sections. '.
            'This is the preferred way to build a landing page that is "like this one but for X": it keeps the '.
            'source page\'s blocks, layout and options, so the result stays on the design system and needs no '.
            'theme deploy. Call describe-page on the source first to get valid section and field names. '.
            'Always creates a draft unless status=publish is explicitly requested and the caller can publish. '.
            'Overrides naming a section or field that does not exist are reported in "skipped", not applied — '.
            'always check that array rather than assuming every override landed.';
    }

    public function execute(array $input): mixed
    {
        $sourceId = (int) $input['source_post_id'];
        $source = get_post($sourceId);

        if ($source === null || $source->post_type !== 'page') {
            return new WP_Error('not_found', "Source page {$sourceId} not found.");
        }

        $status = $input['status'] ?? 'draft';

        if ($status === 'publish' && ! current_user_can('publish_pages')) {
            return new WP_Error(
                'forbidden',
                'Publishing requires the publish_pages capability; the page was not created.'
            );
        }

        $result = $this->editor->apply($source->post_content, $input['overrides'] ?? []);

        $postId = $this->composer->createPage(
            $input['title'],
            $input['slug'],
            $result['content'],
            $status,
        );

        $this->duplicator->copyMeta($sourceId, $postId);

        return [
            'post_id' => $postId,
            'cloned_from' => $sourceId,
            'status' => $status,
            'slug' => get_post_field('post_name', $postId),
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
                'source_post_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the page to clone, as returned by list-pages.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Title for the new page.',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Slug (post_name) for the new page. WordPress appends a suffix if taken; '.
                        'the slug actually used is returned.',
                ],
                'overrides' => [
                    'type' => 'array',
                    'description' => 'Per-section content replacements applied to the copy.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => [
                                'type' => 'string',
                                'description' => 'A section name from describe-page, e.g. "hero".',
                            ],
                            'fields' => [
                                'type' => 'object',
                                'description' => 'Field name to new value, e.g. {"headline": "New headline"}. '.
                                    'Only fields that already exist on the section are applied.',
                            ],
                        ],
                        'required' => ['section', 'fields'],
                    ],
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'publish'],
                    'default' => 'draft',
                    'description' => 'Post status. "publish" requires the publish_pages capability.',
                ],
            ],
            'required' => ['source_post_id', 'title', 'slug'],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
