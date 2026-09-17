<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadProfile;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Listeners\HandleLeadBookingCompletedForReferrer;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralLeadMatcher;
use Illuminate\Support\Str;

/**
 * Referrals are joined to leads by id, through the identity graph.
 *
 * Every case here produced a **duplicate referral row** under the previous email-string match,
 * which is the expensive failure: the referrer sees one prospect twice, and the reward lands on
 * whichever row an admin happens to fulfil.
 */
function linkageReferrer(): Referrer
{
    return Referrer::query()->create([
        'name' => 'Linkage Referrer',
        'email' => 'linkage-'.uniqid().'@agency.com',
        'referral_code' => 'link-'.uniqid(),
        'status' => 'active',
    ]);
}

function linkageLead(Referrer $referrer, array $overrides = []): Lead
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

function bookingListener(): HandleLeadBookingCompletedForReferrer
{
    return new HandleLeadBookingCompletedForReferrer(new LeadActivityLogger, new ReferralLeadMatcher);
}

it('qualifies the existing referral rather than creating a second one', function () {
    $referrer = linkageReferrer();
    $lead = linkageLead($referrer);

    // What ReferrerPortalDashboard::submitDirectLead() now writes.
    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'lead_id' => $lead->id,
        'lead_name' => $lead->name,
        'lead_email' => $lead->email,
        'source' => 'referrer_direct_submission',
        'status' => 'pending',
    ]);

    bookingListener()->handle(new LeadBookingCompleted($lead, 'meeting-link-1', 'calendly'));

    expect(Referral::where('referrer_id', $referrer->id)->count())->toBe(1)
        ->and($referral->fresh()->status)->toBe('qualified');
});

it('matches a phone-only referral, which has no email to match on', function () {
    $referrer = linkageReferrer();

    // A phone-only submission: the Lead is given a synthesised internal address, and the
    // referral row carries a blank email. Neither matched the other under the old lookup, so
    // booking always inserted a second referral.
    $lead = linkageLead($referrer, [
        'email' => 'lead_'.$referrer->referral_code.'_'.time().'@remoteleverage.internal',
        'phone' => '+15550000001',
    ]);

    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'lead_id' => $lead->id,
        'lead_name' => 'Phone Only Prospect',
        'lead_email' => '',
        'lead_phone' => '+15550000001',
        'source' => 'referrer_direct_submission',
        'status' => 'pending',
    ]);

    bookingListener()->handle(new LeadBookingCompleted($lead, 'meeting-link-2', 'calendly'));

    expect(Referral::where('referrer_id', $referrer->id)->count())->toBe(1)
        ->and($referral->fresh()->status)->toBe('qualified');
});

it('matches across two lead rows belonging to one person', function () {
    $referrer = linkageReferrer();

    // CaptureLeadAction always inserts a new Lead; it never updates one. So a prospect who is
    // referred by hand and later fills in the form themselves — under a different address —
    // is two rows tied together by one LeadProfile.
    $profile = LeadProfile::query()->create([
        'uuid' => (string) Str::uuid(),
        'status' => 'active',
    ]);

    $referredLead = linkageLead($referrer, ['email' => 'work-'.uniqid().'@client.com']);
    $referredLead->forceFill(['profile_id' => $profile->id])->save();

    $bookingLead = linkageLead($referrer, ['email' => 'personal-'.uniqid().'@gmail.com']);
    $bookingLead->forceFill(['profile_id' => $profile->id])->save();

    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'lead_id' => $referredLead->id,
        'lead_name' => 'Two Address Prospect',
        'lead_email' => $referredLead->email,
        'source' => 'referrer_direct_submission',
        'status' => 'pending',
    ]);

    bookingListener()->handle(new LeadBookingCompleted($bookingLead, 'meeting-link-3', 'calendly'));

    expect(Referral::where('referrer_id', $referrer->id)->count())->toBe(1)
        ->and($referral->fresh()->status)->toBe('qualified');
});

it('still matches a legacy referral that predates the lead_id column', function () {
    $referrer = linkageReferrer();
    $lead = linkageLead($referrer);

    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'lead_id' => null,
        'lead_name' => 'Legacy Row',
        'lead_email' => $lead->email,
        'source' => 'manual_submission',
        'status' => 'pending',
    ]);

    bookingListener()->handle(new LeadBookingCompleted($lead, 'meeting-link-4', 'calendly'));

    $referral->refresh();

    expect(Referral::where('referrer_id', $referrer->id)->count())->toBe(1)
        ->and($referral->status)->toBe('qualified')
        // Backfilled on the way through, so the next lookup takes the identity path.
        ->and($referral->lead_id)->toBe($lead->id);
});

it('never demotes an already-closed referral back to qualified', function () {
    $referrer = linkageReferrer();
    $lead = linkageLead($referrer);

    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'lead_id' => $lead->id,
        'lead_name' => $lead->name,
        'lead_email' => $lead->email,
        'source' => 'referrer_direct_submission',
        'status' => 'rewarded',
    ]);

    // A prospect booking a second call after the deal closed must not reopen it — the admin
    // screen would otherwise offer to fulfil, and reward, the same referral twice.
    bookingListener()->handle(new LeadBookingCompleted($lead, 'meeting-link-5', 'calendly'));

    expect($referral->fresh()->status)->toBe('rewarded');
});
