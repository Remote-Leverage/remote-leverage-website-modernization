<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Infrastructure\WordPress\Hooks\MarketingPixelHooks;

/**
 * Covers the pixels production loads outside GTM, and the `dataLayer` bridge.
 *
 * Audited from production's live HTML on 2026-09-17. Every one of these is hardcoded into the
 * Elementor-era pages rather than delivered by a container, so nothing carries it across the
 * cutover on its own — and none of them fails loudly when missing. A pixel that stops firing
 * looks exactly like a quiet week.
 */
beforeEach(function () {
    config([
        'pixels.environments' => ['production', 'development'],
        'pixels.meta.pixel_ids' => ['1430907207548734', '1482937899395718'],
        'pixels.meta.track_page_view' => true,
        'pixels.bing_uet.tag_id' => '97187250',
        'pixels.hubspot.portal_id' => '243484989',
        'pixels.hubspot.region' => 'na2',
        'pixels.linkedin.partner_ids' => ['6411876'],
        'pixels.openai.pixel_ids' => ['7QY9HDVocGyeNvMMW1gLWb'],
        'pixels.openai.debug' => false,
        'pixels.google_tag.ids' => ['GT-NCNQ6N2'],
        'pixels.google_tag.linker_domains' => ['remoteleverage.com'],
    ]);

    $GLOBALS['wp_environment_type'] = 'development';
});

function renderPixel(callable $render): string
{
    $hooks = new MarketingPixelHooks;

    ob_start();
    $render($hooks);

    return (string) ob_get_clean();
}

describe('Meta Pixel', function () {
    test('both production pixel ids are initialised off one SDK load', function () {
        /*
         * Facebook is the largest lead source in the Gravity backfill — 2,643 of 3,969 leads —
         * and the pixel is in neither GTM container, so this is the single biggest thing the
         * cutover could silently drop.
         */
        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectMetaPixel());

        expect($out)->toContain("fbq('init', '1430907207548734')")
            ->and($out)->toContain("fbq('init', '1482937899395718')")
            ->and($out)->toContain("fbq('track', 'PageView')")
            // Meta's loader is idempotent, so several ids share one SDK. Loading it twice would
            // not break, but it would be a second network request for nothing.
            ->and(substr_count($out, 'connect.facebook.net'))->toBe(1);
    });

    test('each pixel gets a noscript fallback image', function () {
        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectMetaNoscript());

        expect(substr_count($out, '<noscript>'))->toBe(2)
            ->and($out)->toContain('id=1430907207548734')
            ->and($out)->toContain('id=1482937899395718');
    });

    test('a non-numeric or duplicated pixel id never reaches the script', function () {
        // The id is interpolated into an inline script, and a duplicate would fire two PageViews
        // into one ad account, inflating the number the bidding algorithm optimises against.
        config(['pixels.meta.pixel_ids' => ['1430907207548734', '1430907207548734', 'abc', '', '<script>']]);

        expect((new MarketingPixelHooks)->metaPixelIds())->toBe(['1430907207548734']);
    });

    test('nothing renders when no pixel is configured', function () {
        config(['pixels.meta.pixel_ids' => []]);

        expect(renderPixel(fn ($h) => $h->injectMetaPixel()))->toBe('')
            ->and(renderPixel(fn ($h) => $h->injectMetaNoscript()))->toBe('');
    });
});

describe('Microsoft UET and HubSpot', function () {
    test('the UET tag carries the production tag id', function () {
        // This is what stamps `msclkid`, which the lead export carries and AttributionCollector
        // stores. Without it a Bing booking still arrives, just unattributable to the paid click.
        $out = renderPixel(fn ($h) => $h->injectBingUet());

        expect($out)->toContain('ti:"97187250"')
            ->and($out)->toContain('bat.bing.com/bat.js');
    });

    test('the HubSpot loader uses the account region', function () {
        // Distinct from the server-side CRM sync: this attaches page-view history to the contact
        // the API creates. The region is part of the host and differs per account.
        expect(renderPixel(fn ($h) => $h->injectHubSpot()))
            ->toContain('js-na2.hs-scripts.com/243484989.js');
    });

    test('a malformed id renders nothing rather than a broken script', function () {
        config([
            'pixels.bing_uet.tag_id' => 'not-a-tag',
            'pixels.hubspot.portal_id' => '',
        ]);

        expect(renderPixel(fn ($h) => $h->injectBingUet()))->toBe('')
            ->and(renderPixel(fn ($h) => $h->injectHubSpot()))->toBe('');
    });
});

