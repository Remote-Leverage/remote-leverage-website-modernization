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

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        return [
            [
                'num' => '01',
                'title' => 'Tell us your<br>ideal hire',
                'desc' => 'Tell us who you need. We handle sourcing, screening, and vetting candidates so you can focus on choosing the right person.',
            ],
            [
                'num' => '02',
                'title' => 'Meet your<br>top 1% shortlist',
                'desc' => 'Within 48–72 hours, receive 4–6 candidates pre-vetted for skill, experience, and fit. You interview, you choose. No commitments, no pressure.',
            ],
            [
                'num' => '03',
                'title' => 'Make your<br>selection',
                'desc' => 'Make your selection and get back to growing your business. We handle the details so your new hire can hit the ground running.',
            ],
        ];
    }
}
