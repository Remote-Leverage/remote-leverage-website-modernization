<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TalentGridBlock extends Block
{
    public $name = 'Talent Grid';

    public $slug = 'talent-grid';

    public $description = 'Static grid of pre-vetted Latin American talent profile cards (non-carousel).';

    public $category = 'remote-leverage';

    public $icon = 'grid-view';

    public $keywords = ['talent', 'grid', 'profiles', 'staffing', 'candidates'];

    public $view = 'blocks.talent-grid';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'is_preview' => true,
            ],
        ],
    ];

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
        $fields = Builder::make('talent_grid_block');

        $fields
            ->addRepeater('talent_cards', [
                'label' => 'Talent Cards (Leave empty for default 8 cards)',
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
            : BlockDefaults::vaPricingTalentCards();

        return array_map(function ($card) {
            $bgUrl = BlockDefaults::resolveImageUrl($card['bg'] ?? '');
            $card['bg'] = BlockDefaults::preferWebp($bgUrl);
            $card['logo'] = BlockDefaults::preferWebp(BlockDefaults::resolveImageUrl($card['logo'] ?? ''));

            $bgId = BlockDefaults::getAttachmentId($bgUrl);
            if (is_int($bgId) && function_exists('wp_get_attachment_image_srcset')) {
                $srcset = wp_get_attachment_image_srcset($bgId, 'medium_large');
                if ($srcset) {
                    $card['bg_srcset'] = preg_replace_callback(
                        '/(\S+)(?=\s+\d+w)/',
                        fn ($m) => BlockDefaults::preferWebp($m[1]),
                        $srcset,
                    );
                    $card['bg_sizes'] = '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw';
                }
            }

            return $card;
        }, $cards);
    }
}
