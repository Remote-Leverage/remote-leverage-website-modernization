<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use Illuminate\Support\Str;

/*
 * Someone fills in step 1, reads the revenue-band warning, goes back, picks a different band
 * and continues. That is one lead changing one answer inside a minute.
 *
 * It used to produce two of everything: two lead rows, two Slack cards — the first of them
 * stating a revenue band the visitor had already corrected — two Meta `Lead` conversions for
 * one person, and two admin emails. The card nobody could act on was the loudest thing in the
 * channel, and the orphaned row sat in the dashboard forever as a partial drop-off that nobody
 * had dropped off from.
 *
 * These tests pin both halves: the wizard keeps one row per visit, and the listener reports the
 * second capture as an amendment to the card already posted — or says nothing at all when
 * nothing was amended.
 */

/** A listener that records what it would post and what it would edit. */
function amendmentListener(): object
{
    return new class(app(LeadActivityLogger::class)) extends HandleLeadEventsForSlack
    {
        /** @var array<int, array{text: string, blocks: array, thread_ts: ?string}> */
        public array $sent = [];

        /** @var array<int, array{ts: string, blocks: array}> */
        public array $edited = [];

        public ?array $reply = ['ts' => '1726500000.000100', 'channel' => 'C086BBKUXL5'];

        protected function send(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
        ): ?array {
            $this->sent[] = ['text' => $text, 'blocks' => $blocks, 'thread_ts' => $threadTs];

            return $this->reply;
        }

        protected function edit(Lead $lead, string $ts, string $text, array $blocks = [], ?string $color = null): bool
        {
            $this->edited[] = ['ts' => $ts, 'blocks' => $blocks];

            return true;
        }
    };
}

function announcedLead(array $attributes = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Scarlett Woodford',
        'first_name' => 'Scarlett',
        'last_name' => 'Woodford',
        'email' => 's.woodford100@example.com',
        'phone' => '+19496598096',
        'monthly_revenue' => '$0 to $5k Per Month',
        'submission_type' => 'Partial',
        'status' => 'captured',
    ], $attributes));
}

/** Every text fragment the listener would send, flattened out of the Block Kit. */
function slackText(array $message): string
{
    return json_encode($message['blocks']).$message['text'];
}

describe('a re-submitted step 1 amends the card instead of posting another', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
        config(['services.slack.channel' => '']);
        Lead::query()->forceDelete();
    });

    test('the first alert records what it said', function () {
        // Without this there is nothing for a second capture to diff against, and every
        // amendment degrades to "something changed, we cannot say what".
        $lead = announcedLead();

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        $lead->refresh();

        expect($lead->slack_announced)->toBeArray()
            ->and($lead->slack_announced['revenue'])->toBe('$0 to $5k Per Month')
            ->and($lead->slack_announced['email'])->toBe('s.woodford100@example.com');
    });

    test('a repeat capture that changed nothing says nothing', function () {
        $lead = announcedLead([
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
            'slack_announced' => [
                'name' => 'Scarlett Woodford',
                'email' => 's.woodford100@example.com',
                'phone' => '+19496598096',
                'revenue' => '$0 to $5k Per Month',
                'company' => '',
                'role' => '',
                'weekly hours' => '',
            ],
        ]);

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect($listener->sent)->toBeEmpty()
            ->and($listener->edited)->toBeEmpty();
    });

    test('a changed revenue band is a context reply under the original card', function () {
        $lead = announcedLead([
            'monthly_revenue' => '$5k to $10k Per Month',
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
            'slack_announced' => [
                'name' => 'Scarlett Woodford',
                'email' => 's.woodford100@example.com',
                'phone' => '+19496598096',
                'revenue' => '$0 to $5k Per Month',
                'company' => '',
                'role' => '',
                'weekly hours' => '',
            ],
        ]);

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect($listener->sent)->toHaveCount(1)
            ->and($listener->sent[0]['thread_ts'])->toBe('1726500000.000100')
            ->and($listener->sent[0]['blocks'][0]['type'])->toBe('context')
            ->and(slackText($listener->sent[0]))
            ->toContain('revenue: $0 to $5k Per Month → $5k to $10k Per Month');
    });

    test('the original card is rewritten to the answer that is now true', function () {
        // The reply alone would leave the channel showing a band the visitor has corrected,
        // and the card is what a salesperson reads without opening anything.
        $lead = announcedLead([
            'monthly_revenue' => '$5k to $10k Per Month',
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
            'slack_announced' => ['revenue' => '$0 to $5k Per Month'],
        ]);

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect($listener->edited)->toHaveCount(1)
            ->and($listener->edited[0]['ts'])->toBe('1726500000.000100')
            ->and(json_encode($listener->edited[0]['blocks']))->toContain('$5k to $10k Per Month');
    });

    test('the snapshot moves on, so the same change is not reported twice', function () {
        $lead = announcedLead([
            'monthly_revenue' => '$5k to $10k Per Month',
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
            'slack_announced' => ['revenue' => '$0 to $5k Per Month'],
        ]);

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));
        $lead->refresh();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect($listener->sent)->toHaveCount(1);
    });

    test('an amendment with no reachable card posts a whole one', function () {
        /*
         * The incoming-webhook transport returns no `ts`, and a channel that has changed since
         * disqualifies the one on file. Either way there is nothing to reply under, and a bare
         * "Amended: revenue …" with no card above it says nothing to anyone.
         */
        $lead = announcedLead([
            'monthly_revenue' => '$5k to $10k Per Month',
            'slack_announced' => ['revenue' => '$0 to $5k Per Month'],
        ]);

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect($listener->sent)->toHaveCount(1)
            ->and($listener->sent[0]['thread_ts'])->toBeNull()
            ->and(slackText($listener->sent[0]))->toContain('Scarlett Woodford');
    });

    test('a failed first send leaves nothing to amend', function () {
        // Otherwise the snapshot suppresses the next capture as a duplicate of a card that was
        // never posted, and the lead is never announced at all.
        $lead = announcedLead();

        $listener = amendmentListener();
        $listener->reply = null;
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        $lead->refresh();

        expect($lead->slack_announced)->toBeNull();
    });

    test('a field the snapshot predates is not reported as a change', function () {
        // A row captured before a field joined the snapshot carries the old shape. An absent
        // key is an unknown, and announcing "company: Acme" on a lead that always said Acme
        // would make the first deploy look like a wave of amendments.
        $lead = announcedLead([
            'company' => 'Acme',
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
            'slack_announced' => ['revenue' => '$0 to $5k Per Month'],
        ]);

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect($listener->sent)->toBeEmpty();
    });

    test('a blocked lead is silent here too', function () {
        $lead = announcedLead([
            'monthly_revenue' => '$5k to $10k Per Month',
            'slack_message_ts' => '1726500000.000100',
            'slack_channel_id' => 'C086BBKUXL5',
            'slack_announced' => ['revenue' => '$0 to $5k Per Month'],
        ]);

        // Not fillable — the shadow ban is set by IdentityResolver, never by a caller.
        $lead->forceFill(['is_blocked' => true])->saveQuietly();

        $listener = amendmentListener();
        $listener->handleCreated(new LeadCreated(lead: $lead, context: []));

        expect($listener->sent)->toBeEmpty()
            ->and($listener->edited)->toBeEmpty();
    });

    test('the amendment carries no emoji', function () {
        // Same house rule as every other template. See config/slack-notifications.php.
        $amended = config('slack-notifications.lead_amended');

        expect(json_encode($amended))
            ->not->toMatch('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u');
    });
});

