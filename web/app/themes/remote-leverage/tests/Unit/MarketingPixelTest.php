<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Infrastructure\WordPress\Hooks\MarketingPixelHooks;
use App\Infrastructure\WordPress\Hooks\TrackingHooks;

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
        'pixels.tiktok.pixel_ids' => ['CPMB51BC77U75I0QMMAG'],
        'pixels.tiktok.track_page_view' => true,
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
            'tiktok' => $config['tiktok']['pixel_ids'],
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

describe('legacy events recovered from the 2026-08-27 backup', function () {
    test('the booking confirmation fires the OpenAI conversion the legacy form fired', function () {
        /*
         * rl-elementor-blocks/assets/js/headless-calendly-multistep.js fired
         * `oaiq("measure","appointment_scheduled",{type:"customer_action"})` on
         * `gform_confirmation_loaded`. It is in NEITHER GTM container — verified by grepping both
         * published payloads — so nothing else reproduces it. Without it the OpenAI pixel records
         * page views and zero conversions, and ChatGPT ads optimise against nothing.
         */
        config(['pixels.openai.conversions' => ['vathankyou' => 'appointment_scheduled']]);
        $_SERVER['REQUEST_URI'] = '/VAThankYou/';

        $out = renderPixel(fn ($h) => $h->injectOpenAiConversion());

        expect($out)->toContain('oaiq("measure", "appointment_scheduled"')
            ->and($out)->toContain('type: "customer_action"')
            // Guarded: injectOpenAi() runs at priority 4 and this at 5, but a config that drops
            // the pixel entirely must not leave a call on an undefined object.
            ->and($out)->toContain('window.oaiq &&');
    });

    test('an ordinary page fires no conversion', function () {
        config(['pixels.openai.conversions' => ['vathankyou' => 'appointment_scheduled']]);
        $_SERVER['REQUEST_URI'] = '/hire-va/';

        expect(renderPixel(fn ($h) => $h->injectOpenAiConversion()))->toBe('');
    });

    test('the conversion path match is case-insensitive, unlike the GTM trigger', function () {
        config(['pixels.openai.conversions' => ['vathankyou' => 'appointment_scheduled']]);

        $h = new MarketingPixelHooks;

        expect($h->openAiConversionForRequest('/VAThankYou/'))->toBe('appointment_scheduled')
            ->and($h->openAiConversionForRequest('/vathankyou/'))->toBe('appointment_scheduled')
            // Everything else must stay clean, or every page counts as a booking.
            ->and($h->openAiConversionForRequest('/'))->toBeNull()
            ->and($h->openAiConversionForRequest('/hire-va/'))->toBeNull();
    });

    test('no conversion fires when the OpenAI pixel itself is switched off', function () {
        config([
            'pixels.openai.pixel_ids' => [],
            'pixels.openai.conversions' => ['vathankyou' => 'appointment_scheduled'],
        ]);
        $_SERVER['REQUEST_URI'] = '/VAThankYou/';

        expect(renderPixel(fn ($h) => $h->injectOpenAiConversion()))->toBe('');
    });
});

describe('Customer.io named page events', function () {
    /**
     * The snippet builder is protected, so a subclass exposes it.
     */
    function cioProbe(string $path): string
    {
        $hooks = new class extends TrackingHooks
        {
            public function probe(string $path): string
            {
                return $this->customerIoPageEvents($path);
            }
        };

        return $hooks->probe($path);
    }

    test('the named page events the legacy plugin fired are reproduced', function () {
        /*
         * `analytics.page()` alone is not parity. rl-customer-io's FrontendTracker also fired
         * named track() calls, and the plugin seeded a lead-scoring map that keys on those exact
         * names — Form Submitted 20, Viewed Booking Page 15, Viewed Pricing Page 10, Page Viewed 1
         * (src/Database/Migration.php:72-75 in the 2026-08-27 backup). A score built on them stops
         * moving if only the anonymous page call survives.
         */
        expect(cioProbe('/vapricing/'))->toContain('"Viewed Pricing Page"')
            ->and(cioProbe('/vacalendar/'))->toContain('"Viewed Booking Page"')
            ->and(cioProbe('/booking/'))->toContain('"Viewed Booking Page"')
            ->and(cioProbe('/some-appointment-page/'))->toContain('"Viewed Booking Page"');
    });

    test('ordinary pages emit no named event', function () {
        // Every page firing a scoring event would make the score meaningless.
        expect(cioProbe('/'))->toBe('')
            ->and(cioProbe('/hire-va/'))->toBe('')
            ->and(cioProbe(''))->toBe('');
    });

    test('a page matching two fragments still fires one event', function () {
        // "booking" and "appointment" both map to Viewed Booking Page; a page containing both
        // must not double-score.
        expect(substr_count(cioProbe('/booking-appointment/'), 'analytics.track'))->toBe(1);
    });
});

