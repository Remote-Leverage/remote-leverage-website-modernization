<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class DataTableBlock extends Block
{
    public $name = 'Comparison Data Table';

    public $slug = 'data-table';

    public $description = 'Floating card row comparison matrix of DIY hiring versus Remote Leverage.';

    public $category = 'remote-leverage';

    public $icon = 'editor-table';

    public $keywords = ['table', 'comparison', 'pricing', 'matrix', 'versus'];

    public $view = 'blocks.data-table';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'title' => 'Average Hourly Rates by Role & Experience',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');

        return [
            // Production labels the criterion column on some tables ("Stage" on the
            // screening table, "Term" on the replacement-policies one) and leaves it
            // blank on others. Unset renders the blank cell every existing usage has.
            'col0Header' => ($hasGetField ? get_field('col_0_header') : null) ?: '',
            'col1Header' => ($hasGetField ? get_field('col_1_header') : null) ?: 'DIY',
            'col2Header' => ($hasGetField ? get_field('col_2_header') : null) ?: 'Remote Leverage',
            // 'leverage-first' is the 2026 homepage arrangement; see the view for what it
            // changes and why the mobile half is a separate composition rather than a reflow.
            'variant' => ($hasGetField ? get_field('variant') : null) ?: 'diy',
            'rows' => $this->rows(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('data_table_block');

        $fields
            ->addSelect('variant', [
                'label' => 'Row Arrangement',
                'instructions' => 'DIY is production\'s comparison-page table: label, DIY with a red cross, Remote '
                    .'Leverage with a green tick. Leverage-first is the 2026 homepage: Remote Leverage second in the '
                    .'row with the tick, the competitor last with a slate cross, and a separate mobile treatment '
                    .'driven by the rows\' short values.',
                'choices' => [
                    'diy' => 'DIY first, Remote Leverage last (default)',
                    'leverage-first' => 'Remote Leverage first, competitor last',
                ],
                'default_value' => 'diy',
                'return_format' => 'value',
            ])
            ->addText('col_0_header', [
                'label' => 'Criterion Column Header',
                'instructions' => 'Usually blank. Production labels it on the screening ("Stage") and replacement-policy ("Term") tables.',
                'default_value' => '',
            ])
            ->addText('col_1_header', [
                'label' => 'Column 1 Header',
                'default_value' => 'DIY',
            ])
            ->addText('col_2_header', [
                'label' => 'Column 2 Header',
                'default_value' => 'Remote Leverage',
            ])
            ->addRepeater('rows', [
                'label' => 'Comparison Rows (Leave empty for default 7 rows)',
                'layout' => 'table',
                'button_label' => 'Add Comparison Row',
            ])
            ->addText('feature', ['label' => 'Feature / Criterion'])
            ->addText('diy', ['label' => 'DIY / Competitor Value'])
            ->addText('rl', ['label' => 'Remote Leverage Value'])
            ->addText('diy_short', [
                'label' => 'Competitor Value (mobile)',
                'instructions' => 'Leverage-first only. The mobile comp drops the row label, so each value has to '
                    .'stand on its own in half a phone width. Falls back to the full value when empty.',
            ])
            ->addText('rl_short', [
                'label' => 'Remote Leverage Value (mobile)',
                'instructions' => 'Leverage-first only. Falls back to the full value when empty.',
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function rows(): array
    {
        $items = function_exists('get_field') ? get_field('rows') : null;
        $rows = (! empty($items) && is_array($items))
            ? $items
            : BlockDefaults::dataTableRows();

        return array_map(function ($row) {
            $row['feature'] = BlockDefaults::cleanText($row['feature'] ?? '');
            $row['diy'] = BlockDefaults::cleanText($row['diy'] ?? '');
            $row['rl'] = BlockDefaults::cleanText($row['rl'] ?? '');
            $row['diy_short'] = BlockDefaults::cleanText($row['diy_short'] ?? '') ?: $row['diy'];
            $row['rl_short'] = BlockDefaults::cleanText($row['rl_short'] ?? '') ?: $row['rl'];

            return $row;
        }, $rows);
    }
}
