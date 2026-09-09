<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class BookingBlock extends Block
{
    public $name = 'Booking Funnel';

    public $slug = 'booking';

    public $description = 'Interactive multistep qualification and live scheduling calendar funnel.';

    public $category = 'remote-leverage';

    public $icon = 'calendar-alt';

    public $keywords = ['booking', 'calendar', 'calendly', 'scheduling', 'consultation'];

    public $view = 'blocks.booking';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'skin' => 'glass',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'skin' => (function_exists('get_field') ? get_field('skin') : null) ?: 'glass',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('booking_block');

        $fields
            ->addSelect('skin', [
                'label' => 'Visual Skin',
                'choices' => [
                    'glass' => 'Dark Glassmorphic (For Dark Violet backgrounds)',
                    'light' => 'Light Clean (For Light Gray/White backgrounds)',
                ],
                'default_value' => 'glass',
            ]);

        return $fields->build();
    }
}