describe('environment gating', function () {
    test('pixels stay off outside the allowed environments', function () {
        config(['pixels.environments' => ['production']]);
        $GLOBALS['wp_environment_type'] = 'staging';

        expect((new MarketingPixelHooks)->environmentAllowed())->toBeFalse();
    });

    test('pixels follow GTM_ENVIRONMENTS unless overridden', function () {
        /*
         * The config default falls back to GTM_ENVIRONMENTS so one switch moves the whole
         * tracking surface. An environment that fires GTM but not Meta, or the reverse, is a
         * difference nobody will remember making.
         */
        $config = require __DIR__.'/../../config/pixels.php';

        expect($config['environments'])->toBe(['production']);
    });
});

describe('dataLayer bridge', function () {
    /**
     * A wizard that records what it would have sent to the browser.
     *
     * `pushToDataLayer` is protected and calls Livewire's `js()`, which needs a component
     * lifecycle this bare test container has no way to provide. Subclassing is what lets the
     * logic be asserted without standing up Livewire.
     */
    function wizardSpy(): object
    {
        return new class extends MultistepBookingWizard
        {
            /** @var array<int, string> */
            public array $pushed = [];

            public function js($expression, ...$params)
            {
                $this->pushed[] = (string) $expression;

                return $this;
            }

            public function getId()
            {
                return 'wizard-1';
            }

            /** @param array<string, mixed> $properties */
            public function push(string $event, array $properties = []): void
            {
                $this->pushToDataLayer($event, $properties);
            }
        };
    }

    test('partial capture also emits the legacy form_submit the container listens for', function () {
        /*
         * The container fires GA4 `generate_lead` and Google Ads conversion
         * `oqW3CP3jnJcbEOWU8r4q` on `{{Event}} equals form_submit`. On the Elementor site that
         * came from gtag.js noticing a native Gravity Forms submission — there is no
         * form-submit listener tag in the container, so nothing else could have produced it.
         * A Livewire wizard never submits natively, so without this push a live Google Ads
         * conversion goes quiet at cutover with no error anywhere.
         */
        $w = wizardSpy();
        $w->push('partial_form_submitted', ['lead_id' => 42]);

        $events = array_map(
            static fn (string $js): string => json_decode(
                (string) preg_replace('/^.*?push\((.*)\);$/s', '$1', $js), true
            )['event'] ?? '',
            $w->pushed,
        );

        expect($events)->toBe(['partial_form_submitted', 'form_submit']);
    });

    test('other funnel events are published under their own name only', function () {
        // Publishing the whole funnel means a new GTM trigger is a container change rather than
        // a deploy. Only the partial gets the alias, because only it is a form submission.
        $w = wizardSpy();
        $w->push('booking_finished', ['meeting_id' => 'abc']);

        expect($w->pushed)->toHaveCount(1)
            ->and($w->pushed[0])->toContain('"event":"booking_finished"')
            ->and($w->pushed[0])->toContain('"meeting_id":"abc"');
    });

    test('personal data never reaches the dataLayer', function () {
        // The server-side dispatch is the source of truth and carries identity. This mirror is
        // for GTM triggers, and anything pushed here is readable by every tag in the container.
        $w = wizardSpy();
        $w->push('booking_finished', [
            'email' => 'dana@example.com',
            'phone' => '+13055550199',
            'lead_id' => 7,
        ]);

        expect($w->pushed[0])->not->toContain('dana@example.com')
            ->and($w->pushed[0])->not->toContain('3055550199')
            ->and($w->pushed[0])->toContain('"lead_id":7');
    });

    test('the push guards against a missing dataLayer', function () {
        // GTM may be blocked, or not yet loaded on a fast interaction. `push` on undefined is a
        // TypeError that would surface in Sentry as a booking-wizard error.
        $w = wizardSpy();
        $w->push('form_loaded');

        expect($w->pushed[0])->toStartWith('window.dataLayer = window.dataLayer || [];');
    });
});

