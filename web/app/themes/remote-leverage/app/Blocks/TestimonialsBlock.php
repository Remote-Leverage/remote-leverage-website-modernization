<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TestimonialsBlock extends Block
{
    public $name = 'Client Testimonials';

    public $slug = 'testimonials';

    public $description = 'Video and quote client review cards with video playback modals.';

    public $category = 'remote-leverage';

    public $icon = 'format-quote';

    public $keywords = ['testimonials', 'reviews', 'video', 'case study', 'social proof'];

    public $view = 'blocks.testimonials';

    public function with(): array
    {
        return [
            'title' => get_field('title') ?: 'Client Reviews & Case Studies',
            'description' => get_field('description') ?: "Don't just take our word for it—hear from founders and operators who scale their businesses with Remote Leverage specialists.",
            'layout' => get_field('layout') ?: 'carousel',
            'columns' => get_field('columns') ?: '3',
            'testimonials' => $this->testimonials(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('testimonials_block');

        $fields
            ->addText('title', [
                'label' => 'Section Title',
                'default_value' => 'Client Reviews & Case Studies',
            ])
            ->addTextarea('description', [
                'label' => 'Description',
                'default_value' => "Don't just take our word for it—hear from founders and operators who scale their businesses with Remote Leverage specialists.",
                'rows' => 2,
            ])
            ->addSelect('layout', [
                'label' => 'Layout Style',
                'choices' => [
                    'carousel' => 'Swipeable Carousel / Scroll Snap',
                    'grid' => 'Multi-Column Grid',
                ],
                'default_value' => 'carousel',
            ])
            ->addSelect('columns', [
                'label' => 'Columns (Desktop)',
                'choices' => [
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
                'default_value' => '3',
            ])
            ->addRepeater('testimonials', [
                'label' => 'Testimonials List',
                'layout' => 'block',
                'button_label' => 'Add Testimonial',
            ])
            ->addText('author_name', [
                'label' => 'Author Name',
                'default_value' => 'Marcus Vance',
            ])
            ->addText('role', [
                'label' => 'Role / Title',
                'default_value' => 'Founder & CEO',
            ])
            ->addText('company', [
                'label' => 'Company Name',
                'default_value' => 'Vance Media Group',
            ])
            ->addImage('avatar', [
                'label' => 'Author Avatar / Photo',
                'return_format' => 'url',
            ])
            ->addSelect('avatar_shape', [
                'label' => 'Avatar Shape',
                'choices' => [
                    'circle' => 'Circle',
                    'rounded' => 'Rounded Rectangle',
                    'arch' => 'Dome Arch',
                ],
                'default_value' => 'circle',
            ])
            ->addTextarea('quote', [
                'label' => 'Client Quote',
                'default_value' => 'Hiring our executive assistant through Remote Leverage was the highest ROI decision we made this year. She hit the ground running on day one with zero training required.',
                'rows' => 3,
            ])
            ->addUrl('video_url', [
                'label' => 'Video Review URL (Optional)',
            ])
            ->addText('duration', [
                'label' => 'Video Duration (e.g. 1:42)',
            ])
            ->addNumber('rating', [
                'label' => 'Star Rating (1 - 5)',
                'default_value' => 5,
                'min' => 1,
                'max' => 5,
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function testimonials(): array
    {
        $items = get_field('testimonials');

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        // Realistic fallback matching original Elementor testimonials
        return [
            [
                'author_name' => 'Sarah Lin',
                'role' => 'Managing Partner',
                'company' => 'Acro Growth Agency',
                'avatar' => null,
                'avatar_shape' => 'circle',
                'quote' => 'Our bilingual project coordinator in Colombia manages all 15 client accounts across Slack, Asana, and Loom. Absolute game-changer.',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration' => '2:15',
                'rating' => 5,
            ],
            [
                'author_name' => 'David Miller',
                'role' => 'Broker & Owner',
                'company' => 'Miller Capital Realty',
                'avatar' => null,
                'avatar_shape' => 'circle',
                'quote' => 'We tried hiring offshore in the Philippines before, but the 13-hour time difference killed collaboration. Remote Leverage’s LatAm coordinators work our exact hours.',
                'video_url' => null,
                'duration' => null,
                'rating' => 5,
            ],
            [
                'author_name' => 'Elena Rostova',
                'role' => 'Co-Founder',
                'company' => 'DTC Health Brands',
                'avatar' => null,
                'avatar_shape' => 'circle',
                'quote' => 'We scaled from 200 orders a day to 1,200 with two Remote Leverage customer success reps. Our CSAT score actually went up from 91% to 97%.',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration' => '1:45',
                'rating' => 5,
            ],
        ];
    }
}
