<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AboutStatsBlock extends Block
{
    public $name = 'About Stats Columns';

    public $slug = 'about-stats';

    public $description = 'Three-column stats showcase: a headline metric per column plus a category label and supporting sub-stats.';

    public $category = 'remote-leverage';

    public $icon = 'chart-area';

    public $keywords = ['stats', 'about', 'reach', 'quality', 'guarantee'];

    public $view = 'blocks.about-stats';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'section_title' => 'Why businesses choose us',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'sectionTitle' => BlockDefaults::cleanText(get_field('section_title') ?: 'Why businesses choose us'),
            'columns' => $this->columns(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('about_stats_block');

        $fields
            ->addText('section_title', [
                'label' => 'Section Title',
                'default_value' => 'Why businesses choose us',
            ])
            ->addRepeater('columns', [
                'label' => 'Stat Columns (leave empty for defaults)',
                'layout' => 'block',
                'button_label' => 'Add Column',
            ])
                ->addImage('icon', ['label' => 'Icon', 'return_format' => 'url'])
                ->addText('headline_value', ['label' => 'Headline Value (e.g. 70%)'])
                ->addText('headline_label', ['label' => 'Headline Label'])
                ->addText('category_label', ['label' => 'Category Label (e.g. REACH & SCALE)'])
                ->addText('stat_1_value', ['label' => 'Stat 1 Value'])
                ->addText('stat_1_label', ['label' => 'Stat 1 Label'])
                ->addText('stat_2_value', ['label' => 'Stat 2 Value'])
                ->addText('stat_2_label', ['label' => 'Stat 2 Label'])
                ->addText('stat_3_value', ['label' => 'Stat 3 Value'])
                ->addText('stat_3_label', ['label' => 'Stat 3 Label'])
                ->addText('stat_4_value', ['label' => 'Stat 4 Value'])
                ->addText('stat_4_label', ['label' => 'Stat 4 Label'])
            ->endRepeater();

        return $fields->build();
    }

    public function defaultColumns(): array
    {
        return [
            [
                'headline_value' => '70%',
                'headline_label' => 'Average cost savings',
                'category_label' => 'REACH & SCALE',
                'stat_1_value' => '2,000+', 'stat_1_label' => 'Businesses Served',
                'stat_2_value' => '2,500+', 'stat_2_label' => 'Professionals placed',
                'stat_3_value' => '61+', 'stat_3_label' => 'Countries represented',
                'stat_4_value' => '6', 'stat_4_label' => 'Continents represented',
            ],
            [
                'headline_value' => '48 hrs',
                'headline_label' => 'From consult to interviews',
                'category_label' => 'QUALITY',
                'stat_1_value' => '2,000+', 'stat_1_label' => 'Resumes received daily',
                'stat_2_value' => '4-6', 'stat_2_label' => 'Vetted finalists per role',
                'stat_3_value' => '', 'stat_3_label' => '',
                'stat_4_value' => '', 'stat_4_label' => '',
            ],
            [
                'headline_value' => 'Top 1%',
                'headline_label' => 'Candidate selectivity',
                'category_label' => 'GUARANTEE',
                'stat_1_value' => '12 month', 'stat_1_label' => 'Replacement guarantee',
                'stat_2_value' => '50+', 'stat_2_label' => 'Specialized roles',
                'stat_3_value' => '', 'stat_3_label' => '',
                'stat_4_value' => '', 'stat_4_label' => '',
            ],
        ];
    }

    public function columns(): array
    {
        $custom = get_field('columns');
        $columns = (! empty($custom) && is_array($custom)) ? $custom : $this->defaultColumns();

        return array_map(function ($col) {
            $col['headline_value'] = BlockDefaults::cleanText($col['headline_value'] ?? '');
            $col['headline_label'] = BlockDefaults::cleanText($col['headline_label'] ?? '');
            $col['category_label'] = BlockDefaults::cleanText($col['category_label'] ?? '');
            $col['icon'] = BlockDefaults::resolveImageUrl($col['icon'] ?? '');

            $col['stats'] = [];
            for ($i = 1; $i <= 4; $i++) {
                $value = BlockDefaults::cleanText($col["stat_{$i}_value"] ?? '');
                $label = BlockDefaults::cleanText($col["stat_{$i}_label"] ?? '');
                if ($value !== '' || $label !== '') {
                    $col['stats'][] = ['value' => $value, 'label' => $label];
                }
            }

            return $col;
        }, $columns);
    }
}
