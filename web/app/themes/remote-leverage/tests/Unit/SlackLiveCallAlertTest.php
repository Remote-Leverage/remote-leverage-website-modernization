<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Scheduling\Events\LiveCallRequested;
use App\Domains\Scheduling\Listeners\HandleLiveCallEventsForSlack;
use Illuminate\Support\Str;

/*
 * "Somebody wants to talk right now."
 *
 * The declined case is why this exists. `RouteInstantCallAction` has always had seven ways to
 * turn a visitor away and all seven were silent: the person got a polite line, the request
 * never reached a human, and nothing recorded that the most motivated visitor on the site had
 * asked for the thing we sell and been told no.
 *
 * The distinction these tests pin hardest is between the two kinds of refusal. "No consultant
 * was free" is a staffing fact. "The event type is not configured" is a bug that has been
 * turning away *everyone* since it broke, and a channel that renders the two identically buries
 * the second under the first.
 */

/** A live-call listener that records what it would send. */
function liveCallSlackListener(): object
{
    return new class extends HandleLiveCallEventsForSlack
    {
        /** @var array<int, array{text: string, blocks: array, thread_ts: ?string, broadcast: bool}> */
        public array $sent = [];

        protected function send(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
        ): ?array {
            $this->sent[] = [
                'text' => $text,
                'blocks' => $blocks,
                'thread_ts' => $threadTs,
                'broadcast' => $broadcast,
            ];

            return ['ts' => '1726500003.000400', 'channel' => 'C086BBKUXL5'];
        }

        public function lastJson(): string
        {
            return json_encode(end($this->sent)['blocks'], JSON_UNESCAPED_SLASHES);
        }
    };
}

function liveCallLead(array $attributes = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Rosalind Franklin',
        'first_name' => 'Rosalind',
        'last_name' => 'Franklin',
        'email' => 'rosalind-'.Str::random(6).'@example.com',
        'phone' => '+1 415 555 0177',
        'status' => 'captured',
    ], $attributes));
}

describe('a live call that connects', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    test('announces itself regardless of SLACK_NOTIFY_ON_BOOKING', function () {
        /*
         * The booking alert is off by default, for parity with a legacy feed that had no
         * concept of a live call. A live call cannot inherit that: it starts within fifteen
         * minutes and somebody has to be on it.
         */
        config(['services.slack.notify_on_booking' => false]);

        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::ROUTED,
            reason: '',
            sessionId: 'session_abc',
            visitor: ['name' => 'Rosalind Franklin', 'email' => 'rosalind@example.com'],
            meetUrl: 'https://meet.google.com/real-call',
        ));

        expect($listener->sent)->toHaveCount(1)
            ->and($listener->lastJson())->toContain('Live call connecting now')
            ->and($listener->lastJson())->toContain('Join call');
    });

    test('replies under the lead alert when there is one, and broadcasts it', function () {
        $lead = liveCallLead(['slack_message_ts' => '1726500000.000100']);

        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::ROUTED,
            reason: '',
            sessionId: 'session_abc',
            visitor: [],
            lead: $lead,
            meetUrl: 'https://meet.google.com/real-call',
        ));

        expect($listener->sent[0]['thread_ts'])->toBe('1726500000.000100')
            ->and($listener->sent[0]['broadcast'])->toBeTrue();
    });

    test('a visitor with no lead row still gets named', function () {
        // The live call button does not require a captured lead, so the form's own values are
        // the only thing there is.
        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::ROUTED,
            reason: '',
            sessionId: 'session_abc',
            visitor: ['name' => 'Walk-in Visitor', 'email' => 'walkin@example.com'],
            meetUrl: 'https://meet.google.com/real-call',
        ));

        expect($listener->lastJson())->toContain('Walk-in Visitor')
            ->and($listener->sent[0]['thread_ts'])->toBeNull();
    });
});

describe('a live call that is refused', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    test('a busy team reads as availability, not as a fault', function () {
        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::DECLINED,
            reason: 'consultant_busy',
            sessionId: 'session_abc',
            visitor: ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
        ));

        $json = $listener->lastJson();

        expect($json)->toContain('Live call requested, nobody available')
            ->and($json)->toContain('Another live call is already in progress')
            ->and($json)->not->toContain('configuration or API failure');
    });

    test('a broken event type reads as an incident', function () {
        // This one turns away every visitor until someone fixes it. Rendering it the same as a
        // busy afternoon is how it survives a week.
        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::DECLINED,
            reason: 'not_configured',
            sessionId: 'session_abc',
            visitor: ['name' => 'Ada Lovelace'],
        ));

        $json = $listener->lastJson();

        expect($json)->toContain('Live call request failed')
            ->and($json)->toContain('The live call event type is not configured')
            ->and($json)->toContain('Every live call request fails until it is fixed');
    });

    test('every refusal reason renders a sentence rather than a slug', function () {
        $reasons = [
            'phone_not_supported',
            'team_offline',
            'consultant_busy',
            'not_configured',
            'no_immediate_slot',
            'booking_failed',
            'no_meeting_link',
        ];

        foreach ($reasons as $reason) {
            $listener = liveCallSlackListener();
            $listener->handle(new LiveCallRequested(
                outcome: LiveCallRequested::DECLINED,
                reason: $reason,
                sessionId: 'session_abc',
                visitor: ['name' => 'Ada Lovelace'],
            ));

            expect($listener->lastJson())->not->toContain($reason);
        }
    });

    test('an unnamed visitor is labelled rather than left blank', function () {
        // A card with an empty title reads as a broken alert, and the refusal still counts.
        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::DECLINED,
            reason: 'team_offline',
            sessionId: 'session_abc',
            visitor: [],
        ));

        expect($listener->lastJson())->toContain('Unnamed visitor');
    });

    test('a blocked person cannot reach sales through the live call button', function () {
        /*
         * The one door the shadow ban would otherwise leave open, and the most direct one on
         * the site. Silence rather than a refusal, exactly as the lead alert does it.
         */
        // `is_blocked` is not fillable — BlockLeadProfileAction writes it with a bulk update,
        // so mass-assigning it here would silently do nothing and the test would pass for the
        // wrong reason.
        $lead = liveCallLead();
        $lead->forceFill(['is_blocked' => true])->saveQuietly();

        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::ROUTED,
            reason: '',
            sessionId: 'session_abc',
            visitor: ['name' => 'Blocked Person'],
            lead: $lead,
            meetUrl: 'https://meet.google.com/real-call',
        ));

        expect($listener->sent)->toBe([]);
    });

    test('no emoji reaches Slack from a live call template', function () {
        $listener = liveCallSlackListener();
        $listener->handle(new LiveCallRequested(
            outcome: LiveCallRequested::DECLINED,
            reason: 'no_immediate_slot',
            sessionId: 'session_abc',
            visitor: ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
        ));

        $payload = $listener->sent[0]['text']
            .json_encode($listener->sent[0]['blocks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}]/u', $payload))
            ->toBe(0);
    });
});
