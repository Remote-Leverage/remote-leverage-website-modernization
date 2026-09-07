<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Actions;

use App\Domains\ContentAudit\Services\ElementorAuditService;

class ConvertElementorPostAction
{
    public function __construct(
        protected ElementorAuditService $auditService
    ) {}

    /**
     * Convert an Elementor data AST into native Gutenberg block markup.
     * Enforces the ADR-0005 Amendment human review gate.
     *
     * @return array{gutenberg_content: string, audit: array, status: string}
     */
    public function execute(string|array $elementorData): array
    {
        $audit = $this->auditService->auditElementorData($elementorData);
        $elements = is_array($elementorData) ? $elementorData : json_decode($elementorData, true);

        if (! $audit['has_elementor_dependency'] || ! is_array($elements)) {
            return [
                'gutenberg_content' => '',
                'audit' => $audit,
                'status' => 'clean_no_conversion_needed',
            ];
        }

        $blocksMarkup = [];
        $this->convertElements($elements, $blocksMarkup);

        $renderedContent = implode("\n\n", $blocksMarkup);

        return [
            'gutenberg_content' => $renderedContent,
            'audit' => $audit,
            'status' => 'queued_for_human_editorial_review',
        ];
    }

    protected function convertElements(array $elements, array &$blocksMarkup): void
    {
        foreach ($elements as $element) {
            $elType = $element['elType'] ?? null;
            $widgetType = $element['widgetType'] ?? null;
            $settings = $element['settings'] ?? [];

            if ($elType === 'widget' && $widgetType) {
                $targetBlock = ElementorAuditService::WIDGET_MAPPING[$widgetType] ?? null;

                if ($targetBlock) {
                    if (str_starts_with($targetBlock, 'acf/')) {
                        // Serialize settings into ACF Gutenberg block comment
                        $blockJson = json_encode([
                            'name' => $targetBlock,
                            'data' => $settings,
                            'mode' => 'preview',
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                        $blocksMarkup[] = "<!-- wp:{$targetBlock} {$blockJson} /-->";
                    } elseif ($targetBlock === 'core/heading') {
                        $title = htmlspecialchars($settings['title'] ?? '', ENT_QUOTES);
                        $level = $settings['header_size'] ?? 'h2';
                        $blocksMarkup[] = "<!-- wp:heading {\"level\":2} -->\n<{$level}>{$title}</{$level}>\n<!-- /wp:heading -->";
                    } elseif ($targetBlock === 'core/paragraph') {
                        $editor = $settings['editor'] ?? '';
                        $blocksMarkup[] = "<!-- wp:paragraph -->\n<p>{$editor}</p>\n<!-- /wp:paragraph -->";
                    }
                } else {
                    // Unmapped widget: fallback to HTML block with clear warning comment
                    $unmappedJson = htmlspecialchars(json_encode($settings), ENT_QUOTES);
                    $blocksMarkup[] = "<!-- wp:html -->\n<!-- [ATTENTION REQUIRED: Unmapped Elementor Widget: {$widgetType}] -->\n<div class=\"unmapped-elementor-widget\" data-widget=\"{$widgetType}\">{$unmappedJson}</div>\n<!-- /wp:html -->";
                }
            }

            if (! empty($element['elements']) && is_array($element['elements'])) {
                $this->convertElements($element['elements'], $blocksMarkup);
            }
        }
    }
}
