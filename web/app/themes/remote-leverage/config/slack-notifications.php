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
 * The live-call and referral templates carry their own sets — see the listeners that build
 * them, and docs/slack-app.md for which template each event renders.
 *
 * ## Conditional blocks
 *
 * A block carrying `_when` is dropped unless every listed placeholder resolves to a non-empty
 * value. That is what keeps a lead with no campaign data from rendering a grid of dashes — an
 * alert full of empty fields teaches people to stop reading it.
 *
 * Fields inside a `section` support `_when` the same way and are removed individually.
 *
 * ## House rules
 *
 * **No emoji, anywhere.** Hierarchy comes from headers, dividers and field grouping. Every
 * family of templates below has a test asserting it, because the Block Kit Builder emoji picker
 * is one click away from the JSON you are about to paste in here.
 *
 * **Link buttons only.** Every button in every template here is a `url` button, which needs no
 * signing secret and no handler — it works the moment the card posts. An interactive button
 * would need an app configured to receive it, and Slack prints "not configured to handle
 * interactive responses" beside every one it cannot deliver, under every card, forever.
 *
 * **No `style` on a message button.** Slack renders `primary` and `danger` as filled buttons
 * and everything else as outlined, so a single styled button in a row makes the row look
 * misaligned even though every button is the same height. Leaving them all unstyled is what
 * keeps an action row reading as one control group.
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
         * The buttons stay in their own `actions` block after the card, and they are links —
         * they take you to the portal, the replay or HubSpot rather than changing anything from
         * inside Slack.
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
                /*
                 * Absent on most alerts by design. Only the booking wizard collects revenue;
                 * gated downloads, instant-call requests and referrer-submitted leads do not,
                 * so this is pruned (SlackMessageRenderer::OPTIONAL_TEXT_KEYS) and the card
                 * ships without a subtitle. That is correct — there is no revenue to report —
                 * and better than inventing a "not given" line on the majority of cards.
                 */
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ revenue }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ contact_line }}', 'verbatim' => false],
            ],

            /*
             * Small print directly under the card when the lead may not be a client at all —
             * a phone number from outside the US and Canada, see LeadAudience. It sits against
             * the name and phone it is about rather than down with the attribution, and it is
             * deliberately quiet: a reason to look twice before booking a call, not a reason to
             * skip the lead. `_when` gates it, so every other alert is unchanged.
             */
            [
                'type' => 'context',
                '_when' => ['audience_note'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => '{{ audience_note }}'],
                ],
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
    | On by default (`SLACK_NOTIFY_ON_BOOKING=false` to silence). It was off because the legacy feed did not
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
                    // Guarded like the two below them. The literal `*Email*` / `*Phone*` label
                    // keeps the text object non-empty, so an unset value is never fatal — it
                    // renders a bold heading with nothing under it, which reads as a bug. A
                    // lead captured by gated download or instant call has no phone.
                    ['type' => 'mrkdwn', 'text' => "*Email*\n{{ email_link }}", '_when' => ['email_link']],
                    ['type' => 'mrkdwn', 'text' => "*Phone*\n{{ phone }}", '_when' => ['phone']],
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
    /*
    |--------------------------------------------------------------------------
    | Amended — the visitor changed an answer and submitted step 1 again
    |--------------------------------------------------------------------------
    |
    | Most often the revenue-band warning: someone picks a band, reads what it says, goes back
    | and picks another. The card above has already been edited to the new answers, so this is
    | the record of what moved — a margin note, in context grey, not a second lead.
    |
    | A repeat submission that changed nothing renders no message at all; the listener never
    | reaches this template. See HandleLeadEventsForSlack::dispatchAmendment().
    |
    |   changes   one "field: old → new" per line, already joined
    */
    'lead_amended' => [
        'color' => null,
        'fallback' => '{{ name }} amended their answers: {{ changes }}',
        'blocks' => [
            [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => "Amended before booking\n{{ changes }}"],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Live calls
    |--------------------------------------------------------------------------
    |
    | Somebody asked to talk to a consultant right now. The routed one is a summons: a real
    | meeting starts within fifteen minutes and it is already booked. The declined one is the
    | more useful of the two, because until it existed nobody could see that it had happened.
    */
    'live_call_routed' => [
        'color' => null,
        'fallback' => "Live call connecting now: {{ name }}\n{{ email }}\n{{ phone }}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*{{ headline }}*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => '{{ name }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => 'Starting within 15 minutes', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ contact_line }}', 'verbatim' => false],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        '_when' => ['meeting_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Join call', 'emoji' => false],
                        'url' => '{{ meeting_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['admin_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Open in portal', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                ],
            ],
        ],
    ],

    'live_call_declined' => [
        'color' => null,
        'fallback' => "{{ headline }}: {{ name }}\n{{ reason_label }}\n{{ email }}\n{{ phone }}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*{{ headline }}*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => '{{ name }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ reason_label }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ contact_line }}', 'verbatim' => false],
            ],

            /*
             * Only on the refusals that are ours to fix. A busy team needs no explanation; a
             * missing event type has been turning every visitor away since it broke, and saying
             * so is the difference between a quiet afternoon and an incident.
             */
            [
                'type' => 'context',
                '_when' => ['our_fault'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'This is a configuration or API failure, not availability. Every live call request fails until it is fixed.'],
                ],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        '_when' => ['admin_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Open in portal', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['replay_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Watch session', 'emoji' => false],
                        'url' => '{{ replay_url }}',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Referral programme
    |--------------------------------------------------------------------------
    |
    | Dispatched since the programme shipped and, until now, visible only in wp-admin. A
    | referral is the time-sensitive one: somebody vouched for us to a person they know, and
    | the follow-up either happens while that is warm or it does not happen.
    */
    'referrer_registered' => [
        'color' => null,
        'fallback' => "New referrer: {{ name }}\n{{ email }}\n{{ referral_code }}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*New referrer registered*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => '{{ name }}', 'verbatim' => false],
                /*
                 * `company` used to sit here and is never collected: the public registration
                 * form asks only for name, email and password, so this resolved to an empty
                 * string and Slack rejected the whole message with `invalid_blocks`. Every
                 * referrer registration went unannounced. SlackMessageRenderer now also prunes
                 * an empty subtitle, so this is belt and braces rather than the only guard.
                 */
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ status }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ email_link }}', 'verbatim' => false],
            ],
            [
                'type' => 'context',
                '_when' => ['referral_code'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'Code: {{ referral_code }}   ·   Status: {{ status }}'],
                ],
            ],
            [
                'type' => 'actions',
                '_when' => ['admin_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open referrers', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                ],
            ],
        ],
    ],

    'referral_recorded' => [
        'color' => null,
        'fallback' => "Referral from {{ referrer_name }}: {{ name }}\n{{ email }}\n{{ phone }}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*Referral from {{ referrer_name }}*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => '{{ name }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ status }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ contact_line }}', 'verbatim' => false],
            ],
            [
                'type' => 'context',
                '_when' => ['referral_code'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'Code: {{ referral_code }}'],
                ],
            ],
            [
                'type' => 'actions',
                '_when' => ['admin_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open referrals', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                ],
            ],
        ],
    ],

    'payout_completed' => [
        'color' => null,
        'fallback' => 'Payout sent: {{ amount }} to {{ referrer_name }}',
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*Referral payout sent*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => '{{ amount }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ referrer_name }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ referral_count }}', 'verbatim' => false],
            ],
            [
                'type' => 'context',
                '_when' => ['transfer_id'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'Transfer: {{ transfer_id }}   ·   Status: {{ status }}'],
                ],
            ],
            [
                'type' => 'actions',
                '_when' => ['admin_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open payouts', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendly tier filling up
    |--------------------------------------------------------------------------
    |
    | The leading indicator for the sell-out below: a tier's rolling booking
    | window has crossed 90% or 95% full. Sent once per crossing rather than on
    | a repeat, because unlike a sell-out this is a heads-up and not an active
    | revenue stop — see the band ladder in AvailabilityHealthMonitor.
    |
    | One template for both thresholds. They differ only in wording, and two
    | near-identical templates drift apart the first time one is edited.
    |
    | `remaining` leads the card because it is the number that decides what to
    | do: "6 slots left" is actionable in a way "91.2% full" is not.
    */
    'availability_filling_up' => [
        'color' => null,
        'fallback' => "Calendly {{ tier_label }} is {{ fill_label }}\n{{ remaining }} in the {{ window_label }}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*{{ headline }}*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => 'Calendly {{ tier_label }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ remaining }} in the {{ window_label }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ fill_label }} — {{ counts }}.', 'verbatim' => false],
            ],
            [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => '{{ severity }}. Nothing is broken yet — extend the date range or open host availability on the event type before it sells out.'],
                ],
            ],
            [
                'type' => 'actions',
                '_when' => ['admin_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open diagnostics', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['event_type_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Open in Calendly', 'emoji' => false],
                        'url' => '{{ event_type_url }}',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendly tier sold out
    |--------------------------------------------------------------------------
    |
    | A revenue tier has no bookable times left anywhere — not merely none in the
    | month someone is looking at. See AvailabilityHealthMonitor.
    |
    | The card names the tier rather than a person, because this is the one alert
    | here that is about a calendar and not about a lead. `next_check` and the
    | guidance line exist to stop it reading as an outage: on 2026-09-17 t10 sold
    | out overnight and the first hour of the response went into confirming it was
    | not a bug.
    */
    'availability_sold_out' => [
        'color' => null,
        'fallback' => "Calendly {{ tier_label }} has no bookable times\n{{ month }}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '*No bookable times left*'],
            ],
            [
                'type' => 'card',
                'title' => ['type' => 'mrkdwn', 'text' => 'Calendly {{ tier_label }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => 'Every visitor routed to this tier sees an empty calendar', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => 'Checked {{ month }}, and no later date is open either.', 'verbatim' => false],
            ],
            [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'Usually a sell-out, not an outage. Check the date range and host availability on the event type before treating it as a bug.'],
                ],
            ],
            [
                'type' => 'actions',
                '_when' => ['admin_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Open diagnostics', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['event_type_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Open in Calendly', 'emoji' => false],
                        'url' => '{{ event_type_url }}',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketing cost alert
    |--------------------------------------------------------------------------
    |
    | One card per day, edited in place through the working day rather than reposted — see
    | App\Domains\Marketing\Actions\SendCostAlertAction. Nothing else in this file updates
    | itself, so the layout is built to survive being redrawn: no counters, no "since the last
    | message" phrasing, every line true standing alone at the moment it is rendered.
    |
    | The platform table arrives pre-formatted in `platform_table` rather than as a repeated
    | block, because this renderer substitutes scalars and has no loop. That is the same trick
    | `attribution_line` uses on the lead alert, and it keeps the column alignment here — Slack
    | renders a fenced block in a monospace font, which is the only way a table of costs lines
    | up in a channel people read on a phone.
    |
    | Most sections are `_when`-gated on a value the alert may not have. Phase 1 ships with no ad
    | platform integration at all, so `spend_summary` is empty and `spend_pending` explains why
    | in its place. When spend lands the two swap over with no change to this file.
    */
    'marketing_cost_alert' => [

        /*
         * Red only when the reconciliation found something. A daily digest that is permanently
         * coloured is a daily digest nobody's eye stops on.
         */
        'color' => null,

        'fallback' => "Marketing Cost Alert — {{ subheading }}\n{{ headline_metrics }}",

        /*
         * Structured as labelled text, not as tiles.
         *
         * This was a grid of `section.fields`, then three carousels of cards. Both looked better
         * in isolation and both read as busy in the channel — the feedback that settled it was
         * from the person who reads it daily: "great info, i wonder if we can structure it like
         * the previous ones, easy to read through text". The legacy alert was a wall of terse
         * `Label: value` lines under bold headings, and that is genuinely faster to scan than a
         * card you have to parse the shape of first.
         *
         * So the layout is back to text and the *content* keeps everything the rewrite earned:
         * paid cost separated from blended, the attribution gap broken down by channel, the
         * reconciliation findings at the top, the VA exclusion counted out loud.
         *
         * Four sections, one line per idea. Adding a fifth is how this becomes busy again.
         */
        'blocks' => [
            /*
             * Anything the reader has to know before they read a number: that the figures are
             * fabricated, or that the card came from an environment that is not production.
             *
             * Above the header rather than below it. A card of plausible marketing figures in a
             * channel where people read real ones has to announce itself first, not after
             * somebody has already reacted to the numbers.
             */
            [
                'type' => 'section',
                '_when' => ['notice'],
                'text' => ['type' => 'mrkdwn', 'text' => '{{ notice }}'],
            ],
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => 'Marketing Cost Alert', 'emoji' => false],
            ],
            [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => '{{ subheading }}'],
                ],
            ],

            /*
             * The audit is NOT here.
             *
             * It sat at the top of the card, in a red attachment, from the day the card was
             * built — on the reasoning that its whole purpose is to stop someone acting on the
             * figures below it. In practice the findings that fire most are the ordinary ones, so
             * the card was red most of the day and the colour stopped meaning anything.
             *
             * The findings now go in a threaded reply instead: still attached to the card they
             * are about, still unmissable to anyone reading it, and no longer repainting the
             * whole message. See SendCostAlertAction::deliver().
             */

            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '{{ today_block }}'],
            ],
            /*
             * Yesterday, closed — overnight only, boxed so it reads as a separate thing from the
             * running day above it. It was an italic line under today and nobody saw it.
             */
            [
                'type' => 'card',
                '_when' => ['closed_title'],
                'title' => ['type' => 'mrkdwn', 'text' => '{{ closed_title }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ closed_subtitle }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ closed_body }}', 'verbatim' => false],
            ],
            /*
             * The platforms stay boxed, but stacked rather than in a carousel.
             *
             * A carousel scrolls sideways, which on a phone is the one gesture people do not
             * expect inside a message — the third platform is behind a chevron on the surface
             * where most of these are read. Stacked cards keep the boxes and lose the scroll.
             *
             * Top-level cards also fail better than carousel items. The renderer prunes an empty
             * optional text object on a top-level block, so a platform with no subtitle loses its
             * subtitle; nested in a carousel the same emptiness tripped `hasEmptyTextObject` and
             * took every platform with it.
             *
             * Six slots because the renderer substitutes scalars and cannot loop. Six covers every
             * platform that has ever carried spend here; `LeadPlatform` knows seven in total.
             */
            [
                'type' => 'section',
                '_when' => ['platform_1_title'],
                'text' => ['type' => 'mrkdwn', 'text' => '*By platform — {{ platform_period }}*'],
            ],
            [
                'type' => 'card',
                '_when' => ['platform_1_title'],
                'title' => ['type' => 'mrkdwn', 'text' => '{{ platform_1_title }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ platform_1_subtitle }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ platform_1_body }}', 'verbatim' => false],
            ],
            [
                'type' => 'card',
                '_when' => ['platform_2_title'],
                'title' => ['type' => 'mrkdwn', 'text' => '{{ platform_2_title }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ platform_2_subtitle }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ platform_2_body }}', 'verbatim' => false],
            ],
            [
                'type' => 'card',
                '_when' => ['platform_3_title'],
                'title' => ['type' => 'mrkdwn', 'text' => '{{ platform_3_title }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ platform_3_subtitle }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ platform_3_body }}', 'verbatim' => false],
            ],
            [
                'type' => 'card',
                '_when' => ['platform_4_title'],
                'title' => ['type' => 'mrkdwn', 'text' => '{{ platform_4_title }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ platform_4_subtitle }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ platform_4_body }}', 'verbatim' => false],
            ],
            [
                'type' => 'card',
                '_when' => ['platform_5_title'],
                'title' => ['type' => 'mrkdwn', 'text' => '{{ platform_5_title }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ platform_5_subtitle }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ platform_5_body }}', 'verbatim' => false],
            ],
            [
                'type' => 'card',
                '_when' => ['platform_6_title'],
                'title' => ['type' => 'mrkdwn', 'text' => '{{ platform_6_title }}', 'verbatim' => false],
                'subtitle' => ['type' => 'mrkdwn', 'text' => '{{ platform_6_subtitle }}', 'verbatim' => false],
                'body' => ['type' => 'mrkdwn', 'text' => '{{ platform_6_body }}', 'verbatim' => false],
            ],
            [
                'type' => 'section',
                '_when' => ['activity_block'],
                'text' => ['type' => 'mrkdwn', 'text' => '{{ activity_block }}'],
            ],

            /*
             * Definitions and exclusions, as one line of small print. The reasoning behind each
             * lives in docs/marketing-cost-alerts.md; only the numbers that move belong here.
             */
            [
                'type' => 'context',
                '_when' => ['footnotes'],
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => '{{ footnotes }}'],
                ],
            ],

            /*
             * Link buttons, not interactive ones. A link needs no signing secret and no handler,
             * so it works the moment the card posts — where an interactive button renders a "not
             * configured to handle interactive responses" notice under every card in any
             * environment that has not finished its Slack setup. Each is gated on its URL,
             * because a button pointing nowhere is worse than no button.
             */
            [
                'type' => 'actions',
                '_when' => ['dashboard_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        '_when' => ['dashboard_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Dashboard', 'emoji' => false],
                        'url' => '{{ dashboard_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['leads_url'],
                        'text' => ['type' => 'plain_text', 'text' => "Today's leads", 'emoji' => false],
                        'url' => '{{ leads_url }}',
                    ],
                    [
                        'type' => 'button',
                        '_when' => ['unattributed_url'],
                        'text' => ['type' => 'plain_text', 'text' => 'Unattributed', 'emoji' => false],
                        'url' => '{{ unattributed_url }}',
                    ],
                ],
            ],
        ],
    ],
];
