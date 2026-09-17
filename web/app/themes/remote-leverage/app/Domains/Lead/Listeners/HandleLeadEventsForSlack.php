<?php

declare(strict_types=1);

namespace App\Domains\Lead\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Referral\Models\Referrer;
use App\Infrastructure\Slack\SlackCredentials;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Support\Facades\Log;

/**
 * The "NEW LEAD" Slack alert, ported from the Gravity Forms Slack feed.
 *
 * Two things about the legacy feed are load-bearing and easy to get wrong:
 *
 *  - **It fires on the partial submission, not the completed booking.** The GF feed's condition
 *    is `submission_type is not Final`. The alert exists so sales sees a lead the moment contact
 *    details are entered — including the people who never finish booking, who are exactly the
 *    ones worth chasing. Firing on the final submission instead would alert only on leads that
 *    already booked themselves.
 *  - **The message format is what the sales team reads at a glance.** Field order and labels are
 *    reproduced from the feed rather than redesigned.
 *
 * Transport: {@see SlackTransport}, shared with the referral and live-call alerts — bot token
 * where one is configured, incoming webhook otherwise. The app is now this site's own ("Remote
 * Leverage Website") rather than the Gravity Forms add-on's, which is what made threading and
 * the action buttons possible. See docs/slack-app.md.
 */
class HandleLeadEventsForSlack
{
    /** The GF feed's `submission_type` value that suppresses the alert. */
    private const SUPPRESS_ON = 'Final';

    public function __construct(
        protected LeadActivityLogger $activityLogger,
        protected SlackTransport $transport = new SlackTransport,
    ) {}

    /**
     * Handle LeadCreated — the step 1 partial capture.
     */
    public function handleCreated(LeadCreated $event): void
    {
        /*
         * Shadow ban. The form succeeded and the lead is stored, but nothing downstream fires.
         *
         * Silence rather than a refusal is deliberate: an error tells someone which identifier
         * to change and they are back within a minute under a new address. This costs them
         * nothing to trigger and a great deal to detect.
         */
        if ($event->lead->is_blocked) {
            return;
        }

        $submissionType = (string) ($event->lead->submission_type ?? '');

        // Parity with the GF feed's condition. LeadCreated fires for both the partial capture
        // and the completed booking, so without this the team gets two alerts per lead and the
        // second is the less useful one.
        if (strcasecmp($submissionType, self::SUPPRESS_ON) === 0) {
            return;
        }

        $this->dispatchSlackNotification($event->lead, 'partial');
    }

    /**
     * Handle LeadBookingCompleted.
     *
     * **On by default** since 2026-09-17. It used to be off because the legacy Gravity Forms
     * feed never sent one — a description of the old system rather than a reason to keep the
     * new one quiet about the event sales actually acts on. The alert threads under the lead's
     * existing card rather than starting a new one. Silence it with
     * `SLACK_NOTIFY_ON_BOOKING=false`.
     */
    public function handleBookingCompleted(LeadBookingCompleted $event): void
    {
        if (! config('services.slack.notify_on_booking', true)) {
            return;
        }

        $this->dispatchSlackNotification($event->lead, 'final', [
            'meeting_id' => $event->meetingId,
            'start_time' => $event->startTime,
            'meet_url' => $event->meetUrl,
        ]);
    }

    /**
     * Build and send the message.
     *
     * The partial opens the thread; everything afterwards replies into it. That is why the
     * `ts` is stored here rather than only read: a lead's whole story — booked, canceled,
     * claimed, blocked — hangs off the one card the channel already scrolled past, instead of
     * arriving as a fresh card with no stated relationship to the first.
     */
    protected function dispatchSlackNotification(Lead $lead, string $type, array $context = []): void
    {
        $isFinal = $type === 'final';

        $rendered = app(SlackMessageRenderer::class)->render(
            $isFinal ? 'booked' : 'new_lead',
            $this->valuesFor($lead, $context),
        );

        /*
         * Broadcast the reply. A booking is the event the team is waiting for, and a plain
         * threaded reply is collapsed behind "1 reply" for anyone not already watching the
         * thread — quieter than the flat message it replaced, which is the wrong trade for
         * this one.
         */
        $threadTs = $isFinal ? $this->threadTsFor($lead) : null;

        try {
            $result = $this->send(
                $rendered['text'],
                $rendered['blocks'],
                $rendered['color'] ?? null,
                $threadTs,
                broadcast: $threadTs !== null,
            );

            $success = $result !== null;

            if (! $isFinal) {
                $this->rememberThread($lead, $result);
            }

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: $isFinal ? 'LeadBookingCompleted' : 'LeadCreated',
                actorDomain: 'Slack',
                outcome: $success ? 'succeeded' : 'failed',
                description: $success
                    ? "Dispatched {$type} notification to Slack"
                    : "Failed to dispatch {$type} notification to Slack",
                payload: ['type' => $type],
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadEventsForSlack: Exception sending to Slack for lead #{$lead->id}: ".$e->getMessage());
        }
    }

