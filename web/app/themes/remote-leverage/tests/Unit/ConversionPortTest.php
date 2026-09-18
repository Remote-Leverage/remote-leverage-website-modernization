<?php

declare(strict_types=1);

use App\Infrastructure\WordPress\Hooks\ConversionHooks;
use App\Infrastructure\WordPress\Hooks\MarketingPixelHooks;

/**
 * The tags moved out of `GTM-53JDTQCZ` when it was retired on 2026-09-18.
 *
 * Transcribed from the published container, so what these tests really guard is the
 * transcription. A wrong conversion label does not error: it reports into nothing, and the only
 * symptom is a campaign that looks unprofitable months later. The labels are asserted literally
 * for that reason — if one changes, it should be because somebody meant it.
 */
beforeEach(function () {
    config([
        'pixels.environments' => ['production', 'development'],
        'pixels.google_ads.conversion_id' => 'AW-11406183013',
        'pixels.google_ads.conversions' => [
            ['label' => 'AyW6CJHZnr8ZEOWU8r4q', 'trigger' => 'path:VAThankYou', 'value' => null],
            ['label' => 'pTbrCP6-_dIbEOWU8r4q', 'trigger' => 'path:VAThankYou', 'value' => '1'],
            ['label' => 'oEXvCN-QnpcbEOWU8r4q', 'trigger' => 'click_text:Book a Consultation', 'value' => null],
            ['label' => 'oqW3CP3jnJcbEOWU8r4q', 'trigger' => 'dl:form_submit', 'value' => null],
        ],
        'pixels.ga4.measurement_id' => 'G-SCP464C5EH',
        'pixels.ga4.events' => [
            ['name' => 'appointment_booked', 'trigger' => 'path:VAThankYou'],
            ['name' => 'generate_lead', 'trigger' => 'dl:form_submit'],
            ['name' => 'Book a Consultation Click', 'trigger' => 'click_text:Book a Consultation'],
            ['name' => 'watched_testimonial_video', 'trigger' => 'dl:video_play'],
        ],
        'pixels.posthog_events' => [
            ['name' => 'appointment_booked_web', 'trigger' => 'path:VAThankYou'],
        ],
        'pixels.rewardful.api_key' => '39ea7a',
        'pixels.consent.enabled' => true,
        'pixels.consent.defaults' => ['ad_storage' => 'granted', 'analytics_storage' => 'granted'],
        'pixels.defer.vendors' => [],
    ]);

    $GLOBALS['wp_environment_type'] = 'development';
    $_SERVER['REQUEST_URI'] = '/';
});

function renderConversions(string $uri): string
{
    $_SERVER['REQUEST_URI'] = $uri;

    ob_start();
    (new ConversionHooks)->injectConversions();

    return (string) ob_get_clean();
}

