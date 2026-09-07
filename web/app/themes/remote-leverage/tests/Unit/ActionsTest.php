<?php

declare(strict_types=1);

use App\Domains\ContentAudit\Actions\GenerateSignatureHtmlAction;
use App\Domains\Tracking\Actions\EvaluateVariantAction;
use App\Domains\Tracking\Gateways\PostHogClient;

describe('Domain Actions', function () {
    test('GenerateSignatureHtmlAction produces valid branded signature markup', function () {
        $action = new GenerateSignatureHtmlAction;

        $html = $action->execute([
            'name' => 'Adrian Salvatori',
            'title' => 'Managing Director',
            'email' => 'adrian@remoteleverage.com',
            'phone' => '+1 (305) 555-0199',
            'mobile' => '+1 (305) 555-0188',
            'website' => 'https://remoteleverage.com',
            'website_display' => 'remoteleverage.com',
        ]);

        expect($html)->toContain('Adrian Salvatori')
            ->and($html)->toContain('Managing Director')
            ->and($html)->toContain('adrian@remoteleverage.com')
            ->and($html)->toContain('+1 (305) 555-0199')
            ->and($html)->toContain('+1 (305) 555-0188')
            ->and($html)->toContain('#8A2BE2') // Brand Purple Dome
            ->and($html)->toContain('<table');
    });

    test('GenerateSignatureHtmlAction sanitizes against XSS injections', function () {
        $action = new GenerateSignatureHtmlAction;

        $html = $action->execute([
            'name' => 'John <script>alert("xss")</script> Doe',
            'title' => '<b>Lead Architect</b>',
            'email' => 'john@test.com',
        ]);

        expect($html)->not->toContain('<script>')
            ->and($html)->toContain('&lt;script&gt;')
            ->and($html)->not->toContain('<b>Lead Architect</b>')
            ->and($html)->toContain('&lt;b&gt;Lead Architect&lt;/b&gt;');
    });

    test('EvaluateVariantAction queries PostHog client with user distinct ID', function () {
        $mockClient = $this->createMock(PostHogClient::class);
        $mockClient->expects($this->once())
            ->method('isFeatureEnabled')
            ->with('new_booking_wizard_v2', 'user_abc_123', [])
            ->willReturn(true);

        $action = new EvaluateVariantAction($mockClient);
        $result = $action->execute('new_booking_wizard_v2', 'user_abc_123');

        expect($result)->toBeTrue();
    });

    test('EvaluateVariantAction falls back to cookie when distinct ID is null', function () {
        $_COOKIE['ph_distinct_id'] = 'cookie_distinct_456';

        $mockClient = $this->createMock(PostHogClient::class);
        $mockClient->expects($this->once())
            ->method('isFeatureEnabled')
            ->with('instant_live_call_banner', 'cookie_distinct_456', ['tier' => 'enterprise'])
            ->willReturn(false);

        $action = new EvaluateVariantAction($mockClient);
        $result = $action->execute('instant_live_call_banner', null, ['tier' => 'enterprise']);

        expect($result)->toBeFalse();

        unset($_COOKIE['ph_distinct_id']);
    });
});
