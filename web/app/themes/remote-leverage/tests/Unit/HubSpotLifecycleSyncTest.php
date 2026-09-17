<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Referral\Actions\FulfillReferralAction;
use App\Domains\Referral\Actions\SyncHubSpotLifecycleAction;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The polling half of the HubSpot integration: mirror the contact lifecycle stage onto the
 * lead, and fulfil the referral when the deal closes.
 *
 * `applyStage()` is the seam a future webhook receiver will call, so the rules exercised here
 * — the staleness clock, idempotency, the fulfilment threshold — are the rules both paths get.
 */
beforeEach(function () {
    // The Http facade caches its Factory for the life of the process, and fake() binds the
    // factory into the container too — so without a hard swap every test inherits the previous
    // test's stub closures and the first catch-all registered wins for the whole file.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);

    $GLOBALS['_wp_mock_options']['rl_lead_settings'] = [
        'hubspot_access_token' => 'pat-test-token',
        'hubspot_portal_id' => '243484989',
        'hubspot_lifecycle_property' => 'lifecyclestage',
        'hubspot_lifecycle_fulfilled_value' => 'customer',
    ];

    $GLOBALS['_wp_mock_options']['rl_referral_settings'] = [
        'default_reward_amount' => 250.00,
        'default_reward_currency' => 'USD',
        'default_reward_type' => 'cash',
    ];
});

function syncAction(): SyncHubSpotLifecycleAction
{
    return new SyncHubSpotLifecycleAction(
        new HubSpotGateway,
        new LeadActivityLogger,
        new FulfillReferralAction(new ReferralSettingsService),
        new LeadSettingsService,
    );
}

function lifecycleFixture(string $stage = 'lead', array $leadOverrides = [], string $status = 'qualified'): array
{
    $referrer = Referrer::query()->create([
        'name' => 'Lifecycle Referrer',
        'email' => 'lifecycle-'.uniqid().'@agency.com',
        'referral_code' => 'life-'.uniqid(),
        'status' => 'active',
    ]);

    $lead = Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Lifecycle Prospect',
        'email' => 'lifecycle-prospect-'.uniqid().'@client.com',
        'source_type' => 'referral_hub',
        'source_id' => $referrer->referral_code,
        'status' => 'booked',
        'hubspot_contact_id' => (string) random_int(100000, 999999),
        'hubspot_lifecycle_stage' => $stage,
    ], $leadOverrides));

    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'lead_id' => $lead->id,
        'lead_name' => $lead->name,
        'lead_email' => $lead->email,
        'source' => 'booking_completed',
        'status' => $status,
    ]);

    return [$referrer, $lead, $referral];
}

function fakeHubSpotStage(string $contactId, string $stage): void
{
    Http::fake(fn () => Http::response([
        'results' => [
            ['id' => $contactId, 'properties' => ['lifecyclestage' => $stage]],
        ],
    ], 200));
}

it('records a stage change on the lead and its timeline', function () {
    [, $lead] = lifecycleFixture('lead');
    fakeHubSpotStage((string) $lead->hubspot_contact_id, 'salesqualifiedlead');

    $summary = syncAction()->execute();

    $lead->refresh();

    expect($summary['changed'])->toBe(1)
        ->and($lead->hubspot_lifecycle_stage)->toBe('salesqualifiedlead')
        ->and($lead->hubspot_lifecycle_changed_at)->not->toBeNull()
        ->and($lead->hubspot_lifecycle_synced_at)->not->toBeNull();

    $log = LeadActivityLog::where('lead_id', $lead->id)
        ->where('event_type', 'HubSpotLifecycleChanged')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->payload['from'])->toBe('lead')
        ->and($log->payload['to'])->toBe('salesqualifiedlead');
});

