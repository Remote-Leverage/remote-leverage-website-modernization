<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\Support\LandingPageComposer;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

class CreateLandingPageAbility extends Ability
{
    public function __construct(private LandingPageComposer $composer) {}

    public function label(): string
    {
        return 'Create Landing Page';
    }

    public function description(): string
    {
        return 'Creates a new WordPress landing page by composing an ordered list of already-registered '.
            'Remote Leverage block patterns (see list-patterns for valid slugs). Always creates a draft '.
            'unless status=publish is explicitly requested and the caller has publish_pages. Does not author '.
            'new block types or patterns — only assembles existing ones. Returns the new post ID, edit URL, '.
            'and a block-validity audit.';
    }

    public function execute(array $input): mixed
    {
        $status = $input['status'] ?? 'draft';

        if ($status === 'publish' && ! current_user_can('publish_pages')) {
            return new WP_Error(
                'forbidden',
                'Publishing requires the publish_pages capability; the page was not created.'
            );
        }

        $content = $this->composer->composeContent($input['pattern_slugs']);
        $postId = $this->composer->createPage($input['title'], $input['slug'], $content, $status);
        $audit = $this->composer->auditBlocks($content);

        return [
            'post_id' => $postId,
            'status' => $status,
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
                'title' => [
                    'type' => 'string',
                    'description' => 'The page title.',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'The page slug (post_name).',
                ],
                'pattern_slugs' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Ordered list of registered "remote-leverage/*" pattern slugs to compose '.
                        'into the page, top to bottom. Call list-patterns first to see valid values.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'publish'],
                    'default' => 'draft',
                    'description' => 'Post status. "publish" requires the publish_pages capability.',
                ],
            ],
            'required' => ['title', 'slug', 'pattern_slugs'],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