describe('deferred SDK loading', function () {
    /*
     * The 2026-09-18 Lighthouse run put 4,959ms of the mobile main thread in script evaluation,
     * with six pixels all fetching at `wp_head` priority 4. Deferring the fetch is only safe
     * because every one of these vendors installs a queueing stub first — so what these tests
     * really guard is that the stub stayed synchronous while the fetch moved.
     */
    test('nothing is deferred, and no bootstrap is emitted, until a vendor is named', function () {
        config(['pixels.defer.vendors' => []]);

        $hooks = new MarketingPixelHooks;

        expect($hooks->deferredVendors())->toBe([])
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectDeferBootstrap()))->toBe('')
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectLinkedIn()))->not->toContain('rlDefer');
    });

    test('the bootstrap flushes on interaction, idle or timeout, and announces rl_idle', function () {
        config(['pixels.defer.vendors' => ['linkedin'], 'pixels.defer.timeout_ms' => 1800]);

        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectDeferBootstrap());

        expect($out)->toContain('w.rlDefer = function')
            ->and($out)->toContain('pointerdown')
            ->and($out)->toContain('requestIdleCallback')
            ->and($out)->toContain('w.setTimeout(flush, 1800)')
            // The hook a container tag retriggers on, so TikTok can be deferred without
            // leaving GTM. A tag would point at this event name.
            ->and($out)->toContain("event: 'rl_idle'");
    });

    test('Meta and the Google tag are not deferrable, however they are configured', function () {
        /*
         * Meta carries 67% of paid acquisition and the Google tag is the site's only gtag
         * loader — the container has none of its own, so its GA4 tags piggyback on this one.
         * Both are excluded in code rather than by convention.
         */
        config(['pixels.defer.vendors' => ['meta', 'google_tag', 'linkedin']]);

        $hooks = new MarketingPixelHooks;

        expect($hooks->deferredVendors())->toBe(['linkedin'])
            ->and($hooks->isDeferred('meta'))->toBeFalse()
            ->and($hooks->isDeferred('google_tag'))->toBeFalse()
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectMetaPixel()))->not->toContain('rlDefer')
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectGoogleTag()))->not->toContain('rlDefer');
    });

    test('LinkedIn queues its ids and stub before the wrapper, not inside it', function () {
        config(['pixels.defer.vendors' => ['linkedin']]);

        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectLinkedIn());

        expect($out)->toContain('rlDefer');

        // A `lintrk()` call before the SDK lands has to queue, so the stub cannot be deferred.
        expect(strpos($out, "_linkedin_data_partner_ids.push('6411876')"))
            ->toBeLessThan(strpos($out, 'rlDefer'));
        expect(strpos($out, 'window.lintrk.q = []'))->toBeLessThan(strpos($out, 'rlDefer'));
        expect(strpos($out, 'snap.licdn.com'))->toBeGreaterThan(strpos($out, 'rlDefer'));
    });

    test('OpenAI keeps its stub and init synchronous and defers only the SDK', function () {
        config(['pixels.defer.vendors' => ['openai']]);

        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectOpenAi());

        // `injectOpenAiConversion()` runs at priority 5 and pushes onto this stub.
        expect(strpos($out, 'w.oaiq = q'))->toBeLessThan(strpos($out, 'rlDefer'));
        expect(strpos($out, 'bzrcdn.openai.com'))->toBeGreaterThan(strpos($out, 'rlDefer'));
        expect($out)->toContain("oaiq('init', {pixelId: '7QY9HDVocGyeNvMMW1gLWb'");
    });

    test('UET creates its queue before deferring, so an early push is not lost', function () {
        config(['pixels.defer.vendors' => ['bing_uet']]);

        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectBingUet());

        expect(strpos($out, 'window.uetq = window.uetq || []'))->toBeLessThan(strpos($out, 'rlDefer'));
        expect(strpos($out, 'bat.bing.com'))->toBeGreaterThan(strpos($out, 'rlDefer'));
        expect($out)->toContain('ti:"97187250"');
    });

    test('HubSpot is injected on flush and keeps the id its own code looks for', function () {
        config(['pixels.defer.vendors' => ['hubspot']]);

        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectHubSpot());

        expect($out)->toContain('rlDefer')
            ->and($out)->toContain('s.id="hs-script-loader"')
            ->and($out)->toContain('js-na2.hs-scripts.com/243484989.js')
            // The undeferred path emits a plain tag; the deferred one must not also do that.
            ->and($out)->not->toContain('<script id="hs-script-loader"');
    });
});