it('does not restart the staleness clock when the stage has not moved', function () {
    [, $lead] = lifecycleFixture('opportunity');

    $threeDaysAgo = now()->subDays(3);
    $lead->forceFill(['hubspot_lifecycle_changed_at' => $threeDaysAgo])->save();

    fakeHubSpotStage((string) $lead->hubspot_contact_id, 'opportunity');

    $summary = syncAction()->execute();

    $lead->refresh();

    /*
     * The whole point of a separate `changed_at`. If an unchanged poll refreshed it, the
     * hourly cron would reset every lead's timer and nothing could ever reach the 5-day
     * threshold — staleness would silently never fire.
     */
    expect($summary['changed'])->toBe(0)
        ->and($lead->hubspot_lifecycle_changed_at->toDateString())->toBe($threeDaysAgo->toDateString())
        ->and($lead->hubspot_lifecycle_synced_at)->not->toBeNull()
        ->and(LeadActivityLog::where('lead_id', $lead->id)->where('event_type', 'HubSpotLifecycleChanged')->count())->toBe(0);
});

it('fulfils the referral and creates the reward when the deal closes', function () {
    [, $lead, $referral] = lifecycleFixture('opportunity');
    fakeHubSpotStage((string) $lead->hubspot_contact_id, 'customer');

    $summary = syncAction()->execute();

    $referral->refresh();

    expect($summary['fulfilled'])->toBe(1)
        ->and($referral->status)->toBe('fulfilled');

    $reward = ReferralReward::where('referral_id', $referral->id)->first();

    expect($reward)->not->toBeNull()
        ->and((float) $reward->amount)->toBe(250.00)
        ->and($reward->status)->toBe('due');
});

it('creates at most one reward however many times the closing stage is seen', function () {
    [, $lead, $referral] = lifecycleFixture('opportunity');
    fakeHubSpotStage((string) $lead->hubspot_contact_id, 'customer');

    syncAction()->execute();
    syncAction()->execute();

    expect(ReferralReward::where('referral_id', $referral->id)->count())->toBe(1);
});

it('does not reopen a referral that was rejected after a cancellation', function () {
    [, $lead, $referral] = lifecycleFixture('opportunity', status: 'rejected');
    fakeHubSpotStage((string) $lead->hubspot_contact_id, 'customer');

    syncAction()->execute();

    $referral->refresh();

    // Rejection is an ops decision; a CRM stage change must not silently undo it.
    expect($referral->status)->toBe('rejected')
        ->and(ReferralReward::where('referral_id', $referral->id)->count())->toBe(0);
});

it('leaves the known stage alone when HubSpot returns no record for the contact', function () {
    [, $lead] = lifecycleFixture('opportunity');

    // A contact deleted or merged away in the portal: HubSpot omits it from `results`.
    Http::fake(fn () => Http::response(['results' => []], 200));

    $summary = syncAction()->execute();

    $lead->refresh();

    // The sqlite test database is shared across the file rather than rolled back per test, so
    // leads created by earlier tests are polled by this run too and counted alongside this
    // one. The lead-level assertions below are the ones that matter.
    expect($summary['unresolved'])->toBeGreaterThanOrEqual(1)
        ->and($summary['changed'])->toBe(0)
        // Crucially not nulled: writing "no stage" would read on the dashboard as the deal
        // regressing, and would reset the staleness clock on a lead nobody touched.
        ->and($lead->hubspot_lifecycle_stage)->toBe('opportunity')
        ->and($lead->hubspot_lifecycle_synced_at)->toBeNull();
});

it('honours a portal that tracks status on a different property', function () {
    $GLOBALS['_wp_mock_options']['rl_lead_settings']['hubspot_lifecycle_property'] = 'hs_lead_status';
    $GLOBALS['_wp_mock_options']['rl_lead_settings']['hubspot_lifecycle_fulfilled_value'] = 'CONNECTED';

    [, $lead, $referral] = lifecycleFixture('OPEN');

    Http::fake(fn () => Http::response([
        'results' => [
            ['id' => (string) $lead->hubspot_contact_id, 'properties' => ['hs_lead_status' => 'CONNECTED']],
        ],
    ], 200));

    syncAction()->execute();

    expect($lead->fresh()->hubspot_lifecycle_stage)->toBe('CONNECTED')
        ->and($referral->fresh()->status)->toBe('fulfilled');
});
