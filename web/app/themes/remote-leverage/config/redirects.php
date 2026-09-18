<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy URL 301 Redirect Map (ADR-0006 § SEO & Risk Mitigation)
    |--------------------------------------------------------------------------
    |
    | Maps retired legacy paths (Elementor landing page variants, old testing
    | slugs, restructured permalinks) to their modern canonical destination.
    | Keys and values are site-relative paths, without a leading slash.
    | Query strings and UTM parameters on the incoming request are preserved
    | and appended to the redirect target automatically.
    |
    | An empty-string target means the site root (the WordPress front page).
    |
    */

    'hire-va-old' => 'hire-va-4',
    'hire-virtual-assistant' => 'hire-va-4',
    'book-a-call' => 'book-consultation',
    'join-live-call' => 'live-call/connect',
    // Was 'partner-portal', which is not a registered route or page (known-issues.md bug #1).
    // The real portal path is 'referrer-portal' (route name `referrer.portal`).
    'partner-dashboard' => 'referrer-portal',

    // Duplicate thank-you slugs on production: /thank-you/ and /vathankyou/ serve the
    // same confirmation page. /vathankyou/ is canonical in v2 (page ID 126,
    // page-vathankyou.blade.php), so the duplicate 301s to it rather than being rebuilt.
    // The Livewire signature generator was removed 2026-09-15: /social-media-kit/ is a
    // strict superset of it (same six variants, plus the asset library and the setup
    // instructions), and keeping two implementations had already let their templates drift.
    'tools/signature-generator' => 'social-media-kit',
    'thank-you' => 'vathankyou',

    /*
    | Social Media Kit assets, carried over from the retired rl-social-kit plugin.
    |
    | Filenames and the per-tab structure are unchanged; only the prefix moved, because
    | Bedrock serves the theme from /app/themes/ rather than /wp-content/plugins/. Listed
    | one by one because LegacyRedirectMiddleware matches a whole normalized path and has no
    | prefix rules — and because an explicit list is what the RoutesTest target check can
    | verify actually exists on disk. The match is case-insensitive, but only on the key:
    | these targets keep their case because they name real files.
    */
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_01_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_01_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_02_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_02_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_03_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_03_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_04_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_04_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_05_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_05_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_06_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_06_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_07_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_07_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/linkedin/RL_LKD_PersonalBanner_08_4400x1100.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_08_4400x1100.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_01_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_01_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_02_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_02_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_03_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_03_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_04_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_04_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_05_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_05_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_06_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_06_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_07_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_07_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_08_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_08_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_09_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_09_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/facebook/RL_FC_Personal_Banner_10_850x315.jpg' => 'app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_10_850x315.jpg',
    'wp-content/plugins/rl-social-kit/assets/resources/other/avatar.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/avatar.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/avatar_02-1.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/avatar_02-1.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/avatar_02.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/avatar_02.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-1.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-1.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-2.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-2.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-3.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-3.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-4.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-4.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-5.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-5.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-6.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-6.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-7.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-7.png',
    'wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-8.png' => 'app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-8.png',
    'wp-content/plugins/rl-social-kit/assets/fb.png' => 'app/themes/remote-leverage/public/images/social-media-kit/brand/fb.png',
    'wp-content/plugins/rl-social-kit/assets/ig.png' => 'app/themes/remote-leverage/public/images/social-media-kit/brand/ig.png',
    'wp-content/plugins/rl-social-kit/assets/ln.png' => 'app/themes/remote-leverage/public/images/social-media-kit/brand/ln.png',
    'wp-content/plugins/rl-social-kit/assets/logo-icon-black.svg' => 'app/themes/remote-leverage/public/images/social-media-kit/brand/logo-icon-black.svg',
    'wp-content/plugins/rl-social-kit/assets/logo-icon-white.svg' => 'app/themes/remote-leverage/public/images/social-media-kit/brand/logo-icon-white.svg',
    'wp-content/plugins/rl-social-kit/assets/x.png' => 'app/themes/remote-leverage/public/images/social-media-kit/brand/x.png',
    'wp-content/plugins/rl-social-kit/assets/yt.png' => 'app/themes/remote-leverage/public/images/social-media-kit/brand/yt.png',

    /*
    |--------------------------------------------------------------------------
    | Cutover map for the discarded production pages (ADR-0006 cutover gate)
    |--------------------------------------------------------------------------
    |
    | PAGE-MIGRATION-STATUS.md closes migration scope at 48 URLs. Production has
    | 236 published pages; subtracting the in-scope URLs (§1 and §3 P0-P4), the 23
    | case studies carried by row 8, and the out-of-scope pages that already exist
    | in v2 and must not be shadowed (/vapricing/, /affiliate-program/,
    | /comparison-wing-assistant-ads/ — kept, §4a resolved 2026-09-15) leaves the
    | killed URLs. Each one gets a 301 here so no legacy URL 404s at cutover.
    |
    | /referral/ was in that must-not-shadow list until 2026-09-15. The local page
    | (ID 213) was built from the wrong source — it rendered hire-va-4-full, while
    | production's /referral/ is a homepage variant (27 of 27 headings match the
    | homepage, 4 of 27 match /hire-va-4/). It was deleted, so the premise for
    | excluding the key is gone and the key now exists in the homepage bucket.
    |
    | Policy: nearest live equivalent, else the site root. Where a target was not
    | obvious the production page was fetched and classified by its actual <title>
    | and heading set rather than by its slug — which matters, because a dozen of
    | these slugs promise a landing page and serve the homepage.
    |
    | Every target below is a live v2 page or a registered route. Nothing points at
    | /ecommerce-virtual-assistant/ (still unbuilt, §3 P3); the two ecommerce role
    | pages sit in the role bucket and should be re-pointed when that page ships.
    |
    */

    // Old homepage variants and homepage clones -> / (the front page).
    // Every slug here serves production's homepage content, or is an abandoned homepage
    // redesign. Several were verified by fetching the live page: the slug promises a landing
    // page, the h1/h2 set is the homepage's ("Great Talent Changes Everything", "World's Best
    // Talent, Hired Directly for You", "Beyond the 'Virtual Assistant.'").
    // Note on 'home': v2's front page (ID 7) carries the slug "home" but is served at /,
    // so this key shadows nothing — it 301s production's separate /home/ variant (ID 19288).
    '0-to-hire-a-virtual-assistant' => '',
    'b' => '',
    'form-lp' => '',
    'form-lp2' => '',
    'global-talent' => '',
    'guaranteed-fit' => '',
    // 'hire-va' was listed here as a killed homepage clone. It is not one: production page 38574
    // serves the "Virtual Assistant Roles" landing template (same as /hire-va-isolated-form/),
    // not the homepage. The client reopened scope for it on 2026-09-15 and it is now built at
    // patterns/hire-va.php, so the key is removed — leaving it would 301 the live page to /.
    // Exact template twins of /hire-va/ (same chassis, only the advertised price differs).
    // They pointed at the front page until /hire-va/ was itself rebuilt; sending them to the
    // page they are variants of keeps the original intent rather than dumping the visitor
    // on the homepage.
    'hire-va-2' => 'hire-va',
    'hire-va-3' => 'hire-va',
    'hire-va-t' => 'hire-va',
    'home' => '',
    'home-eu' => '',
    'home-tasks-variation' => '',
    'home-tasks-variation-dark' => '',
    'home-test' => '',
    'homepage-sept-26-newer' => '',
    // Production ID 27582, "Home – Referral" — a referral-traffic variant of the
    // homepage, not a landing page. v2's page 213 was deleted 2026-09-15.
    'referral' => '',
    'homepage-sept-26-old' => '',
    'howitworks' => '',
    'live-call-test-remote-leverage-home' => '',
    'midway-warning-step-test' => '',
    'new-home-26-v2' => '',
    'new-home-26-v2b' => '',
    'new-homepage-26-4' => '',
    'partnership-program-next-steps' => '',
    'prtnrshp-insp-lp' => '',
    'scale-operators' => '',
    'time-activity-monitoring-for-your-virtual-assistant' => '',
    'va-hire' => '',
    'vlet-lp' => '',
    'zero-to-hire' => '',
    'zero-to-hire-latam-eu' => '',
    'zero-to-hire-latam-ph' => '',

    // Role / industry SEO pages -> /hire-va-4/.
    // One page per role or vertical ("Legal Virtual Assistants from Latin America",
    // "Telemarketers from Latin America", ...). /hire-va-4/ is the canonical hire-a-VA landing
    // page in v2 and is already the target of the two pre-existing hire-va redirects above, so
    // the whole SEO long tail lands there.
    //
    // All fourteen role pages shipped on 2026-09-16, so none of their own slugs appears below
    // any more — each one now resolves to its own page. What remains is the long tail, and it
    // no longer lands on /hire-va-4/: every alias points at the role page it is actually about,
    // which is what the note above §P3 always said should happen once those pages existed.
    //
    // Two of the fourteen are new URLs rather than rebuilt ones. Production has no hyphenated
    // social-media page — its live page is /socialmediavirtualassistants/, which now redirects
    // to /social-media-virtual-assistants/ — and no /marketing-virtual-assistants/ either, so
    // the whole /marketing-assistants*/ family redirects forward to it.
    'accounting-virtual-assistants' => 'bookkeeping-virtual-assistants',
    'appointment-setting-virtual-assistants' => 'lead-generation-virtual-assistants',
    'b2b-sales-virtual-assistants' => 'sales-virtual-assistants',
    'bookkeeping-accounting-virtual-assistants' => 'bookkeeping-virtual-assistants',
    'cold-calling-virtual-assistants' => 'sales-virtual-assistants',
    'ecommerce-seo-expert' => 'ecommerce-virtual-assistants',
    'executive-assistants-eu-b' => 'executive-virtual-assistants',
    'get-sales-virtual-assistants' => 'sales-virtual-assistants',
    'healthcare-virtual-assistants' => 'medical-virtual-assistants',
    'high-volume-cold-callers' => 'sales-virtual-assistants',
    'hire-admin-virtual-assistants' => 'admin-virtual-assistants',
    'hire-direct' => 'hire-va-4',
    'hire-direct-latam-eu' => 'hire-va-4',
    'hire-direct-latam-ph' => 'hire-va-4',
    'hire-executive-virtual-assistants' => 'executive-virtual-assistants',
    'hire-marketing-virtual-assistants' => 'marketing-virtual-assistants',
    'hire-sales-virtual-assistants' => 'sales-virtual-assistants',
    'lead-generation-assistants' => 'lead-generation-virtual-assistants',
    'lifecycle-marketing-managers' => 'marketing-virtual-assistants',
    'marketing-assistants' => 'marketing-virtual-assistants',
    'marketing-assistants-2' => 'marketing-virtual-assistants',
    'marketing-assistants-from-latin-america' => 'marketing-virtual-assistants',
    'marketing-assistants-legacy' => 'marketing-virtual-assistants',
    'medical-assistant' => 'medical-virtual-assistants',
    'medical-receptionist-virtual-assistants' => 'medical-virtual-assistants',
    'paid-ads-managers' => 'marketing-virtual-assistants',
    'paralegal' => 'legal-virtual-assistants',
    'personal-virtual-assistants' => 'hire-va-4',
    'sales-landing-page' => 'sales-virtual-assistants',
    'sales-new-2026' => 'sales-virtual-assistants',
    'sales-virtual-assistants-2' => 'sales-virtual-assistants',
    'sales-virtual-assistants-from-latin-america' => 'sales-virtual-assistants',
    'shopify-amazon-vas' => 'ecommerce-virtual-assistants',
    'socialmediavirtualassistants' => 'social-media-virtual-assistants',
    'tech-virtual-assistants' => 'hire-va-4',
    'telemarketers' => 'sales-virtual-assistants',
    'virtual-admin-assistants' => 'admin-virtual-assistants',
    'virtual-assistants' => 'hire-va-4',
    'virtual-assistants-from-latin-america' => 'hire-va-4',
    'virtual-assistants-latin-america-and-philippines' => 'hire-va-4',
    'virtual-attorney-assistants' => 'legal-virtual-assistants',
    'virtual-ecommerce-assistants' => 'ecommerce-virtual-assistants',
    'virtual-legal-assistants' => 'legal-virtual-assistants',
    'virtual-legal-assistants-2' => 'legal-virtual-assistants',
    'virtual-medical-assistants' => 'medical-virtual-assistants',
    'virtual-telehealth-assistants' => 'medical-virtual-assistants',

    // Dead ad landings built on production's VA-roles template -> /hire-va-isolated-form/.
    // Identified by their shared section set ("Latin American Virtual Assistants $6-$10 Per
    // Hour" hero, "Virtual Assistant Roles", "Why Hire Virtual Assistants Through Remote
    // Leverage?"). In v2 that template is resources/patterns/va-roles-landing.php, whose live
    // pages are /hire-va-isolated-form/ and /1monthonus/. These carry no offer, so they go to
    // the plain variant.
    'elementor-50922' => 'hire-va-isolated-form',
    'hire-va-5' => 'hire-va-isolated-form',
    'hire-va-live-calling-feature' => 'hire-va-isolated-form',
    'hire-virtual-assistants' => 'hire-va-isolated-form',
    'hireva' => 'hire-va-isolated-form',
    'latin-america-virtual-assistants' => 'hire-va-isolated-form',
    'simplified' => 'hire-va-isolated-form',
    'va-form' => 'hire-va-isolated-form',
    'vsl-lp' => 'hire-va-isolated-form',

    // Dead offer landings (first month free / $0 to start / refund) -> /1monthonus/.
    // Same VA-roles template as the group above, but with an offer hero and the "First Month
    // Salary Paid By Us" / free-trial benefit row. /1monthonus/ is the live v2 page carrying
    // that offer.
    '0tostart' => '1monthonus',
    '0tostart-elite-talent' => '1monthonus',
    '1monthonus-eu' => '1monthonus',
    'dontpaytohire' => '1monthonus',
    'freethisweek' => '1monthonus',
    'freetrial' => '1monthonus',
    'hire-today' => '1monthonus',
    'hire3forthecostof1' => '1monthonus',
    'love-it-or-get-a-refund' => '1monthonus',
    'moneyback' => '1monthonus',
    'tellustherole' => '1monthonus',
    'withoutpayingfirst' => '1monthonus',

    // Hire-VA campaign variants -> /hire-va-6/.
    // Production titles these "Hire talent for 70% less. Book your free 15-min consultation
    // now." and builds them on the hire-va-campaign template, live in v2 as /hire-va-6/ and
    // /hire-va-1st-month-free/.
    'hire-va-new-live-calling-feature' => 'hire-va-6',

    // US/UK talent variant of the 70%-less campaign -> /hire-va-4/.
    // Same campaign template and the same production <title>, but the hero swaps LATAM pricing
    // for "Hire American & British Professionals Living Abroad For $10-$15/Hour". It was pointed
    // at /hire-for-less/ until 2026-09-15; that page sells Latin American VAs at $6-$10/hour, so
    // it misstated the offer to anyone arriving on this URL. Retargeted to /hire-va-4/, the
    // canonical hire page already used by the 'hire-va-old' / 'hire-virtual-assistant' 301s.
    'hire-us-uk-now' => 'hire-va-4',

    // Consultation-landing A/B leftovers -> the live variant-b page.
    // Production's <title> and section set are byte-identical to
    // /hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b/,
    // which is migrated (page ID 1000047).
    'test-variant-b' => 'hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b',

    // Duplicate of the VA Store sales sheet -> /vastore5/.
    // Production ships /vastore5/ and /vastore5-b/ as the same collateral; -b is the
    // hourly-rate pricing breakdown of the same document.
    'vastore5-b' => 'vastore5',

    // Retired booking calendars -> /vacalendar/ (the migrated 15-minute consultation calendar).
    'appointment-calendar' => 'vacalendar',
    'booking-template-test' => 'vacalendar',
    'calendar-ppp' => 'vacalendar',
    'vainterview' => 'vacalendar',
    'vainterview2' => 'vacalendar',

    // Internal "meet a manager" booking page -> the v2 booking funnel route.
    // This is not the public VA-hiring calendar; it books a call with an HR / hiring manager
    // about Contractor-of-Record services.
    //
    // 'service-hiring' was freed 2026-09-16 when it became a real v2 page (page 1000192,
    // patterns/service-hiring-full.php). It books the post-deposit "Onboarding + Applicant
    // Criteria" meeting, which /book-consultation/ does not — that route's wizard resolves its
    // Calendly event type from the lead's revenue tier, so the 301 was sending bookings to a
    // different meeting than the one this page's copy describes.
    'service-cor' => 'book-consultation',

    // Retired confirmation / thank-you pages -> /vathankyou/, the canonical confirmation page.
    'cor-thank-you' => 'vathankyou',
    'deposit-received' => 'vathankyou',
    'thankyou-ppp' => 'vathankyou',
    'virtual-assistant-consultation-scheduled' => 'vathankyou',
    'virtual-assistant-hiring-consultation-booked-v2' => 'vathankyou',

    // Retired onboarding form -> the migrated VA client onboarding form.
    'onboardingform' => 'vaonboardingform',

    // Editorial / guide content -> /blog/.
    // Production's /guides/ literally renders the blog archive; the rest are long-form how-tos
    // and onboarding guides whose nearest live home is the blog.
    'guides' => 'blog',
    'how-to-increase-your-virtual-assistants-productivity-with-bonuses' => 'blog',
    'how-to-train-your-virtual-assistant' => 'blog',
    'vaguides' => 'blog',
    // 'vaonboardingguide' removed 2026-09-15: it is being built as a real v2 page (the
    // 6-video VA-facing onboarding guide that /services/ links to). A key equal to a live
    // page slug 301s that page away. Distinct from /onboardingguide/, which is the separate
    // 28-video client-facing guide — different page, also built, also not a key.
    // Dead variant slug. Retargeted 2026-09-15 from 'blog' to the real page, now that it
    // exists — a visitor on the variant URL wants the guide, not the blog archive.
    'vaonboardingguide2' => 'vaonboardingguide',

    // Retired voice-sample variants -> /samples/, the migrated sample recordings page.
    'samples-healthcare-industry' => 'samples',
    'samples-legacy' => 'samples',
    'samples-sales' => 'samples',

    // Retired testimonial pages -> /reviews/, the migrated review wall.
    'categorized-testimonials' => 'reviews',
    'reviewsva' => 'reviews',

    // Contractor-of-Record service page -> /contractor-management/.
    // Production /cor/ is the COR platform page ("Compliance, Payments, and Performance
    // Management in One Platform", "HRIS: One Management Tool"), which is what
    // /contractor-management/ covers in v2.
    'cor' => 'contractor-management',

    // 'store' was here, 301ing to /contractor-payments/ as the nearest live equivalent of
    // production's COR pricing sheet. Scope reopened on 2026-09-15 and /store/ is now being
    // built as a real v2 page, so the key is removed: a redirect key that matches a live page
    // slug would 301 that page away.

    // Partner recruitment page -> the v2 partner hub.
    'partner-capability-brief' => 'partners',

    // No live equivalent in v2 -> / (the default under the approved policy).
    // Internal collateral, admin-gated tools, retired generators, one-off event pages and
    // build-time test pages. None of these has anything in v2 to point at, so they fall back to
    // the homepage rather than inventing a target.
    'job-description-generator' => '',
    'job-description-generator-2' => '',
    'job-posting-template-generator' => '',
    'live-session' => '',
    'live-session-10x-revenue-with-ai' => '',
    'live-session-build-ai-tools-for-businesses' => '',
    'marketing-email-generator' => '',
    'resume' => '',
    'test-landing-page-26' => '',
    'test-landing-page-26-2' => '',
    'text-optimizer' => '',
    'tools' => '',
    'workshop' => '',
    'youtube-script-generator' => '',

    // 'contractoragreement' and 'services' were here, both 301ing to / under the no-live-
    // equivalent policy. Scope reopened on 2026-09-15 and both are now being built as real v2
    // pages, so the keys are removed: a redirect key that matches a live page slug would 301
    // that page away, which is exactly what the shadowing check exists to prevent.

    /*
    |--------------------------------------------------------------------------
    | External targets
    |--------------------------------------------------------------------------
    |
    | A target may also be an absolute http(s):// URL, for content that moved off
    | this site entirely. It is still a 301 and the incoming query string (UTM) is
    | still preserved. Absolute targets are only ever read from this static map --
    | never from request input -- so this cannot become an open redirect; see
    | LegacyRedirectMiddleware::isExternalTarget() and the guard test in
    | tests/Unit/LegacyRedirectTest.php.
    |
    */

    // Anyshore spun out as its own product on its own domain; production /anyshore/ was its
    // pre-launch landing page and there is no v2 equivalent to point at. Retargeted from /
    // on 2026-09-15.
    'anyshore' => 'https://anyshore.ai/',

    // Production 301s /deposit/ (and /Deposit/) straight to a Stripe payment link rather than
    // serving a page. It is the documented fallback in the /vastore5/ objection-handling script
    // for when the primary route fails, so it has to keep the same behaviour, not become a page.
    // Verified against production 2026-09-16: 301 -> buy.stripe.com/cNi14nfrY18b7tX6DkfrW0E.
    'deposit' => 'https://buy.stripe.com/cNi14nfrY18b7tX6DkfrW0E',

    // Production 301 /refund/ (and /Refund/) straight to the JostForm refund form rather than
    // serving a page. It is the main redirect under /refund/ path in the GoDaddy version.
    // Verified against production 2026-09-17: 301 -> https://form.jotform.com/252185178652665
    'refund' => 'https://form.jotform.com/252185178652665',

    /*
    |--------------------------------------------------------------------------
    | Vanity / operational short links ported from the GoDaddy install
    |--------------------------------------------------------------------------
    |
    | Audited 2026-09-18 by reading the legacy box directly
    | (339104.us18.ssh.myftpupload.com:/html). Two redirect stores were found there
    | and neither had been carried into v2: the `301-redirects` plugin
    | (table `wp_add9221751_wf301_redirect_rules`, 328 rules) and Yoast SEO Premium
    | (option `wpseo-premium-redirects-export-plain`, 355 rules). The `.htaccess` is
    | empty; there is no server-level rule anywhere. Full findings and the complete
    | rule dump: docs/legacy-redirects-audit.md.
    |
    | These are the operational short links staff and candidates paste into emails,
    | job posts and Slack. They are not marketing pages and have no v2 equivalent by
    | design -- the destination is a third-party tool. Trailing hit counts are
    | lifetime totals since 2024-10-20, read off the plugin's `last_count` column;
    | they are why these are ported ahead of the SEO long tail. /apply alone has
    | served 43,636 redirects and is the entry point of the whole recruiting funnel.
    |
    | Every URL below is also declared in $externalTargets in
    | tests/Feature/RoutesTest.php, which is what confines wp_safe_redirect()'s
    | allowlist to these hosts.
    |
    */

    'apply' => 'https://remoteleveragejobs.com/?ref=https://remoteleverage.com/apply',
    'recruitinginterview' => 'https://calendly.com/pacific-recruiters/job-interview-remote-leverage-team-clone',
    'jobinterviewinstructions' => 'https://vimeo.com/1143608840/e4c8dc87eb?fl=tl&fe=ec',
    'recruiterszoom' => 'https://us02web.zoom.us/j/6153893236',
    'zoom' => 'https://us02web.zoom.us/j/9794933436?pwd=dENpRHJLL2h2RVlTZjJaODRndVJnQT09',
    'submitvideo' => 'https://forms.gle/rqHADHQk1M31TjGPA',
    'typing' => 'https://www.livechat.com/typing-speed-test/#/',
    'adminassessment' => 'https://forms.gle/3oUqzMLizn6bfPAS9',
    'nyxzoom' => 'https://us02web.zoom.us/j/9907147461?pwd=hwOW946MrYD3cpZYbIa9Fa6zkfpj1I.1',
    'it' => 'https://remoteleveragetech.atlassian.net/servicedesk/customer/portal/1/group/1/create/1',
    'zoom2' => 'https://us02web.zoom.us/j/7172910526?pwd=ryHFp0X4eFvSMerLFsae3LG2RArcno.1',
    'claireinterview' => 'https://calendly.com/claire-remoteleverage/job-interview-remote-leverage-team-clone',
    'salesassessment' => 'https://forms.gle/Ntxhgn9hNfxBKjQN7',
    'adminzoom' => 'https://us02web.zoom.us/j/4055248514?pwd=XwiKkIbXlEoaaWjCP3wOqzratPxmqV.1',
    'w9' => 'https://drive.google.com/file/d/1w1_Pi54QZBe5k2xxtbE3rCvClNaeWX0l/view?usp=sharing',
    'interviewzoom' => 'https://us02web.zoom.us/j/4469787983?pwd=iwK8oQenKMxgxGHSeEiteUs1G34KD8.1',
    'seifszoom' => 'https://us02web.zoom.us/j/8438962979?pwd=BtNW4PHSBO1CXfkJ0kF46Kkzxrbonr.1',
    'dashboards' => 'https://sso.online.tableau.com/public/idp/SSO',
    'estrecruitinginterview' => 'https://calendly.com/eastern-recruiters-remoteleverage/job-interview-remote-leverage-team',
    'virtual-assistant-posts/salary-guide-for-businesses-hiring-virtual-assistants-guide' => 'https://anyshore.ai/blog/latin-american-va-salary-guide/',
    'googlereview' => 'https://g.page/r/CTuB-J467qJwEAE/review',
    'angelicagomez' => 'https://recruitcrm.io/apply/17811178058780133835zgp',
    'christinaszoom' => 'https://us02web.zoom.us/j/4821528296?pwd=aG5Q6HhLv8Bwuf0b2Sr6xtqmDvzMaw.1',
    'testimonial' => 'https://calendly.com/d/cs4r-k2g-d2p/remote-leverage-testimonial-session',
    'angie' => 'https://recruitcrm.io/apply/17806985112870087768JmC',
    'miry' => 'https://recruitcrm.io/apply/17811401732160133835JtX',
    'marketingmaterials' => 'https://docs.google.com/document/d/1UUGApiJ0W21RKI3GQ5QHncYJF1Wf1RraPjg1LG4un1Q/edit?usp=sharing',
    'uptime' => 'https://stats.uptimerobot.com/c7mpGQmbnC',
    'deel' => 'https://get.deel.com/eotkl4au2m8w',
    'interviewvideo' => 'https://vimeo.com/1124026646/649fff7922?share=copy',
    'abbascalendar' => 'https://calendly.com/abbas-remoteleverage/30min',
    'lead' => 'https://forms.gle/8ZMPNRKwBveBa67M7',
    'ideas' => 'https://form.jotform.com/260564755295164',
    'fire' => 'https://form.jotform.com/252558126272155',
    'natasha' => 'https://us02web.zoom.us/j/7543604107?pwd=cm1BQzRlOWNocklwOHNMTDBKODFRQT09',
    'vaonboarding' => 'https://calendly.com/remoteleverage/client-va-onboarding-call',
    'introcall' => 'https://calendly.com/d/cqnx-7z2-2rq/remote-leverage-onboarding-applicant-criteria',
    'jobinvitation' => 'https://form.jotform.com/243466875000052',
    'join' => 'https://buy.stripe.com/6oEeWM0EUegh1qgdQW',
    'cordeposit' => 'https://buy.stripe.com/3cIfZh1B8cQT29D0eWfrW0F',
    'princessinterview' => 'https://calendly.com/remoteleverage/job-interview-test',
    'interview' => 'https://calendly.com/remoteleverage/job-interview-test',
    'replit' => 'https://recruiting-helper.replit.app/',
    'laura' => 'https://recruitcrm.io/apply/17806984609920087768oJQ',
    'lina' => 'https://recruitcrm.io/apply/17811180148840133835icy',
    'splitpayment' => 'https://form.jotform.com/243395973807471',
    'firefighting' => 'https://form.jotform.com/252558126272155',
    'onboardingmeeting' => 'https://calendly.com/remoteleverage/onboarding',
    'it2' => 'https://remoteleveragetech.atlassian.net/servicedesk/customer/portal/1/group/1/create/1',
    'training' => 'https://calendly.com/remoteleverage/coldcallingtraining',
    'vatraining' => 'https://docs.google.com/document/d/1GoY3pWKRwPVmH7fyCWbNL-5PCeNDcxkX-eNp2mn91TA/edit?usp=sharing',
    '12monthlyfee' => 'https://buy.stripe.com/7sIg0QcnCa01d8Y5kB',
    '15' => 'https://calendly.com/remoteleverage/15-minute-meeting',
    '1500' => 'https://buy.stripe.com/fZe4i81IY6NP2uk00o',
    '2000' => 'https://buy.stripe.com/7sIdSI3R6fklfh6cNd',
    '6monthlyfee' => 'https://buy.stripe.com/5kAbKAfzOdcdfh6aEU',
    'cruzcalendar' => 'https://calendly.com/cruzremoteleverage/virtual-assistant-hiring-consultation-clone',
    'estinterviewzoom' => 'https://us02web.zoom.us/j/6990050267?pwd=RpNwxbjq22OrcMAe36gJJfxUNuI0Ha.1',
    'extendedguarantee' => 'https://buy.stripe.com/6oE2a01IY2xz7OE6oT',
    'followupmonthlyfee' => 'https://buy.stripe.com/14kaGwcnC7RT4Cs8wL',
    'monthlyfee' => 'https://buy.stripe.com/eVacOEafu5JL1qg28g',
    'natashacalendar' => 'https://calendly.com/remoteleveragesales/natasha-1-on-1-meeting',
    'vaexam' => 'https://forms.gle/jGL2PVu11C9189WN6',

    /*
    |--------------------------------------------------------------------------
    | Legacy internal paths with recorded traffic (same 2026-09-18 audit)
    |--------------------------------------------------------------------------
    |
    | Same two stores, targets that stay on this site. Each target was resolved
    | against the v2 database rather than copied from the legacy row, because the
    | legacy targets are stale in three ways:
    |
    |   - Redirect chains. Legacy pointed some of these at slugs that are themselves
    |     keys in this map. Those are collapsed to the final destination here, so no
    |     visitor takes two hops.
    |   - Renamed content. /blog/<old-slug> pairs were re-pointed at the slug the post
    |     actually carries in v2 (the 15 case studies moved to the case_study CPT).
    |   - Content v2 does not have. Thirteen Yoast rules target '-guide' blog slugs
    |     that were never migrated. Fuzzy-matching them to surviving posts produced
    |     nothing convincing, so they follow this map's existing editorial policy and
    |     land on /blog/ rather than on a guessed article.
    |
    | Two entries are flagged inline as unresolved: /coldcallscript/ and
    | /followupscript/ served Download Monitor PDFs (download/2637 and 2640) that have
    | no v2 equivalent because the PDFs were not migrated. They fall back to the root
    | under this map's documented no-live-equivalent policy; re-point them if the
    | collateral is ever brought over.
    |
    */

    'partnership-program' => 'referral-program', // 604 hits
    'case-studies' => 'case-study', // 504 hits  // CPT archive (case_study has_archive => 'case-study')
    'virtual-assistant-posts/how-much-does-athena-virtual-assistant-cost-guide' => 'blog/athena-virtual-assistant', // 467 hits
    'virtual-assistant-posts/virtual-medical-receptionist-revolutionizing-healthcare-support-guide' => 'blog/best-medical-receptionist-services', // 373 hits  // legacy target slug was renamed in v2
    'virtual-assistant-posts/medical-coordinator-key-requirements-duties-responsibilities-and-skills-guide' => 'blog/patient-care-coordinator-cost', // 317 hits
    'landing-page-2' => '', // 250 hits
    'virtual-assistant-posts/why-hiring-a-marketing-assistant-can-transform-your-business-guide' => 'blog/marketing-virtual-assistant-vs-marketing-agency', // 225 hits
    'virtual-assistant-posts/understanding-the-role-of-an-executive-administrative-assistant-guide' => 'blog/executive-assistant-vs-virtual-assistant', // 202 hits
    'marketing-assistants-b' => 'marketing-virtual-assistants', // 149 hits  // chain collapsed: legacy -> marketing-assistants, which is itself a key here
    'blog/how-ku%ca%bbulei-found-high-level-social-media-marketing-talent-through-remote-leverage' => 'case-study/hawaiian-philanthropy', // 143 hits
    'partnership-program-form' => '', // 135 hits
    'blog/how-remote-leverage-helped-a-u-s-law-firm-build-a-high-performing-remote-team' => 'case-study/smiley-injury-law', // 128 hits
    'blog/case-study-how-a-texas-auto-shop-doubled-local-hiring-power-with-remote-leverage' => 'case-study/jeremis-auto-repair', // 80 hits
    'blog/case-study-how-on-the-outskirt-marketing-hired-their-first-virtual-employee-with-ease' => 'case-study/on-the-outskirt', // 76 hits
    'blog/finding-the-perfect-hire-for-a-private-practice-with-remote-leverage' => 'case-study/watson-psychiatry', // 71 hits
    'blog/how-anchorage-care-coordination-gained-reliable-daily-support-with-remote-leverage' => 'case-study/anchorage-care-coordination', // 71 hits
    'blog/case-study-mobile-mixologists-hires-a-bilingual-va-fast-and-frees-up-the-founder-to-scale' => 'case-study/mobile-mixologist', // 70 hits
    'blog/goldsoil-realty-investments-hires-a-closer-on-the-spot' => 'case-study/the-acre-hub', // 70 hits
    'blog/how-doran-industries-scaled-event-sales-with-a-virtual-assistant-from-remote-leverage' => 'case-study/doran-industries', // 70 hits
    'blog/how-haus-of-her-studios-saved-money-gained-a-highly-qualified-va-with-remote-leverage' => 'case-study/haus-of-her', // 70 hits
    'blog/how-bench-accounting-scaled-fast-by-hiring-31-virtual-assistants-through-remote-leverage' => 'case-study/bench-accounting', // 66 hits
    'blog/how-sales-leader-zack-beck-reclaimed-his-focus-with-a-virtual-assistant' => 'case-study/conservice', // 62 hits
    'blog/how-fast-real-estate-hired-a-cold-calling-va-and-built-scalable-systems-with-remote-leverage' => 'case-study/fast-real-estate', // 61 hits
    'footer-fix-test' => '', // 45 hits
    'the-secret-to-scaling-your-ebay-store-hire-an-ebay-virtual-assistant' => 'blog', // 34 hits  // legacy '-guide' target absent from v2's blog
    'partnership-program-thank-you' => '', // 33 hits
    'coldcallscript' => '', // 24 hits  // Download Monitor PDF (download/2637) has no v2 equivalent - PDF not migrated
    'followupscript' => '', // 24 hits  // Download Monitor PDF (download/2640) has no v2 equivalent - PDF not migrated
    'real-estate-cold-calling-virtual-assistants-the-secret-to-real-estate-success' => 'blog', // 11 hits  // legacy '-guide' target absent from v2's blog
    'virtual-administrative-assistant' => 'blog', // 8 hits  // legacy '-guide' target absent from v2's blog
    'hire-social-media-content-creator-everything-you-need-to-know' => 'blog', // 7 hits  // legacy '-guide' target absent from v2's blog
    'virtual-assistants-and-time-management-how-to-delegate-effectively' => 'blog', // 5 hits  // legacy '-guide' target absent from v2's blog
    'virtual-assistants-for-different-industries-tailoring-services-to-your-needs' => 'blog', // 5 hits  // legacy '-guide' target absent from v2's blog
    'virtual-medical-administrative-assistant' => 'blog', // 5 hits  // legacy '-guide' target absent from v2's blog
    'what-is-an-example-kpi-for-administrative-assistant' => 'blog', // 5 hits  // legacy '-guide' target absent from v2's blog
    'live-session-what-to-automate-from-day-1' => '', // 4 hits  // chain collapsed: legacy -> live-session-build-ai-tools-for-businesses, which is itself a key here -> /
    'cal' => '', // 3 hits
    'tasks-landing-page' => '', // 3 hits
    'essential-guide-to-a-virtual-assistant-contract-template' => 'blog', // 1 hits  // legacy '-guide' target absent from v2's blog
    'how-much-does-athena-virtual-assistant-cost' => 'blog/athena-virtual-assistant', // 1 hits  // legacy '-guide' slug absent from v2; this post is the real equivalent
    'the-virtual-financial-planning-assistant-your-secret-weapon-to-scaling-your-business' => 'blog', // 1 hits  // legacy '-guide' target absent from v2's blog
    'understanding-white-label-virtual-assistant-services' => 'blog', // 1 hits  // legacy '-guide' target absent from v2's blog
    'virtual-assistant-vs-in-house-employee-pros-and-cons' => 'blog', // 1 hits  // legacy '-guide' target absent from v2's blog
    'why-hiring-an-admin-assistant-working-from-home-is-a-game-changer-for-busy-business-owners' => 'blog', // 1 hits  // legacy '-guide' target absent from v2's blog

];
