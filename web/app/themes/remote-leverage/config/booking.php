<?php

/**
 * Per-revenue-band behaviour for the booking wizard.
 *
 * The legacy Elementor widget carried this as a mapping per choice of the monthly-revenue
 * question — `show_warning_step`, `warning_text`, `warning_btn_text`, `event_uri`, skip-calendar.
 * v2 ported the Calendly routing half (T10/T0 by MRR) but not the warning half, so the $0–5k
 * interstitial was silently missing: those visitors booked a sales call without ever seeing the
 * pricing, which wastes the call for both sides.
 *
 * Keyed by the exact choice value the form submits, because that string is what the Lead and
 * HubSpot both store — matching on anything derived would drift the moment a band is renamed.
 */
return [
    'revenue_bands' => [

        '$0 to $5k Per Month' => [
            'show_warning' => true,
            'warning_heading' => "Due to your company revenue,\nour service might be too expensive!",
            'warning_button' => 'Continue',
            'warning_body' => [
                ['type' => 'paragraph', 'text' => "We offer exceptional Virtual Assistants who work directly with you. Here's how our straightforward pricing works:"],
                ['type' => 'paragraph', 'lead' => 'No Monthly Fees:', 'text' => "Unlike other services, we don't charge ongoing or recurring fees"],
                ['type' => 'paragraph', 'lead' => 'Simple Payment Structure:'],
                ['type' => 'bullet', 'text' => 'One-time recruiting fee that is 40% of the annual VA salary. That is equal to about $4000 to $6000 when you find your ideal VA'],
                ['type' => 'bullet', 'text' => 'After that, you pay your VA directly at your agreed hourly rate'],
                ['type' => 'paragraph', 'text' => 'Is this within your budget? If this investment aligns with your budget, and you want to speak to a sales rep, click the button below.'],
            ],
        ],

        // Every other band proceeds straight to the calendar. Listed rather than omitted so the
        // set of bands is visible in one place, and adding a warning to one is a data change.
        '$5k to $10k Per Month' => ['show_warning' => false],
        '$10k to $50k Per Month' => ['show_warning' => false],
        '$50k-$100k Per Month' => ['show_warning' => false],
        '$100k+ Per Month' => ['show_warning' => false],
    ],
];
