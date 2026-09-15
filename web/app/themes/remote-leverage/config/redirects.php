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
    'thank-you' => 'vathankyou',

    /*
    |--------------------------------------------------------------------------
    | Cutover map for the discarded production pages (ADR-0006 cutover gate)
    |--------------------------------------------------------------------------
    |
    | PAGE-MIGRATION-STATUS.md closes migration scope at 48 URLs. Production has
    | 236 published pages; subtracting the in-scope URLs (§1 and §3 P0-P4), the 23
    | case studies carried by row 8, and the four out-of-scope pages that already
    | exist in v2 and must not be shadowed (/vapricing/, /affiliate-program/,
    | /referral/, /comparison-wing-assistant-ads/ — §4a, decision still open) leaves
    | 164 killed URLs. Each one gets a 301 here so no legacy URL 404s at cutover.
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
    'hire-va' => '',
    'hire-va-2' => '',
    'hire-va-3' => '',
    'hire-va-t' => '',
    'home' => '',
    'home-eu' => '',
    'home-tasks-variation' => '',
    'home-tasks-variation-dark' => '',
    'home-test' => '',
    'homepage-sept-26-newer' => '',
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
    'sales-talents' => 'hire-va-4',
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

    // US/UK talent variant of the 70%-less campaign -> /hire-for-less/.
    // Same campaign template and the same production <title>; the hero swaps LATAM pricing for
    // "Hire American & British Professionals Living Abroad For $10-$15/Hour".
    'hire-us-uk-now' => 'hire-for-less',

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
    'onboardingguide' => 'blog',
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

    // COR pricing sheet -> /contractor-payments/.
    // Production /store/ is "Virtual Assistant Hiring Plans" but its body is Contractor-of-
    // Record seat pricing, so the payments page is the nearest live equivalent.
    'store' => 'contractor-payments',

    // Partner recruitment page -> the v2 partner hub.
    'partner-capability-brief' => 'partners',

    // No live equivalent in v2 -> / (the default under the approved policy).
    // Internal collateral, admin-gated tools, retired generators, one-off event pages and
    // build-time test pages. None of these has anything in v2 to point at, so they fall back to
    // the homepage rather than inventing a target.
    'anyshore' => '',
    'contractoragreement' => '',
    'hmchecklists' => '',
    'job-description-generator' => '',
    'job-description-generator-2' => '',
    'job-posting-template-generator' => '',
    'live-session' => '',
    'live-session-10x-revenue-with-ai' => '',
    'live-session-build-ai-tools-for-businesses' => '',
    'marketing-email-generator' => '',
    'recruiterchecklists' => '',
    'resume' => '',
    'saleschecklists' => '',
    'services' => '',
    'test-landing-page-26' => '',
    'test-landing-page-26-2' => '',
    'text-optimizer' => '',
    'tools' => '',
    'workshop' => '',
    'youtube-script-generator' => '',

];
