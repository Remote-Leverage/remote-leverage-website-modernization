<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\Support\LandingPageComposer;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

class UpdateLandingPageContentAbility extends Ability
{
    public function __construct(private LandingPageComposer $composer) {}

    public function label(): string
    {
        return 'Update Landing Page Content';
    }

    public function description(): string
    {
        return 'Replaces the content of an existing landing page (created via create-landing-page) with a new '.
            'ordered list of already-registered Remote Leverage block patterns. Use this to iterate on a page '.
            'without creating a duplicate. Returns a block-validity audit of the new content.';
    }

    public function execute(array $input): mixed
    {
        $postId = (int) $input['post_id'];

        if (get_post($postId) === null) {
            return new WP_Error('not_found', "Page {$postId} not found.");
        }

        $content = $this->composer->composeContent($input['pattern_slugs']);
        $this->composer->updatePage($postId, $content);
        $audit = $this->composer->auditBlocks($content);

        return [
            'post_id' => $postId,
            'edit_url' => admin_url("post.php?post={$postId}&action=edit"),
            'preview_url' => get_permalink($postId),
            'audit' => $audit,
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
                    'description' => 'The ID of the existing landing page to update.',
                ],
                'pattern_slugs' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Ordered list of registered "remote-leverage/*" pattern slugs that will '.
                        'replace the page\'s current content, top to bottom.',
                ],
            ],
            'required' => ['post_id', 'pattern_slugs'],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
