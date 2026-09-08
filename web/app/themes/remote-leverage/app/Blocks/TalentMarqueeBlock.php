<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TalentMarqueeBlock extends Block
{
    public $name = 'Talent Marquee';

    public $slug = 'talent-marquee';

    public $description = 'Infinite marquee of pre-vetted Latin American talent profile cards.';

    public $category = 'remote-leverage';

    public $icon = 'groups';

    public $keywords = ['talent', 'marquee', 'profiles', 'staffing', 'candidates'];

    public $view = 'blocks.talent-marquee';

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public function with(): array
    {
        return [
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('talent_marquee_block');

        $fields
            ->addRepeater('talent_cards', [
                'label' => 'Talent Cards (Leave empty for default 11 cards)',
                'layout' => 'block',
                'button_label' => 'Add Talent Profile',
            ])
            ->addText('name', ['label' => 'Full Name'])
            ->addText('title', ['label' => 'Job Title'])
            ->addTextarea('desc', ['label' => 'Bio / Description', 'rows' => 2])
            ->addImage('bg', ['label' => 'Photo Background', 'return_format' => 'url'])
            ->addImage('logo', ['label' => 'Prior Company Logo', 'return_format' => 'url'])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $custom = function_exists('get_field') ? get_field('talent_cards') : null;
        $cards = (! empty($custom) && is_array($custom))
            ? $custom
            : \App\Support\BlockDefaults::talentCards();

        return array_map(function ($card) {
            $card['bg'] = \App\Support\BlockDefaults::resolveImageUrl($card['bg'] ?? '');
            $card['logo'] = \App\Support\BlockDefaults::resolveImageUrl($card['logo'] ?? '');
            return $card;
        }, $cards);
    }
}
