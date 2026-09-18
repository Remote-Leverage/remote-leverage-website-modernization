<?php

/**
 * The live-transfer booking form — `/live-transfer-contact-creation/`.
 *
 * An internal sales tool, recovered from production post 51147 on 2026-09-17 (see
 * `docs/recovered/live-transfer-contact-creation/`). A BDR fills it in after a live call so a
 * lead who agreed to a meeting but never went through the online funnel still gets booked.
 *
 * On production the n8n URL was hardcoded inside the page body, which meant changing it was a
 * content edit in Elementor. Here it is configuration, and the view passes it to the script
 * through a `data-` attribute rather than interpolating it into JavaScript.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | n8n webhook
    |--------------------------------------------------------------------------
    |
    | Where a completed form is POSTed. A **different** endpoint from the
    | Gravity Forms lead webhook — see docs/domains/tracking-event-matrix.md.
    |
    | Blank disables the route's submit button rather than posting into the
    | void, so a missing value fails visibly instead of swallowing bookings.
    */
    'webhook_url' => (string) env(
        'LIVE_TRANSFER_WEBHOOK_URL',
        'https://n8n.srv1338052.hstgr.cloud/webhook/live-call-transfer',
    ),

    /*
    |--------------------------------------------------------------------------
    | Booking timezone
    |--------------------------------------------------------------------------
    |
    | Bookings are always entered in Eastern time, whatever the rep's own clock
    | says. That is deliberate and was hardcoded in the production script; it is
    | surfaced here so it is changed on purpose rather than by accident.
    */
    'timezone' => (string) env('LIVE_TRANSFER_TIMEZONE', 'America/New_York'),
];
