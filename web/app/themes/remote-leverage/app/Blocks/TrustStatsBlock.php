<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class TrustStatsBlock extends Block
{
    public $name = 'Trust & Impact Stats';

    public $slug = 'trust-stats';

    public $description = 'Visual stats cards displaying VAs onboarded, countries with flags, and economic impact.';

    public $category = 'remote-leverage';

    public $icon = 'chart-bar';

    public $keywords = ['stats', 'trust', 'impact', 'metrics', 'counter'];

    public $view = 'blocks.trust-stats';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'title' => 'Proven Scale & Reliability',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');

        return [
            'onboardedCount' => ($hasGetField ? get_field('onboarded_count') : null) ?: '+2500',
            'countriesCount' => ($hasGetField ? get_field('countries_count') : null) ?: '+50',
            'economicImpact' => ($hasGetField ? get_field('economic_impact') : null) ?: 'USD 41,920,000',
            'timeframe' => ($hasGetField ? get_field('timeframe') : null) ?: "Last 12\nMonths",
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('trust_stats_block');

        $fields
            ->addText('onboarded_count', [
                'label' => 'VAs Onboarded Counter',
                'default_value' => '+2500',
            ])
            ->addText('countries_count', [
                'label' => 'Countries Counter',
                'default_value' => '+50',
            ])
            ->addText('economic_impact', [
                'label' => 'Economic Impact Amount',
                'default_value' => 'USD 41,920,000',
            ])
            ->addTextarea('timeframe', [
                'label' => 'Timeframe Text',
                // No default_value: ACF bakes it into the saved block JSON, where
                // WordPress's slash-stripping eats the backslash and leaves a bare
                // "n" ("Last 12nMonths"). Left empty, the field falls through to the
                // PHP default in fields() below, which never round-trips through JSON.
                'rows' => 2,
            ]);

        return $fields->build();
    }
}
