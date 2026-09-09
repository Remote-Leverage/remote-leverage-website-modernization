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
    'partner-dashboard' => 'partner-portal',
];
