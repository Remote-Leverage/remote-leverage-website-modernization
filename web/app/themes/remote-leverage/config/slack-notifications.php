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
 *   lead_id           what an action button carries; the handler reads everything else from
 *                     the database, which is what stops a payload asserting facts about a lead
 *   interactive       a flag, not a fact: non-empty only once SLACK_SIGNING_SECRET is set.
 *                     `_when` on it is how the action buttons stay hidden until they work
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
 * **No `style` on a message button.** Slack renders `primary` and `danger` as filled buttons
 * and everything else as outlined, so a single styled button in a row makes the row look
 * misaligned even though every button is the same height. Leaving them all unstyled is what
 * keeps an action row reading as one control group.
 *
 * The one exception is the `style` inside a `confirm` dialog, which colours the dialog's own
 * confirm button rather than anything in the channel. Block keeps it: the modal is where the
 * warning actually belongs, and it is the last moment before an irreversible action.
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

            /*
             * The buttons that do something here rather than send you somewhere.
             *
             * A separate row from the links above because they are a different kind of thing:
             * one row leaves Slack, the other changes a lead without leaving it. Six buttons on
             * one line wrap into an unreadable block at any sensible window width anyway.
             *
             * `_when: interactive` is the feature flag. It resolves empty until
             * SLACK_SIGNING_SECRET is set, and an unconfigured app must not render these —
             * Slack answers a button it cannot deliver with "not configured to handle
             * interactive responses" printed in the channel, under every lead, forever.
             */
            [
                'type' => 'actions',
                '_when' => ['interactive'],
                'elements' => [
                    [
                        'type' => 'button',
                        'action_id' => 'lead_claim',
                        'text' => ['type' => 'plain_text', 'text' => 'Claim', 'emoji' => false],
                        'value' => '{{ lead_id }}',
                    ],
                    [
                        'type' => 'button',
                        'action_id' => 'lead_contacted',
                        'text' => ['type' => 'plain_text', 'text' => 'Mark contacted', 'emoji' => false],
                        'value' => '{{ lead_id }}',
                    ],
                    [
                        'type' => 'button',
                        'action_id' => 'lead_block',
                        'text' => ['type' => 'plain_text', 'text' => 'Block', 'emoji' => false],
                        'value' => '{{ lead_id }}',

                        /*
                         * Blocking is silent and covers every identifier the person has ever
                         * used, so it is both the most destructive button here and the one whose
                         * effect is hardest to see afterwards. Slack's own confirm dialog is the
                         * cheapest guard against a mis-tap on a phone.
                         */
                        'confirm' => [
                            'title' => ['type' => 'plain_text', 'text' => 'Block this person?', 'emoji' => false],
                            'text' => [
                                'type' => 'mrkdwn',
                                'text' => 'Blocks every email, phone and device already linked to *{{ name }}*, and any identifier linked later. Their forms keep working and nothing reaches sales, the CRM or this channel again.',
                            ],
                            'confirm' => ['type' => 'plain_text', 'text' => 'Block', 'emoji' => false],
                            'deny' => ['type' => 'plain_text', 'text' => 'Cancel', 'emoji' => false],
                            'style' => 'danger',
                        ],
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
    | Action confirmations
    |--------------------------------------------------------------------------
    |
    | Posted by SlackInteractionController when somebody presses a button, as a reply under
    | that lead's own alert. Context blocks rather than sections: this is a margin note on a
    | message that is already there, and rendering it at the same weight as the lead itself
    | would make a busy channel read as twice as busy.
    |
    | The block confirmation is the exception. It broadcasts to the channel, because a
    | moderation decision taken silently by one person is the kind of thing the rest of the
    | team should be able to see and question.
    */
    'lead_claimed' => [
        'color' => null,
        'fallback' => '{{ actor }} claimed {{ name }}',
        'blocks' => [
            [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'Claimed by {{ actor }}'],
                ],
            ],
        ],
    ],

    'lead_contacted' => [
        'color' => null,
        'fallback' => '{{ actor }} marked {{ name }} contacted',
        'blocks' => [
            [
                'type' => 'context',
                'elements' => [
                    ['type' => 'mrkdwn', 'text' => 'Marked contacted by {{ actor }}'],
                ],
            ],
        ],
    ],

    'lead_blocked' => [
        'color' => null,
        'fallback' => '{{ actor }} blocked {{ name }}',
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Blocked by {{ actor }}*\n{{ name }} and every identifier linked to them. Nothing from this person reaches sales, the CRM or this channel again.",
                ],
            ],
            [
                'type' => 'actions',
                '_when' => ['admin_url'],
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Review in portal', 'emoji' => false],
                        'url' => '{{ admin_url }}',
                    ],
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
];
