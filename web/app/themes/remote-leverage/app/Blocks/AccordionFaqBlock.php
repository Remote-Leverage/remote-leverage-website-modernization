<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AccordionFaqBlock extends Block
{
    public $name = 'FAQ Accordion';

    public $slug = 'accordion-faq';

    public $description = 'Semantic accordion FAQ with automated Schema.org structured data.';

    public $category = 'remote-leverage';

    public $icon = 'editor-help';

    public $keywords = ['faq', 'questions', 'accordion', 'schema', 'seo'];

    public $view = 'blocks.accordion-faq';

    public function with(): array
    {
        $faqs = $this->faqs();

        return [
            'headline' => get_field('headline') ?: 'Frequently Asked Questions',
            'layout' => get_field('layout') ?: 'modern',
            'faqs' => $faqs,
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
            ->addSelect('layout', [
                'label' => 'Layout Style',
                'choices' => [
                    'modern' => 'Modern Card Style (Clean Spacing)',
                    'article' => 'Article Style (Bordered Accordion)',
                ],
                'default_value' => 'modern',
            ])
            ->addRepeater('faqs', [
                'label' => 'FAQ Items',
                'layout' => 'block',
                'button_label' => 'Add Question',
            ])
            ->addText('question', [
                'label' => 'Question',
                'default_value' => 'How quickly can a specialist start on our team?',
            ])
            ->addTextarea('answer', [
                'label' => 'Answer',
                'default_value' => 'Most clients interview and onboard their matched specialist within 5 to 7 business days from our initial discovery call.',
                'rows' => 3,
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function faqs(): array
    {
        $items = get_field('faqs');

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        // Fallback matching original Elementor ArticleFAQAccordionWidget
        return [
            [
                'question' => 'How much does hiring through Remote Leverage cost?',
                'answer' => 'Our specialists range between $8/hr and $15/hr depending on technical specialization (e.g. executive assistance vs. advanced transaction coordination or media buying). There are zero recruitment fees or placement retainers.',
            ],
            [
                'question' => 'How does the 6-month replacement guarantee work?',
                'answer' => 'If at any point during the first 6 months your specialist is not meeting your expectations, our recruitment director immediately assigns a replacement specialist to your account at zero placement fee.',
            ],
            [
                'question' => 'Are candidates fluent in English?',
                'answer' => 'Yes. Every candidate we present has undergone rigorous C1/C2 verbal and written assessments, including recorded video introductions and live English fluency checks with our US leadership.',
            ],
            [
                'question' => 'What timezones do your specialists work in?',
                'answer' => 'Our talent pool is 100% located across Latin America (predominantly Colombia, Mexico, and Argentina), enabling seamless alignment with Eastern, Central, Mountain, or Pacific business hours.',
            ],
        ];
    }

    protected function generateSchemaJson(array $faqs): string
    {
        $mainEntity = [];
        foreach ($faqs as $faq) {
            if (empty($faq['question']) || empty($faq['answer'])) {
                continue;
            }
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => strip_tags($faq['question']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq['answer']),
                ],
            ];
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