describe('Google Ads conversions', function () {
    test('both thank-you conversions fire on the page the wizard redirects to', function () {
        $out = renderConversions('/VAThankYou/');

        expect($out)->toContain('AW-11406183013/AyW6CJHZnr8ZEOWU8r4q')
            ->and($out)->toContain('AW-11406183013/pTbrCP6-_dIbEOWU8r4q');
    });

    test('the valued conversion carries its value and a currency', function () {
        $out = renderConversions('/VAThankYou/');

        expect($out)->toContain('"value":"1"')
            ->and($out)->toContain('"currency":"USD"');
    });

    test('thank-you conversions do not fire on any other page', function () {
        $out = renderConversions('/pricing/');

        expect($out)->not->toContain('AyW6CJHZnr8ZEOWU8r4q')
            ->and($out)->not->toContain('pTbrCP6-_dIbEOWU8r4q');
    });

    test('the path match is case-insensitive, unlike the container predicate', function () {
        /*
         * GTM matched `VAThankYou` case-sensitively. The wizard preserves the capitals so it
         * always matched, but a lowercase URL serves the identical page and would have reported
         * nothing. See MultistepBookingWizard, which had to work around the same trap once.
         */
        expect((new ConversionHooks)->pathMatches('VAThankYou'))->toBeFalse();

        $_SERVER['REQUEST_URI'] = '/vathankyou/';
        expect((new ConversionHooks)->pathMatches('VAThankYou'))->toBeTrue();
    });

    test('click and form conversions are wired, not fired inline', function () {
        $out = renderConversions('/');

        expect($out)->toContain('oEXvCN-QnpcbEOWU8r4q')
            ->and($out)->toContain('oqW3CP3jnJcbEOWU8r4q')
            ->and($out)->toContain('click_text:Book a Consultation')
            ->and($out)->toContain('dl:form_submit')
            // Wired means inside the table, not called at parse time.
            ->and($out)->not->toContain('send({"vendor":"gtag","name":"conversion","params":{"send_to":"AW-11406183013/oqW3CP3jnJcbEOWU8r4q"}});');
    });

    test('a malformed label or conversion id is refused rather than emitted', function () {
        config([
            'pixels.google_ads.conversions' => [
                ['label' => "'); alert(1); //", 'trigger' => 'path:VAThankYou', 'value' => null],
                ['label' => 'short', 'trigger' => 'path:VAThankYou', 'value' => null],
            ],
        ]);

        $out = renderConversions('/VAThankYou/');

        expect($out)->not->toContain('alert(1)');

        config(['pixels.google_ads.conversion_id' => 'not-an-id']);

        $actions = (new ConversionHooks)->actions();
        $ads = array_filter($actions, fn ($a) => ($a['call']['name'] ?? '') === 'conversion');

        expect($ads)->toBe([]);
    });
});

describe('GA4 and PostHog events', function () {
    test('appointment_booked and the PostHog capture fire on the thank-you page', function () {
        $out = renderConversions('/VAThankYou/');

        expect($out)->toContain('"name":"appointment_booked"')
            ->and($out)->toContain('"send_to":"G-SCP464C5EH"')
            ->and($out)->toContain('"vendor":"posthog"')
            ->and($out)->toContain('"name":"appointment_booked_web"')
            // The container sent the page path with it.
            ->and($out)->toContain('"page_path":"/VAThankYou/"');
    });

    test('generate_lead stays on form_submit', function () {
        $out = renderConversions('/');

        expect($out)->toContain('"name":"generate_lead"')
            ->and($out)->toContain('dl:form_submit');
    });
});

describe('consent defaults and Rewardful', function () {
    test('consent defaults are granted, as the container set them', function () {
        ob_start();
        (new ConversionHooks)->injectConsentDefaults();
        $out = (string) ob_get_clean();

        expect($out)->toContain("gtag('consent', 'default'")
            ->and($out)->toContain('"ad_storage":"granted"')
            ->and($out)->toContain('"analytics_storage":"granted"');
    });

    test('Rewardful keeps its key and its append-to-body behaviour', function () {
        ob_start();
        (new ConversionHooks)->injectRewardful();
        $out = (string) ob_get_clean();

        expect($out)->toContain("data-rewardful', '39ea7a'")
            ->and($out)->toContain('r.wdfl.co/rw.js')
            ->and($out)->toContain('document.body.appendChild');
    });

    test('Rewardful defers its fetch but not its queue', function () {
        config(['pixels.defer.vendors' => ['rewardful']]);

        ob_start();
        (new ConversionHooks)->injectRewardful();
        $out = (string) ob_get_clean();

        expect(strpos($out, 'w[r] = w[r] ||'))->toBeLessThan(strpos($out, 'rlDefer'));
        expect(strpos($out, 'r.wdfl.co'))->toBeGreaterThan(strpos($out, 'rlDefer'));
    });
});

