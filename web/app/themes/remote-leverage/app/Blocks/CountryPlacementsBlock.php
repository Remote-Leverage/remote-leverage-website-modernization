<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class CountryPlacementsBlock extends Block
{
    public $name = 'Country Placements Table';

    public $slug = 'country-placements';

    public $description = 'Flag + country + count rows laid out in three columns under a dark banner heading.';

    public $category = 'remote-leverage';

    public $icon = 'admin-site-alt3';

    public $keywords = ['countries', 'placements', 'table', 'flags', 'stats', 'impact', 'report'];

    public $view = 'blocks.country-placements';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Country placements',
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
            'headline' => (function_exists('get_field') ? get_field('headline') : null) ?: 'Country placements',
            'rows' => $this->rows(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('country_placements_block');

        $fields
            ->addText('headline', [
                'label' => 'Banner Heading',
                'default_value' => 'Country placements',
            ])
            ->addRepeater('rows', [
                'label' => 'Countries (leave empty for the 2026 Impact Report set)',
                'layout' => 'table',
                'button_label' => 'Add country',
            ])
            ->addImage('flag', ['label' => 'Flag', 'return_format' => 'url'])
            ->addText('country', ['label' => 'Country'])
            ->addText('count', ['label' => 'Placements'])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * @return array<int, array{flag: string, country: string, count: string}>
     */
    protected function rows(): array
    {
        $custom = function_exists('get_field') ? get_field('rows') : null;
        $rows = is_array($custom) && $custom !== [] ? $custom : BlockDefaults::countryPlacements();

        return array_values(array_map(static fn (array $row): array => [
            'flag' => BlockDefaults::resolveImageUrl($row['flag'] ?? ''),
            'country' => BlockDefaults::cleanText($row['country'] ?? ''),
            'count' => BlockDefaults::cleanText((string) ($row['count'] ?? '')),
        ], array_filter($rows, 'is_array')));
    }
}
