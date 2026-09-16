<?php

declare(strict_types=1);

use App\Blocks\PaymentGatewayBlock;

/*
 * Where a completed payment lands.
 *
 * The chain is block field → STRIPE_DEFAULT_THANKYOU_URL → home_url(DEFAULT_THANKYOU_PATH).
 * The last step exists because the first two are both routinely empty, and an empty result
 * renders `success-url=""` on a live $100 checkout — the payer completes the charge and lands
 * nowhere. These tests pin the precedence and, more importantly, pin that the chain cannot
 * terminate in an empty string.
 *
 * `home_url()` is stubbed in tests/stubs.php; what matters here is that the fallback goes
 * through it at all rather than through a stored absolute URL. An absolute URL is what put a
 * local `.test` host one `seed-staging-secrets.sh` run away from a live checkout.
 */

describe('PaymentGatewayBlock::resolveSuccessUrl', function () {
    test('the block field wins over everything', function () {
        expect(PaymentGatewayBlock::resolveSuccessUrl(
            'https://example.com/custom/',
            'https://example.com/configured/',
        ))->toBe('https://example.com/custom/');
    });

    test('the env var is used when the block field is blank', function () {
        expect(PaymentGatewayBlock::resolveSuccessUrl('', 'https://example.com/configured/'))
            ->toBe('https://example.com/configured/');
    });

    test('a whitespace-only field is treated as blank, not as a URL', function () {
        expect(PaymentGatewayBlock::resolveSuccessUrl('   ', 'https://example.com/configured/'))
            ->toBe('https://example.com/configured/');
    });

    test('falls back to this site own thank-you page when neither is set', function () {
        expect(PaymentGatewayBlock::resolveSuccessUrl('', ''))
            ->toBe(home_url(PaymentGatewayBlock::DEFAULT_THANKYOU_PATH));
    });

    test('never returns an empty string, whatever it is handed', function () {
        // null is what ACF returns for an unset field, and what config() returns for an unset key.
        foreach ([[null, null], ['', ''], ['  ', null], [null, '   '], [false, false], [[], []]] as [$field, $configured]) {
            expect(PaymentGatewayBlock::resolveSuccessUrl($field, $configured))->not->toBe('');
        }
    });

    test('the default path is the migrated deposit thank-you page', function () {
        // patterns/referral-program-thank-you-deposit.php. Changing this slug without changing
        // the pattern sends every unconfigured payment to a 404.
        expect(PaymentGatewayBlock::DEFAULT_THANKYOU_PATH)
            ->toBe('/referral-program-thank-you-page-deposit/');
    });

    test('the fallback is same-host, so it cannot strand a payer on another environment', function () {
        $resolved = PaymentGatewayBlock::resolveSuccessUrl(null, null);

        expect($resolved)->toStartWith(home_url());
    });
});
