<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ProcessStepCardsBlock extends Block
{
    public $name = 'Process Step Cards';

    public $slug = 'process-step-cards';

    public $description = 'Stacked full-width step cards with an oversized numeral and a right-hand illustration — the "How Remote Leverage Works" band on the competitor-comparison pages.';

    public $category = 'remote-leverage';

    public $icon = 'list-view';

    public $keywords = ['process', 'steps', 'how it works', 'numbered'];

    public $view = 'blocks.process-step-cards';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['headline' => 'How Remote Leverage Works', 'is_preview' => true],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        $steps = array_values(array_filter(
            (array) ($field('steps') ?: []),
            fn ($s) => ! empty($s['title'])
        ));

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'subheadline' => BlockDefaults::cleanText($field('subheadline') ?: ''),
            'steps' => array_map(fn ($s, $i) => [
                'number' => $s['number'] ?: str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'title' => $s['title'] ?? '',
                'text' => $s['text'] ?? '',
                'image' => BlockDefaults::resolveImageUrl($s['image'] ?? ''),
            ], $steps, array_keys($steps)),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('process_step_cards_block');

        $fields
            ->addText('headline', ['label' => 'Headline'])
            ->addTextarea('subheadline', ['label' => 'Subheadline', 'rows' => 2])
            ->addRepeater('steps', ['label' => 'Steps', 'button_label' => 'Add step', 'min' => 1])
            ->addText('number', ['label' => 'Number', 'instructions' => 'Leave blank to auto-number (01, 02, …).'])
            ->addText('title', ['label' => 'Title'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 2])
            ->addImage('image', ['label' => 'Illustration', 'return_format' => 'url'])
            ->endRepeater();

        return $fields->build();
    }
}
