<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ResultsPreviewBlock extends Block
{
    public $name = 'Results Preview Cards';

    public $slug = 'results-preview';

    public $description = 'Case-study preview cards with a background photo, tag pills, mini-stats, and a "Read the Full Story" link.';

    public $category = 'remote-leverage';

    public $icon = 'chart-line';

    public $keywords = ['case study', 'results', 'preview', 'cards'];

    public $view = 'blocks.results-preview';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'section_title' => 'Real Businesses, Real Results',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'sectionTitle' => BlockDefaults::cleanText(get_field('section_title') ?: 'Real Businesses, Real Results'),
            'sectionDesc' => BlockDefaults::cleanText(get_field('section_desc') ?: ''),
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('results_preview_block');

        $fields
            ->addText('section_title', [
                'label' => 'Section Title',
                'default_value' => 'Real Businesses, Real Results',
            ])
            ->addTextarea('section_desc', ['label' => 'Section Description', 'rows' => 2])
            ->addRepeater('cards', [
                'label' => 'Result Cards (leave empty for defaults)',
                'layout' => 'block',
                'button_label' => 'Add Card',
            ])
                ->addImage('image', ['label' => 'Background Photo', 'return_format' => 'url'])
                ->addText('tag_1', ['label' => 'Tag 1'])
                ->addText('tag_2', ['label' => 'Tag 2'])
                ->addText('tag_3', ['label' => 'Tag 3'])
                ->addText('title', ['label' => 'Client / Company Name'])
                ->addTextarea('desc', ['label' => 'Description', 'rows' => 3])
                ->addText('stat_1_value', ['label' => 'Stat 1 Value'])
                ->addText('stat_1_label', ['label' => 'Stat 1 Label'])
                ->addText('stat_2_value', ['label' => 'Stat 2 Value'])
                ->addText('stat_2_label', ['label' => 'Stat 2 Label'])
                ->addText('stat_3_value', ['label' => 'Stat 3 Value'])
                ->addText('stat_3_label', ['label' => 'Stat 3 Label'])
                ->addUrl('link_url', ['label' => 'Full Story Link'])
                ->addText('link_text', ['label' => 'Link Text', 'default_value' => 'Read the Full Story'])
            ->endRepeater();

        return $fields->build();
    }

    public function defaultCards(): array
    {
        return [
            [
                'tag_1' => 'Accounting', 'tag_2' => 'Large Team', 'tag_3' => 'Enterprise',
                'title' => 'Bench Accounting',
                'desc' => "Bench Accounting needed to scale its remote bookkeeping team fast – without lowering the bar on quality. They were so impressed with the quality of our talent, that after their first hires they kept coming back for more.",
                'stat_1_value' => '31', 'stat_1_label' => 'hires in 4 months',
                'stat_2_value' => '60%', 'stat_2_label' => 'less time screening',
                'stat_3_value' => '7–10 days', 'stat_3_label' => 'to replace a hire',
                'link_url' => '/case-study/bench-accounting/',
                'link_text' => 'Read the Full Story',
            ],
            [
                'tag_1' => 'Food & Beverage', 'tag_2' => 'Franchise', 'tag_3' => 'Mid-size',
                'title' => 'Chick-fil-A',
                'desc' => "This Chick-fil-A franchise grew into a second location, the ownership team needed experienced support staff. They wanted to hire 1 VA, but when presented with 6 candidates, they hired more.",
                'stat_1_value' => '70%', 'stat_1_label' => 'savings in cost',
                'stat_2_value' => '7 days', 'stat_2_label' => 'avg. time to hire',
                'stat_3_value' => '2', 'stat_3_label' => 'staff hired',
                'link_url' => '/case-study/chick-fil-a/',
                'link_text' => 'Read the Full Story',
            ],
        ];
    }

    public function cards(): array
    {
        $custom = get_field('cards');
        $cards = (! empty($custom) && is_array($custom)) ? $custom : $this->defaultCards();

        return array_map(function ($card) {
            $card['title'] = BlockDefaults::cleanText($card['title'] ?? '');
            $card['desc'] = BlockDefaults::cleanText($card['desc'] ?? '');
            $card['image'] = BlockDefaults::resolveImageUrl($card['image'] ?? '');
            $card['tags'] = array_values(array_filter([
                BlockDefaults::cleanText($card['tag_1'] ?? ''),
                BlockDefaults::cleanText($card['tag_2'] ?? ''),
                BlockDefaults::cleanText($card['tag_3'] ?? ''),
            ]));

            $card['stats'] = [];
            for ($i = 1; $i <= 3; $i++) {
                $value = BlockDefaults::cleanText($card["stat_{$i}_value"] ?? '');
                $label = BlockDefaults::cleanText($card["stat_{$i}_label"] ?? '');
                if ($value !== '' || $label !== '') {
                    $card['stats'][] = ['value' => $value, 'label' => $label];
                }
            }

            $card['link_text'] = BlockDefaults::cleanText($card['link_text'] ?? 'Read the Full Story');

            return $card;
        }, $cards);
    }
}