    /**
     * The message later events should reply to, or null to post at top level.
     *
     * A `ts` identifies a message only within the channel that produced it. If `SLACK_CHANNEL`
     * has changed since the parent was posted, replying with the old timestamp does not fail —
     * Slack posts it flat, which merely looks like the threading is broken. Checking the stored
     * channel is what keeps that from being mysterious.
     */
    protected function threadTsFor(Lead $lead): ?string
    {
        $ts = trim((string) $lead->slack_message_ts);

        if ($ts === '') {
            return null;
        }

        $stored = trim((string) $lead->slack_channel_id);

        // Through SlackCredentials, not config directly: an environment wired by the admin
        // setting would otherwise compare against an empty string here and thread onto a
        // message in whatever channel it used to post to.
        $current = SlackCredentials::channel();

        // Only a definite disagreement disqualifies it. An unknown on either side is the
        // webhook transport or an older row, and flat-posting those would be a regression.
        if ($stored !== '' && $current !== '' && $stored !== $current) {
            return null;
        }

        return $ts;
    }

    /**
     * Store where the alert landed, so the rest of this lead's life can reply to it.
     *
     * `saveQuietly` on purpose: this is bookkeeping about a notification, and letting it emit
     * model events would re-enter the very listeners the notification came from. Skipped for a
     * lead that is not in the database — the value would have nowhere to go, and inserting one
     * to hold it would invent a lead.
     */
    protected function rememberThread(Lead $lead, ?array $result): void
    {
        $ts = $result['ts'] ?? null;

        if ($ts === null || $ts === '' || ! $lead->exists) {
            return;
        }

        $lead->forceFill([
            'slack_message_ts' => (string) $ts,
            'slack_channel_id' => $result['channel'] ?? null,
        ])->saveQuietly();
    }

