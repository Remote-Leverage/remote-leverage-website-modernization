<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TestimonialsBlock extends Block
{
    public $name = 'Client Testimonials';

    public $slug = 'testimonials';

    public $description = 'Video and quote client review cards with self-contained Vimeo playback modal.';

    public $category = 'remote-leverage';

    public $icon = 'format-quote';

    public $keywords = ['testimonials', 'reviews', 'video', 'case study', 'social proof'];

    public $view = 'blocks.testimonials';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Client Stories & Verified Results',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'testimonials' => $this->testimonials(),
            // Production's /reviews/ and /vathankyou/ walls are three across; /signedup/ is
            // two. Unset falls back to three so every existing usage is unchanged.
            'columns' => get_field('columns') ?: '3',
            // 'plain' drops the quote/company/duration chrome for a bare video wall
            // (production's /signedup/). 'cards' is the default everywhere else.
            'layout' => get_field('layout') ?: 'cards',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('testimonials_block');

        $fields
            ->addSelect('layout', [
                'label' => 'Card style',
                'choices' => ['cards' => 'Cards with quote (default)', 'plain' => 'Bare video tiles'],
                'default_value' => 'cards',
            ])
            ->addSelect('columns', [
                'label' => 'Columns',
                'choices' => ['3' => 'Three across (default)', '2' => 'Two across'],
                'default_value' => '3',
            ])
            ->addRepeater('testimonials', [
                'label' => 'Testimonials List',
                'layout' => 'block',
                'button_label' => 'Add Testimonial',
            ])
            ->addText('company', [
                'label' => 'Company Name',
                'default_value' => 'Liberty Hill',
            ])
            ->addTextarea('quote', [
                'label' => 'Client Quote',
                'default_value' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates.”',
                'rows' => 2,
            ])
            ->addUrl('video_url', [
                'label' => 'Vimeo Video URL',
                'default_value' => 'https://vimeo.com/1067577532',
            ])
            ->addImage('image', [
                'label' => 'Video Thumbnail Poster',
                'return_format' => 'url',
            ])
            ->addText('duration', [
                'label' => 'Duration (e.g. 01:21)',
                'default_value' => '01:21',
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function testimonials(): array
    {
        $items = function_exists('get_field') ? get_field('testimonials') : null;
        $testimonials = (! empty($items) && is_array($items))
            ? $items
            : BlockDefaults::testimonials();

        return array_map(function ($item) {
            $item['image'] = BlockDefaults::resolveImageUrl($item['image'] ?? '');

            return $item;
        }, $testimonials);
    }
}
