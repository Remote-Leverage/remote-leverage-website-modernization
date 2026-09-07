<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Services;

class ElementorAuditService
{
    /**
     * Map of legacy Elementor widgets to modern native Gutenberg ACF blocks per ADR-0005.
     */
    public const WIDGET_MAPPING = [
        'HeroWidget' => 'acf/hero-block',
        'HeroSectionWidget' => 'acf/hero-block',
        'HeroCarouselWidget' => 'acf/hero-block',
        'HeadlessCalendlyMultistepWidget' => 'acf/booking-block',
        'GoogleCalendarMultistepWidget' => 'acf/booking-block',
        'IsolatedFieldsHeadlessCalendlyMultistepWidget' => 'acf/booking-block',
        'JoinLiveCallWidget' => 'acf/live-call-block',
        'TestimonialCardWidget' => 'acf/testimonials-block',
        'TestimonialListWidget' => 'acf/testimonials-block',
        'TrustSectionWidget' => 'acf/testimonials-block',
        'DepartmentCardWidget' => 'acf/department-cards-block',
        'ContractorCardWidget' => 'acf/department-cards-block',
        'GlassCardWidget' => 'acf/department-cards-block',
        'ProcessStepsWidget' => 'acf/process-steps-block',
        'HiringProcessWidget' => 'acf/process-steps-block',
        'BenefitsSectionWidget' => 'acf/benefits-guarantee-block',
        'GuaranteeSectionWidget' => 'acf/benefits-guarantee-block',
        'ArticleFAQAccordionWidget' => 'acf/accordion-faq-block',
        'ArticleDataTableWidget' => 'acf/data-table-block',
        'ContactCTAWidget' => 'acf/cta-banner-block',
        'ArticleLeadFormWidget' => 'acf/cta-banner-block',
        // Common standard elements
        'heading' => 'core/heading',
        'text-editor' => 'core/paragraph',
        'image' => 'core/image',
        'button' => 'core/button',
    ];

    /**
     * Audit a single post's Elementor data JSON string or array.
     */
    public function auditElementorData(string|array $elementorData): array
    {
        $elements = is_array($elementorData) ? $elementorData : json_decode($elementorData, true);

        if (! is_array($elements) || empty($elements)) {
            return [
                'has_elementor_dependency' => false,
                'total_elements' => 0,
                'widgets_found' => [],
                'mapped_widgets' => [],
                'unmapped_widgets' => [],
                'safe_for_instant_cutover' => true,
            ];
        }

        $widgetsFound = [];
        $this->traverseElements($elements, $widgetsFound);

        $mapped = [];
        $unmapped = [];

        foreach ($widgetsFound as $widgetType => $count) {
            if (isset(self::WIDGET_MAPPING[$widgetType])) {
                $mapped[$widgetType] = [
                    'count' => $count,
                    'target_block' => self::WIDGET_MAPPING[$widgetType],
                ];
            } else {
                $unmapped[$widgetType] = [
                    'count' => $count,
                    'suggested_fallback' => 'core/html',
                ];
            }
        }

        return [
            'has_elementor_dependency' => true,
            'total_widgets' => array_sum($widgetsFound),
            'widgets_found' => $widgetsFound,
            'mapped_widgets' => $mapped,
            'unmapped_widgets' => $unmapped,
            'all_mapped' => empty($unmapped),
            'safe_for_instant_cutover' => false,
        ];
    }

    /**
     * Recursively walk the Elementor JSON AST to extract widgetTypes.
     */
    protected function traverseElements(array $elements, array &$widgetsFound): void
    {
        foreach ($elements as $element) {
            $elType = $element['elType'] ?? null;
            $widgetType = $element['widgetType'] ?? null;

            if ($elType === 'widget' && $widgetType) {
                $widgetsFound[$widgetType] = ($widgetsFound[$widgetType] ?? 0) + 1;
            }

            if (! empty($element['elements']) && is_array($element['elements'])) {
                $this->traverseElements($element['elements'], $widgetsFound);
            }
        }
    }
}
