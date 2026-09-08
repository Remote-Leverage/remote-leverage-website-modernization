<?php

declare(strict_types=1);

namespace App\Blocks;

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

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        return [
            ['feature' => 'Time to Hire', 'diy' => '4 - 8 weeks', 'rl' => '72 hrs'],
            ['feature' => 'Vetting Quality', 'diy' => 'Hit or miss', 'rl' => 'Top 1% pre-screened'],
            ['feature' => 'Payroll & taxes', 'diy' => 'DIY or expensive local lawyer', 'rl' => 'Fully managed'],
            ['feature' => 'Compliance risk', 'diy' => 'High - misclassification, local laws', 'rl' => 'Zero - 170+ countries covered'],
            ['feature' => 'Ongoing fees', 'diy' => 'Often 30-50% monthly markup', 'rl' => 'One-time flat fee only'],
            ['feature' => 'Replacement guarantee', 'diy' => 'None', 'rl' => '12-months, no extra costs'],
            ['feature' => 'Centralized reporting', 'diy' => 'Spreadsheets', 'rl' => 'Dashboard to manage your team'],
        ];
    }
}
