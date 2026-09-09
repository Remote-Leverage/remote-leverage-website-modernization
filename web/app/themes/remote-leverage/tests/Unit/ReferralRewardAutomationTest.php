<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadBookingCanceled;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Actions\FulfillReferralAction;
use App\Domains\Referral\Listeners\HandleLeadBookingCanceledForReferrer;
use App\Domains\Referral\Listeners\HandleLeadBookingCompletedForReferrer;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Support\Str;

function makeRewardReferrer(array $overrides = []): Referrer
{
    return Referrer::query()->create(array_merge([
        'name' => 'Reward Test Referrer',
        'email' => 'reward-referrer-'.uniqid().'@venture.com',
        'referral_code' => 'reward-'.uniqid(),
        'status' => 'active',
    ], $overrides));
}

function makeAttributedLead(Referrer $referrer, array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Referred Prospect',
        'email' => 'prospect-'.uniqid().'@client.com',
        'source_type' => 'referral_hub',
        'source_id' => $referrer->referral_code,
        'status' => 'booked',
    ], $overrides));
}

describe('HandleLeadBookingCompletedForReferrer qualification (booking != fulfillment)', function () {
    test('booking a call only qualifies the referral and creates no reward', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger);
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();

        expect($referral)->not->toBeNull()
            ->and($referral->status)->toBe('qualified')
            ->and(ReferralReward::where('referral_id', $referral->id)->count())->toBe(0);
    });

    test('is idempotent: replaying the event does not create duplicate referrals', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger);
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));

        expect(Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->count())->toBe(1);
    });

    test('skips referral creation for a self-referral (lead email matches referrer email)', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer, ['email' => $referrer->email]);

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger);
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));

        expect(Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->count())->toBe(0);
    });

    test('does nothing for a lead with no referrer attribution', function () {
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Organic Prospect',
            'email' => 'organic-'.uniqid().'@client.com',
            'source_type' => 'organic',
            'status' => 'booked',
        ]);

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger);
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-2', 'calendly'));

        expect(Referral::where('lead_email', $lead->email)->count())->toBe(0);
    });
});

describe('FulfillReferralAction (the deal actually closing)', function () {
    test('creates a due reward using the configured defaults', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'lead_name' => $lead->name,
            'lead_email' => $lead->email,
            'status' => 'qualified',
        ]);

        $reward = (new FulfillReferralAction(new ReferralSettingsService))->execute($referral);

        expect($reward->status)->toBe('due')
            ->and((float) $reward->amount)->toBeGreaterThan(0)
            ->and($reward->referrer_id)->toBe($referrer->id)
            ->and($reward->description)->toBe("Reward for fulfilled deal #{$referral->id}");
    });

    test('is idempotent: calling it twice does not create a duplicate reward', function () {
        $referrer = makeRewardReferrer();
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Idempotent Lead',
            'lead_email' => 'idempotent-'.uniqid().'@client.com',
            'status' => 'qualified',
        ]);

        $action = new FulfillReferralAction(new ReferralSettingsService);
        $action->execute($referral);
        $action->execute($referral);

        expect(ReferralReward::where('referral_id', $referral->id)->count())->toBe(1);
    });

    test('honors a custom amount, currency, and description', function () {
        $referrer = makeRewardReferrer();
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Custom Reward Lead',
            'lead_email' => 'custom-'.uniqid().'@client.com',
            'status' => 'qualified',
        ]);

        $reward = (new FulfillReferralAction(new ReferralSettingsService))
            ->execute($referral, customAmount: 250.00, customCurrency: 'EUR', customDescription: 'Negotiated bonus');

        expect((float) $reward->amount)->toBe(250.00)
            ->and($reward->currency)->toBe('EUR')
            ->and($reward->description)->toBe('Negotiated bonus');
    });
});

describe('HandleLeadBookingCanceledForReferrer reversal', function () {
    test('marks a merely-qualified referral rejected (no reward exists yet to clean up)', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $completedListener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger);
        $completedListener->handle(new LeadBookingCompleted($lead, 'meeting-3', 'calendly'));

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();
        expect($referral->status)->toBe('qualified');

        $canceledListener = new HandleLeadBookingCanceledForReferrer(new LeadActivityLogger);
        $canceledListener->handle(new LeadBookingCanceled($lead, 'Invitee canceled'));

        $referral->refresh();

        expect($referral->status)->toBe('rejected')
            ->and(ReferralReward::where('referral_id', $referral->id)->count())->toBe(0);
    });

    test('edge case: removes a still-due reward if the deal was fulfilled before the cancellation arrived', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $completedListener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger);
        $completedListener->handle(new LeadBookingCompleted($lead, 'meeting-4', 'calendly'));

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();
        (new FulfillReferralAction(new ReferralSettingsService))->execute($referral);
        expect(ReferralReward::where('referral_id', $referral->id)->where('status', 'due')->count())->toBe(1);

        $canceledListener = new HandleLeadBookingCanceledForReferrer(new LeadActivityLogger);
        $canceledListener->handle(new LeadBookingCanceled($lead, 'Invitee canceled'));

        $referral->refresh();

        expect($referral->status)->toBe('rejected')
            ->and(ReferralReward::where('referral_id', $referral->id)->count())->toBe(0);
    });

    test('edge case: leaves an already-issued reward untouched but still marks the referral rejected', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $completedListener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger);
        $completedListener->handle(new LeadBookingCompleted($lead, 'meeting-5', 'calendly'));

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();
        $reward = (new FulfillReferralAction(new ReferralSettingsService))->execute($referral);
        $reward->update(['status' => 'issued', 'issued_at' => now()]);

        $canceledListener = new HandleLeadBookingCanceledForReferrer(new LeadActivityLogger);
        $canceledListener->handle(new LeadBookingCanceled($lead, 'Invitee canceled'));

        $referral->refresh();

        expect($referral->status)->toBe('rejected')
            ->and(ReferralReward::where('referral_id', $referral->id)->where('status', 'issued')->count())->toBe(1);
    });

    test('does nothing when no referral exists for the canceled booking', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer, ['email' => 'never-had-a-referral-'.uniqid().'@client.com']);

        $canceledListener = new HandleLeadBookingCanceledForReferrer(new LeadActivityLogger);
        $canceledListener->handle(new LeadBookingCanceled($lead, 'Invitee canceled'));

        expect(Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->count())->toBe(0);
    });
});
