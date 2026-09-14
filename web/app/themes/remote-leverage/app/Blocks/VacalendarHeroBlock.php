<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class VacalendarHeroBlock extends Block
{
    public $name = 'VA Calendar Hero';

    public $slug = 'vacalendar-hero';

    public $description = 'Split 15-minute consultation booking hero with dark background and live scheduling funnel.';

    public $category = 'remote-leverage';

    public $icon = 'calendar-alt';

    public $keywords = ['vacalendar', 'consultation', 'booking', 'calendar'];

    public $view = 'blocks.vacalendar-hero';

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public function with(): array
    {
        return [
            'headline' => (function_exists('get_field') ? get_field('headline') : null) ?: '15 Minute Virtual Assistant Hiring Consultation',
            'paragraph_1' => (function_exists('get_field') ? get_field('paragraph_1') : null) ?: 'During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.',
            'paragraph_2' => (function_exists('get_field') ? get_field('paragraph_2') : null) ?: 'This consultation will be done over zoom so it is best if you could be on a computer!',
            'skin' => (function_exists('get_field') ? get_field('skin') : null) ?: 'glass',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('vacalendar_hero_block');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => '15 Minute Virtual Assistant Hiring Consultation',
            ])
            ->addTextarea('paragraph_1', [
                'label' => 'Paragraph 1',
                'rows' => 3,
                'default_value' => 'During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.',
            ])
            ->addTextarea('paragraph_2', [
                'label' => 'Paragraph 2',
                'rows' => 2,
                'default_value' => 'This consultation will be done over zoom so it is best if you could be on a computer!',
            ])
            ->addSelect('skin', [
                'label' => 'Booking Skin',
                'choices' => [
                    'glass' => 'Dark Glassmorphic (for violet backgrounds)',
                    'light' => 'Light Clean (for white/gray backgrounds)',
                ],
                'default_value' => 'glass',
            ]);

        return $fields->build();
    }
}
