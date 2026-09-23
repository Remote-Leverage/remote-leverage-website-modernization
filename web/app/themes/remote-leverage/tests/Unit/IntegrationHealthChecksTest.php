<?php

declare(strict_types=1);

use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Payment\Services\StripePaymentIntentGateway;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Tracking\Gateways\MetaConversionsApiClient;
use App\Infrastructure\Observability\Health\Checks\CalendlyHealthCheck;
use App\Infrastructure\Observability\Health\Checks\CustomerIoHealthCheck;
use App\Infrastructure\Observability\Health\Checks\HubSpotHealthCheck;
use App\Infrastructure\Observability\Health\Checks\MetaHealthCheck;
use App\Infrastructure\Observability\Health\Checks\PostHogHealthCheck;
use App\Infrastructure\Observability\Health\Checks\SlackHealthCheck;
use App\Infrastructure\Observability\Health\Checks\StripeHealthCheck;
use App\Infrastructure\Observability\Health\Checks\ZeroBounceHealthCheck;

/*
 * The rest of the checks in Health/Checks/ are thin: each `isConfigured()` just asks whatever
 * gateway or credential source already exists for that integration. These tests exist so that
 * "is Slack configured" cannot quietly drift from what SlackCredentials itself would say — each
 * one is a one-line delegation, and a one-line delegation is exactly the kind of thing that
 * silently breaks when the class behind it is refactored.
 */

beforeEach(function () {
    update_option(LeadSettingsService::OPTION_KEY, []);

    config([
        'services.slack.bot_token' => '',
        'services.stripe.test_mode' => false,
        'services.stripe.test_secret_key' => '',
        'services.stripe.live_secret_key' => '',
        'services.posthog.api_key' => '',
        'services.customer_io.site_id' => '',
        'services.customer_io.api_key' => '',
        'services.zerobounce.api_key' => '',
        'services.meta_capi.access_token' => '',
        'services.meta_capi.enabled' => true,
        'pixels.meta.pixel_ids' => [],
    ]);
});

describe('CalendlyHealthCheck', function () {
    test('configured when the token pool has an eligible token', function () {
        $check = new CalendlyHealthCheck(new CalendlyTokenPool([
            ['label' => 'Primary', 'token' => 'tok', 'enabled' => true],
        ]));

        expect($check->integration())->toBe('calendly')
            ->and($check->isConfigured())->toBeTrue();
    });

    test('not configured when the pool is empty', function () {
        expect((new CalendlyHealthCheck(new CalendlyTokenPool([])))->isConfigured())->toBeFalse();
    });

    test('not configured when every token is disabled', function () {
        $check = new CalendlyHealthCheck(new CalendlyTokenPool([
            ['label' => 'Primary', 'token' => 'tok', 'enabled' => false],
        ]));

        expect($check->isConfigured())->toBeFalse();
    });
});

describe('HubSpotHealthCheck', function () {
    test('configured when the gateway has an access token', function () {
        update_option(LeadSettingsService::OPTION_KEY, ['hubspot_access_token' => 'pat-test-token']);

        expect((new HubSpotHealthCheck(new HubSpotGateway))->isConfigured())->toBeTrue();
    });

    test('not configured with no access token anywhere', function () {
        expect((new HubSpotHealthCheck(new HubSpotGateway))->isConfigured())->toBeFalse();
    });
});

describe('SlackHealthCheck', function () {
    test('configured from the env-first bot token', function () {
        config(['services.slack.bot_token' => 'xoxb-test']);

        expect((new SlackHealthCheck)->isConfigured())->toBeTrue();
    });

    test('configured from the admin-setting fallback when env is blank', function () {
        update_option(LeadSettingsService::OPTION_KEY, ['slack_bot_token' => 'xoxb-admin-set']);

        expect((new SlackHealthCheck)->isConfigured())->toBeTrue();
    });

    test('not configured with no token anywhere', function () {
        expect((new SlackHealthCheck)->isConfigured())->toBeFalse();
    });
});

describe('StripeHealthCheck', function () {
    test('configured from the live secret key outside test mode', function () {
        config(['services.stripe.live_secret_key' => 'sk_live_x']);

        expect((new StripeHealthCheck(new StripePaymentIntentGateway))->isConfigured())->toBeTrue();
    });

    test('the live key does not count while test mode is on', function () {
        config([
            'services.stripe.test_mode' => true,
            'services.stripe.live_secret_key' => 'sk_live_x',
        ]);

        expect((new StripeHealthCheck(new StripePaymentIntentGateway))->isConfigured())->toBeFalse();
    });

    test('configured from the test secret key in test mode', function () {
        config([
            'services.stripe.test_mode' => true,
            'services.stripe.test_secret_key' => 'sk_test_x',
        ]);

        expect((new StripeHealthCheck(new StripePaymentIntentGateway))->isConfigured())->toBeTrue();
    });
});

describe('PostHogHealthCheck', function () {
    test('configured when a project key is present', function () {
        config(['services.posthog.api_key' => 'phc_test']);

        expect((new PostHogHealthCheck)->isConfigured())->toBeTrue();
    });

    test('not configured when blank', function () {
        expect((new PostHogHealthCheck)->isConfigured())->toBeFalse();
    });
});

describe('CustomerIoHealthCheck', function () {
    test('configured only when both the site id and the api key are present', function () {
        config(['services.customer_io.site_id' => 'site123']);
        expect((new CustomerIoHealthCheck)->isConfigured())->toBeFalse();

        config(['services.customer_io.api_key' => 'key123']);
        expect((new CustomerIoHealthCheck)->isConfigured())->toBeTrue();
    });
});

describe('ZeroBounceHealthCheck', function () {
    test('configured from the admin-first setting', function () {
        update_option(LeadSettingsService::OPTION_KEY, ['zerobounce_api_key' => 'zb-admin-key']);

        expect((new ZeroBounceHealthCheck(new LeadSettingsService))->isConfigured())->toBeTrue();
    });

    test('falls back to the env-backed config when the setting is blank', function () {
        config(['services.zerobounce.api_key' => 'zb-env-key']);

        expect((new ZeroBounceHealthCheck(new LeadSettingsService))->isConfigured())->toBeTrue();
    });

    test('not configured with neither', function () {
        expect((new ZeroBounceHealthCheck(new LeadSettingsService))->isConfigured())->toBeFalse();
    });
});

describe('MetaHealthCheck', function () {
    test('configured with a token and at least one pixel id', function () {
        config([
            'services.meta_capi.access_token' => 'meta-token',
            'pixels.meta.pixel_ids' => ['1234567890'],
        ]);

        expect((new MetaHealthCheck(new MetaConversionsApiClient))->isConfigured())->toBeTrue();
    });

    test('not configured with a token but no pixel ids', function () {
        config(['services.meta_capi.access_token' => 'meta-token']);

        expect((new MetaHealthCheck(new MetaConversionsApiClient))->isConfigured())->toBeFalse();
    });

    test('not configured when explicitly disabled, even with a token and pixel', function () {
        config([
            'services.meta_capi.access_token' => 'meta-token',
            'services.meta_capi.enabled' => false,
            'pixels.meta.pixel_ids' => ['1234567890'],
        ]);

        expect((new MetaHealthCheck(new MetaConversionsApiClient))->isConfigured())->toBeFalse();
    });
});