describe('the wizard keeps one lead per visit', function () {
    beforeEach(function () {
        Lead::query()->forceDelete();
    });

    /** Step 1, filled in and submitted. */
    $submitStepOne = function (MultistepBookingWizard $wizard, string $band): void {
        $wizard->monthlyRevenue = $band;
        $wizard->currentStep = 1;
        $wizard->goToStep(2);
    };

    test('going back to change the revenue band does not fork the lead', function () use ($submitStepOne) {
        $wizard = new MultistepBookingWizard;
        $wizard->mount('test', 'glass');
        $wizard->email = 'scarlett@example.com';
        $wizard->firstName = 'Scarlett';
        $wizard->lastName = 'Woodford';
        $wizard->phone = '+1 949 659 8096';
        $wizard->consent = true;

        $submitStepOne($wizard, '$0 to $5k Per Month');
        $submitStepOne($wizard, '$5k to $10k Per Month');

        expect(Lead::query()->count())->toBe(1)
            ->and(Lead::query()->first()->monthly_revenue)->toBe('$5k to $10k Per Month');
    });

    test('a reload mid-visit resumes the same lead', function () use ($submitStepOne) {
        // `$leadId` lives in the Livewire snapshot and a reload discards it, so a second
        // component instance is exactly what a refreshed page produces.
        $first = new MultistepBookingWizard;
        $first->mount('test', 'glass');
        $first->email = 'scarlett@example.com';
        $first->firstName = 'Scarlett';
        $first->lastName = 'Woodford';
        $first->phone = '+1 949 659 8096';
        $first->posthogSessionId = '0199c0de-visit-1';

        $submitStepOne($first, '$0 to $5k Per Month');

        $second = new MultistepBookingWizard;
        $second->mount('test', 'glass');
        $second->email = 'scarlett@example.com';
        $second->firstName = 'Scarlett';
        $second->lastName = 'Woodford';
        $second->phone = '+1 949 659 8096';
        $second->posthogSessionId = '0199c0de-visit-1';

        $submitStepOne($second, '$5k to $10k Per Month');

        expect(Lead::query()->count())->toBe(1);
    });

    test('a different browser session is a new lead, and a new alert', function () use ($submitStepOne) {
        $first = new MultistepBookingWizard;
        $first->mount('test', 'glass');
        $first->email = 'scarlett@example.com';
        $first->firstName = 'Scarlett';
        $first->lastName = 'Woodford';
        $first->phone = '+1 949 659 8096';
        $first->posthogSessionId = '0199c0de-visit-1';

        $submitStepOne($first, '$0 to $5k Per Month');

        $later = new MultistepBookingWizard;
        $later->mount('test', 'glass');
        $later->email = 'scarlett@example.com';
        $later->firstName = 'Scarlett';
        $later->lastName = 'Woodford';
        $later->phone = '+1 949 659 8096';
        $later->posthogSessionId = '0199c0de-visit-2';

        $submitStepOne($later, '$5k to $10k Per Month');

        expect(Lead::query()->count())->toBe(2);
    });
});
