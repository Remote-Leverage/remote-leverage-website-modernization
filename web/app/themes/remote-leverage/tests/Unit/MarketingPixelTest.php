<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Infrastructure\WordPress\Admin\PixelDeferralAdmin;
use App\Infrastructure\WordPress\Hooks\MarketingPixelHooks;
use App\Infrastructure\WordPress\Hooks\TrackingHooks;
use App\Support\PixelDeferral;

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
        // LinkedIn and OpenAI are switched off in the shipped config since 2026-09-21, TikTok
        // since 2026-09-19. All three are on here so the emission stays covered for whenever one
        // goes back on; the shipped defaults are asserted separately, per vendor.
        'pixels.linkedin.enabled' => true,
        'pixels.linkedin.partner_ids' => ['6411876'],
        'pixels.openai.enabled' => true,
        'pixels.openai.pixel_ids' => ['7QY9HDVocGyeNvMMW1gLWb'],
        'pixels.openai.debug' => false,
        'pixels.tiktok.enabled' => true,
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

describe('Microsoft UET', function () {
    test('the UET tag carries the production tag id', function () {
        // This is what stamps `msclkid`, which the lead export carries and AttributionCollector
        // stores. Without it a Bing booking still arrives, just unattributable to the paid click.
        $out = renderPixel(fn ($h) => $h->injectBingUet());

        expect($out)->toContain('ti:"97187250"')
            ->and($out)->toContain('bat.bing.com/bat.js');
    });

    test('a malformed id renders nothing rather than a broken script', function () {
        config(['pixels.bing_uet.tag_id' => 'not-a-tag']);

        expect(renderPixel(fn ($h) => $h->injectBingUet()))->toBe('');
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

    test('the LinkedIn switch emits nothing at all, head tag and noscript alike', function () {
        /*
         * Off has to mean no bytes. A `lintrk` stub with no SDK would still ship the snippet, and
         * the noscript image is a request of its own that no amount of JavaScript gating stops —
         * so both read the same id list rather than each testing the flag.
         */
        config(['pixels.linkedin.enabled' => false]);

        $hooks = new MarketingPixelHooks;

        expect($hooks->linkedInPartnerIds())->toBe([])
            ->and(renderPixel(fn ($h) => $h->injectLinkedIn()))->toBe('')
            ->and(renderPixel(fn ($h) => $h->injectLinkedInNoscript()))->toBe('');
    });

    test('the OpenAI switch takes the conversion with it', function () {
        /*
         * The conversion runs at priority 5 and pushes onto the stub priority 4 installs. If the
         * flag only gated the pixel, a thank-you page would emit an `oaiq("measure", ...)` with
         * no `oaiq` to receive it — harmless but dishonest, and it would look like the pixel was
         * still live to anyone reading the page source.
         */
        config([
            'pixels.openai.enabled' => false,
            'pixels.openai.conversions' => ['vathankyou' => 'appointment_scheduled'],
        ]);

        $_SERVER['REQUEST_URI'] = '/VAThankYou/';

        $hooks = new MarketingPixelHooks;

        expect($hooks->openAiPixelIds())->toBe([])
            ->and(renderPixel(fn ($h) => $h->injectOpenAi()))->toBe('')
            ->and(renderPixel(fn ($h) => $h->injectOpenAiConversion()))->toBe('');
    });

    test('the shipped config has both off, with the ids kept for switching back on', function () {
        /*
         * Reads the real config rather than the fixture: the point is what the site serves.
         * `LINKEDIN_PIXEL_ENABLED` / `OPENAI_PIXEL_ENABLED` are the only levers — if this starts
         * failing, somebody either set one of those or flipped a default, and both are decisions
         * worth noticing.
         */
        $config = require __DIR__.'/../../config/pixels.php';

        expect($config['linkedin']['enabled'])->toBeFalse()
            ->and($config['linkedin']['partner_ids'])->toBe(['6411876', '9514236'])
            ->and($config['openai']['enabled'])->toBeFalse()
            ->and($config['openai']['pixel_ids'])->toBe(['7QY9HDVocGyeNvMMW1gLWb', 'GtXTy8ihLz5qrMUanZ3fqf'])
            // The conversion map outlives the switch, so it is still right when one goes back on.
            ->and($config['openai']['conversions'])->toBe(['vathankyou' => 'appointment_scheduled']);
    });

    test('each vendor env var switches only its own pixel back on', function () {
        foreach (['LINKEDIN_PIXEL_ENABLED' => 'linkedin', 'OPENAI_PIXEL_ENABLED' => 'openai'] as $var => $vendor) {
            $_ENV[$var] = 'true';
            $_SERVER[$var] = 'true';
            putenv("{$var}=true");

            try {
                $config = require __DIR__.'/../../config/pixels.php';

                $other = $vendor === 'linkedin' ? 'openai' : 'linkedin';

                expect($config[$vendor]['enabled'])->toBeTrue()
                    ->and($config[$other]['enabled'])->toBeFalse();
            } finally {
                unset($_ENV[$var], $_SERVER[$var]);
                putenv($var);
            }
        }
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
            ->and($out)->toContain('gtag("config", "GT-NCNQ6N2", {"url_passthrough": true})')
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
            ->and($out)->toContain("w.addEventListener('load', flush)")
            // Interaction only schedules the flush; running Meta/gtag inside pointerdown
            // is how deferred pixels become a 500 ms field INP.
            ->and($out)->toContain('function scheduleFlush')
            ->and($out)->toContain('w.setTimeout(flush, 0)')
            // The hook a container tag retriggers on, so TikTok can be deferred without
            // leaving GTM. A tag would point at this event name.
            ->and($out)->toContain("event: 'rl_idle'");
    });

    test('Meta and the Google tag defer the SDK fetch and keep the stub synchronous', function () {
        /*
         * Phase 3 of docs/performance-homepage-plan.md. Conversions already travel
         * server-side; what these two uniquely cost on the critical path is PageView
         * collection, and a PSI mobile run on 2026-09-22 measured that as 5.4 s LCP.
         * The stub still has to be synchronous — a `fbq('track')` / `gtag('event')`
         * before the SDK arrives has to queue.
         */
        config(['pixels.defer.vendors' => ['meta', 'google_tag']]);

        $hooks = new MarketingPixelHooks;

        expect($hooks->deferredVendors())->toBe(['meta', 'google_tag'])
            ->and($hooks->isDeferred('meta'))->toBeTrue()
            ->and($hooks->isDeferred('google_tag'))->toBeTrue();

        $meta = renderPixel(fn (MarketingPixelHooks $h) => $h->injectMetaPixel());

        expect($meta)->toContain('rlDefer')
            ->and(strpos($meta, "fbq('init', '1430907207548734')"))->toBeLessThan(strpos($meta, 'rlDefer'))
            ->and(strpos($meta, "fbq('track', 'PageView')"))->toBeLessThan(strpos($meta, 'rlDefer'))
            ->and(strpos($meta, 'connect.facebook.net'))->toBeGreaterThan(strpos($meta, 'rlDefer'));

        $gtag = renderPixel(fn (MarketingPixelHooks $h) => $h->injectGoogleTag());

        expect($gtag)->toContain('rlDefer')
            ->and(strpos($gtag, 'function gtag()'))->toBeLessThan(strpos($gtag, 'rlDefer'))
            ->and(strpos($gtag, 'gtag("config", "GT-NCNQ6N2"'))->toBeLessThan(strpos($gtag, 'rlDefer'))
            ->and(strpos($gtag, 'googletagmanager.com/gtag/js?id=GT-NCNQ6N2'))->toBeGreaterThan(strpos($gtag, 'rlDefer'))
            ->and($gtag)->not->toContain('<script async src="https://www.googletagmanager.com/gtag/js');
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

    test('the shipped defaults defer UET and the Google tag, keep Meta immediate, and wait past LCP', function () {
        $config = require dirname(__DIR__, 2).'/config/pixels.php';

        expect($config['defer']['vendors'])->toBe(['bing_uet', 'google_tag'])
            ->and($config['defer']['timeout_ms'])->toBe(6000);
    });

    test('no environment variable can reach the deferral any more', function () {
        /*
         * PIXEL_DEFER_VENDORS crossed four hops to reach PHP and silently dropped unknown
         * names; production shipped with nothing deferred while the secret said otherwise.
         * Settings → Marketing Pixels owns it now.
         */
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/config/pixels.php');

        expect($src)->not->toContain("env('PIXEL_DEFER");
    });
});

describe('Settings → Marketing Pixels', function () {
    afterEach(function () {
        delete_option(PixelDeferral::VENDORS_OPTION);
        delete_option(PixelDeferral::TIMEOUT_OPTION);
    });

    test('config supplies the defaults until the screen is saved', function () {
        config(['pixels.defer.vendors' => ['bing_uet', 'google_tag'], 'pixels.defer.timeout_ms' => 6000]);

        expect(PixelDeferral::vendors())->toBe(['google_tag', 'bing_uet'])
            ->and(PixelDeferral::timeoutMs())->toBe(6000);
    });

    test('a saved selection wins over config, and reaches the emitted markup', function () {
        config(['pixels.defer.vendors' => ['bing_uet', 'google_tag']]);
        update_option(PixelDeferral::VENDORS_OPTION, ['meta']);
        update_option(PixelDeferral::TIMEOUT_OPTION, 2500);

        expect((new MarketingPixelHooks)->deferredVendors())->toBe(['meta'])
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectDeferBootstrap()))->toContain('w.setTimeout(flush, 2500)')
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectMetaPixel()))->toContain('rlDefer')
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectGoogleTag()))->not->toContain('rlDefer');
    });

    test('saving with every box unticked defers nothing, rather than falling back to config', function () {
        config(['pixels.defer.vendors' => ['bing_uet', 'google_tag']]);
        update_option(PixelDeferral::VENDORS_OPTION, PixelDeferralAdmin::sanitizeVendors(null));

        expect(PixelDeferral::vendors())->toBe([])
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectDeferBootstrap()))->toBe('');
    });

    test('the sanitizers drop unknown vendors and clamp the timeout', function () {
        expect(PixelDeferralAdmin::sanitizeVendors(['google_tag', 'uet', 'meta', '<script>']))->toBe(['meta', 'google_tag'])
            ->and(PixelDeferralAdmin::sanitizeTimeout('-5'))->toBe(0)
            ->and(PixelDeferralAdmin::sanitizeTimeout('999999'))->toBe(30000)
            ->and(PixelDeferralAdmin::sanitizeTimeout('abc'))->toBe(6000);
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

    test('the switch emits nothing at all, not a dormant stub', function () {
        /*
         * Off has to mean no bytes. A `ttq` stub with no `load()` would still ship the snippet
         * and still queue a PageView that drains the moment anything else loads events.js.
         */
        config(['pixels.tiktok.enabled' => false]);

        $hooks = new MarketingPixelHooks;

        expect($hooks->tikTokPixelIds())->toBe([])
            ->and(renderPixel(fn (MarketingPixelHooks $h) => $h->injectTikTok()))->toBe('');
    });

    test('the shipped config has it off, with the id kept for switching back on', function () {
        /*
         * Reads the real config rather than the fixture: the point is what the site serves.
         * `TIKTOK_PIXEL_ENABLED` is the only lever — if this starts failing, somebody either set
         * that variable or flipped the default, and both are decisions worth noticing.
         */
        $config = require __DIR__.'/../../config/pixels.php';

        expect($config['tiktok']['enabled'])->toBeFalse()
            ->and($config['tiktok']['pixel_ids'])->toBe(['CPMB51BC77U75I0QMMAG']);
    });

    test('TIKTOK_PIXEL_ENABLED switches it back on', function () {
        $_ENV['TIKTOK_PIXEL_ENABLED'] = 'true';
        $_SERVER['TIKTOK_PIXEL_ENABLED'] = 'true';
        putenv('TIKTOK_PIXEL_ENABLED=true');

        try {
            $config = require __DIR__.'/../../config/pixels.php';

            expect($config['tiktok']['enabled'])->toBeTrue();
        } finally {
            unset($_ENV['TIKTOK_PIXEL_ENABLED'], $_SERVER['TIKTOK_PIXEL_ENABLED']);
            putenv('TIKTOK_PIXEL_ENABLED');
        }
    });
});

