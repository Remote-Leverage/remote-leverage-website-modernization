<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Tracking\Support\GoogleEnhancedConversion;
use App\Infrastructure\WordPress\Hooks\ConversionHooks;

/**
 * Google Ads enhanced conversions, added 2026-09-18 in response to the "no tag pings sent
 * recently / make sure the event snippet has a transaction ID" diagnostic in the Ads UI.
 *
 * What is really guarded here is the normalisation. Google matches on the hash, so a stray
 * character or a missing `+` produces a valid-looking digest that matches nobody, and the only
 * symptom is a match rate that quietly reads zero.
 */
beforeEach(function () {
    config([
        'pixels.environments' => ['production', 'development'],
        'pixels.google_ads.conversion_id' => 'AW-11406183013',
        'pixels.google_ads.conversions' => [
            ['label' => 'AyW6CJHZnr8ZEOWU8r4q', 'trigger' => 'path:VAThankYou', 'value' => null],
            ['label' => 'pTbrCP6-_dIbEOWU8r4q', 'trigger' => 'path:VAThankYou', 'value' => '1'],
            ['label' => 'oqW3CP3jnJcbEOWU8r4q', 'trigger' => 'dl:form_submit', 'value' => null],
        ],
        'pixels.ga4.measurement_id' => '',
        'pixels.ga4.events' => [],
        'pixels.posthog_events' => [],
    ]);

    $GLOBALS['wp_environment_type'] = 'development';
    session()->forget(GoogleEnhancedConversion::SESSION_KEY);
});

function enhancedLead(array $overrides = []): Lead
{
    $lead = new Lead;
    $lead->forceFill(array_merge([
        'uuid' => '0f2c8b1e-4a6d-4c2f-9b77-1d3e5a7c9042',
        'email' => '  Adrian@RemoteLeverage.com ',
        'phone' => '(555) 123-4567',
        'phone_country' => '+1',
        'first_name' => ' Adrián ',
        'last_name' => 'Salvatori',
    ], $overrides));

    return $lead;
}

describe('normalisation', function () {
    test('an email is trimmed and lowercased, and nothing else', function () {
        expect(GoogleEnhancedConversion::normalizeEmail('  Adrian@RemoteLeverage.com '))
            ->toBe('adrian@remoteleverage.com');
    });

    test('a gmail address keeps its dots, because Google does not ask for them to go', function () {
        // Inventing a normalisation step is how a hash silently stops matching.
        expect(GoogleEnhancedConversion::normalizeEmail('First.Last@gmail.com'))
            ->toBe('first.last@gmail.com');
    });

    test('a phone becomes E.164, keeping the + that Meta strips', function () {
        expect(GoogleEnhancedConversion::normalizePhone(enhancedLead()))->toBe('+15551234567');
    });

    test('a country code already on the number is not doubled', function () {
        expect(GoogleEnhancedConversion::normalizePhone(enhancedLead([
            'phone' => '+1 555 123 4567',
        ])))->toBe('+15551234567');
    });

    test('no phone yields no value rather than a bare +', function () {
        expect(GoogleEnhancedConversion::normalizePhone(enhancedLead(['phone' => ''])))->toBe('');
    });

    test('an empty field hashes to nothing, not to the digest of an empty string', function () {
        expect(GoogleEnhancedConversion::hash(''))->toBe('')
            ->and(GoogleEnhancedConversion::hash('a'))->toHaveLength(64);
    });
});

describe('payload', function () {
    test('user data is hashed under the keys the Google tag reads', function () {
        $payload = GoogleEnhancedConversion::payload(enhancedLead());

        expect($payload['user_data']['sha256_email_address'])
            ->toBe(hash('sha256', 'adrian@remoteleverage.com'))
            ->and($payload['user_data']['sha256_phone_number'])
            ->toBe(hash('sha256', '+15551234567'))
            ->and($payload['user_data']['address']['sha256_first_name'])
            ->toBe(hash('sha256', 'adrián'))
            ->and($payload['user_data']['address']['sha256_last_name'])
            ->toBe(hash('sha256', 'salvatori'));
    });

    test('no plaintext identifier survives into the payload', function () {
        $encoded = json_encode(GoogleEnhancedConversion::payload(enhancedLead()));

        expect($encoded)->not->toContain('remoteleverage.com')
            ->and($encoded)->not->toContain('5551234567')
            ->and($encoded)->not->toContain('Salvatori');
    });

    test('the transaction id is the key Meta already dedupes on', function () {
        // MetaConversionsApiClient::eventId() builds the identical string, deliberately.
        expect(GoogleEnhancedConversion::payload(enhancedLead())['transaction_id'])
            ->toBe('lead-0f2c8b1e-4a6d-4c2f-9b77-1d3e5a7c9042');
    });

    test('a lead with no contact details still yields a transaction id', function () {
        $payload = GoogleEnhancedConversion::payload(enhancedLead([
            'email' => '', 'phone' => '', 'first_name' => '', 'last_name' => '',
        ]));

        expect($payload['user_data'])->toBe([])
            ->and($payload['transaction_id'])->toBe('lead-0f2c8b1e-4a6d-4c2f-9b77-1d3e5a7c9042');
    });
});

