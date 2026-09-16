<?php

/**
 * Slack message layouts, as Block Kit templates.
 *
 * The layout lives here rather than in PHP so it can be designed rather than coded: build it
 * visually in Slack's Block Kit Builder (https://app.slack.com/block-kit-builder), paste the
 * JSON in, and swap literal values for the placeholders below. Changing the design becomes a
 * config edit, not a change to a listener with its own tests.
 *
 * ## Placeholders
 *
 * Any `{{ key }}` in a string is replaced. Unknown keys and absent values become an empty
 * string, so a template can reference something a given lead does not have without producing
 * the literal text "{{ key }}" in a sales channel.
 *
 *   name              first + last, or the single name field
 *   email             raw address                      email_link    <mailto:…|…> form
 *   phone             as captured (E.164 where validated)
 *   revenue           the monthly-revenue band the visitor selected
 *   source medium campaign content term                the five UTMs, individually
 *   partner           partner referral, when present
 *   landing_url       full URL                         landing_display  host+path, trimmed
 *   replay_url        PostHog session replay           admin_url     wp-admin lead detail
 *   submission_type   Partial | Final
 *
 * ## Conditional blocks
 *
 * A block carrying `_when` is dropped unless every listed placeholder resolves to a non-empty
 * value. That is what keeps a lead with no campaign data from rendering a grid of dashes — an
 * alert full of empty fields teaches people to stop reading it.
 *
 * Fields inside a `section` support `_when` the same way and are removed individually.
 *
 * ## House rule
 *
 * No emoji, anywhere. Hierarchy comes from headers, dividers and field grouping.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | New lead — the partial capture
    |--------------------------------------------------------------------------
    |
    | Fires when someone completes step 1, including the people who never finish
    | booking. Ported from the Gravity Forms Slack feed, which alerted on the
    | partial only (`submission_type is not Final`).
    */
    'new_lead' => [

        /*
         * No attachment colour: a `card` block boxes itself, so wrapping it in an attachment
         * would draw a bar around a box.
         */
        'color' => null,

        /** Notification preview and the fallback for clients that cannot render blocks. */
        'fallback' => "{{ headline }}: {{ name }}\n{{ email }}\n{{ phone }}\n{{ revenue }}",

        /*
         * Built on a `card` block: it carries its own title/subtitle/body and renders boxed,
         * which is what the section-row layout was approximating with dividers and bold labels.
         *
         * Verified against chat.postMessage on 2026-09-16 rather than assumed — card is a newer
         * block type and not every Block Kit element is accepted on every surface.
         *
         * Buttons stay in their own `actions` block after the card. Until the app has an
         * interactivity request URL, Slack renders a "not configured to handle interactive
         * responses" notice beside them; `links_line` is the placeholder to swap in if the
         * buttons are ever traded for plain links.
         */
        'blocks' => [
            /*
             * The headline sits outside the card, so the channel is readable before anything
             * else — "New lead from Facebook", or "New organic lead" when nothing attributed.
             * That is the first thing a salesperson wants and the card title is not the place
             * for it, because the name is.
             */
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*{{ headline }}*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => '{{ name }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ revenue }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ contact_line }}', 'verbatim' => false],
            ],
            [
                'type' => 'context',
                '_when' => ['attribution_labeled'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => '{{ attribution_labeled }}'],
                ],
            ],
            [
                'type' => 'context',
                '_when' => ['landing_url'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'Landing page: <{{ landing_url }}|{{ landing_display }}>'],
                ],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        '_when' => ['admin_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Open in portal', 'emoji' => false],
                        'style' => 'primary',
                        'url' => '{{ admin_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['replay_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Watch session', 'emoji' => false],
                        'url' => '{{ replay_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['hubspot_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Open in HubSpot', 'emoji' => false],
                        'url' => '{{ hubspot_url }}',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Call booked
    |--------------------------------------------------------------------------
    |
    | Off by default (`SLACK_NOTIFY_ON_BOOKING`), because the legacy feed did not
    | send it. See HandleLeadEventsForSlack.
    */
    'booked' => [
        'color' => null,

        'fallback' => "Call booked: {{ name }}\n{{ email }}\n{{ meeting_time }}",

        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => "*Call booked*\n{{ name }}"],
            ],
            [
                'type' => 'section',
                'fields' => [
                    ['type' => 'mrkdwn', 'text' => "*Email*\n{{ email_link }}"],
                    ['type' => 'mrkdwn', 'text' => "*Phone*\n{{ phone }}"],
                    ['type' => 'mrkdwn', 'text' => "*Revenue*\n{{ revenue }}", '_when' => ['revenue']],
                    ['type' => 'mrkdwn', 'text' => "*When*\n{{ meeting_time }}", '_when' => ['meeting_time']],
                ],
            ],
            [
                'type' => 'actions',
                '_when' => ['admin_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        '_when' => ['meeting_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Join meeting', 'emoji' => false],
                        'url' => '{{ meeting_url }}',
                    ],
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open lead', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                ],
            ],
        ],
    ],
];