describe('the second LinkedIn and OpenAI accounts came home', function () {
    test('both ids are emitted now that the container is gone', function () {
        $config = require __DIR__.'/../../config/pixels.php';

        expect($config['linkedin']['partner_ids'])->toBe(['6411876', '9514236'])
            ->and($config['openai']['pixel_ids'])->toBe(['7QY9HDVocGyeNvMMW1gLWb', 'GtXTy8ihLz5qrMUanZ3fqf'])
            // Nothing is delivered by a container any more.
            ->and(array_merge(...array_values($config['delivered_by_gtm'] ?: [[]])))->toBe([]);
    });

    test('one SDK load serves both ids', function () {
        config([
            'pixels.linkedin.partner_ids' => ['6411876', '9514236'],
            'pixels.openai.pixel_ids' => ['7QY9HDVocGyeNvMMW1gLWb', 'GtXTy8ihLz5qrMUanZ3fqf'],
        ]);

        $h = new MarketingPixelHooks;

        ob_start();
        $h->injectLinkedIn();
        $li = (string) ob_get_clean();

        ob_start();
        $h->injectOpenAi();
        $oa = (string) ob_get_clean();

        expect(substr_count($li, 'snap.licdn.com'))->toBe(1)
            ->and($li)->toContain("push('6411876')")
            ->and($li)->toContain("push('9514236')")
            ->and(substr_count($oa, 'bzrcdn.openai.com'))->toBe(1)
            ->and($oa)->toContain("pixelId: '7QY9HDVocGyeNvMMW1gLWb'")
            ->and($oa)->toContain("pixelId: 'GtXTy8ihLz5qrMUanZ3fqf'");
    });
});

describe('the container is retired', function () {
    test('no GTM container is configured by default', function () {
        $config = require __DIR__.'/../../config/site-kit.php';

        expect($config['containers'])->toBe([]);
    });
});

describe('triggers that the container actually used', function () {
    /*
     * `form_submit` is a dataLayer event, not a native submit. `MultistepBookingWizard::
     * pushToDataLayer()` pushes it alongside `partial_form_submitted` when a lead is captured,
     * and the container's "Lead" trigger was a Custom Event on that name. An earlier revision
     * of ConversionHooks listened for a native `submit`, which a Livewire form never fires.
     */
    test('the lead conversions listen on dataLayer, not on a native submit', function () {
        $out = renderConversions('/');

        expect($out)->toContain('dl:form_submit')
            ->and($out)->toContain('w.dataLayer.push = function')
            // Replays what was pushed before this script parsed.
            ->and($out)->toContain('for (var n = 0; n < w.dataLayer.length; n++)')
            ->and($out)->not->toContain("addEventListener('submit'");
    });

    test('the wizard really does push form_submit on a successful capture', function () {
        /*
         * Reads the component rather than trusting the comment: this conversion is only worth
         * anything if that name still reaches dataLayer.
         */
        $wizard = file_get_contents(__DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php');

        expect($wizard)->toContain("? [\$eventName, 'form_submit']")
            ->and($wizard)->toContain('$this->pushToDataLayer($eventName, $properties);')
            ->and($wizard)->toContain("trackStepEvent('partial_form_submitted'");
    });

    test('video_play is wired and every player can reach it', function () {
        $out = renderConversions('/');

        expect($out)->toContain('dl:video_play')
            // Any <video>, without the block knowing this exists. `play` does not bubble.
            ->and($out)->toContain("d.addEventListener('play'")
            ->and($out)->toContain("el.tagName !== 'VIDEO'");

        // The two players that are not <video> elements push it themselves.
        $testimonials = file_get_contents(__DIR__.'/../../resources/views/blocks/testimonials.blade.php');
        $caseStudy = file_get_contents(__DIR__.'/../../resources/views/blocks/case-study.blade.php');

        expect($testimonials)->toContain("event: 'video_play'")
            ->and($caseStudy)->toContain("event: 'video_play'")
            // A line comment inside an HTML attribute would swallow the rest if newlines collapse.
            ->and($testimonials)->not->toContain('// Not a');
    });
});
