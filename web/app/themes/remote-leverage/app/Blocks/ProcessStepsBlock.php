<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ProcessStepsBlock extends Block
{
    public $name = 'Process & Hiring Steps';

    public $slug = 'process-steps';

    public $description = 'Numbered 3-step hiring timeline with connected progress line.';

    public $category = 'remote-leverage';

    public $icon = 'networking';

    public $keywords = ['process', 'steps', 'timeline', 'hiring', 'onboarding'];

    public $view = 'blocks.process-steps';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'badge' => 'HOW IT WORKS',
                'headline' => 'Hiring Top Talent in 3 Simple Steps',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'steps' => $this->steps(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('process_steps_block');

        $fields
            ->addRepeater('steps', [
                'label' => 'Steps Timeline (Leave empty for default 3 steps)',
                'layout' => 'block',
                'button_label' => 'Add Step',
            ])
            ->addText('num', [
                'label' => 'Step Number (e.g. 01)',
                'default_value' => '01',
            ])
            ->addText('title', [
                'label' => 'Step Title (supports HTML like <br>)',
                'default_value' => 'Tell us your<br>ideal hire',
            ])
            ->addTextarea('desc', [
                'label' => 'Step Description',
                'default_value' => 'Tell us who you need. We handle sourcing, screening, and vetting candidates so you can focus on choosing the right person.',
                'rows' => 2,
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function steps(): array
    {
        $items = function_exists('get_field') ? get_field('steps') : null;
        $steps = (! empty($items) && is_array($items))
            ? $items
            : \App\Support\BlockDefaults::steps();

        return array_map(function ($step) {
            $step['title'] = \App\Support\BlockDefaults::cleanText($step['title'] ?? '');
            $step['desc'] = \App\Support\BlockDefaults::cleanText($step['desc'] ?? '');
            return $step;
        }, $steps);
    }
}
