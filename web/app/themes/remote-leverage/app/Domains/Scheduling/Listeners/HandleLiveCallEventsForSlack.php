<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Listeners;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Scheduling\Events\LiveCallRequested;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Support\Facades\Log;

/**
 * "Somebody wants to talk right now" — in the channel, while it is still true.
 *
 * A routed call posts because it needs a human inside fifteen minutes, and it cannot wait on
 * `SLACK_NOTIFY_ON_BOOKING` (on by default since 2026-09-17, for a legacy feed that had no concept
 * of a live call). A declined one posts because it is the most expensive silence on the site:
 * the visitor asked for the thing we sell, got a polite refusal, and left.
 *
 * ## Two kinds of refusal
 *
 * A busy consultant and a missing Calendly event type both end the same way for the visitor and
 * mean completely different things to us — one is a staffing question, the other is a bug that
 * has been turning people away since the last deploy. `faultIsOurs()` is what separates them, so
 * the second reads as an incident rather than as a quiet afternoon.
 *
 * ## Threading
 *
 * Where the visitor is a known lead, this replies under their existing alert. A live-call
 * request is the loudest thing that lead will ever do, and it belongs next to the rest of their
 * story rather than as an unattached card someone has to match up by name.
 */
class HandleLiveCallEventsForSlack
{
    /**
     * Refusal slugs that mean the system is broken rather than busy.
     *
     * These turn every visitor away until someone fixes them, which is why they get their own
     * headline: "nobody was free" is information, "the event type is not configured" is an
     * incident, and a channel that renders them identically buries the second under the first.
     */
    private const OUR_FAULT = [
        'not_configured',
        'booking_failed',
        'no_meeting_link',
    ];

    /**
     * Why the visitor was turned away, in words a person can act on.
     */
    private const REASONS = [
        'phone_not_supported' => 'Phone number is outside the US and Canada',
        'team_offline' => 'The team is marked offline',
        'consultant_busy' => 'Another live call is already in progress',
        'not_configured' => 'The live call event type is not configured',
        'no_immediate_slot' => 'No consultant had an opening within 15 minutes',
        'booking_failed' => 'Calendly would not create the invitee',
        'no_meeting_link' => 'No meeting link came back from Calendly',
    ];

    public function __construct(
        protected SlackTransport $transport = new SlackTransport,
    ) {}

    public function handle(LiveCallRequested $event): void
    {
        /*
         * Shadow ban, matching HandleLeadEventsForSlack. A blocked person who finds the live
         * call button must not reach sales through it — that would be the one door the block
         * left open, and the most direct one on the site.
         */
        if ($event->lead?->is_blocked === true) {
            return;
        }

        $declined = $event->wasDeclined();
        $threadTs = $this->threadTsFor($event->lead);

        try {
            $rendered = app(SlackMessageRenderer::class)->render(
                $declined ? 'live_call_declined' : 'live_call_routed',
                $this->valuesFor($event),
            );

            $this->send(
                $rendered['text'],
                $rendered['blocks'],
                $rendered['color'] ?? null,
                $threadTs,

                /*
                 * A threaded live-call notice that nobody sees is the same as no notice. Both
                 * outcomes broadcast: one needs somebody on a call in fifteen minutes, the
                 * other is a person we just lost.
                 */
                broadcast: $threadTs !== null,
            );
        } catch (\Throwable $e) {
            Log::error('HandleLiveCallEventsForSlack: exception sending to Slack: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    protected function valuesFor(LiveCallRequested $event): array
    {
        $lead = $event->lead;
        $visitor = $event->visitor;

        // The form's own name first: a live call can be requested by someone who has no lead
        // row yet, and where both exist the form is the more recent of the two.
        $name = trim((string) ($visitor['name'] ?? ''));

        if ($name === '' && $lead) {
            $name = trim(((string) ($lead->first_name ?: $lead->name)).' '.(string) $lead->last_name);
        }

        $email = (string) ($visitor['email'] ?? $lead?->email ?? '');
        $phone = (string) ($visitor['phone'] ?? $lead?->phone ?? '');

        $ourFault = $this->faultIsOurs($event->reason);

        return [
            'name' => $name !== '' ? $name : 'Unnamed visitor',
            'email' => $email,
            'email_link' => $email !== '' ? "<mailto:{$email}|{$email}>" : '',
            'phone' => $phone,
            /*
             * Never empty. This is bound to the card's `body`, and Slack rejects an entire
             * message over one empty text object — so a request with no contact details, which
             * includes every configuration failure and every anonymous visitor, was announced
             * to nobody. The incident alert ("every live call request fails until it is fixed")
             * is precisely the one that must not be the one that goes missing.
             */
            'contact_line' => implode('   ', array_filter([
                $email !== '' ? "<mailto:{$email}|{$email}>" : '',
                $phone,
            ])) ?: 'No contact details captured',

            'headline' => $event->wasDeclined()
                ? ($ourFault ? 'Live call request failed' : 'Live call requested, nobody available')
                : 'Live call connecting now',

            'reason_label' => self::REASONS[$event->reason] ?? ($event->reason !== '' ? $event->reason : ''),

            /*
             * A flag for the template, not a sentence. `_when` drops a block whose named value
             * is empty, which is how the incident note appears on exactly the refusals that are
             * ours to fix and on none of the others.
             */
            'our_fault' => $ourFault ? 'yes' : '',

            'meeting_url' => (string) ($event->meetUrl ?? ''),
            'session_id' => $event->sessionId,

            'admin_url' => $lead?->id && function_exists('admin_url')
                ? (string) \admin_url('admin.php?page=rl-leads&view_lead='.$lead->id)
                : '',
            'replay_url' => (string) ($lead?->posthogReplayUrl() ?? ''),
        ];
    }

    /**
     * Is this refusal a bug rather than a busy afternoon?
     */
    protected function faultIsOurs(string $reason): bool
    {
        return in_array($reason, self::OUR_FAULT, true);
    }

    protected function threadTsFor(?Lead $lead): ?string
    {
        $ts = trim((string) ($lead->slack_message_ts ?? ''));

        return $ts === '' ? null : $ts;
    }

    /**
     * The seam the tests override, matching the other Slack listeners.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{ts: ?string, channel: ?string}|null
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
