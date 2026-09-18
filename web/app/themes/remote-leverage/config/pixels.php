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
 * ## An empty variable is not a configuration
 *
 * Every id below reads `trim((string) env(X, '')) ?: '<default>'` rather than `env(X, '<default>')`.
 * An `env()` default only applies when the variable is **absent**; a variable that is present and
 * empty wins, and silently turns the pixel off. That is not theoretical — it took PostHog dark on
 * 2026-09-18 the moment its GTM tag was deleted, because production carries an empty
 * `POSTHOG_API_KEY` and the default underneath never got a chance. An empty value means "not
 * configured", so it falls through to the default here.
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
            explode(',', trim((string) env('META_PIXEL_IDS', '')) ?: '1430907207548734,1482937899395718'),
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
        'tag_id' => trim((string) env('BING_UET_TAG_ID', '')) ?: '97187250',
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
            explode(',', trim((string) env('LINKEDIN_PARTNER_IDS', '')) ?: '6411876,9514236'),
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
            explode(',', trim((string) env('OPENAI_PIXEL_IDS', '')) ?: '7QY9HDVocGyeNvMMW1gLWb,GtXTy8ihLz5qrMUanZ3fqf'),
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
    | Re-verified 2026-09-18 against the published container:
    |
    |  - The container **does** publish a Google tag of its own --
    |    `AW-11406183013`, with `send_to: G-SCP464C5EH`. So this tag is not the
    |    site's only gtag loader; the two overlap on both of those destinations.
    |
    |    An earlier revision of this note claimed the opposite, on the strength
    |    of grepping `GTM-53JDTQCZ` for `AW-11406183013` and finding nothing.
    |    The grep was wrong, not the container: GTM stores the id as
    |    `["template","AW-",["macro",5]]`, so the prefix and the number are
    |    separate strings and the joined form appears nowhere in the payload.
    |    **Grep the bare number.**
    |  - `G-JFBLS33ET8` really is reachable through nothing else -- it appears
    |    nowhere in the container under any spelling. That is still the reason
    |    this tag has to stay.
    |  - `AW-1140618301` is **not** a typo of `AW-11406183013`. It appears in the
    |    gtag routing map as its own `publicId`, so both are live destinations.
    |
    | Cost: this tag fans out to four destination configs, ~730KB transferred on
    | a cold load, and the container's Google tag duplicates two of them. The
    | overlap is worth removing, but which side to cut is a question for the ads
    | accounts and GTM Preview rather than something to infer from a payload.
    |
    | Emitted here rather than by Site Kit's Analytics module, so that one
    | mechanism owns page-level tags and the module stays disconnected.
    */
    'google_tag' => [
        'ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', trim((string) env('GOOGLE_TAG_IDS', '')) ?: 'GT-NCNQ6N2'),
        ))),
        'linker_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', trim((string) env('GOOGLE_TAG_LINKER_DOMAINS', '')) ?: 'remoteleverage.com'),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | TikTok Pixel
    |--------------------------------------------------------------------------
    |
    | Moved out of `GTM-53JDTQCZ` on 2026-09-18. It was the largest single
    | non-Google third party on the page — 162KB transferred, 94ms of blocking
    | time — and a tag inside the container cannot have its fetch held back
    | from here. Emitted as code, it goes through the `defer` block below like
    | every other pixel.
    |
    | **The GTM tag must be deleted in the same change.** Two TikTok pixels on
    | one page is two PageViews into the account the bidding optimises against.
    | `MarketingPixelTest` asserts this id is not also in `delivered_by_gtm` —
    | that guard catches the config mistake, not a tag left live in GTM.
    |
    | If marketing needs to change this without a deploy, put it back in the
    | container and retrigger it on the `rl_idle` event the defer bootstrap
    | pushes. That keeps the deferral and hands back the control.
    */
    'tiktok' => [
        'pixel_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', trim((string) env('TIKTOK_PIXEL_IDS', '')) ?: 'CPMB51BC77U75I0QMMAG'),
        ))),
        'track_page_view' => filter_var(env('TIKTOK_TRACK_PAGE_VIEW', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rewardful
    |--------------------------------------------------------------------------
    |
    | Referral attribution. Ported out of the container on 2026-09-18 verbatim,
    | including appending to `body` rather than `head` as its snippet does.
    |
    | `rewardful_id` on a lead comes from this; without it a referred booking
    | still arrives, it just stops paying the person who referred it.
    */
    'rewardful' => [
        'api_key' => trim((string) env('REWARDFUL_API_KEY', '')) ?: '39ea7a',
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent defaults
    |--------------------------------------------------------------------------
    |
    | What the container's "Consent - Default Granted" tag set, reproduced
    | exactly: both granted, on initialisation.
    |
    | This is not a consent manager and does not pretend to be one. It is the
    | default state Google's tags read before any CMP speaks. If a real CMP is
    | ever added it has to run ahead of this and set these itself.
    */
    'consent' => [
        'enabled' => filter_var(env('PIXEL_CONSENT_DEFAULTS', true), FILTER_VALIDATE_BOOLEAN),
        'defaults' => [
            'ad_storage' => 'granted',
            'analytics_storage' => 'granted',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversions and events, ported out of GTM-53JDTQCZ
    |--------------------------------------------------------------------------
    |
    | Read off the published container on 2026-09-18 before it was retired, tag
    | by tag, so these are transcriptions rather than reconstructions. The
    | labels are the part that cannot be guessed: a wrong one reports a
    | conversion into nothing and the only symptom is a campaign that looks bad.
    |
    | `trigger` is one of:
    |
    |   path:<fragment>   request path contains <fragment>, case-insensitively.
    |                     Rendered server-side on that page, so it cannot miss.
    |   dl:<event>        a `dataLayer` push with that `event` name, which is
    |                     what GTM's Custom Event triggers watched. The wizard
    |                     already pushes `form_submit` on a successful capture
    |                     -- see `MultistepBookingWizard::pushToDataLayer()`.
    |   click_text:<s>    a click whose element text contains <s>.
    |   click_class:<s>   a click whose element classes contain <s>.
    |
    | The container matched `VAThankYou` case-sensitively and the wizard's
    | redirect preserves the capitals. Matched case-insensitively here for the
    | same reason `openai.conversions` is -- there is no reason to inherit a
    | trap we already had to work around once.
    */
    'google_ads' => [
        'conversion_id' => trim((string) env('GOOGLE_ADS_CONVERSION_ID', '')) ?: 'AW-11406183013',

        /*
         * `label => [trigger, value]`. Value is null where the container sent
         * none; only the second VAThankYou conversion carried one.
         */
        'conversions' => [
            ['label' => 'AyW6CJHZnr8ZEOWU8r4q', 'trigger' => 'path:VAThankYou', 'value' => null],
            ['label' => 'pTbrCP6-_dIbEOWU8r4q', 'trigger' => 'path:VAThankYou', 'value' => '1'],
            ['label' => 'oEXvCN-QnpcbEOWU8r4q', 'trigger' => 'click_text:Book a Consultation', 'value' => null],
            ['label' => 'oqW3CP3jnJcbEOWU8r4q', 'trigger' => 'dl:form_submit', 'value' => null],
        ],
    ],

    'ga4' => [
        'measurement_id' => trim((string) env('GA4_MEASUREMENT_ID', '')) ?: 'G-SCP464C5EH',

        'events' => [
            ['name' => 'appointment_booked', 'trigger' => 'path:VAThankYou'],
            ['name' => 'generate_lead', 'trigger' => 'dl:form_submit'],
            ['name' => 'Book a Consultation Click', 'trigger' => 'click_text:Book a Consultation'],

            /*
             * Revived on 2026-09-18. The container triggered this on
             * `e-font-icon-svg e-eicon-play`, an Elementor play icon that v2
             * never renders, so it had reported nothing since the cutover --
             * the zero meant "trigger is broken", not "nobody watches".
             *
             * It now fires on a `video_play` dataLayer push, which covers all
             * four players: the runtime turns any `<video>` play into one
             * (sample-applicant-videos, media-copy), and the two that are not
             * `<video>` elements push it themselves (testimonials' modal,
             * case-study's Vimeo embed).
             */
            ['name' => 'watched_testimonial_video', 'trigger' => 'dl:video_play'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PostHog events, ported out of GTM-53JDTQCZ
    |--------------------------------------------------------------------------
    |
    | The container's "PostHog - Meeting Booked Web" tag, verbatim: it captured
    | `appointment_booked_web` with the page path. PostHog itself is loaded by
    | `TrackingHooks`, so this is only the event.
    */
    'posthog_events' => [
        ['name' => 'appointment_booked_web', 'trigger' => 'path:VAThankYou'],
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
    | the earliest of first user interaction, `window` load, browser idle, or
    | `timeout_ms`. Load is included so a real visit that paints in ~2s does
    | not wait out the Lighthouse-oriented ceiling; the timeout is the floor
    | for a lab run that never goes idle and never fires `load` before LCP.
    |
    | Two are deliberately NOT deferrable, and adding them here does nothing:
    |
    |  - **meta** -- Facebook is 67% of paid acquisition. A PageView that lands
    |    late is still counted, but this is not the pixel to experiment on.
    |  - **google_tag** -- GA4 and Google Ads conversions should not wait behind
    |    an idle callback, and the tag is already `async` so it costs no parse
    |    time. See the Google tag block above for how it overlaps the
    |    container's own Google tag.
    |
    | The flush also pushes `rl_idle` onto `dataLayer`, which is the intended
    | way to defer a tag that lives in the container rather than here: retrigger
    | it on that custom event instead of on `gtm.js`. TikTok (162KB, the largest
    | single non-Google third party) is the reason that hook exists, and it is
    | in the default vendor list for that reason.
    */
    'defer' => [
        'vendors' => array_values(array_filter(array_map(
            'trim',
            explode(',', trim((string) env('PIXEL_DEFER_VENDORS', '')) ?: 'linkedin,openai,hubspot,bing_uet,tiktok'),
        ))),

        /*
         * Upper bound on the wait, in milliseconds. 6000 is past the hire-va
         * LCP window on Slow 4G (5.7s staging, 13s production on 2026-09-18).
         * The previous 2500ms ceiling flushed during LCP, so deferred pixels
         * still fought the hero image. `?:` so an empty PIXEL_DEFER_TIMEOUT_MS
         * falls through the same way the pixel ids do.
         */
        'timeout_ms' => max(0, (int) (trim((string) env('PIXEL_DEFER_TIMEOUT_MS', '')) ?: 6000)),
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
    | **TikTok left this list on 2026-09-18** — it is emitted above so that it
    | can be deferred. The container tag must be deleted to match.
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
        // Empty since 2026-09-18: GTM-53JDTQCZ was retired and everything it carried is emitted
        // here. Kept as a key, not deleted, because `MarketingPixelTest` asserts against it and
        // the day a tag goes back into a container is the day this needs to be populated again.
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
        /*
         * **Off by default since 2026-09-18.** Set `HUBSPOT_PORTAL_ID` to switch it back on;
         * `243484989` is the production portal.
         *
         * It was defaulted on earlier the same day, because an unset variable had left browser
         * tracking silently dark. Measuring it once it ran settled the question the other way:
         * `hs-analytics.net` was the single most expensive script on the page, 5,582ms of
         * main-thread time on a throttled mobile profile against 189ms on desktop. The absolute
         * figure is inflated by contention -- a 29x gap is far more than 4x throttling explains
         * -- but it was the top entry either way, and it is the only script here whose cost is
         * measured in seconds.
         *
         * What is lost is page-view history and visitor de-anonymisation. Contacts themselves
         * are unaffected: `HubSpotGateway` creates and updates them server-side from
         * `HUBSPOT_ACCESS_TOKEN`, which is a different credential and stays in the environment.
         */
        'portal_id' => trim((string) env('HUBSPOT_PORTAL_ID', '')),
        'region' => trim((string) env('HUBSPOT_SCRIPT_REGION', '')) ?: 'na2',
    ],
];
