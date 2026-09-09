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
            'col1Header' => ($hasGetField ? get_field('col_1_header') : null) ?: 'DIY',
            'col2Header' => ($hasGetField ? get_field('col_2_header') : null) ?: 'Remote Leverage',
            'rows' => $this->rows(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('data_table_block');

        $fields
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
            ->addText('diy', ['label' => 'DIY Value'])
            ->addText('rl', ['label' => 'Remote Leverage Value'])
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

            return $row;
        }, $rows);
    }
}