describe('defaults that used to depend on an unset variable', function () {
    /*
     * Both of these were dark on production with no error anywhere, because an unset
     * environment variable is indistinguishable from a deliberate opt-out. Neither value is a
     * secret — both ship in the page HTML — so both are defaulted in config and asserted here.
     */
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
            'services.posthog.flag_environments' => ['local', 'development', 'staging'],
        ]);

        $hooks = new TrackingHooks;

        /*
         * `development` is in `flag_environments` by default since 2026-09-22, so it now emits a
         * flags-only snippet rather than nothing at all — see docs/ab-testing.md. The guarantee
         * this test exists to protect is unchanged and asserted below: nothing outside
         * `environments` may write into the PostHog project.
         */
        $GLOBALS['wp_environment_type'] = 'development';
        expect($hooks->postHogEnvironmentAllowed())->toBeFalse()
            ->and($hooks->postHogFlagsOnly())->toBeTrue();

        ob_start();
        $hooks->injectPostHogSnippet();
        $devOut = (string) ob_get_clean();

        expect($devOut)->toContain('before_send = function () { return null; }')
            ->and($devOut)->toContain('"disable_session_recording":true')
            ->and($devOut)->toContain('"autocapture":false');

        // An environment in neither list still emits nothing whatsoever.
        $GLOBALS['wp_environment_type'] = 'qa-sandbox';
        expect($hooks->postHogSnippetAllowed())->toBeFalse();

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
            ->and($out)->toContain('"disable_surveys":true')
            // Production captures for real: the flags-only suppression must not leak into it.
            ->and($out)->not->toContain('before_send')
            ->and($out)->toContain('/static/array.js')
            /*
             * array.js is no longer deferred. It was held back to interaction/idle/load until
             * 2026-09-22, which made feature flags unreadable during render and so made a
             * client-side A/B test impossible. If `requestIdleCallback` comes back here, the
             * experiments in docs/ab-testing.md stop deciding before paint.
             */
            ->and($out)->not->toContain('requestIdleCallback')
            ->and($out)->not->toContain('parentNode.insertBefore');

        expect(strpos($out, 'posthog.init('))
            ->toBeLessThan(strpos($out, '/static/array.js'));
    });

    test('Customer.io is gated to production, now that its write key has a default', function () {
        config([
            'services.customer_io.cdp_write_key' => 'ebb5281c53e9fca6b1a5',
            'services.customer_io.environments' => ['production'],
        ]);

        $hooks = new TrackingHooks;

        $GLOBALS['wp_environment_type'] = 'staging';
        expect($hooks->customerIoEnvironmentAllowed())->toBeFalse();

        ob_start();
        $hooks->injectCustomerIOSnippet();
        expect((string) ob_get_clean())->toBe('');

        $GLOBALS['wp_environment_type'] = 'production';
        expect($hooks->customerIoEnvironmentAllowed())->toBeTrue();

        ob_start();
        $hooks->injectCustomerIOSnippet();
        $out = (string) ob_get_clean();

        expect($out)->toContain('cioanalytics')
            ->and($out)->toContain('cdp.customer.io');
    });
});

