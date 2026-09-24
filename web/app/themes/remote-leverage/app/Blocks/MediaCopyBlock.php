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
            'videoUrl' => get_field('video_url') ?: '',
            'imagePosition' => $field('image_position') ?: 'left',
            'ctaText' => $field('cta_text') ?: '',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
            // Production uses this shape on both a pale and a dark surface.
            'tone' => $field('tone') ?: 'light',
            // With no media, production's text-only bands (Athena's "roles supported")
            // put the heading in one column and the copy in the other. Off by default,
            // so every existing usage keeps heading-above-copy in a single cell.
            'headingBesideBody' => (bool) $field('heading_beside_body'),
            // /referral-program/ aligns each row's copy toward its artwork and sets the
            // heading in brand-navy. Both default to today's behaviour everywhere else.
            'textAlign' => $field('text_align') ?: 'left',
            'headingColor' => $field('heading_color') ?: 'default',
            // /referral-program/'s art is 440px square natively; the 560px default would
            // upscale it. Existing usages leave this unset and keep 560.
            'imageMaxWidth' => (int) ($field('image_max_width') ?: 560),
            // /become-a-partner/ rounds its photo and runs a numbered timeline under the
            // heading. Both default off, so no existing usage moves. Named `timeline`, not
            // `steps`: BlockDefaults::filterLoadValue() matches repeaters by name alone and
            // would fill an empty `steps` with the process-steps presets on every media-copy.
            'imageRounded' => (bool) $field('image_rounded'),
            'verticalAlign' => $field('vertical_align') ?: 'center',
            'padding' => $field('padding') ?: 'default',
            'timeline' => array_values(array_filter(array_map(
                fn ($row) => BlockDefaults::cleanText(is_array($row) ? ($row['text'] ?? '') : ''),
                is_array($field('timeline')) ? $field('timeline') : [],
            ))),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('media_copy_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addWysiwyg('body', ['label' => 'Body', 'tabs' => 'visual', 'media_upload' => 0])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'url'])
            ->addUrl('video_url', [
                'label' => 'Video URL (optional)',
                'instructions' => 'An MP4 here replaces the image with a player; the image, if set, becomes its poster.',
            ])
            ->addSelect('image_position', [
                'label' => 'Image Position',
                'choices' => ['left' => 'Left', 'right' => 'Right'],
                'default_value' => 'left',
            ])
            ->addText('cta_text', ['label' => 'CTA Text', 'instructions' => 'Leave blank to hide.'])
            ->addUrl('cta_url', ['label' => 'CTA URL', 'default_value' => '#booking-footer'])
            ->addNumber('image_max_width', [
                'label' => 'Image max width (px)',
                'instructions' => 'Leave empty for 560. Set to the art\'s natural width to avoid upscaling.',
                'default_value' => 560,
            ])
            ->addSelect('text_align', [
                'label' => 'Copy alignment',
                'choices' => ['left' => 'Left (default)', 'right' => 'Right', 'center' => 'Centre'],
                'default_value' => 'left',
            ])
            ->addSelect('heading_color', [
                'label' => 'Heading colour',
                'choices' => ['default' => 'Default', 'navy' => 'Brand navy'],
                'default_value' => 'default',
            ])
            ->addTrueFalse('heading_beside_body', [
                'label' => 'Heading in its own column',
                'instructions' => 'Only applies when there is no image or video. Splits the heading and the copy across the two columns.',
                'ui' => 1,
                'default_value' => 0,
            ])
            ->addSelect('vertical_align', [
                'label' => 'Vertical alignment',
                'instructions' => 'How the copy sits against the image when the two differ in height.',
                'choices' => ['center' => 'Centred (default)', 'top' => 'Top'],
                'default_value' => 'center',
            ])
            ->addSelect('padding', [
                'label' => 'Band padding',
                'choices' => ['default' => '80px (default)', 'roomy' => '100px'],
                'default_value' => 'default',
            ])
            ->addTrueFalse('image_rounded', [
                'label' => 'Round the image corners',
                'ui' => 1,
                'default_value' => 0,
            ])
            ->addRepeater('timeline', [
                'label' => 'Numbered Timeline',
                'instructions' => 'Optional. Rendered under the body as a numbered vertical list.',
                'layout' => 'table',
                'button_label' => 'Add Step',
            ])
            ->addText('text', ['label' => 'Step'])
            ->endRepeater()
            ->addSelect('tone', [
                'label' => 'Surface',
                'choices' => [
                    'light' => 'Pale (default)',
                    'white' => 'White',
                    'dark' => 'Dark purple',
                ],
                'default_value' => 'light',
            ]);

        return $fields->build();
    }
}
