<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class LiveCallBlock extends Block
{
    public $name = 'Live Call CTA';

    public $slug = 'live-call';

    public $description = 'Real-time sales consultant presence indicator and instant Google Meet video call launcher.';

    public $category = 'remote-leverage';

    public $icon = 'phone';

    public $keywords = ['live call', 'video', 'google meet', 'instant call', 'consultant'];

    public $view = 'blocks.live-call';

    public function with(): array
    {
        return [
            'headline' => get_field('headline') ?: 'Speak with our Staffing Director Right Now',
            'description' => get_field('description') ?: 'No need to wait days for an appointment. If our team is green, jump into a private 1-on-1 strategy call immediately.',
            'buttonSize' => get_field('button_size') ?: 'hero',
            'showCardWrapper' => get_field('show_card_wrapper') ?? true,
            'buttonOnly' => (bool) (get_field('button_only') ?? false),
            'alignment' => get_field('alignment') ?: 'center',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('live_call_block');

        $fields
            ->addTrueFalse('button_only', [
                'label' => 'Button Only Mode (No Header / Wrapper)',
                'default_value' => false,
                'ui' => 1,
            ])
            ->addSelect('alignment', [
                'label' => 'Alignment',
                'choices' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ],
                'default_value' => 'center',
            ])
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'Speak with our Staffing Director Right Now',
            ])
            ->addTextarea('description', [
                'label' => 'Description',
                'default_value' => 'No need to wait days for an appointment. If our team is green, jump into a private 1-on-1 strategy call immediately.',
                'rows' => 2,
            ])
            ->addSelect('button_size', [
                'label' => 'Button Style / Size',
                'choices' => [
                    'hero' => 'High-Conversion Hero CTA',
                    'default' => 'Standard Pill Button',
                    'compact' => 'Compact Header Pill',
                ],
                'default_value' => 'hero',
            ])
            ->addTrueFalse('show_card_wrapper', [
                'label' => 'Display inside Glassmorphism Card',
                'default_value' => true,
                'ui' => 1,
            ]);

        return $fields->build();
    }
}