describe('TikTok, moved out of the container', function () {
    test('the production sdkid loads and a PageView is queued', function () {
        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectTikTok());

        expect($out)->toContain("ttq.load('CPMB51BC77U75I0QMMAG')")
            ->and($out)->toContain('ttq.page();')
            ->and($out)->toContain('analytics.tiktok.com/i18n/pixel/events.js')
            // One SDK regardless of how many ids are configured.
            ->and(substr_count($out, 'analytics.tiktok.com'))->toBe(1);
    });

    test('a junk sdkid is refused rather than interpolated into the script', function () {
        config(['pixels.tiktok.pixel_ids' => ['CPMB51BC77U75I0QMMAG', "'); alert(1); //", 'short']]);

        $hooks = new MarketingPixelHooks;

        expect($hooks->tikTokPixelIds())->toBe(['CPMB51BC77U75I0QMMAG']);
    });

    test('the stub and the PageView stay synchronous while only ttq.load waits', function () {
        /*
         * `ttq.load()` is the call that inserts events.js, so it is the one deferred. The method
         * stubs and `ttq.page()` must not be: the queued PageView is what events.js drains.
         */
        config(['pixels.defer.vendors' => ['tiktok']]);

        $out = renderPixel(fn (MarketingPixelHooks $h) => $h->injectTikTok());

        expect(strpos($out, 'ttq.setAndDefer'))->toBeLessThan(strpos($out, 'rlDefer'));
        expect(strpos($out, 'rlDefer'))->toBeLessThan(strpos($out, 'ttq.page();'));
        expect($out)->toContain("window.rlDefer(function(){ttq.load('CPMB51BC77U75I0QMMAG');});");
    });

    test('nothing is emitted when no id is configured', function () {
        config(['pixels.tiktok.pixel_ids' => []]);

        expect(renderPixel(fn (MarketingPixelHooks $h) => $h->injectTikTok()))->toBe('');
    });
});

describe('defaults that used to depend on an unset variable', function () {
    /*
     * Both of these were dark on production with no error anywhere, because an unset
     * environment variable is indistinguishable from a deliberate opt-out. Neither value is a
     * secret — both ship in the page HTML — so both are defaulted in config and asserted here.
     */
    test('the HubSpot portal id is defaulted, so browser tracking is not silently off', function () {
        $config = require __DIR__.'/../../config/pixels.php';

        expect($config['hubspot']['portal_id'])->toBe('243484989')
            ->and($config['hubspot']['region'])->toBe('na2');
    });

    test('the PostHog publishable key is defaulted', function () {
        $config = require __DIR__.'/../../config/services.php';

        expect($config['posthog']['api_key'])->toBe('phc_3PbasnDYndH8YVEky0ksHrB3SFwBZKmzkf5bl37o8u0');
    });

    test('PostHog is gated to production, now that its key has a default', function () {
        /*
         * The guard that stops the default becoming a regression. Without it, every local page
         * load and staging smoke test would ingest into the production project. PostHog used to
         * arrive via GTM, which `GTM_ENVIRONMENTS` already gated to production — so this keeps
         * behaviour rather than changing it.
         */
        config([
            // Set explicitly: the test harness does not resolve this file's env() defaults,
            // and the shipped default is asserted by the test above instead.
            'services.posthog.api_key' => 'phc_3PbasnDYndH8YVEky0ksHrB3SFwBZKmzkf5bl37o8u0',
            'services.posthog.host' => 'https://us.i.posthog.com',
            'services.posthog.environments' => ['production'],
        ]);

        $hooks = new TrackingHooks;

        $GLOBALS['wp_environment_type'] = 'development';
        expect($hooks->postHogEnvironmentAllowed())->toBeFalse();

        ob_start();
        $hooks->injectPostHogSnippet();
        expect((string) ob_get_clean())->toBe('');

        $GLOBALS['wp_environment_type'] = 'production';
        expect($hooks->postHogEnvironmentAllowed())->toBeTrue();

        ob_start();
        $hooks->injectPostHogSnippet();
        $out = (string) ob_get_clean();

        expect($out)->toContain('posthog.init(')
            // Surveys are the 33KB nothing in this codebase asks for.
            ->and($out)->toContain('disable_surveys:true');
    });
});
