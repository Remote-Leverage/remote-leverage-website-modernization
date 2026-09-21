# Tracking event matrix — three-way audit

Every analytics or marketing event the site fires, across three sources, audited **2026-09-17**.

| Source | What was read |
| :--- | :--- |
| **Production (live)** | `remoteleverage.com` HTML on 12 pages; both GTM containers' published payloads (`googletagmanager.com/gtm.js?id=…`, public); the legacy form JS under `wp-content/plugins/rl-elementor-blocks/assets/js/` |
| **Backup (2026-08-27)** | `~/Downloads/c8a2dcdb-…_full_2026-08-27T03_04_40Z` — `wp-content/` in full, plus `mwp_db/db_dom339104.sql` |
| **v2** | This theme |

> **The backup's SQL dump is truncated.** It holds 49 tables, alphabetically `actionscheduler_actions` … `options`, and stops there — verified by `grep -c '^CREATE TABLE'` and by the absence of any `_posts` or `_snippets` CREATE. Table prefix is `wp_add9221751_`, not `wp_`.
>
> So `wp_posts` (WPCode snippet bodies), `wp_snippets` (Code Snippets — **the plugin is active**), `wp_usermeta` and every `rl_*` custom table are **not in this audit**. That was the blind spot. **It has since been closed over SSH for `snippets` and the `wpcode` posts** — see the next section; `postmeta` remains unscanned by choice, because a full `LIKE` sweep of a 2.7 GB table on a live site is not a safe thing to run.

## Production SSH audit, 2026-09-17 — what it corrected

Read-only session against the live server. Four things the offline sources got wrong or could not see:

**1. The backup is materially stale.** `themes/hello-theme-child/functions.php` was modified
**2026-09-05**, after the backup was taken, and no longer contains the GTM loader or any pixel.
GTM is now delivered by **Site Kit**, which is why the live HTML carries
`<!-- Google Tag Manager snippet added by Site Kit -->`. Any statement sourced from the backup's
`functions.php` is describing a site that no longer exists.

**2. The Code Snippets blind spot is closed, and it is empty.** `wp_add9221751_snippets` holds 8
rows, **2 active**, neither doing any tracking: "Redact and Download Resumes" (hooks
`wpforms_process_complete_14353` — WPForms is not installed, so it is dead code, and its
`redact_resume()` returns an undefined variable) and "Expose Yoast SEO meta to REST API".

**3. The LinkedIn partner id is very likely `9514236`.** WPCode draft #42187 is titled
*"Linkedin Pixel (Already inserted as GTM)"* and its body carries `9514236` — someone went to
paste the pixel, found it already in the container, and left the draft. That makes `9514236` the
one being actively deployed and `6411876` the older leftover.

Not changed here, deliberately: `6411876` is still firing on production today, and a draft title
is strong evidence rather than proof. Confirm in LinkedIn Campaign Manager -> Account Assets ->
Insight Tag, then drop `6411876` from `config/pixels.php`. Both keep firing until then.

**4. The pixels are in no file at all.** Grepping the whole docroot for `1430907207548734`,
`6411876` and `7QY9HDVocGyeNvMMW1gLWb` returns only page-cache HTML. They are served from the
`uicore_theme_options` row — a theme-options blob. **Pixels held in a theme's options panel do not
survive a theme change**, which is exactly why they are ported into `config/pixels.php`.

Also confirmed: WPCode draft #38942 contains `posthog.capture('form_submitted')` and
`posthog.capture('thankyou_page_viewed')` but has never been published, so neither event has ever
fired. They are correctly absent from the matrix.

### Scale, for the cutover

| | Production | v2 release artifact |
| :--- | ---: | ---: |
| Database | **7 GB** | 39 MB |
| Uploads | **3.3 GB** | 752 MB |

74% of the database is `postmeta` (2.7 GB over 85k rows) and `posts` (2.5 GB over 21k rows) —
Elementor page data and revisions, none of which migrates. The rest that matters:
`gf_entry_meta` 479 MB / 1.87M rows, `rl_cio_events` 296 MB, `rl_cio_pageviews` 183 MB,
`rl_calendly_logs` 98 MB.

