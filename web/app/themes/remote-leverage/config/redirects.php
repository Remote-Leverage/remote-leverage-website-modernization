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
    | one by one because LegacyRedirectMiddleware matches on an exact normalized path and
    | has no prefix rules — and because an explicit list is what the RoutesTest target check
    | can verify actually exists on disk.
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
    // "Telemarketers from Latin America", ...). None of them is being rebuilt; /hire-va-4/ is
    // the canonical hire-a-VA landing page in v2 and is already the target of the two
    // pre-existing hire-va redirects above, so the whole SEO long tail lands there.
    'accounting-virtual-assistants' => 'hire-va-4',
    'admin-virtual-assistants' => 'hire-va-4',
    'appointment-setting-virtual-assistants' => 'hire-va-4',
    'b2b-sales-virtual-assistants' => 'hire-va-4',
    'bookkeeping-accounting-virtual-assistants' => 'hire-va-4',
    'bookkeeping-virtual-assistants' => 'hire-va-4',
    'cold-calling-virtual-assistants' => 'hire-va-4',
    'customer-support-virtual-assistants' => 'hire-va-4',
    'ecommerce-seo-expert' => 'hire-va-4',
    'ecommerce-virtual-assistants' => 'hire-va-4',
    'executive-assistants-eu-b' => 'hire-va-4',
    'executive-virtual-assistants' => 'hire-va-4',
    'get-sales-virtual-assistants' => 'hire-va-4',
    'graphic-design-virtual-assistants' => 'hire-va-4',
    'healthcare-virtual-assistants' => 'hire-va-4',
    'high-volume-cold-callers' => 'hire-va-4',
    'hire-admin-virtual-assistants' => 'hire-va-4',
    'hire-direct' => 'hire-va-4',
    'hire-direct-latam-eu' => 'hire-va-4',
    'hire-direct-latam-ph' => 'hire-va-4',
    'hire-executive-virtual-assistants' => 'hire-va-4',
    'hire-marketing-virtual-assistants' => 'hire-va-4',
    'hire-sales-virtual-assistants' => 'hire-va-4',
    'insurance-virtual-assistants' => 'hire-va-4',
    'lead-generation-assistants' => 'hire-va-4',
    'lead-generation-virtual-assistants' => 'hire-va-4',
    'legal-virtual-assistants' => 'hire-va-4',
    'lifecycle-marketing-managers' => 'hire-va-4',
    'marketing-assistants' => 'hire-va-4',
    'marketing-assistants-2' => 'hire-va-4',
    'marketing-assistants-from-latin-america' => 'hire-va-4',
    'marketing-assistants-legacy' => 'hire-va-4',
    'medical-assistant' => 'hire-va-4',
    'medical-receptionist-virtual-assistants' => 'hire-va-4',
    'medical-virtual-assistants' => 'hire-va-4',
    'paid-ads-managers' => 'hire-va-4',
    'paralegal' => 'hire-va-4',
    'personal-virtual-assistants' => 'hire-va-4',
    'real-estate-virtual-assistants' => 'hire-va-4',
    'sales-landing-page' => 'hire-va-4',
    'sales-new-2026' => 'hire-va-4',
    'sales-virtual-assistants' => 'hire-va-4',
    'sales-virtual-assistants-2' => 'hire-va-4',
    'sales-virtual-assistants-from-latin-america' => 'hire-va-4',
    'shopify-amazon-vas' => 'hire-va-4',
    'socialmediavirtualassistants' => 'hire-va-4',
    'tech-virtual-assistants' => 'hire-va-4',
    'telemarketers' => 'hire-va-4',
    'virtual-admin-assistants' => 'hire-va-4',
    'virtual-assistants' => 'hire-va-4',
    'virtual-assistants-from-latin-america' => 'hire-va-4',
    'virtual-assistants-latin-america-and-philippines' => 'hire-va-4',
    'virtual-attorney-assistants' => 'hire-va-4',
    'virtual-ecommerce-assistants' => 'hire-va-4',
    'virtual-legal-assistants' => 'hire-va-4',
    'virtual-legal-assistants-2' => 'hire-va-4',
    'virtual-medical-assistants' => 'hire-va-4',
    'virtual-telehealth-assistants' => 'hire-va-4',

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

    // Internal "meet a manager" booking pages -> the v2 booking funnel route.
    // These are not the public VA-hiring calendar; they book a call with an HR / hiring manager
    // about Contractor-of-Record services.
    'service-cor' => 'book-consultation',
    'service-hiring' => 'book-consultation',

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
    'vaonboardingguide' => 'blog',
    'vaonboardingguide2' => 'blog',

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

];
