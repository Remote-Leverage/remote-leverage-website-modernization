<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use Illuminate\Support\Str;

/*
 * One message per lead, and everything that happens to them hangs off it.
 *
 * Before this, a lead who filled in step 1 and then booked produced two unrelated cards in the
 * channel, and reconstructing which belonged to whom meant reading names and timestamps. These
 * tests pin the two halves that make threading work: the partial has to *store* where it landed,
 * and the booking has to *use* that — neither is observable from the message itself, which is
 * why both were easy to get subtly wrong.
 */

/** A listener that records what it would send, including where it would thread it. */
function threadingListener(): object
{
    return new class(app(LeadActivityLogger::class)) extends HandleLeadEventsForSlack
    {
        /** @var array<int, array{text: string, thread_ts: ?string, broadcast: bool}> */
        public array $sent = [];

        /** What chat.postMessage will pretend to answer with. Null means the send failed. */
        public ?array $reply = ['ts' => '1726500000.000100', 'channel' => 'C086BBKUXL5'];

        protected function send(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
        ): ?array {
            $this->sent[] = ['text' => $text, 'thread_ts' => $threadTs, 'broadcast' => $broadcast];

            return $this->reply;
        }
    };
}

/** A lead that is actually in the database, because storing a `ts` on it is the point. */
function persistedLead(array $attributes = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Grace Hopper',
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.com',
        'phone' => '+1 650 555 0199',
        'submission_type' => 'Partial',
        'status' => 'captured',
    ], $attributes));
}

describe('the lead alert opens a thread', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
        config(['services.slack.notify_on_booking' => true]);
        config(['services.slack.channel' => '']);
    });

    test('the partial stores where it landed', function () {
        $lead = persistedLead();

        $listener = threadingListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        $lead->refresh();

        expect($lead->slack_message_ts)->toBe('1726500000.000100')
            ->and($lead->slack_channel_id)->toBe('C086BBKUXL5');
    });

    test('the partial itself is not a reply', function () {
        // It is the root of the thread. Threading it onto something would need a parent that
        // by definition does not exist yet.
        $listener = threadingListener();
        $listener->handleCreated(new LeadCreated(lead: persistedLead(), context: []));

        expect($listener->sent[0]['thread_ts'])->toBeNull()
            ->and($listener->sent[0]['broadcast'])->toBeFalse();
    });

    test('a failed send stores nothing, so the next event does not reply into a void', function () {
        $lead = persistedLead();

        $listener = threadingListener();
        $listener->reply = null;
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        $lead->refresh();

        expect($lead->slack_message_ts)->toBeNull();
    });

    test('a webhook send, which returns no ts, stores nothing', function () {
        // The incoming-webhook transport answers `ok` and nothing else. Threading is simply
        // unavailable there, and a null must not be written as though it were an id.
        $lead = persistedLead();

        $listener = threadingListener();
        $listener->reply = ['ts' => null, 'channel' => null];
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        $lead->refresh();

        expect($lead->slack_message_ts)->toBeNull();
    });

    test('a lead that is not in the database is not invented to hold a timestamp', function () {
        $lead = new Lead;
        $lead->forceFill(['id' => 9999, 'name' => 'Transient', 'email' => 't@example.com']);

        $before = Lead::query()->count();

        $listener = threadingListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect(Lead::query()->count())->toBe($before);
    });
});

describe('the booking replies into that thread', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
        config(['services.slack.notify_on_booking' => true]);
        config(['services.slack.channel' => '']);
    });

    test('a booking replies under the lead, and broadcasts so the channel still sees it', function () {
        $lead = persistedLead([
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
        ]);

        $listener = threadingListener();
        $listener->handleBookingCompleted(new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'cal_123',
            provider: 'calendly',
            meetUrl: 'https://meet.example.com/abc',
            startTime: '2026-09-20T15:00:00Z',
        ));

        expect($listener->sent[0]['thread_ts'])->toBe('1726500000.000100')
            ->and($listener->sent[0]['broadcast'])->toBeTrue();
    });

    test('a lead whose alert never landed still gets a flat booking message', function () {
        // Degrading to the old behaviour beats dropping the alert that says money arrived.
        $listener = threadingListener();
        $listener->handleBookingCompleted(new LeadBookingCompleted(
            lead: persistedLead(),
            meetingId: 'cal_123',
            provider: 'calendly',
        ));

        expect($listener->sent)->toHaveCount(1)
            ->and($listener->sent[0]['thread_ts'])->toBeNull()
            ->and($listener->sent[0]['broadcast'])->toBeFalse();
    });

    test('a timestamp from a different channel is not reused', function () {
        /*
         * A `ts` identifies a message only inside the channel that produced it. Replying with
         * one from elsewhere does not error — Slack posts it flat — so the failure mode is a
         * channel where threading silently stopped working and nobody can say when.
         */
        config(['services.slack.channel' => 'C_NEW_CHANNEL']);

        $lead = persistedLead([
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C_OLD_CHANNEL',
        ]);

        $listener = threadingListener();
        $listener->handleBookingCompleted(new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'cal_123',
            provider: 'calendly',
        ));

        expect($listener->sent[0]['thread_ts'])->toBeNull();
    });

    test('a timestamp with no recorded channel is still used', function () {
        // Rows written before the channel column existed, and the webhook transport. Refusing
        // these would turn threading off for every lead captured before the migration.
        config(['services.slack.channel' => 'C086BBKUXL5']);

        $lead = persistedLead([
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => null,
        ]);

        $listener = threadingListener();
        $listener->handleBookingCompleted(new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'cal_123',
            provider: 'calendly',
        ));

        expect($listener->sent[0]['thread_ts'])->toBe('1726500000.000100');
    });

    test('the booking does not overwrite the thread it just replied to', function () {
        // Only the partial opens a thread. If the reply stored its own `ts`, every later event
        // would nest one level deeper than the last.
        $lead = persistedLead([
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
        ]);

        $listener = threadingListener();
        $listener->reply = ['ts' => '1726599999.000999', 'channel' => 'C086BBKUXL5'];
        $listener->handleBookingCompleted(new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'cal_123',
            provider: 'calendly',
        ));

        $lead->refresh();

        expect($lead->slack_message_ts)->toBe('1726500000.000100');
    });
});
