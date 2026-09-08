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
                    'q' => $item['question'],
                    'a' => $item['answer'],
                ];
            }, $items);
        }

        return [
            [
                'q' => 'What countries do you hire from?',
                'a' => '<p>We focus on four key regions:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Latin America and The Caribbean</li><li>The Philippines</li><li>South Africa</li><li>Egypt</li></ul><p class="mt-3">Our Latin American Virtual Assistants are especially popular with US businesses, thanks to their exceptional English fluency, strong cultural alignment, and convenient time zone overlap with North America.</p>',
            ],
            [
                'q' => 'How do taxes & payroll work when hiring Virtual Assistants?',
                'a' => '<p>Your VA is an independent contractor, so there\'s no payroll involved. If you\'d rather not manage contractor agreements, documentation, and international payments yourself, our Contractor of Record (COR) add-on puts Remote Leverage in the contracting seat – we handle onboarding, verified time tracking, and cross-border payment, and you get a single invoice.</p>',
            ],
            [
                'q' => 'How do you get paid?',
                'a' => '<p>It\'s simple – we charge a one-time flat fee, but only after you\'ve found your perfect match.</p><p class="mt-2">Whatever hourly pay you decide to pay goes directly to the Virtual Assistant you hire.</p>',
            ],
            [
                'q' => 'What\'s the difference between Staffing and Recruiting Agencies?',
                'a' => '<p>Staffing agencies charge monthly fees but only pay a small portion to Virtual Assistants. At Remote Leverage, we charge just one flat fee after you hire. Your Virtual Assistant receives 100% of what you pay them directly.</p>',
            ],
            [
                'q' => 'What if I have questions and need help after hiring?',
                'a' => '<p>After hiring your Virtual Assistant, you\'ll have access to a dedicated Customer Success Manager who will help ensure your success with reviewing performance, monitoring progress, training guidance, and any other requests.</p>',
            ],
            [
                'q' => 'What if they don\'t turn out to be a good fit?',
                'a' => '<p>We offer a 12-month replacement guarantee at no extra cost and unlimited candidate interviews to ensure you find the best match.</p>',
            ],
            [
                'q' => 'How is their English and Communication skills?',
                'a' => '<p>We maintain extremely high standards for English fluency. All candidates must submit an English voice recording, and we only select those with fluent English and minimal accents.</p>',
            ],
            [
                'q' => 'Can I start with Part-time?',
                'a' => '<p>Yes, you can start with either part-time or full-time. The minimum is 20 hours per week, as our most qualified Virtual Assistants prefer stable positions with consistent hours.</p>',
            ],
            [
                'q' => 'What time zone will they be working in?',
                'a' => '<p>Your Virtual Assistant will work according to your schedule and time zone. They\'re accustomed to US hours, and you get to set the working hours that best fit your needs.</p>',
            ],
            [
                'q' => 'How much does the average Virtual Assistant cost?',
                'a' => '<p>Virtual Assistant\'s hourly rates depend on skills, experience and region:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li><strong>Entry Level:</strong> $6-$10 per hour</li><li><strong>Highly Experienced:</strong> $11-$15 per hour</li></ul><p class="mt-3">The hourly rate you agree to pay goes directly to your Virtual Assistant.</p>',
            ],
        ];
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
