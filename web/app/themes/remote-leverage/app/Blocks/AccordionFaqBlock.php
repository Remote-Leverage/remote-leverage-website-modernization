<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AccordionFaqBlock extends Block
{
    public $name = 'FAQ Accordion';

    public $slug = 'accordion-faq';

    public $description = 'Semantic accordion FAQ in one or two columns, with optional Schema.org FAQPage structured data.';

    public $category = 'remote-leverage';

    public $icon = 'editor-help';

    public $keywords = ['faq', 'questions', 'accordion', 'schema', 'seo'];

    public $view = 'blocks.accordion-faq';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Frequently Asked Questions',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $faqs = $this->faqs();
        $columns = $this->columns();

        // One column keeps every row in reading order; two columns balance them, which is the
        // block's original and still-default behaviour.
        $split = $columns === '1' ? count($faqs) : (int) ceil(count($faqs) / 2);

        return [
            'headline' => $this->headline(),
            'columns' => $columns,
            'style' => (function_exists('get_field') ? get_field('style') : null) ?: 'rules',
            'headingAlign' => (function_exists('get_field') ? get_field('heading_align') : null) ?: 'left',
            'faqsLeft' => array_slice($faqs, 0, $split, true),
            'faqsRight' => array_slice($faqs, $split, null, true),
            'schemaJson' => $this->schemaEnabled() ? $this->generateSchemaJson($faqs) : '',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('accordion_faq_block');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'Frequently Asked Questions',
            ])
            ->addSelect('style', [
                'label' => 'Row Treatment',
                'instructions' => 'Rules is production: rows divided by a hairline. Cards is the 2026 homepage: each '
                    .'row a white rounded card on the page ground.',
                'choices' => ['rules' => 'Hairline rules (default)', 'cards' => 'White cards (2026 homepage)'],
                'default_value' => 'rules',
                'return_format' => 'value',
            ])
            ->addSelect('heading_align', [
                'label' => 'Heading Alignment',
                'choices' => ['left' => 'Left (default)', 'center' => 'Centred'],
                'default_value' => 'left',
                'return_format' => 'value',
            ])
            ->addSelect('columns', [
                'label' => 'Columns',
                'instructions' => 'Two balanced columns is the default. Use one column where the rows are worked through in order rather than scanned.',
                'choices' => ['2' => 'Two columns (balanced)', '1' => 'One column'],
                'default_value' => '2',
                'allow_null' => 0,
                'multiple' => 0,
                'ui' => 0,
                'return_format' => 'value',
            ])
            ->addTrueFalse('schema', [
                'label' => 'Emit Schema.org FAQPage data',
                'instructions' => 'On by default. Turn it off on internal or noindex pages, where customer-facing FAQ markup does not belong.',
                'default_value' => 1,
                'ui' => 1,
            ])
            ->addRepeater('faqs', [
                'label' => 'FAQ Items (Leave empty for default 10 questions)',
                'layout' => 'block',
                'button_label' => 'Add Question',
            ])
            ->addText('question', ['label' => 'Question'])
            ->addTextarea('answer', ['label' => 'Answer (Supports basic HTML)', 'rows' => 3])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * An explicitly empty headline means "render no heading" — used where the surrounding
     * pattern already supplies its own. Only an absent field falls back to the default.
     */
    public function headline(): string
    {
        $headline = function_exists('get_field') ? get_field('headline') : null;

        return $headline === null ? 'Frequently Asked Questions' : (string) $headline;
    }

    /**
     * '1' or '2'. Anything else — including a block saved before this field existed —
     * is the original two-column layout.
     */
    public function columns(): string
    {
        $columns = function_exists('get_field') ? get_field('columns') : null;

        return (string) $columns === '1' ? '1' : '2';
    }

    /**
     * Whether to emit Schema.org FAQPage structured data. A block saved before this field
     * existed reports null and keeps emitting, which is what it does today.
     */
    public function schemaEnabled(): bool
    {
        if (! function_exists('get_field')) {
            return true;
        }

        $schema = get_field('schema');

        return $schema === null || $schema === '' ? true : (bool) $schema;
    }

    public function faqs(): array
    {
        $items = function_exists('get_field') ? get_field('faqs') : null;

        if (! empty($items) && is_array($items)) {
            return array_map(function ($item) {
                return [
                    'q' => BlockDefaults::cleanText($item['question'] ?? ($item['q'] ?? '')),
                    'a' => BlockDefaults::cleanText($item['answer'] ?? ($item['a'] ?? '')),
                ];
            }, $items);
        }

        return BlockDefaults::faqs();
    }

    private function generateSchemaJson(array $faqs): string
    {
        $entities = [];

        foreach ($faqs as $faq) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq['a']),
                ],
            ];
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
