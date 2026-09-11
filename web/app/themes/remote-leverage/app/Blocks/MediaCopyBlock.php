<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class MediaCopyBlock extends Block
{
    public $name = 'Media & Copy';

    public $slug = 'media-copy';

    public $description = 'An image on one side with a heading, rich copy and a CTA on the other.';

    public $category = 'remote-leverage';

    public $icon = 'align-pull-left';

    public $keywords = ['media', 'image', 'copy', 'split'];

    public $view = 'blocks.media-copy';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['headline' => 'Talent pool, time zones, and language standards', 'is_preview' => true],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'body' => $field('body') ?: '',
            'image' => BlockDefaults::resolveImageUrl($field('image') ?: ''),
            'imagePosition' => $field('image_position') ?: 'left',
            'ctaText' => $field('cta_text') ?: '',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('media_copy_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addWysiwyg('body', ['label' => 'Body', 'tabs' => 'visual', 'media_upload' => 0])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'url'])
            ->addSelect('image_position', [
                'label' => 'Image Position',
                'choices' => ['left' => 'Left', 'right' => 'Right'],
                'default_value' => 'left',
            ])
            ->addText('cta_text', ['label' => 'CTA Text', 'instructions' => 'Leave blank to hide.'])
            ->addUrl('cta_url', ['label' => 'CTA URL', 'default_value' => '#booking-footer']);

        return $fields->build();
    }
}
