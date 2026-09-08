<?php

declare(strict_types=1);

namespace App\Blocks;

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

    public function with(): array
    {
        return [
            'testimonials' => $this->testimonials(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('testimonials_block');

        $fields
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
            : \App\Support\BlockDefaults::testimonials();

        return array_map(function ($item) {
            $item['image'] = \App\Support\BlockDefaults::resolveImageUrl($item['image'] ?? '');
            return $item;
        }, $testimonials);
    }
}
