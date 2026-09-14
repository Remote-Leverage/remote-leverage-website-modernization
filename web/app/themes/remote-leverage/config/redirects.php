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
];