Uploads are 3.15 GB of real media across 2023-2026 plus ~130 MB of plugin cruft
(`wp-file-manager-pro`, `backup`, `complianz`). **v2 carries 752 MB**, so either v2 is missing
media or the remainder is unreferenced legacy. Worth resolving before the flip.

Legend: **OK** parity · **FIXED** was missing, now present · **GAP** still missing · **N/A** no v2 equivalent by design · **NEW** v2 addition.

---

## 1. PostHog — booking funnel

Legacy fired these client-side from `headless-calendly-multistep.js`. v2 briefly moved them server-side, which took the whole funnel off the air between cutover and 2026-09-18 — no person, no identify, and a deferred dispatch that never ran on a page render. They are captured **in the browser** again, from `MultistepBookingWizard::capturePostHog()`, with `posthog.identify()` at partial capture. Customer.io still receives the same events from PHP. Names are pinned by `tests/Unit/BookingFunnelEventParityTest.php`.

| Event | Fires on | Legacy | v2 | Status |
| :--- | :--- | :---: | :---: | :--- |
| `form_loaded` | form init | yes | yes | OK |
| `form_started` | first field input | yes | yes | OK |
| `step_date_selection` | arrival at calendar | yes | yes | OK |
| `partial_form_submitted` | step 1 validated, lead row written | yes | yes | OK |
| `hour_selected` | slot clicked | yes | yes | OK |
| `booking_request_sent` | before booking attempt | yes | yes | OK |
| `booking_finished` | booking confirmed | yes | yes | OK |
| `js_script_loaded` | form JS initialised | yes | no | **N/A** — diagnostic for a script v2 does not have. If a PostHog funnel starts on it, that funnel reads zero |
| `session_started` | widget render (PHP inline) | yes | no | **N/A** — same |
| `calendar_button_clicked` | add-to-calendar click | yes | no | **N/A** — v2 has no add-to-calendar feature |
| `step_viewed` | any step change | no | yes | NEW |
| `pricing_warning_shown` | revenue-band warning shown | no | yes | NEW |
| `pricing_warning_accepted` | warning dismissed | no | yes | NEW |
| `posthog.identify(email)` | partial capture | yes | yes | **FIXED 2026-09-18** — the booking form never identified in v2, so every row above had an empty Person |

## 2. PostHog / Customer.io — checkout funnel

Dual-dispatched through `RecordBehaviorEventAction`. Legacy names are preserved by `CheckoutFunnelStep::legacyPostHogEvent()` / `legacyCustomerIoEvent()`, pinned by `PaymentCheckoutTelemetryTest`.

| v2 event | Legacy Customer.io name | Status |
| :--- | :--- | :--- |
| `form_started` | *(PostHog `form_started`)* | OK |
| `Payment Partial Email Captured` | `Payment Partial_email_captured` | OK |
| `Payment Intent Loaded` | `Payment Intent_loaded` | OK |
| `Payment Attempted` | `Payment Attempted` | OK |
| `Payment Succeeded` | `Payment Succeeded` | OK |
| `Payment Failed` | `Payment Failed` | OK |
| `payment_stripe_load_failed` | — | OK |
| `Payment Gateway Viewed` | — | NEW |

## 3. Customer.io — page and lead events

| Event | Fires on | Legacy | v2 | Status |
| :--- | :--- | :---: | :---: | :--- |
| `analytics.page()` | every page | yes | yes | OK |
| `Viewed Pricing Page` | path contains `pricing` | yes | yes | **FIXED** — `TrackingHooks::customerIoPageEvents()` |
| `Viewed Booking Page` | path contains `booking`/`appointment`/`vacalendar` | yes | yes | **FIXED** |
| `Form Submitted` | Gravity confirmation | yes | **no** | **GAP** — see below |
| `Partial Form Captured` | server, partial capture | yes | no | v2 sends `Lead Captured` instead |
| `User Identified` | server, identify | yes | no | v2 identifies via `CustomerIOClient::identify()` on `LeadCreated` |

**The `Form Submitted` gap matters more than it looks.** The legacy plugin seeded a Customer.io lead-scoring map keyed on these exact names — `Form Submitted` 20, `Viewed Booking Page` 15, `Viewed Pricing Page` 10, `Page Viewed` 1 (`rl-customer-io/src/Database/Migration.php:72-75`). v2 sends `Lead Captured` at that moment instead, so any score or campaign built on `Form Submitted` stops moving. Whether to send the alias is a call for whoever owns those campaigns.

