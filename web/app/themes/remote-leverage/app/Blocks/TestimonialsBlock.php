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
            // Overlay for the bare tiles; see the field's instructions. Unset keeps /signedup/'s.
            'plain_chrome' => get_field('plain_chrome') ?: 'bare',
            'mobile_columns' => get_field('mobile_columns') ?: '1',
            'control_style' => get_field('control_style') ?: 'outline',
            // Production collapses the wall behind a "show more" control on the campaign
            // and steal landing pages, but NOT on /reviews/ (77 cards, all shown) or the
            // bare video walls of /1monthonus/, /hire-va-isolated-form/ and /signedup/.
            // So the collapse is opt-in: unset keeps every existing usage unchanged.
            'show_more' => (bool) get_field('show_more'),
            // Production shows six (two rows of three) on the hire-va family and three
            // (one row) on the steal family — an even split, so the default is the one
            // that is also two full rows at the block's default three columns.
            'visible_count' => max(1, (int) (get_field('visible_count') ?: 6)),
            // The control is an outline pill that reads black on the light pages and
            // white on the near-black steal pages.
            'tone' => get_field('tone') ?: 'light',
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
            ->addSelect('plain_chrome', [
                'label' => 'Bare Tile Overlay',
                'instructions' => 'Only applies to the bare-video-tile layout. Bare is /signedup/: one centred glass '
                    .'play button. Player is the 2026 homepage: a magenta play control bottom-left, the duration '
                    .'opposite it, and a scrub bar along the bottom.',
                'choices' => ['bare' => 'Centred glass button (default)', 'player' => 'Player chrome (2026 homepage)'],
                'default_value' => 'bare',
                'return_format' => 'value',
            ])
            ->addSelect('control_style', [
                'label' => 'Show-more Control Style',
                'instructions' => 'Outline is production\'s pill. Pill is the 2026 homepage\'s magenta CTA button '
                    .'with a circled chevron, matching the other CTAs down that page.',
                'choices' => ['outline' => 'Outline pill (default)', 'pill' => 'Magenta CTA pill (2026 homepage)'],
                'default_value' => 'outline',
                'return_format' => 'value',
            ])
            ->addSelect('mobile_columns', [
                'label' => 'Tiles per row on mobile',
                'instructions' => 'Bare-tile layout only. One is /signedup/. Two is the 2026 homepage, which also '
                    .'switches the tiles to 3:4 below md — a 16:9 tile in half a phone width is too short to read.',
                'choices' => ['1' => 'One (default)', '2' => 'Two'],
                'default_value' => '1',
                'return_format' => 'value',
            ])
            ->addSelect('columns', [
                'label' => 'Columns',
                'choices' => ['3' => 'Three across (default)', '2' => 'Two across'],
                'default_value' => '3',
            ])
            ->addTrueFalse('show_more', [
                'label' => 'Collapse behind a "show more" control',
                'instructions' => 'Off by default — the full wall renders, which is how /reviews/ and the bare video walls behave.',
                'default_value' => 0,
                'ui' => 1,
            ])
            ->addNumber('visible_count', [
                'label' => 'Cards visible before collapse',
                'instructions' => 'Only used when "show more" is on. Production uses 6 on the hire-va pages and 3 on the steal pages.',
                'default_value' => 6,
                'min' => 1,
                'step' => 1,
            ])
            ->addSelect('tone', [
                'label' => 'Control tone',
                'instructions' => 'Outline colour of the "show more" pill.',
                'choices' => ['light' => 'Black on a light ground (default)', 'dark' => 'White on a dark ground'],
                'default_value' => 'light',
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
