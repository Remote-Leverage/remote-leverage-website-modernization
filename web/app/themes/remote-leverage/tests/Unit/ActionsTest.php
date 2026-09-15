<?php

declare(strict_types=1);

use App\Domains\Tracking\Actions\EvaluateVariantAction;
use App\Domains\Tracking\Gateways\PostHogClient;

describe('Domain Actions', function () {

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