## 4. GTM dataLayer

| Event | Legacy source | v2 | Status |
| :--- | :--- | :---: | :--- |
| `form_submit` | gtag.js auto-detecting a native Gravity submit | yes | **FIXED** — `MultistepBookingWizard::pushToDataLayer()`. Drives GA4 `generate_lead` + Ads conversion `oqW3CP3jnJcbEOWU8r4q` |
| every funnel event above | — | yes | NEW — lets a GTM trigger be built without a deploy |

## 5. GA4 and Google Ads — all delivered by `GTM-53JDTQCZ`

| Tag | Trigger | v2 satisfies? |
| :--- | :--- | :--- |
| GA4 `appointment_booked` | Page Path contains `VAThankYou` | **FIXED** — redirect casing, see below |
| Ads conversion `AyW6CJHZnr8ZEOWU8r4q` | same | **FIXED** |
| Ads conversion `pTbrCP6-_dIbEOWU8r4q` (value 1) | same | **FIXED** |
| PostHog `appointment_booked_web` | same | **FIXED** |
| GA4 `generate_lead` | `form_submit` | **FIXED** |
| Ads conversion `oqW3CP3jnJcbEOWU8r4q` | `form_submit` | **FIXED** |
| GA4 `Book a Consultation Click` | click text contains "Book a Consultation" | OK — in 27 patterns |
| Ads conversion `oEXvCN-QnpcbEOWU8r4q` | same | OK |
| GA4 `watched_testimonial_video` | click class `e-font-icon-svg e-eicon-play` | **GAP** — Elementor class, no v2 equivalent |
| Google tag `AW-11406183013` + `G-SCP464C5EH` | `gtm.init` | OK |

### The `/VAThankYou/` casing

Gravity Forms' default confirmation redirects to `https://remoteleverage.com/VAThankYou`, which WordPress serves as `/VAThankYou/` with the capitals intact. The GTM predicate compiles to a bare `_cn` with **no ignore-case flag**, so it is case-sensitive.

v2 redirected to lowercase `/vathankyou/` — the byte-identical page, matching nothing. That silently switched off the primary conversion event and two live Ads conversions. Fixed in `MultistepBookingWizard` and pinned by `BookingFunnelEventParityTest`.

## 6. Marketing pixels

| Pixel | ID | Delivered by (prod) | v2 | Status |
| :--- | :--- | :--- | :---: | :--- |
| Meta Pixel | `1430907207548734` | page HTML | yes | **FIXED** |
| Meta Pixel | `1482937899395718` | page HTML | yes | **FIXED** |
| Meta `PageView` | — | page HTML | yes | **FIXED** |
| **Meta CAPI `Lead`** | `1430907207548734` | **server, every GF submit** | **no** | **GAP** — see below |
| Microsoft UET | `97187250` | page HTML | yes | **FIXED** |
| HubSpot tracking | `243484989` | page HTML | yes | **FIXED** |
| LinkedIn Insight | `6411876` | page HTML | yes | **FIXED** |
| LinkedIn Insight | `9514236` | GTM tag 64 | via GTM | OK — both kept, account unknown |
| OpenAI pixel | `7QY9HDVocGyeNvMMW1gLWb` | page HTML | yes | **FIXED** |
| OpenAI pixel | `GtXTy8ihLz5qrMUanZ3fqf` | GTM tag 65 | via GTM | OK — both kept |
| **OpenAI `appointment_scheduled`** | — | legacy form JS, **not GTM** | yes | **FIXED** — `injectOpenAiConversion()` |
| Google tag | `GT-NCNQ6N2` | Site Kit, page HTML | yes | **FIXED** — routes to `G-SCP464C5EH`, `AW-11406183013` and **`G-JFBLS33ET8`**, which nothing else reaches |
| TikTok | `CPMB51BC77U75I0QMMAG` | GTM tag 8 | via GTM | OK |
| StatCounter | `13176576` | GTM tag 57 | via GTM | OK |
| Rewardful | `39ea7a` | GTM tag 62 | via GTM | OK |
| PostHog | `phc_3Pbasn…` | GTM tag 61 **and** theme | — | **CONFLICT** — see below |
| Customer.io CDP | `ebb5281c53e9fca6b1a5` | page HTML | yes | OK — key matches exactly |

