<?php

declare(strict_types=1);

use App\Domains\ContentAudit\Actions\ConvertElementorPostAction;
use App\Domains\ContentAudit\Services\ElementorAuditService;

describe('Elementor Audit & Conversion (ADR-0005 Amendment)', function () {
    test('ElementorAuditService recognizes clean posts with no Elementor dependency', function () {
        $service = new ElementorAuditService;

        $cleanResult = $service->auditElementorData('');
        expect($cleanResult['has_elementor_dependency'])->toBeFalse()
            ->and($cleanResult['safe_for_instant_cutover'])->toBeTrue();

        $emptyArrayResult = $service->auditElementorData([]);
        expect($emptyArrayResult['has_elementor_dependency'])->toBeFalse()
            ->and($emptyArrayResult['safe_for_instant_cutover'])->toBeTrue();
    });

    test('ElementorAuditService traverses AST and categorizes mapped and unmapped widgets', function () {
        $service = new ElementorAuditService;

        $elementorJson = json_encode([
            [
                'elType' => 'section',
                'elements' => [
                    [
                        'elType' => 'column',
                        'elements' => [
                            [
                                'elType' => 'widget',
                                'widgetType' => 'HeroWidget',
                                'settings' => ['headline' => 'Scale With Nearshore Talent'],
                            ],
                            [
                                'elType' => 'widget',
                                'widgetType' => 'HeadlessCalendlyMultistepWidget',
                                'settings' => ['calendar_id' => 'cal_123'],
                            ],
                            [
                                'elType' => 'widget',
                                'widgetType' => 'ObsoleteCustomLegacyWidget',
                                'settings' => ['custom_key' => 'legacy_val'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $audit = $service->auditElementorData($elementorJson);

        expect($audit['has_elementor_dependency'])->toBeTrue()
            ->and($audit['safe_for_instant_cutover'])->toBeFalse()
            ->and($audit['total_widgets'])->toBe(3)
            ->and($audit['mapped_widgets'])->toHaveKey('HeroWidget')
            ->and($audit['mapped_widgets']['HeroWidget']['target_block'])->toBe('acf/hero-block')
            ->and($audit['mapped_widgets'])->toHaveKey('HeadlessCalendlyMultistepWidget')
            ->and($audit['mapped_widgets']['HeadlessCalendlyMultistepWidget']['target_block'])->toBe('acf/booking-block')
            ->and($audit['unmapped_widgets'])->toHaveKey('ObsoleteCustomLegacyWidget')
            ->and($audit['all_mapped'])->toBeFalse();
    });

    test('ConvertElementorPostAction converts Elementor AST into Gutenberg block markup and queues for human review', function () {
        $auditService = new ElementorAuditService;
        $convertAction = new ConvertElementorPostAction($auditService);

        $elementorAst = [
            [
                'elType' => 'section',
                'elements' => [
                    [
                        'elType' => 'column',
                        'elements' => [
                            [
                                'elType' => 'widget',
                                'widgetType' => 'JoinLiveCallWidget',
                                'settings' => ['btn_label' => 'Join Live Call Now'],
                            ],
                            [
                                'elType' => 'widget',
                                'widgetType' => 'UnknownLegacyWidget',
                                'settings' => ['foo' => 'bar'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $convertAction->execute($elementorAst);

        expect($result['status'])->toBe('queued_for_human_editorial_review')
            ->and($result['gutenberg_content'])->toContain('<!-- wp:acf/live-call-block')
            ->and($result['gutenberg_content'])->toContain('Join Live Call Now')
            ->and($result['gutenberg_content'])->toContain('[ATTENTION REQUIRED: Unmapped Elementor Widget: UnknownLegacyWidget]');
    });
});
