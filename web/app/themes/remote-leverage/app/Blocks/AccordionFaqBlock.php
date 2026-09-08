<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AccordionFaqBlock extends Block
{
    public $name = 'FAQ Accordion';

    public $slug = 'accordion-faq';

    public $description = 'Semantic 2-column accordion FAQ with automated Schema.org structured data.';

    public $category = 'remote-leverage';

    public $icon = 'editor-help';

    public $keywords = ['faq', 'questions', 'accordion', 'schema', 'seo'];

    public $view = 'blocks.accordion-faq';

    public function with(): array
    {
        $faqs = $this->faqs();
        $total = count($faqs);
        $half = (int) ceil($total / 2);

        return [
            'headline' => (function_exists('get_field') ? get_field('headline') : null) ?: 'Frequently Asked Questions',
            'faqsLeft' => array_slice($faqs, 0, $half, true),
            'faqsRight' => array_slice($faqs, $half, null, true),
            'schemaJson' => $this->generateSchemaJson($faqs),
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

    public function faqs(): array
    {
        $items = function_exists('get_field') ? get_field('faqs') : null;

        if (! empty($items) && is_array($items)) {
            return array_map(function ($item) {
                return [
                    'q' => $item['question'] ?? ($item['q'] ?? ''),
                    'a' => $item['answer'] ?? ($item['a'] ?? ''),
                ];
            }, $items);
        }

        return \App\Support\BlockDefaults::faqs();
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
