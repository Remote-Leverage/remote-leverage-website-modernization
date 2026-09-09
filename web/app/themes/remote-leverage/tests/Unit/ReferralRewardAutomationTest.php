<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadBookingCanceled;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
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

describe('HandleLeadBookingCompletedForReferrer reward automation', function () {
    test('creates a fulfilled Referral and a due ReferralReward for an attributed booking', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger, new ReferralSettingsService);
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();

        expect($referral)->not->toBeNull()
            ->and($referral->status)->toBe('fulfilled');

        $reward = ReferralReward::where('referral_id', $referral->id)->first();

        expect($reward)->not->toBeNull()
            ->and($reward->status)->toBe('due')
            ->and((float) $reward->amount)->toBeGreaterThan(0);
    });

    test('is idempotent: replaying the event does not create duplicate referrals or rewards', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger, new ReferralSettingsService);
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));

        expect(Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->count())->toBe(1);

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();
        expect(ReferralReward::where('referral_id', $referral->id)->count())->toBe(1);
    });

    test('skips referral/reward creation for a self-referral (lead email matches referrer email)', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer, ['email' => $referrer->email]);

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger, new ReferralSettingsService);
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

        $listener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger, new ReferralSettingsService);
        $listener->handle(new LeadBookingCompleted($lead, 'meeting-2', 'calendly'));

        expect(Referral::where('lead_email', $lead->email)->count())->toBe(0);
    });
});

describe('HandleLeadBookingCanceledForReferrer reversal', function () {
    test('removes a still-due reward and marks the referral rejected when the booking is canceled', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $completedListener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger, new ReferralSettingsService);
        $completedListener->handle(new LeadBookingCompleted($lead, 'meeting-3', 'calendly'));

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();
        expect(ReferralReward::where('referral_id', $referral->id)->where('status', 'due')->count())->toBe(1);

        $canceledListener = new HandleLeadBookingCanceledForReferrer(new LeadActivityLogger);
        $canceledListener->handle(new LeadBookingCanceled($lead, 'Invitee canceled'));

        $referral->refresh();

        expect($referral->status)->toBe('rejected')
            ->and(ReferralReward::where('referral_id', $referral->id)->count())->toBe(0);
    });

    test('leaves an already-issued reward untouched but still marks the referral rejected', function () {
        $referrer = makeRewardReferrer();
        $lead = makeAttributedLead($referrer);

        $completedListener = new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger, new ReferralSettingsService);
        $completedListener->handle(new LeadBookingCompleted($lead, 'meeting-4', 'calendly'));

        $referral = Referral::where('referrer_id', $referrer->id)->where('lead_email', $lead->email)->first();
        $reward = ReferralReward::where('referral_id', $referral->id)->first();
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
