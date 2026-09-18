<?php

/**
 * Marketing pixels that production loads **outside** GTM, as code.
 *
 * Audited against production's live HTML on 2026-09-17. Everything here is hardcoded into the
 * Elementor-era pages by a plugin or the legacy theme rather than delivered by a container — so
 * unlike the tags inside `GTM-53JDTQCZ`, none of it survives the cutover on its own. Each one
 * disappears the moment v2 serves the page.
 *
 * That matters most for Meta: Facebook is the single largest lead source in the Gravity backfill
 * (2,643 of 3,969 leads, 67%), and its pixel is **not** in either GTM container. Losing it means
 * losing conversion signal on two thirds of paid acquisition, with nothing in the container to
 * put it back.
 *
 * ## These belong in GTM eventually
 *
 * A pixel is exactly what a tag manager is for, and the container is where marketing can change
 * one without a deploy. This file exists because the containers do not currently carry them and
 * a cutover cannot wait on someone editing GTM.
 *
 * **If you move one of these into the container, blank it here in the same change.** Production
 * already demonstrates what happens otherwise — it loads LinkedIn Insight twice, from the page
 * *and* from GTM, under two different partner ids (`6411876` hardcoded, `9514236` in the
 * container), and the OpenAI pixel the same way. Nobody meant to do that either time.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | Which `wp_get_environment_type()` values load these. Falls back to
    | GTM_ENVIRONMENTS so that one switch moves the whole tracking surface
    | together; a staging environment that fires GTM but not Meta, or the
    | reverse, is a difference nobody will remember making.
    |
    | Production only by default: these are conversion pixels, and a test
    | booking on staging teaches the ad platforms from traffic that was never
    | a customer.
    */
    'environments' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PIXEL_ENVIRONMENTS', env('GTM_ENVIRONMENTS', 'production'))),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Meta (Facebook) Pixel
    |--------------------------------------------------------------------------
    |
    | Production fires two, both on every page, both with a PageView. Two is
    | deliberate here only because it is what production does — whether both
    | accounts are still wanted is a question for whoever owns the ad spend,
    | and dropping one is a one-line change once somebody answers it.
    |
    | Not in GTM. Verified 2026-09-17 against both containers.
    */
    'meta' => [
        'pixel_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('META_PIXEL_IDS', '1430907207548734,1482937899395718')),
        ))),
        'track_page_view' => filter_var(env('META_TRACK_PAGE_VIEW', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Advertising UET
    |--------------------------------------------------------------------------
    |
    | Hardcoded on production, not in GTM. This is where the `msclkid` values
    | in the lead export come from, so without it Bing-sourced bookings stop
    | being attributable — 35 leads in the backfill, small but paid.
    */
    'bing_uet' => [
        'tag_id' => (string) env('BING_UET_TAG_ID', '97187250'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LinkedIn Insight
    |--------------------------------------------------------------------------
    |
    | Production fires LinkedIn twice, into two different accounts: partner
    | `6411876` hardcoded in the page header block, and `9514236` from GTM
    | tag 64. Nobody appears to have meant that.
    |
    | Which is "correct" could not be determined from outside: LinkedIn's
    | beacon returns 302 for any partner id including invented ones, none of
    | the 3,969 imported leads carries an `li_fat_id`, and the container only
    | tells us its tag was among the most recently created. The answer lives in
    | LinkedIn Campaign Manager, under Account Assets -> Insight Tag.
    |
    | So both are kept firing, which is precisely what production does today.
    | Dropping the wrong one takes an ad account dark with no error anywhere;
    | keeping both changes nothing about current behaviour.
    |
    | Only the hardcoded id is emitted here. The other keeps arriving from the
    | container -- see `delivered_by_gtm` below, which is enforced by a test.
    */
    'linkedin' => [
        'partner_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LINKEDIN_PARTNER_IDS', '6411876')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI pixel
    |--------------------------------------------------------------------------
    |
    | The same split as LinkedIn: `7QY9HDVocGyeNvMMW1gLWb` hardcoded in the
    | page, `GtXTy8ihLz5qrMUanZ3fqf` from GTM tag 65. Same reasoning, same
    | resolution -- both stay live until someone reads the real one off the ads
    | account.
    |
    | `debug` defaults to false. Production sends `debug:true`, which is almost
    | certainly a paste that was never cleaned up rather than a decision.
    */
    'openai' => [
        'pixel_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPENAI_PIXEL_IDS', '7QY9HDVocGyeNvMMW1gLWb')),
        ))),
        'debug' => filter_var(env('OPENAI_PIXEL_DEBUG', false), FILTER_VALIDATE_BOOLEAN),

        /*
         * Conversions, as `request path fragment => event name`.
         *
         * The legacy form fired `oaiq("measure", "appointment_scheduled",
         * {type: "customer_action"})` from
         * rl-elementor-blocks/assets/js/headless-calendly-multistep.js on
         * `gform_confirmation_loaded` — the moment the booking confirmed. It is
         * **not** in the GTM container, so nothing else reproduces it.
         *
         * Without it the OpenAI pixel records page views and zero conversions,
         * and ChatGPT ads optimise against nothing. v2's equivalent of that
         * confirmation is the thank-you page load.
         *
         * Matched case-insensitively here, unlike the GTM trigger — this is our
         * own comparison and there is no reason to inherit that trap.
         */
        'conversions' => [
            'vathankyou' => 'appointment_scheduled',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google tag (gtag.js)
    |--------------------------------------------------------------------------
    |
    | Production's Site Kit loads `GT-NCNQ6N2` directly in the page, separately
    | from GTM. Resolving that tag's own payload shows it routes to:
    |
    |   G-SCP464C5EH    GA4 -- the container references this property directly
    |   AW-11406183013  Google Ads
    |   AW-1140618301   Google Ads -- a second, separately configured account
    |   G-JFBLS33ET8    GA4 -- reachable through NOTHING ELSE
    |
    | `G-JFBLS33ET8` is the reason this is here: without it a whole GA4 property
    | goes dark at cutover and the first sign is a flat graph nobody is watching.
    |
    | Re-verified 2026-09-18 by resolving each payload. Two corrections to the
    | note that used to sit here:
    |
    |  - The container carries **no Google tag of its own** -- no `AW-` or `GT-`
    |    id appears anywhere in `GTM-53JDTQCZ`. So this tag is the site's only
    |    gtag loader and the container's GA4 tags piggyback on it. That is why
    |    it is deliberately absent from the `defer` block below.
    |  - `AW-1140618301` is **not** a typo of `AW-11406183013`. It appears in the
    |    routing map as its own `publicId`, so both are live destinations.
    |
    | Cost: one Google tag fanning out to four destinations pulls four config
    | payloads, ~730KB transferred on a cold load. Trimming that means unlinking
    | a destination in the Google tag UI -- there is nothing to change here.
    |
    | Emitted here rather than by Site Kit's Analytics module, so that one
    | mechanism owns page-level tags and the module stays disconnected.
    */
    'google_tag' => [
        'ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_TAG_IDS', 'GT-NCNQ6N2')),
        ))),
        'linker_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_TAG_LINKER_DOMAINS', 'remoteleverage.com')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deferred SDK loading
    |--------------------------------------------------------------------------
    |
    | Which pixels wait before fetching their SDK. Script *evaluation* is the
    | expensive part of this page -- 4,959ms of main-thread work in the
    | 2026-09-18 Lighthouse run -- and six pixels all fetching at `wp_head`
    | priority 4 contend for it during the load.
    |
    | **No event is lost by deferring.** Every vendor here installs a queueing
    | stub and drains it when the SDK arrives, so the stub and the `init` /
    | `track` calls still run immediately and only the network fetch waits. See
    | `MarketingPixelHooks::injectDeferBootstrap()` for the flush conditions:
    | the earliest of first user interaction, browser idle, or `timeout_ms`.
    |
    | Two are deliberately NOT deferrable, and adding them here does nothing:
    |
    |  - **meta** -- Facebook is 67% of paid acquisition. A PageView that lands
    |    late is still counted, but this is not the pixel to experiment on.
    |  - **google_tag** -- it is the site's only gtag loader and the container's
    |    GA4 tags piggyback on it. See the Google tag block above.
    |
    | The flush also pushes `rl_idle` onto `dataLayer`, which is the intended
    | way to defer a tag that lives in the container rather than here: retrigger
    | it on that custom event instead of on `gtm.js`. TikTok (162KB, the largest
    | single non-Google third party) is the reason that hook exists.
    */
    'defer' => [
        'vendors' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PIXEL_DEFER_VENDORS', 'linkedin,openai,hubspot,bing_uet')),
        ))),

        /*
         * Upper bound on the wait, in milliseconds. `requestIdleCallback`
         * normally fires well inside this; the timeout is the floor for a page
         * that never goes idle, which is precisely the busy page that made
         * deferring worth doing.
         */
        'timeout_ms' => max(0, (int) env('PIXEL_DEFER_TIMEOUT_MS', 2500)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Already delivered by GTM-53JDTQCZ
    |--------------------------------------------------------------------------
    |
    | Not configuration -- a declaration of what the container already fires, so
    | that adding one of these above is caught rather than discovered in a
    | billing report. `MarketingPixelTest` asserts the emitted ids never
    | intersect this list.
    |
    | Production is the cautionary tale: it runs LinkedIn and OpenAI twice each
    | because two people solved the same problem in two places, years apart,
    | and nothing anywhere said so.
    |
    | If a tag is removed from the container, move its id up into the relevant
    | block in the same change.
    |
    | **PostHog left this list on 2026-09-18.** It is now loaded by
    | `TrackingHooks::injectPostHogSnippet()` from `POSTHOG_API_KEY`, because
    | theme code has a hard runtime dependency on `window.posthog` -- the
    | booking wizard reads the session id for the replay link, and
    | `resources/js/payment-gateway.js` dispatches funnel events to it. A
    | dependency of our own conversion path should not be editable by whoever
    | owns the container. **The GTM tag must be deleted in the same change** or
    | PostHog initialises twice.
    */
    'delivered_by_gtm' => [
        'linkedin' => ['9514236'],
        'openai' => ['GtXTy8ihLz5qrMUanZ3fqf'],
        'tiktok' => ['CPMB51BC77U75I0QMMAG'],
        'statcounter' => ['13176576'],
        'rewardful' => ['39ea7a'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HubSpot tracking code
    |--------------------------------------------------------------------------
    |
    | The browser-side script, which is a different thing from the server-side
    | CRM sync `HubSpotGateway` already does with `HUBSPOT_ACCESS_TOKEN`. This
    | one does page-view history and visitor de-anonymisation; without it a
    | contact created by the API arrives with no browsing history attached.
    |
    | Portal id is shared with the server-side integration, so it is read from
    | the same variable rather than duplicated. The region prefix is part of
    | the script host and differs per account: production serves `na2`.
    */
    'hubspot' => [
        'portal_id' => (string) env('HUBSPOT_PORTAL_ID', ''),
        'region' => (string) env('HUBSPOT_SCRIPT_REGION', 'na2'),
    ],
];
