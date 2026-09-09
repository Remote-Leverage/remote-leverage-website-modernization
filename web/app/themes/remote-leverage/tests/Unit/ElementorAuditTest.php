<?php

declare(strict_types=1);

use App\Domains\ContentAudit\Actions\ApplyElementorConversionAction;
use App\Domains\ContentAudit\Actions\ConvertElementorPostAction;
use App\Domains\ContentAudit\Services\ElementorAuditService;

describe('Elementor Audit & Conversion (ADR-0005 Amendment)', function () {
    beforeEach(function () {
        $GLOBALS['_wp_mock_post_meta'] = [];
        $GLOBALS['_wp_mock_posts'] = [];
    });

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

    test('ApplyElementorConversionAction persists converted content and queues _rl_conversion_status = needs_review', function () {
        $postId = 501;
        update_post_meta($postId, '_elementor_data', json_encode([
            [
                'elType' => 'widget',
                'widgetType' => 'JoinLiveCallWidget',
                'settings' => ['btn_label' => 'Join Now'],
            ],
            [
                'elType' => 'widget',
                'widgetType' => 'UnknownLegacyWidget',
                'settings' => ['foo' => 'bar'],
            ],
        ]));

        $action = new ApplyElementorConversionAction(new ConvertElementorPostAction(new ElementorAuditService));
        $result = $action->execute($postId);

        expect($result['status'])->toBe('needs_review')
            ->and($result['unmapped_widgets'])->toBe(['UnknownLegacyWidget'])
            ->and($result['dry_run'])->toBeFalse();

        expect($GLOBALS['_wp_mock_posts'][$postId]['post_content'])->toContain('<!-- wp:acf/live-call-block');
        expect(get_post_meta($postId, '_rl_conversion_status', true))->toBe('needs_review');
        expect(get_post_meta($postId, '_rl_conversion_unmapped_widgets', true))->toBe(['UnknownLegacyWidget']);
    });

    test('ApplyElementorConversionAction dry-run mode does not write post content or meta', function () {
        $postId = 502;
        update_post_meta($postId, '_elementor_data', json_encode([
            ['elType' => 'widget', 'widgetType' => 'HeroWidget', 'settings' => []],
        ]));

        $action = new ApplyElementorConversionAction(new ConvertElementorPostAction(new ElementorAuditService));
        $result = $action->execute($postId, dryRun: true);

        expect($result['status'])->toBe('needs_review')
            ->and($result['dry_run'])->toBeTrue();

        expect($GLOBALS['_wp_mock_posts'][$postId] ?? null)->toBeNull();
        expect(get_post_meta($postId, '_rl_conversion_status', true))->toBe('');
    });

    test('ApplyElementorConversionAction skips posts with no _elementor_data', function () {
        $action = new ApplyElementorConversionAction(new ConvertElementorPostAction(new ElementorAuditService));
        $result = $action->execute(999);

        expect($result['status'])->toBe('skipped_no_elementor_data');
    });

    test('ApplyElementorConversionAction::approve archives _elementor_data and marks the post approved', function () {
        $postId = 503;
        update_post_meta($postId, '_elementor_data', '{"foo":"bar"}');
        update_post_meta($postId, '_rl_conversion_status', 'needs_review');

        $action = new ApplyElementorConversionAction(new ConvertElementorPostAction(new ElementorAuditService));
        $action->approve($postId);

        expect(get_post_meta($postId, '_rl_conversion_status', true))->toBe('approved')
            ->and(get_post_meta($postId, '_elementor_data_archived', true))->toBe('{"foo":"bar"}')
            ->and(get_post_meta($postId, '_elementor_data', true))->toBe('');
    });
});
