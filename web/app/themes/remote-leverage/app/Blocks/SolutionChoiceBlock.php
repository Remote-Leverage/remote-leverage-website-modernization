<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class SolutionChoiceBlock extends Block
{
    public $name = 'Solution Choice';

    public $slug = 'solution-choice';

    public $description = 'Two photo-topped service cards beside a section heading and CTA — the "Which solution is right for you?" band on the competitor-comparison pages.';

    public $category = 'remote-leverage';

    public $icon = 'columns';

    public $keywords = ['comparison', 'solution', 'cards', 'versus', 'choice'];

    public $view = 'blocks.solution-choice';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Which Virtual Assistant Solution Is Right For You?',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: 'Which Virtual Assistant Solution Is Right For You?'),
            'subheadline' => BlockDefaults::cleanText($field('subheadline') ?: ''),
            'ctaText' => $field('cta_text') ?: 'Talk to an expert',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
            'cards' => [
                [
                    'image' => BlockDefaults::resolveImageUrl($field('card_1_image') ?: ''),
                    'title' => $field('card_1_title') ?: '',
                    'text' => $field('card_1_text') ?: '',
                    'icon' => $field('card_1_icon') ?: 'asterisk',
                ],
                [
                    'image' => BlockDefaults::resolveImageUrl($field('card_2_image') ?: ''),
                    'title' => $field('card_2_title') ?: '',
                    'text' => $field('card_2_text') ?: '',
                    'icon' => $field('card_2_icon') ?: 'check',
                ],
            ],
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('solution_choice_block');

        $icons = [
            'asterisk' => 'Asterisk (competitor)',
            'check' => 'Green check (Remote Leverage)',
            'none' => 'No icon',
        ];

        $fields
            ->addTextarea('headline', [
                'label' => 'Headline',
                'default_value' => 'Which Virtual Assistant Solution Is Right For You?',
                'rows' => 2,
            ])
            ->addTextarea('subheadline', [
                'label' => 'Subheadline',
                'default_value' => 'Managed service gives you a ready-to-go team, with zero operational burden and costs you can count on.',
                'rows' => 3,
            ])
            ->addText('cta_text', [
                'label' => 'CTA Text',
                'default_value' => 'Talk to an expert',
            ])
            ->addUrl('cta_url', [
                'label' => 'CTA URL',
                'default_value' => '#booking-footer',
            ])
            ->addImage('card_1_image', ['label' => 'Left Card Image', 'return_format' => 'url'])
            ->addText('card_1_title', ['label' => 'Left Card Title'])
            ->addTextarea('card_1_text', ['label' => 'Left Card Text', 'rows' => 3])
            ->addSelect('card_1_icon', ['label' => 'Left Card Icon', 'choices' => $icons, 'default_value' => 'asterisk'])
            ->addImage('card_2_image', ['label' => 'Right Card Image', 'return_format' => 'url'])
            ->addText('card_2_title', ['label' => 'Right Card Title'])
            ->addTextarea('card_2_text', ['label' => 'Right Card Text', 'rows' => 3])
            ->addSelect('card_2_icon', ['label' => 'Right Card Icon', 'choices' => $icons, 'default_value' => 'check']);

        return $fields->build();
    }
}
