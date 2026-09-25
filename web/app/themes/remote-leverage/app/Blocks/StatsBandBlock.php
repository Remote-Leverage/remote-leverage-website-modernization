<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's flat dark stats strip (/ecommerce-virtual-assistant/ §5).
 *
 * Field naming note: ACF Composer derives a repeater sub-field key as
 * `field_<group>_<repeater>_<sub>`, so the top-level field here is `tone` —
 * deliberately not `stats_*`, which would collide with a `stats` sub-field.
 */
class StatsBandBlock extends Block
{
    public $name = 'Stats Band';

    public $slug = 'stats-band';

    public $description = 'Flat three-column band of headline figures separated by hairlines, with an optional eyebrow above a value.';

    public $category = 'remote-leverage';

    public $icon = 'chart-bar';

    public $keywords = ['stats', 'metrics', 'figures', 'band', 'impact'];

    public $view = 'blocks.stats-band';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            // Production's band is the flat #250D4A surface; 'light' exists for pages
            // that run the same figures on the pale surface.
            'tone' => (string) ($field('tone') ?: 'dark'),
            // 'pills' is /become-a-partner/'s centred row of white pills, drawn on the
            // pattern's own background. Default stays the band.
            'layout' => (string) ($field('layout') ?: 'band'),
            'stats' => $this->stats($field('stats')),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('stats_band_block');

        $fields
            ->addSelect('layout', [
                'label' => 'Layout',
                'choices' => [
                    'band' => 'Full-width band, three columns (default)',
                    'pills' => 'Centred row of white pills, no band',
                ],
                'default_value' => 'band',
            ])
            ->addSelect('tone', [
                'label' => 'Surface',
                'choices' => [
                    'dark' => 'Dark violet (default)',
                    'light' => 'Pale',
                ],
                'default_value' => 'dark',
            ])
            ->addRepeater('stats', [
                'label' => 'Stats (leave empty for the production three)',
                'layout' => 'table',
                'button_label' => 'Add stat',
            ])
            ->addText('eyebrow', ['label' => 'Eyebrow', 'instructions' => 'Small label above the value, e.g. USD.'])
            ->addText('value', ['label' => 'Value'])
            ->addText('label', ['label' => 'Label'])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * @return array<int, array{eyebrow: string, value: string, label: string}>
     */
    public function stats(mixed $stats): array
    {
        $stats = is_array($stats) ? array_values(array_filter(
            $stats,
            fn ($stat) => is_array($stat) && ($stat['value'] ?? '') !== '',
        )) : [];

        if ($stats === []) {
            $stats = static::defaultStats();
        }

        return array_map(fn (array $stat): array => [
            'eyebrow' => BlockDefaults::cleanText($stat['eyebrow'] ?? ''),
            'value' => BlockDefaults::cleanText($stat['value'] ?? ''),
            'label' => BlockDefaults::cleanText($stat['label'] ?? ''),
        ], $stats);
    }

    /**
     * Production's three figures, reproduced verbatim — including the malformed
     * "41,920,00" and the truncated "Economic Impact Create". Both are live copy
     * on /ecommerce-virtual-assistant/; the migration copies, it does not correct.
     *
     * @return array<int, array{eyebrow: string, value: string, label: string}>
     */
    public static function defaultStats(): array
    {
        return [
            ['eyebrow' => '', 'value' => '+2500', 'label' => 'Contractors paid'],
            ['eyebrow' => '', 'value' => '+50', 'label' => 'Countries covered'],
            ['eyebrow' => 'USD', 'value' => '41,920,00', 'label' => 'Economic Impact Create'],
        ];
    }
}
