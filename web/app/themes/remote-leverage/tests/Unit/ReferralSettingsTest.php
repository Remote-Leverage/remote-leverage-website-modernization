<?php

declare(strict_types=1);

use App\Domains\Referral\Services\ReferralSettingsService;

beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];
});

describe('ReferralSettingsService', function () {
    test('get returns defaults when nothing has been configured', function () {
        $service = new ReferralSettingsService;

        $settings = $service->get();

        expect($settings['default_reward_currency'])->toBe('USD')
            ->and($settings['default_reward_type'])->toBe('cash')
            ->and($settings['cookie_days'])->toBe(ReferralSettingsService::DEFAULT_COOKIE_DAYS)
            ->and($settings['landing_pages'])->toBe(ReferralSettingsService::defaultLandingPages());
    });

    test('save rejects a non-positive reward amount', function () {
        $service = new ReferralSettingsService;

        $result = $service->save([
            'default_reward_amount' => 0,
            'default_reward_currency' => 'USD',
            'cookie_days' => 60,
            'landing_pages' => 'Homepage = https://example.com',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'])->toContain('Default reward amount must be greater than zero.');
    });

    test('save rejects an invalid currency code', function () {
        $service = new ReferralSettingsService;

        $result = $service->save([
            'default_reward_amount' => 20,
            'default_reward_currency' => 'US Dollars',
            'cookie_days' => 60,
            'landing_pages' => 'Homepage = https://example.com',
        ]);

        expect($result['success'])->toBeFalse();
    });

    test('save rejects a cookie lifetime below 1 day', function () {
        $service = new ReferralSettingsService;

        $result = $service->save([
            'default_reward_amount' => 20,
            'default_reward_currency' => 'USD',
            'cookie_days' => 0,
            'landing_pages' => 'Homepage = https://example.com',
        ]);

        expect($result['success'])->toBeFalse();
    });

    test('save rejects an empty landing pages list', function () {
        $service = new ReferralSettingsService;

        $result = $service->save([
            'default_reward_amount' => 20,
            'default_reward_currency' => 'USD',
            'cookie_days' => 60,
            'landing_pages' => '',
        ]);

        expect($result['success'])->toBeFalse();
    });

    test('save persists sanitized settings and syncs the legacy cookie-days option', function () {
        $service = new ReferralSettingsService;

        $result = $service->save([
            'default_reward_amount' => '25.5',
            'default_reward_currency' => 'usd',
            'default_reward_type' => 'credit',
            'cookie_days' => '30',
            'landing_pages' => "Homepage = https://example.com\nGrowth = https://example.com/growth\n\ninvalid line without equals",
        ]);

        expect($result['success'])->toBeTrue();

        $settings = $service->get();

        expect($settings['default_reward_amount'])->toBe(25.5)
            ->and($settings['default_reward_currency'])->toBe('USD')
            ->and($settings['default_reward_type'])->toBe('credit')
            ->and($settings['cookie_days'])->toBe(30)
            ->and($settings['landing_pages'])->toHaveCount(2)
            ->and(get_option('rl_ref_cookie_days'))->toBe(30);
    });

    test('landingPagesToText round-trips through parseLandingPages via save/get', function () {
        $service = new ReferralSettingsService;

        $service->save([
            'default_reward_amount' => 10,
            'default_reward_currency' => 'USD',
            'cookie_days' => 45,
            'landing_pages' => 'Homepage = https://example.com',
        ]);

        $settings = $service->get();
        $text = $service->landingPagesToText($settings['landing_pages']);

        expect($text)->toBe('Homepage = https://example.com');
    });
});
