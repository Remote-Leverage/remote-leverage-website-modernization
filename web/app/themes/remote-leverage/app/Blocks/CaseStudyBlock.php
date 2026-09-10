<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Single reusable block for the full /case-study/ archive (22+ client case studies).
 *
 * Every case study on production shares one structural template -- eyebrow, client
 * name + logo + headline + company info + stat tiles, a client quote, a series of
 * narrative sections (which are sometimes rich text, sometimes a repeat of the stat
 * tiles, sometimes a small metric/outcome table), and an optional client video --
 * so rather than hand-authoring bespoke Gutenberg markup per client, this one block
 * exposes all of that as fields and is placed once per case-study page.
 */
class CaseStudyBlock extends Block
{
    public $name = 'Case Study';

    public $slug = 'case-study';

    public $description = 'Full client case study: hero, company info, stat tiles, quote, narrative sections, and optional video.';

    public $category = 'remote-leverage';

    public $icon = 'awards';

    public $keywords = ['case study', 'client story', 'testimonial', 'results'];

    public $view = 'blocks.case-study';

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
        $get = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            'clientName' => BlockDefaults::cleanText($get('client_name') ?: 'Client Name'),
            'headline' => BlockDefaults::cleanText($get('headline') ?: 'How this client hired faster with Remote Leverage'),
            'subheadline' => BlockDefaults::cleanText($get('subheadline') ?: ''),
            'logo' => BlockDefaults::resolveImageUrl($get('logo') ?: ''),
            'websiteUrl' => (string) ($get('website_url') ?: ''),
            'infoItems' => $this->infoItems(),
            'stats' => $this->stats(),
            'quoteText' => BlockDefaults::cleanText($get('quote_text') ?: ''),
            'quotePhoto' => BlockDefaults::resolveImageUrl($get('quote_photo') ?: ''),
            'quoteName' => BlockDefaults::cleanText($get('quote_name') ?: ''),
            'quoteCompany' => BlockDefaults::cleanText($get('quote_company') ?: ''),
            'sections' => $this->sections(),
            'video' => $this->video(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('case_study_block');

        $fields
            ->addText('client_name', ['label' => 'Client Name'])
            ->addText('headline', ['label' => 'Headline (Result Statement)'])
            ->addTextarea('subheadline', [
                'label' => 'Subheadline',
                'instructions' => 'Optional one-line summary shown under the headline instead of / alongside the company info row.',
                'rows' => 2,
            ])
            ->addImage('logo', ['label' => 'Client Logo', 'return_format' => 'url'])
            ->addUrl('website_url', ['label' => 'Client Website URL'])
            ->addRepeater('info_items', [
                'label' => 'Company Info Row (Headquarters / Industry / Website, etc.)',
                'layout' => 'table',
                'button_label' => 'Add Info Item',
            ])
            ->addText('label', ['label' => 'Label'])
            ->addText('value', ['label' => 'Value'])
            ->endRepeater()
            ->addRepeater('stats', [
                'label' => 'Hero Stat Tiles',
                'layout' => 'table',
                'button_label' => 'Add Stat',
            ])
            ->addText('value', ['label' => 'Value'])
            ->addText('label', ['label' => 'Label'])
            ->endRepeater()
            ->addTextarea('quote_text', ['label' => 'Client Quote', 'rows' => 3])
            ->addImage('quote_photo', ['label' => 'Quote Headshot', 'return_format' => 'url'])
            ->addText('quote_name', ['label' => 'Quote Attribution Name'])
            ->addText('quote_company', ['label' => 'Quote Attribution Title / Company'])
            ->addRepeater('sections', [
                'label' => 'Narrative Sections',
                'layout' => 'block',
                'button_label' => 'Add Section',
            ])
            ->addText('heading', ['label' => 'Section Heading (leave blank for an untitled closing paragraph)'])
            ->addSelect('type', [
                'label' => 'Section Type',
                'choices' => [
                    'richtext' => 'Rich Text',
                    'stats' => 'Stat Tiles',
                    'table' => 'Metric / Outcome Table',
                ],
                'default_value' => 'richtext',
            ])
            ->addWysiwyg('body', [
                'label' => 'Body (used when Section Type is Rich Text)',
                'media_upload' => 0,
                'toolbar' => 'basic',
            ])
            ->addTextarea('rows_raw', [
                'label' => 'Rows (used when Section Type is Stat Tiles or Table)',
                'instructions' => 'One row per line, formatted value|label (Stat Tiles) or metric|outcome (Table).',
                'rows' => 4,
            ])
            ->endRepeater()
            ->addText('video_name', ['label' => 'Video Attribution Name'])
            ->addText('video_caption', ['label' => 'Video Caption'])
            ->addText('video_vimeo_id', ['label' => 'Vimeo Video ID'])
            ->addImage('video_poster', ['label' => 'Video Poster Image', 'return_format' => 'url']);

        return $fields->build();
    }

    public function infoItems(): array
    {
        $items = function_exists('get_field') ? get_field('info_items') : null;
        $items = (! empty($items) && is_array($items))
            ? $items
            : [];

        return array_map(fn ($row) => [
            'label' => BlockDefaults::cleanText($row['label'] ?? ''),
            'value' => BlockDefaults::cleanText($row['value'] ?? ''),
        ], $items);
    }

    public function stats(): array
    {
        $items = function_exists('get_field') ? get_field('stats') : null;
        $items = (! empty($items) && is_array($items))
            ? $items
            : [];

        return array_map(fn ($row) => [
            'value' => BlockDefaults::cleanText($row['value'] ?? ''),
            'label' => BlockDefaults::cleanText($row['label'] ?? ''),
        ], $items);
    }

    public function sections(): array
    {
        $items = function_exists('get_field') ? get_field('sections') : null;
        $items = (! empty($items) && is_array($items))
            ? $items
            : [];

        return array_map(function ($row) {
            $type = $row['type'] ?? 'richtext';
            $section = [
                'heading' => BlockDefaults::cleanText($row['heading'] ?? ''),
                'type' => $type,
                'body' => '',
                'rows' => [],
            ];

            if ($type === 'richtext') {
                $section['body'] = $row['body'] ?? '';
            } else {
                $section['rows'] = $this->parseRows($row['rows_raw'] ?? '');
            }

            return $section;
        }, $items);
    }

    protected function parseRows(string $raw): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$value, $label] = array_pad(explode('|', $line, 2), 2, '');
            $rows[] = [
                'value' => BlockDefaults::cleanText(trim($value)),
                'label' => BlockDefaults::cleanText(trim($label)),
            ];
        }

        return $rows;
    }

    public function video(): ?array
    {
        $hasGetField = function_exists('get_field');
        $vimeoId = $hasGetField ? get_field('video_vimeo_id') : null;

        if (empty($vimeoId)) {
            return null;
        }

        return [
            'name' => BlockDefaults::cleanText(get_field('video_name') ?: ''),
            'caption' => BlockDefaults::cleanText(get_field('video_caption') ?: ''),
            'vimeoId' => (string) $vimeoId,
            'poster' => BlockDefaults::resolveImageUrl(get_field('video_poster') ?: ''),
        ];
    }
}
