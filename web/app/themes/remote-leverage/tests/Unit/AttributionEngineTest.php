<?php

declare(strict_types=1);

use App\Domains\Referral\Services\AttributionEngine;

describe('AttributionEngine', function () {
    test('resolves referral slug from query parameters with priority order', function () {
        $engine = new AttributionEngine;

        // via parameter takes highest priority
        expect($engine->resolveReferralSlug('acme-partner', 'other-ref', 'r-code'))->toBe('acme-partner');

        // ref parameter used if via is empty
        expect($engine->resolveReferralSlug(null, 'growth-agency', 'r-code'))->toBe('growth-agency');

        // r parameter used if via and ref are empty
        expect($engine->resolveReferralSlug('', '', 'affiliate123'))->toBe('affiliate123');
    });

    test('falls back to cookie when query parameters are missing or empty', function () {
        $engine = new AttributionEngine;

        expect($engine->resolveReferralSlug(null, null, null, 'cookie-partner'))->toBe('cookie-partner');
        expect($engine->resolveReferralSlug('', '', '', 'cookie-partner'))->toBe('cookie-partner');
    });

    test('query parameters override cookie', function () {
        $engine = new AttributionEngine;

        expect($engine->resolveReferralSlug('query-override', null, null, 'old-cookie'))->toBe('query-override');
    });

    test('extracts referral slug from HTTP_REFERER when query and cookie are empty', function () {
        $engine = new AttributionEngine;

        $refererWithVia = 'https://external-site.com/review?via=tech-advisor&utm_source=partner';
        expect($engine->resolveReferralSlug(null, null, null, null, $refererWithVia))->toBe('tech-advisor');

        $refererWithRef = 'https://partner-hub.com/landing?ref=capital-partners';
        expect($engine->resolveReferralSlug(null, null, null, null, $refererWithRef))->toBe('capital-partners');

        $refererWithR = 'https://affiliate.org/?r=direct-affiliate';
        expect($engine->resolveReferralSlug(null, null, null, null, $refererWithR))->toBe('direct-affiliate');
    });

    test('returns null when no referral parameters, cookie, or referer are present', function () {
        $engine = new AttributionEngine;

        expect($engine->resolveReferralSlug(null, null, null, null, null))->toBeNull();
        expect($engine->resolveReferralSlug('', '', '', '', 'https://google.com/search?q=va+agency'))->toBeNull();
    });

    test('sanitizes input slugs and strips malicious tags', function () {
        $engine = new AttributionEngine;

        $malicious = '<script>alert("xss")</script>clean-slug';
        $sanitized = $engine->resolveReferralSlug($malicious);

        expect($sanitized)->not->toContain('<script>');
        expect($sanitized)->toContain('clean-slug');
    });

    test('calculates default and custom cookie lifetime in seconds', function () {
        $engine = new AttributionEngine;

        // Default: 60 days = 5,184,000 seconds
        expect($engine->getCookieMaxAge())->toBe(60 * 86400);

        // Custom option: 30 days
        $GLOBALS['_wp_mock_options']['rl_ref_cookie_days'] = 30;
        expect($engine->getCookieMaxAge())->toBe(30 * 86400);

        // Reset
        unset($GLOBALS['_wp_mock_options']['rl_ref_cookie_days']);
    });
});
