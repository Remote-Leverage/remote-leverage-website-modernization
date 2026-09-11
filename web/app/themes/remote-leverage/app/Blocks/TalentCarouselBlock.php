<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TalentCarouselBlock extends Block
{
    public $name = 'Talent Carousel';

    public $slug = 'talent-carousel';

    public $description = 'Copy on the left with a centre-mode carousel of talent profile cards on the right.';

    public $category = 'remote-leverage';

    public $icon = 'images-alt2';

    public $keywords = ['talent', 'carousel', 'profiles', 'candidates'];

    public $view = 'blocks.talent-carousel';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['headline' => 'Finding the right talent to help you scale', 'is_preview' => true],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        $profiles = array_values(array_filter(
            (array) ($field('profiles') ?: []),
            fn ($p) => ! empty($p['name'])
        ));

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'body' => $field('body') ?: '',
            'ctaText' => $field('cta_text') ?: '',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
            'profiles' => array_map(fn ($p) => [
                'image' => BlockDefaults::resolveImageUrl($p['image'] ?? ''),
                'name' => $p['name'] ?? '',
                'role' => $p['role'] ?? '',
                'rate' => $p['rate'] ?? '',
            ], $profiles),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('talent_carousel_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addWysiwyg('body', ['label' => 'Body', 'tabs' => 'visual', 'media_upload' => 0])
            ->addText('cta_text', ['label' => 'CTA Text', 'default_value' => 'Book a consultation'])
            ->addUrl('cta_url', ['label' => 'CTA URL', 'default_value' => '#booking-footer'])
            ->addRepeater('profiles', ['label' => 'Profiles', 'button_label' => 'Add profile', 'min' => 1])
            ->addImage('image', ['label' => 'Photo', 'return_format' => 'url'])
            ->addText('name', ['label' => 'Name'])
            ->addText('role', ['label' => 'Role'])
            ->addText('rate', ['label' => 'Rate', 'instructions' => 'e.g. $8 Per Hour'])
            ->endRepeater();

        return $fields->build();
    }
}