    /**
     * The legacy feed's message, field for field.
     *
     * Reproduced rather than redesigned: the sales team reads these at a glance all day, and
     * the order is the order they scan in.
     */
    /**
     * The values a template may reference.
     *
     * This is the seam that makes the layout designable: the listener's job is to say what is
     * known about a lead, and `config/slack-notifications.php` decides how it looks. Adding a
     * field to the message is a config edit; adding a new *fact* is a key here.
     *
     * @return array<string, string>
     */
    protected function valuesFor(Lead $lead, array $context = []): array
    {
        $name = trim(($lead->first_name ?: $lead->name).' '.(string) $lead->last_name);
        $landing = (string) ($lead->landing_page_base ?: $lead->landing_url ?: '');

        return [
            /*
             * Bound to the card's title, which cannot be `_when` guarded and is not an optional
             * key — so an empty name costs the whole card (name, revenue and contact details),
             * leaving only the headline. Every capture path validates a name today, so this is
             * a guard against a lead blanked in wp-admin or a future caller, not a live bug.
             */
            'name' => $name !== '' ? $name : 'Unnamed lead',
            'email' => (string) $lead->email,
            'email_link' => $lead->email ? "<mailto:{$lead->email}|{$lead->email}>" : '',
            'phone' => (string) $lead->phone,
            'revenue' => (string) $lead->monthly_revenue,
            'company' => (string) $lead->company,

            'source' => (string) $lead->utm_source,
            'medium' => (string) $lead->utm_medium,
            'campaign' => (string) $lead->utm_campaign,
            'content' => (string) $lead->utm_content,
            'term' => (string) $lead->utm_term,
            'partner' => (string) $lead->partner,

            /*
             * Pre-joined so a template can put attribution on one line without rendering
             * "linkedin ·  · va-q4" when a UTM is absent. The renderer substitutes values; it
             * deliberately does not know how to join them, because that is a layout decision
             * that differs per template.
             */
            'attribution_line' => implode(' · ', array_filter([
                (string) $lead->utm_source,
                (string) $lead->utm_medium,
                (string) $lead->utm_campaign,
                (string) $lead->utm_content,
                (string) $lead->utm_term,
            ])),

            /*
             * The same attribution as backtick chips, which Slack renders as small grey pills —
             * the nearest thing it has to a tag. Each carries its label because a row of bare
             * values gives no way to tell campaign from content, which is what made the original
             * one-line version unreadable.
             */
            /*
             * The same pairs without backticks, for rendering in a `context` block.
             *
             * Slack's inline-code styling is what draws the chip outline, and it is also what
             * makes the text orange — mrkdwn has no colour control, so the box and the colour
             * are the same decision. A context block gives small grey text instead, which is
             * what secondary information should look like.
             */
            'attribution_labeled' => $this->tags([
                'source' => (string) $lead->utm_source,
                'medium' => (string) $lead->utm_medium,
                'campaign' => (string) $lead->utm_campaign,
                'content' => (string) $lead->utm_content,
                'term' => (string) $lead->utm_term,
                'id' => (string) $lead->utm_id,
                'gclid' => (string) $lead->gclid,
                'fbclid' => (string) $lead->fbclid,
                'partner' => (string) $lead->partner,
            ], code: false),

            'attribution_tags' => $this->tags([
                'source' => (string) $lead->utm_source,
                'medium' => (string) $lead->utm_medium,
                'campaign' => (string) $lead->utm_campaign,
                'content' => (string) $lead->utm_content,
                'term' => (string) $lead->utm_term,
                'id' => (string) $lead->utm_id,
                'gclid' => (string) $lead->gclid,
                'fbclid' => (string) $lead->fbclid,
                'partner' => (string) $lead->partner,
            ]),
            'contact_line' => implode('   ', array_filter([
                $lead->email ? "<mailto:{$lead->email}|{$lead->email}>" : '',
                (string) $lead->phone,
            ])),

            'landing_url' => $landing,
            'landing_display' => $landing === '' ? '' : $this->shorten($landing),

            /*
             * A line of small print when the lead does not look like a client — a phone number
             * from outside the US and Canada. It resolves empty for everyone else and the block
             * that carries it is `_when`-gated, so a normal lead's alert is unchanged.
             */
            'audience_note' => (string) ($lead->audience()->note() ?? ''),

            'replay_url' => (string) ($lead->posthogReplayUrl() ?? ''),
            'hubspot_url' => (string) ($lead->hubspotContactUrl() ?? ''),

            /*
             * The three destinations as one markdown line. Assembled here rather than as three
             * template blocks because the separator between them only makes sense once you know
             * which of them survived.
             */
            'channel_label' => $this->channelLabel($lead),
            'headline' => $this->headline($lead),

            'links_line' => implode('   ·   ', array_filter([
                $lead->id && function_exists('admin_url')
                    ? '<'.\admin_url('admin.php?page=rl-leads&view_lead='.$lead->id).'|Open in portal>'
                    : '',
                $lead->posthogReplayUrl() ? '<'.$lead->posthogReplayUrl().'|Watch session>' : '',
                $lead->hubspotContactUrl() ? '<'.$lead->hubspotContactUrl().'|Open in HubSpot>' : '',
            ])),
            'admin_url' => $lead->id && function_exists('admin_url')
                ? (string) \admin_url('admin.php?page=rl-leads&view_lead='.$lead->id)
                : '',

            'submission_type' => (string) $lead->submission_type,
            'meeting_time' => (string) ($context['start_time'] ?? ''),
            'meeting_url' => (string) ($context['meet_url'] ?? ''),

            /*
             * What an action button needs to name the lead it acts on. A Block Kit button
             * carries a single opaque `value` string, so the lead id is the whole payload —
             * everything else the handler needs it reads from the database, which is also what
             * stops a crafted interaction from asserting facts about a lead.
             */
            'lead_id' => (string) ($lead->id ?? ''),

            /*
             * Gates the action buttons. Until the app has a signing secret there is no
             * interactivity request URL either, and Slack renders "not configured to handle
             * interactive responses" beside every button — so an unconfigured environment gets
             * the link buttons it always had and none of the broken ones.
             *
             * The value is a flag, not a fact, which is why it renders as the empty string when
             * off: `_when` drops a block whose named value is empty, and that is the whole
             * mechanism.
             */
            'interactive' => $this->interactionsEnabled() && $lead->id ? 'yes' : '',
        ];
    }

    /**
     * Is there anywhere for a button press to go?
     */
    protected function interactionsEnabled(): bool
    {
        return SlackCredentials::signingSecret() !== '';
    }

    /**
     * A human name for where this lead came from.
     *
     * `utm_source` is raw and inconsistent — "fb", "facebook" and "Meta" are the same channel to
     * a salesperson and three different strings in the data. This maps them onto the words the
     * team actually uses, so the alert says "Facebook" rather than "fb".
     *
     * Returns an empty string when there is nothing to attribute, which is what makes the lead
     * organic rather than unattributed-paid.
     */
    protected function channelLabel(Lead $lead): string
    {
        // A partner referral is the strongest signal: it names an actual relationship, where a
        // UTM only names a platform.
        if ($partner = trim((string) $lead->partner)) {
            return ucfirst($partner);
        }

        $source = strtolower(trim((string) $lead->utm_source));
        $medium = strtolower(trim((string) $lead->utm_medium));

        if ($source === '') {
            return '';
        }

        $paid = in_array($medium, ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'ads', 'display'], true);

