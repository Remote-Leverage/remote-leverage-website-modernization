<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class DataTableBlock extends Block
{
    public $name = 'Comparison Data Table';

    public $slug = 'data-table';

    public $description = 'Mobile-responsive cost and capability comparison matrix.';

    public $category = 'remote-leverage';

    public $icon = 'editor-table';

    public $keywords = ['table', 'comparison', 'pricing', 'matrix', 'versus'];

    public $view = 'blocks.data-table';

    public function with(): array
    {
        return [
            'tableTitle' => get_field('table_title') ?: 'How Remote Leverage Compares to Staffing Alternatives',
            'col1Header' => get_field('col_1_header') ?: 'Key Criteria',
            'col2Header' => get_field('col_2_header') ?: 'Remote Leverage (LatAm)',
            'col3Header' => get_field('col_3_header') ?: 'Domestic US Hire',
            'col4Header' => get_field('col_4_header') ?: 'Traditional Offshore BPO',
            'rows' => $this->rows(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('data_table_block');

        $fields
            ->addText('table_title', [
                'label' => 'Table Title',
                'default_value' => 'How Remote Leverage Compares to Staffing Alternatives',
            ])
            ->addText('col_1_header', [
                'label' => 'Column 1 Header (Criteria)',
                'default_value' => 'Key Criteria',
            ])
            ->addText('col_2_header', [
                'label' => 'Column 2 Header (Primary Solution)',
                'default_value' => 'Remote Leverage (LatAm)',
            ])
            ->addText('col_3_header', [
                'label' => 'Column 3 Header (Alternative 1)',
                'default_value' => 'Domestic US Hire',
            ])
            ->addText('col_4_header', [
                'label' => 'Column 4 Header (Alternative 2)',
                'default_value' => 'Traditional Offshore BPO',
            ])
            ->addRepeater('rows', [
                'label' => 'Comparison Rows',
                'layout' => 'table',
                'button_label' => 'Add Comparison Row',
            ])
            ->addText('feature_name', [
                'label' => 'Feature / Metric',
                'default_value' => 'All-in Hourly Cost',
            ])
            ->addText('col_2_val', [
                'label' => 'Remote Leverage Value',
                'default_value' => '$8 – $15 / hr',
            ])
            ->addText('col_3_val', [
                'label' => 'US Hire Value',
                'default_value' => '$35 – $65 / hr',
            ])
            ->addText('col_4_val', [
                'label' => 'Offshore Value',
                'default_value' => '$6 – $12 / hr',
            ])
            ->addTrueFalse('highlight', [
                'label' => 'Highlight Row',
                'default_value' => false,
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function rows(): array
    {
        $items = get_field('rows');

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        // Fallback matching original Elementor ArticleDataTableWidget
        return [
            [
                'feature_name' => 'All-in Hourly Cost',
                'col_2_val' => '$8 – $15 / hr',
                'col_3_val' => '$35 – $65 / hr + benefits',
                'col_4_val' => '$6 – $12 / hr',
                'highlight' => true,
            ],
            [
                'feature_name' => 'Timezone Alignment',
                'col_2_val' => '100% US Working Hours (EST/CST/PST)',
                'col_3_val' => 'US Hours',
                'col_4_val' => 'Graveyard shift (12-14 hr lag)',
                'highlight' => false,
            ],
            [
                'feature_name' => 'English Fluency & Accent',
                'col_2_val' => 'C1 / C2 Executive Fluency',
                'col_3_val' => 'Native',
                'col_4_val' => 'Variable / Strong Accent',
                'highlight' => false,
            ],
            [
                'feature_name' => 'Hiring & Placement Fees',
                'col_2_val' => '$0 Upfront Placement Retainers',
                'col_3_val' => '$5k – $15k Recruiter Fee',
                'col_4_val' => 'Setup & Agent Surcharges',
                'highlight' => false,
            ],
            [
                'feature_name' => 'Replacement Guarantee',
                'col_2_val' => '6-Month Free Replacement',
                'col_3_val' => 'None (Standard At-Will)',
                'col_4_val' => '30 Days Max',
                'highlight' => true,
            ],
        ];
    }
}