describe('what the thank-you page emits', function () {
    function renderEnhanced(string $uri): string
    {
        $_SERVER['REQUEST_URI'] = $uri;

        ob_start();
        (new ConversionHooks)->injectConversions();

        return (string) ob_get_clean();
    }

    test('both Ads conversions carry the transaction id once the wizard has run', function () {
        session()->put(GoogleEnhancedConversion::SESSION_KEY, GoogleEnhancedConversion::payload(enhancedLead()));

        $out = renderEnhanced('/VAThankYou/');

        expect(substr_count($out, '"transaction_id":"lead-0f2c8b1e-4a6d-4c2f-9b77-1d3e5a7c9042"'))->toBe(2);
    });

    test('user data is set before the conversions, or it attaches to nothing', function () {
        session()->put(GoogleEnhancedConversion::SESSION_KEY, GoogleEnhancedConversion::payload(enhancedLead()));

        $out = renderEnhanced('/VAThankYou/');

        expect($out)->toContain('gtag("set", "user_data"')
            ->and(strpos($out, 'gtag("set", "user_data"'))
            ->toBeLessThan(strpos($out, 'AyW6CJHZnr8ZEOWU8r4q'));
    });

    test('a direct visit that never booked emits the conversions exactly as before', function () {
        $out = renderEnhanced('/VAThankYou/');

        expect($out)->toContain('AW-11406183013/AyW6CJHZnr8ZEOWU8r4q')
            ->and($out)->not->toContain('transaction_id')
            ->and($out)->not->toContain('user_data');
    });

    test('the click and dataLayer conversions never inherit a stale transaction id', function () {
        // These fire anywhere on the site. A transaction_id left in the session from an earlier
        // booking would attach this visitor's click to that booking's conversion.
        session()->put(GoogleEnhancedConversion::SESSION_KEY, GoogleEnhancedConversion::payload(enhancedLead()));

        $out = renderEnhanced('/');

        expect($out)->toContain('oqW3CP3jnJcbEOWU8r4q')
            ->and($out)->not->toContain('transaction_id');
    });

    test('a reload re-sends the same transaction id, so Google collapses the two', function () {
        session()->put(GoogleEnhancedConversion::SESSION_KEY, GoogleEnhancedConversion::payload(enhancedLead()));

        $first = renderEnhanced('/VAThankYou/');
        $second = renderEnhanced('/VAThankYou/');

        expect($second)->toContain('"transaction_id":"lead-0f2c8b1e-4a6d-4c2f-9b77-1d3e5a7c9042"')
            ->and($second)->toBe($first);
    });
});

describe('the session is only touched where it is needed', function () {
    /*
     * Reading the session opens one, an open session sets a cookie, and docker/nginx.conf treats
     * a Set-Cookie as "never cache this". Calling this from wp_footer on every request would
     * have made the whole site uncacheable — a performance regression with no visible symptom in
     * the rendered output, which is why it is pinned with a read counter rather than left to the
     * comment on the gate.
     */
    beforeEach(fn () => $GLOBALS['_test_session_reads'] = 0);

    test('a page with no Ads conversion to decorate never reads it', function () {
        session()->put(GoogleEnhancedConversion::SESSION_KEY, GoogleEnhancedConversion::payload(enhancedLead()));
        $GLOBALS['_test_session_reads'] = 0;

        renderEnhanced('/');

        expect($GLOBALS['_test_session_reads'])->toBe(0);
    });

    test('the thank-you page reads it exactly once', function () {
        session()->put(GoogleEnhancedConversion::SESSION_KEY, GoogleEnhancedConversion::payload(enhancedLead()));
        $GLOBALS['_test_session_reads'] = 0;

        expect(renderEnhanced('/VAThankYou/'))->toContain('"transaction_id":"lead-0f2c8b1e')
            ->and($GLOBALS['_test_session_reads'])->toBe(1);
    });
});

describe('the click trigger', function () {
    test('matches an uppercase CTA, which is how most of them are authored', function () {
        // HomeHeroBlock, AboutHeroBlock and RolePages all default to BOOK A CONSULTATION.
        $out = renderEnhanced('/');

        expect($out)->toContain('needle.toLowerCase()')
            ->and($out)->toContain('.trim().toLowerCase()');
    });
});