describe('an empty environment variable falls through to the default', function () {
    /*
     * The bug that took PostHog dark on production on 2026-09-18, minutes after its GTM tag was
     * deleted. `env(X, 'default')` only applies the default when X is *absent*; production
     * carries an empty `POSTHOG_API_KEY`, and an empty string beat the default underneath it.
     *
     * Asserted by re-reading the config files with the variables set to empty, because the
     * mistake this catches is somebody reverting one of them to a plain `env()` default.
     */
    test('every public pixel id survives its variable being present but empty', function () {
        $vars = [
            'META_PIXEL_IDS', 'BING_UET_TAG_ID', 'LINKEDIN_PARTNER_IDS', 'OPENAI_PIXEL_IDS',
            'TIKTOK_PIXEL_IDS', 'GOOGLE_TAG_IDS', 'GOOGLE_TAG_LINKER_DOMAINS',
        ];

        foreach ($vars as $var) {
            $_ENV[$var] = '';
            $_SERVER[$var] = '';
            putenv("{$var}=");
        }

        try {
            $config = require __DIR__.'/../../config/pixels.php';

            // The one pixel since 2026-09-26, which replaced 1430907207548734 and
            // 1482937899395718; see config/pixels.php for what did not move with them.
            expect($config['meta']['pixel_ids'])->toBe(['1821781852398281'])
                ->and($config['bing_uet']['tag_id'])->toBe('97187250')
                // Both accounts are emitted here since GTM-53JDTQCZ was retired.
                ->and($config['linkedin']['partner_ids'])->toBe(['6411876', '9514236'])
                ->and($config['openai']['pixel_ids'])->toBe(['7QY9HDVocGyeNvMMW1gLWb', 'GtXTy8ihLz5qrMUanZ3fqf'])
                ->and($config['tiktok']['pixel_ids'])->toBe(['CPMB51BC77U75I0QMMAG'])
                ->and($config['google_tag']['ids'])->toBe(['GT-NCNQ6N2']);
        } finally {
            foreach ($vars as $var) {
                unset($_ENV[$var], $_SERVER[$var]);
                putenv($var);
            }
        }
    });

    test('the PostHog key survives an empty POSTHOG_API_KEY', function () {
        $_ENV['POSTHOG_API_KEY'] = '';
        $_SERVER['POSTHOG_API_KEY'] = '';
        putenv('POSTHOG_API_KEY=');

        try {
            $config = require __DIR__.'/../../config/services.php';

            expect($config['posthog']['api_key'])->toBe('phc_3PbasnDYndH8YVEky0ksHrB3SFwBZKmzkf5bl37o8u0');
        } finally {
            unset($_ENV['POSTHOG_API_KEY'], $_SERVER['POSTHOG_API_KEY']);
            putenv('POSTHOG_API_KEY');
        }
    });
});

describe('HubSpot browser tracking is gone', function () {
    test('there is no injector and no config key to switch back on', function () {
        /*
         * Removed 2026-09-19. It was the most expensive script on the site — 5,582ms of
         * main-thread time on throttled mobile — and the flag that was meant to hold it off did
         * not, because it was "is HUBSPOT_PORTAL_ID empty" and `HubSpotGateway` reads that same
         * variable as a credential. Asserted structurally for that reason: an empty-value check
         * is exactly what failed last time.
         */
        $config = require __DIR__.'/../../config/pixels.php';

        expect($config)->not->toHaveKey('hubspot')
            ->and(method_exists(MarketingPixelHooks::class, 'injectHubSpot'))->toBeFalse();
    });

    test('server-side CRM sync keeps its own credentials, untouched', function () {
        /*
         * The whole point of removing the browser script rather than blanking the portal id:
         * contacts still sync. Different config file, different key, unaffected.
         */
        $services = require __DIR__.'/../../config/services.php';

        expect($services)->toHaveKey('hubspot');
    });

    test('HubSpot is no longer a deferrable vendor', function () {
        config(['pixels.defer.vendors' => ['hubspot']]);

        expect((new MarketingPixelHooks)->deferredVendors())->toBe([]);
    });
});