### PostHog double-init

The container's tag 61 runs the full PostHog snippet, which loads the SDK. `POSTHOG_API_KEY` in v2's `.env` is byte-identical to it, and v2 already initialises PostHog from `TrackingHooks`. Enabling GTM gives two SDK copies and double pageviews. **Remove the PostHog init tag from GTM**; keep tag 63 (`posthog.capture`), which is harmless.

## 7. Server-side integrations (backup, `gf_addon_feed` — 44 feeds)

| Integration | Detail | v2 | Status |
| :--- | :--- | :---: | :--- |
| **n8n** `…/webhook/gravityforms-leads` | 8 forms (1, 20, 25, 29, 30, 31, 34, 35), no auth header | yes | OK — `HandleLeadEventsForWebhook` posts here by default since 2026-09-17 (`config/services.php`). `lead.partial_captured` covers **both** the step-one capture and the completed submission, which the flow separates by `lead.submission_type` (`Partial`/`Final`) exactly as the GF feed did; `lead.booking_completed` follows when the slot is confirmed. The payload is the whole lead row. |
| **n8n** `…/webhook/lead-form` | v2 addition, no GF equivalent | yes | `HandleLeadEventsForWebhook::handleLeadFormCaptured()`. **Partial captures only** — `LeadCreated` is raised for the completed submission too and this one is gated on `submission_type`, so it is one POST per lead. Payload is `email` plus `captured_at_est` (`Y-m-d H:i:s` in `America/New_York`), alongside `captured_at_est_iso` and `captured_at_est_abbreviation` (`EDT`/`EST`) so a consumer can tell which offset applied. Read off `created_at`, not send time. |
| **n8n** `…/webhook/hubspot-lead-creation` | v2 addition, no GF equivalent | yes | `HandleLeadEventsForWebhook::handleHubSpotSynced()`, called from `LeadServiceProvider` right after `HubSpotGateway::syncContact()` — not off an event, because the contact id does not exist until the sync returns. Fires for a create **and** an update (`action`), partial captures only, and stays silent when `Lead::hubspotContactUrl()` is null. Payload is `email` plus `hubspot_contact_url`. |
| **Supabase** `…/functions/v1/gravity-forms-webhook` | form 27 only, `x-webhook-secret` header | no | **GAP** — form 27 has HubSpot and Slack feeds **inactive**, so Supabase is its only destination |
| **HubSpot** | 14 feeds, portal `243484989`, 2 revenue-gated | yes | OK — `HubSpotGateway`; needs `HUBSPOT_ACCESS_TOKEN` |
| **Slack** | 12 feeds → channel `C086BBKUXL5`; Join Live Call → `C09HXD9S76Z` | yes | OK — different mechanism, same purpose |
| **Meta CAPI `Lead`** | `graph.facebook.com/v11.0/1430907207548734/events`, hashed PII + fbc/fbp/IP/UA | **no** | **GAP** — `handl_fb_capi_enabled = 1`, verified in the dump. Fires on every submission today |
| Zapier | 9 feeds | — | all `is_active = 0`, dead |
| GF admin email | active on forms 3, 22, 32, 33 only | — | inactive on every lead-routing form |

---

## Open items

1. **Meta CAPI `Lead`** — live on production today, absent from v2. Server-side conversions are the ad-blocker-proof half of Meta signal.
2. **Export the truncated tables** — `wp_add9221751_snippets` (Code Snippets is active) and `wp_posts`/`wp_postmeta`. Until then the audit has a known hole.
3. **Point the lead webhook at n8n**, or rebuild the eight-form flow.
4. **Form 27 → Supabase** has no v2 equivalent.
5. **Customer.io `Form Submitted`** alias, if the scoring map is still in use.
6. **Remove PostHog init from GTM** before `POSTHOG_API_KEY` goes live anywhere.
7. **`watched_testimonial_video`** — rewrite or delete the Elementor-keyed trigger.
8. **Rotate the secrets** found in plaintext in the backup: Slack bot token, HubSpot tokens, Calendly PAT, OpenAI key, Meta CAPI token, Supabase secret.