describe('LinkedIn and OpenAI, ported from the page', function () {
    test('the page-hardcoded LinkedIn partner id is emitted', function () {
        /*
         * Production runs LinkedIn twice into two different accounts — 6411876 hardcoded and
         * 9514236 from GTM tag 64 — and which one is the live ad account could not be determined
         * from outside. LinkedIn's beacon returns 302 for any partner id including invented ones,
         * and not one of the 3,969 imported leads carries an `li_fat_id`. Both stay live rather
         * than guessing; dropping the wrong one goes dark with no error anywhere.
         */
        $out = renderPixel(fn ($h) => $h->injectLinkedIn());

        expect($out)->toContain("push('6411876')")
            ->and($out)->toContain('snap.licdn.com/li.lms-analytics/insight.min.js')
            // An array by design, so the container's tag coexists on one SDK load.
            ->and($out)->toContain('window._linkedin_data_partner_ids || []');
    });

    test('the OpenAI pixel is emitted with debug off', function () {
        // Production sends debug:true, which reads as a paste nobody cleaned up.
        $out = renderPixel(fn ($h) => $h->injectOpenAi());

        expect($out)->toContain("pixelId: '7QY9HDVocGyeNvMMW1gLWb'")
            ->and($out)->toContain('debug: false')
            ->and($out)->toContain('bzrcdn.openai.com/sdk/oaiq.min.js');
    });

    test('malformed ids are dropped for both', function () {
        config([
            'pixels.linkedin.partner_ids' => ['6411876', 'abc', '', '12'],
            'pixels.openai.pixel_ids' => ['7QY9HDVocGyeNvMMW1gLWb', 'short', '<script>'],
        ]);

        $h = new MarketingPixelHooks;

        expect($h->linkedInPartnerIds())->toBe(['6411876'])
            ->and($h->openAiPixelIds())->toBe(['7QY9HDVocGyeNvMMW1gLWb']);
    });

    test('nothing this file emits is also delivered by the GTM container', function () {
        /*
         * The guard that turns "remember not to double up" into a failing build.
         *
         * Production is the cautionary tale: it fires LinkedIn and OpenAI twice each because two
         * people solved the same problem in two places and nothing anywhere said so. The symptom
         * is not an error, it is a number in a billing report.
         *
         * Reads the shipped config rather than the test fixture, because the mistake this catches
         * is somebody editing that file.
         */
        $config = require __DIR__.'/../../config/pixels.php';
        $gtm = $config['delivered_by_gtm'];

        $emitted = [
            'linkedin' => $config['linkedin']['partner_ids'],
            'openai' => $config['openai']['pixel_ids'],
        ];

        foreach ($emitted as $vendor => $ids) {
            expect(array_intersect($ids, $gtm[$vendor] ?? []))->toBe(
                [],
                "{$vendor} id is emitted here AND by the GTM container — it will fire twice",
            );
        }

        // The Meta ids are in neither container; if one ever moves into GTM this catches it too.
        $allGtm = array_merge(...array_values($gtm));
        expect(array_intersect($config['meta']['pixel_ids'], $allGtm))->toBe([]);
    });
});

describe('Google tag', function () {
    test('the page-level Google tag production loads is emitted', function () {
        /*
         * Production's Site Kit loads GT-NCNQ6N2 directly, separately from GTM. Resolving that
         * tag's own payload shows it routes to G-SCP464C5EH and AW-11406183013 — both also
         * reachable via the container — **and to G-JFBLS33ET8, which nothing else on the site
         * reaches**. Without this, that GA4 property goes dark at cutover and the first symptom
         * is a flat graph nobody is watching.
         */
        $out = renderPixel(fn ($h) => $h->injectGoogleTag());

        expect($out)->toContain('googletagmanager.com/gtag/js?id=GT-NCNQ6N2')
            ->and($out)->toContain('gtag("config", "GT-NCNQ6N2")')
            // Cross-domain linker, as production configures it.
            ->and($out)->toContain('"domains":["remoteleverage.com"]')
            // Must not clobber a dataLayer GTM already created.
            ->and($out)->toContain('window.dataLayer = window.dataLayer || []');
    });

    test('a malformed tag id renders nothing', function () {
        config(['pixels.google_tag.ids' => ['not-a-tag', '', 'XX-1']]);

        expect(renderPixel(fn ($h) => $h->injectGoogleTag()))->toBe('');
    });
});
