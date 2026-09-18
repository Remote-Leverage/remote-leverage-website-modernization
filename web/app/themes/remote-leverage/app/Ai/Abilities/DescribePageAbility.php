<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\Support\PageSectionEditor;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Returns a page as an editable outline rather than as raw block markup.
 *
 * Handing an agent 60KB of serialized ACF block comments and asking it to
 * rewrite the copy is how markup gets forked. This returns the section keys
 * and field values that clone-page and update-page-sections actually accept,
 * so the natural next step is a targeted override instead of a rewrite.
 */
class DescribePageAbility extends Ability
{
    public function __construct(private PageSectionEditor $editor) {}

    public function label(): string
    {
        return 'Describe Page';
    }

    public function description(): string
    {
        return 'Returns a page broken down into its named sections, each with the block that renders it and '.
            'its current editable field values. Call this to understand a page before cloning it or editing it: '.
            'the "section" and "field" names it returns are exactly the ones clone-page and update-page-sections '.
            'take as overrides. Fields are typed — "text" is copy you may rewrite, "image_id" is an attachment ID, '.
            '"number" is a numeric setting, and "repeater_count" is the number of rows in a repeater and should '.
            'usually be left alone. '.
            "\n\n".
            'Fields typed "design" are the layout and styling controls every section has: spacing above and '.
            'below, background, hide-on-mobile/desktop, anchor, extra classes, and per-section custom CSS. They '.
            'are listed on every acf/* section even when never set — an empty value means "unchanged", not '.
            '"unavailable" — and each carries a "label" and an "accepts" string saying exactly what it takes. '.
            'Pass them to update-page-sections like any other field. Spacing and styling on this site are '.
            'therefore editable through the API; do not report them as theme-source-only.';
    }

    public function execute(array $input): mixed
    {
        $postId = (int) $input['post_id'];
        $post = get_post($postId);

        if ($post === null || $post->post_type !== 'page') {
            return new WP_Error('not_found', "Page {$postId} not found.");
        }

        return [
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'url' => get_permalink($post),
            'sections' => $this->editor->outline($post->post_content),
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
                    'description' => 'The ID of the page to describe, as returned by list-pages.',
                ],
            ],
            'required' => ['post_id'],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'post_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => ['type' => 'string'],
                            'block' => ['type' => 'string'],
                            'depth' => ['type' => 'integer'],
                            'fields' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'field' => ['type' => 'string'],
                                        'type' => ['type' => 'string'],
                                        // ACF stores copy as strings and both
                                        // image IDs and repeater counts as
                                        // numbers, so this is genuinely mixed.
                                        //
                                        // 'array' and 'object' are here because
                                        // some ACF field types (a multi-select,
                                        // a checkbox, a link) store one, and the
                                        // adapter validates this schema before
                                        // returning: omitting them did not
                                        // degrade those fields, it failed the
                                        // whole call with
                                        // "value is not of type
                                        // string,number,boolean,null" and took
                                        // every other section on the page down
                                        // with it. /hire-va-4/ was unreadable,
                                        // and so uneditable, for that reason.
                                        'value' => ['type' => ['string', 'number', 'boolean', 'null', 'array', 'object']],
                                        // Present only on design controls, which
                                        // are advertised whether set or not.
                                        'label' => ['type' => 'string'],
                                        'accepts' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