        return match (true) {
            str_contains($source, 'facebook'), $source === 'fb', str_contains($source, 'meta') => 'Facebook',
            str_contains($source, 'instagram'), $source === 'ig' => 'Instagram',
            str_contains($source, 'linkedin') => 'LinkedIn',
            str_contains($source, 'tiktok') => 'TikTok',
            str_contains($source, 'youtube') => 'YouTube',
            str_contains($source, 'twitter'), $source === 'x' => 'X',
            str_contains($source, 'google') => $paid ? 'Google Ads' : 'Google',
            str_contains($source, 'bing'), str_contains($source, 'microsoft') => $paid ? 'Microsoft Ads' : 'Bing',
            str_contains($source, 'newsletter'), str_contains($source, 'email'), $medium === 'email' => 'Email',
            default => ucfirst($source),
        };
    }

    /**
     * The line above the card.
     *
     * "New lead from Facebook" tells the team where to look before they read anything else;
     * "New organic lead" says the same thing about the absence of a campaign, which is
     * information rather than a gap.
     */
    protected function headline(Lead $lead): string
    {
        /*
         * A referral outranks every other channel, and is checked here rather than in
         * channelLabel() so the wording can differ: "New referral from Dana Whitfield" names a
         * person who is owed a commission, which is a different fact from "New lead from
         * Facebook" naming a platform.
         *
         * This used to be missed entirely. channelLabel() reads `partner` and the UTMs, and
         * neither is set by the referral programme — that attribution lives in
         * `source_type`/`source_id` — so a correctly attributed referral with no campaign
         * parameters fell through to "New organic lead". The lead was right in the database and
         * wrong in the only place anyone reads it.
         */
        if ($referrer = $this->referrerName($lead)) {
            return "New referral from {$referrer}";
        }

        $channel = $this->channelLabel($lead);

        return $channel === '' ? 'New organic lead' : "New lead from {$channel}";
    }

    /**
     * The name of the referrer this lead is attributed to, or null.
     *
     * Falls back to the raw code when the referrer row cannot be found: a lead stamped
     * `referral_hub:some-code` is still a referral, and saying so with the code is more use to
     * whoever reads the alert than calling it organic.
     */
    protected function referrerName(Lead $lead): ?string
    {
        if (! in_array($lead->source_type, ['referral_hub', 'partnership'], true)) {
            return null;
        }

        $code = trim((string) $lead->source_id);

        if ($code === '') {
            return null;
        }

        try {
            $name = Referrer::query()->where('referral_code', $code)->value('name');
        } catch (\Throwable $e) {
            // Never let a lookup cost the alert; the headline degrades, the message still sends.
            $name = null;
        }

        return trim((string) ($name ?: $code)) ?: null;
    }

    /**
     * Render a label => value map as backtick chips, skipping anything empty.
     *
     * Long click ids are truncated: a 90-character gclid on its own wraps the whole row and
     * buries the campaign next to it, and nobody reads a click id off Slack anyway — it is
     * there to say the click was attributed, not to be copied.
     *
     * @param  array<string, string>  $pairs
     */
    protected function tags(array $pairs, int $maxValue = 24, bool $code = true): string
    {
        $chips = [];

        foreach ($pairs as $label => $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            if (mb_strlen($value) > $maxValue) {
                $value = mb_substr($value, 0, $maxValue - 1).'…';
            }

            $chips[] = $code
                ? '`'.$label.': '.$value.'`'
                : $label.': '.$value;
        }

        return implode($code ? '  ' : '   ·   ', $chips);
    }

    /**
     * Trim a URL for display without losing which page it was.
     */
    protected function shorten(string $url, int $max = 60): string
    {
        $display = preg_replace('#^https?://#', '', $url) ?? $url;

        return mb_strlen($display) > $max ? mb_substr($display, 0, $max - 1).'…' : $display;
    }

    /**
     * Send by bot token where one is configured, else by incoming webhook.
     *
     * A thin seam over {@see SlackTransport} rather than the transport itself: the tests for
     * this listener assert on what it *would* send by overriding this one method, and that is
     * worth keeping now that three listeners share the transport underneath.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{ts: ?string, channel: ?string}|null Null when nothing was sent.
     */
    protected function send(
        string $text,
        array $blocks = [],
        ?string $color = null,
        ?string $threadTs = null,
        bool $broadcast = false,
    ): ?array {
        return $this->transport->post($text, $blocks, $color, $threadTs, $broadcast);
    }
}
