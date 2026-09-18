<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\Support\MediaUploader;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Adds a file to the media library and returns the attachment id.
 *
 * This is the missing half of image editing. Every image field on a page is
 * addressed by attachment id, so update-page-sections and clone-page can move
 * existing art around but could never introduce new art — that step had to
 * happen by hand in wp-admin, which is exactly the handoff these abilities
 * exist to remove.
 *
 * Gated on upload_files, which this project revokes by default. See
 * ContentAgentProvisioner: an upload writes to the shared uploads volume and
 * there is no undo, so it is opt-in per environment like publishing is.
 */
class UploadMediaAbility extends Ability
{
    public function __construct(private MediaUploader $uploader) {}

    public function label(): string
    {
        return 'Upload Media';
    }

    public function description(): string
    {
        return 'Uploads an image or file to the WordPress media library and returns its attachment_id, which is '
            .'what image fields on a page are set to. Pass source_url for a file already on the web, or '
            .'data_base64 for one the person has given you directly. Always set alt_text on an image — it is '
            .'what screen readers announce and what search engines read. Use the returned attachment_id as the '
            .'value when calling update-page-sections on a field that describe-page reports as image_id.';
    }

    public function execute(array $input): mixed
    {
        return $this->uploader->upload($input);
    }

    /**
     * upload_files rather than edit_pages: adding a file to the library is a
     * different act from editing a page, and an environment may reasonably want
     * one without the other.
     */
    public function permission(): bool|WP_Error
    {
        return current_user_can('upload_files');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'source_url' => [
                    'type' => 'string',
                    'description' => 'http(s) URL to fetch the file from. Mutually exclusive with data_base64.',
                ],
                'data_base64' => [
                    'type' => 'string',
                    'description' => 'Base64-encoded file contents, with or without a data: URI prefix. '
                        .'Mutually exclusive with source_url. Capped at 8MB decoded — use wp-admin for anything larger.',
                ],
                'filename' => [
                    'type' => 'string',
                    'description' => 'Filename to store it under, including the extension. Required with '
                        .'data_base64; defaults to the basename of source_url otherwise.',
                ],
                'title' => ['type' => 'string', 'description' => 'Media library title.'],
                'alt_text' => [
                    'type' => 'string',
                    'description' => 'Alternative text. Set this for any image that carries meaning.',
                ],
                'caption' => ['type' => 'string', 'description' => 'Caption shown beneath the image where a block displays one.'],
                'description' => ['type' => 'string', 'description' => 'Long description, stored on the attachment.'],
            ],
        ];
    }

    public function category(): ?string
    {
        return 'site';
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ok' => ['type' => 'boolean'],
                'error' => ['type' => 'string'],
                'attachment_id' => ['type' => 'integer'],
                'url' => ['type' => 'string'],
                'filename' => ['type' => 'string'],
                'mime_type' => ['type' => 'string'],
                'width' => ['type' => ['integer', 'null']],
                'height' => ['type' => ['integer', 'null']],
                'alt_text' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
