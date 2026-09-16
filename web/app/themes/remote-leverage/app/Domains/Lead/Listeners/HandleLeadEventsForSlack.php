<?php

declare(strict_types=1);

namespace App\Domains\Lead\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Lead\Services\SlackMessageRenderer;
use Illuminate\Support\Facades\Http;
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
 * Transport: production posts through the Gravity Forms Slack add-on's bot token to a channel
 * (`chat.postMessage`). This supports that, and falls back to a plain incoming webhook when no
 * token is configured. Moving to a dedicated Slack app is WR-186.
 */
class HandleLeadEventsForSlack
{
    /** The GF feed's `submission_type` value that suppresses the alert. */
    private const SUPPRESS_ON = 'Final';

    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle LeadCreated — the step 1 partial capture.
     */
    public function handleCreated(LeadCreated $event): void
    {
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
     * **Off by default**, because the legacy feed did not send it — see the class docblock.
     * Enable with `SLACK_NOTIFY_ON_BOOKING=true` if the team wants a second alert when a lead
     * actually books.
     */
    public function handleBookingCompleted(LeadBookingCompleted $event): void
    {
        if (! config('services.slack.notify_on_booking', false)) {
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
     */
    protected function dispatchSlackNotification(Lead $lead, string $type, array $context = []): void
    {
        $isFinal = $type === 'final';

        $rendered = app(SlackMessageRenderer::class)->render(
            $isFinal ? 'booked' : 'new_lead',
            $this->valuesFor($lead, $context),
        );

        try {
            $success = $this->send($rendered['text'], $rendered['blocks'], $rendered['color'] ?? null);

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
            'name' => $name,
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
        ];
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
        $channel = $this->channelLabel($lead);

        return $channel === '' ? 'New organic lead' : "New lead from {$channel}";
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
     * The bot path is what production uses and is the only one that can target a channel by id.
     * Both are supported because the webhook needs no Slack app, and a dedicated app is still
     * pending (WR-186).
     */
    protected function send(string $text, array $blocks = [], ?string $color = null): bool
    {
        /*
         * Environment first, admin setting second — the same precedence HubSpotGateway and the
         * ZeroBounce check use. The setting exists because ECS maps Secrets Manager keys to
         * environment variables one at a time in the task definition, so a newly added
         * credential is unreachable until that changes; this lets an environment be wired
         * without waiting on it.
         */
        $settings = (new LeadSettingsService)->get();

        $token = (string) (config('services.slack.bot_token') ?: ($settings['slack_bot_token'] ?? ''));
        $channel = (string) (config('services.slack.channel') ?: ($settings['slack_channel'] ?? ''));

        if ($token !== '' && $channel !== '') {
            $payload = [
                'channel' => $channel,
                'text' => $text,
                'mrkdwn' => true,

                /*
                 * No link previews. The landing page is a marketing page, so Slack unfurls it
                 * into a card with the hero copy and a reading time — several times taller than
                 * the alert itself, and it buries the lead's details under an advert for our
                 * own site.
                 */
                'unfurl_links' => false,
                'unfurl_media' => false,
            ];

            if ($blocks !== []) {
                /*
                 * A colour means box it: blocks nested in an attachment render with a coloured
                 * bar down the left and read as one unit rather than as loose blocks in the
                 * channel. Without a colour they go at top level, unboxed.
                 *
                 * `text` stays on the message itself either way — it is the notification
                 * preview, and Slack does not take it from an attachment.
                 */
                if ($color !== null) {
                    $payload['attachments'] = [['color' => $color, 'blocks' => $blocks]];
                } else {
                    $payload['blocks'] = $blocks;
                }
            }

            $response = Http::withToken($token)
                ->timeout(5)
                ->post('https://slack.com/api/chat.postMessage', $payload);

            // Slack answers 200 with `ok: false` on an application error (bad token, missing
            // scope, bot not in channel), so the status code alone is not the outcome.
            if ($response->successful() && $response->json('ok') === true) {
                return true;
            }

            Log::warning('HandleLeadEventsForSlack: chat.postMessage rejected', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return false;
        }

        $webhookUrl = $this->webhookUrl();

        if ($webhookUrl === '') {
            Log::info('HandleLeadEventsForSlack: no bot token or webhook URL configured; nothing sent.');

            return false;
        }

        $response = Http::timeout(5)->post($webhookUrl, array_filter([
            'text' => $text,
            'blocks' => $blocks ?: null,
            'unfurl_links' => false,
        ]));

        return $response->successful();
    }

    protected function webhookUrl(): string
    {
        $url = (string) (config('services.slack.webhook_url') ?? '');

        if ($url === '' && function_exists('get_option')) {
            $url = (string) (get_option('rl_jlc_slack_webhook_url') ?: get_option('rl_slack_webhook_url') ?: '');
        }

        return $url;
    }
}
