<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ProgressStepsBlock extends Block
{
    public $name = 'Progress Bar Steps';

    public $slug = 'progress-steps';

    public $description = 'Heading and intro above a three-segment progress bar, with the steps beneath in divider-separated columns.';

    public $category = 'remote-leverage';

    public $icon = 'minus';

    public $keywords = ['steps', 'progress', 'bar', 'how it works', 'columns'];

    public $view = 'blocks.progress-steps';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['is_preview' => true],
        ],
    ];

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public function with(): array
    {
        $field = fn (string $key) => function_exists('get_field') ? get_field($key) : null;

        return [
            'headline' => $field('headline') ?: '',
            'subheadline' => $field('subheadline') ?: '',
            'steps' => $this->steps(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('progress_steps_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2, 'instructions' => 'Inline <br> allowed.'])
            ->addTextarea('subheadline', ['label' => 'Subheadline', 'rows' => 2])
            ->addRepeater('steps', ['label' => 'Steps (3)', 'button_label' => 'Add step', 'min' => 1])
            ->addText('title', ['label' => 'Title'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 3])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * @return array<int, array{title: string, text: string}>
     */
    protected function steps(): array
    {
        $custom = function_exists('get_field') ? get_field('steps') : null;

        if (! is_array($custom) || $custom === []) {
            return [];
        }

        return array_values(array_map(static fn (array $s): array => [
            'title' => BlockDefaults::cleanText($s['title'] ?? ''),
            'text' => BlockDefaults::cleanText($s['text'] ?? ''),
        ], array_filter($custom, 'is_array')));
    }
}
