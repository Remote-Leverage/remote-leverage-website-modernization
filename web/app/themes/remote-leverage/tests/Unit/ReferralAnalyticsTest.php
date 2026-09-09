<?php

declare(strict_types=1);

use App\Domains\Referral\Actions\FulfillReferralAction;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;
use App\Infrastructure\WordPress\Admin\ReferralAdminDashboard;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget('rl_referral_analytics_metrics');
});

function makeAnalyticsReferrer(array $overrides = []): Referrer
{
    return Referrer::query()->create(array_merge([
        'name' => 'Analytics Test Referrer',
        'email' => 'analytics-'.uniqid().'@venture.com',
        'referral_code' => 'analytics-'.uniqid(),
        'status' => 'active',
    ], $overrides));
}

describe('ReferralAdminDashboard analytics', function () {
    test('renders pipeline breakdown, conversion rates, leaderboard, and recent referrals', function () {
        $topReferrer = makeAnalyticsReferrer(['name' => 'Top Referrer']);
        $otherReferrer = makeAnalyticsReferrer(['name' => 'Other Referrer']);

        // 2 clicks for the top referrer (reach), 1 for the other
        ReferralClick::create(['referrer_id' => $topReferrer->id, 'referrer_slug' => $topReferrer->referral_code, 'ip_address' => '10.0.0.1', 'created_at' => now()]);
        ReferralClick::create(['referrer_id' => $topReferrer->id, 'referrer_slug' => $topReferrer->referral_code, 'ip_address' => '10.0.0.2', 'created_at' => now()]);
        ReferralClick::create(['referrer_id' => $otherReferrer->id, 'referrer_slug' => $otherReferrer->referral_code, 'ip_address' => '10.0.0.3', 'created_at' => now()]);

        // Top referrer: one fulfilled (with reward) + one qualified referral
        $fulfilledReferral = Referral::create([
            'referrer_id' => $topReferrer->id,
            'lead_name' => 'Fulfilled Lead',
            'lead_email' => 'fulfilled-'.uniqid().'@client.com',
            'status' => 'qualified',
        ]);
        (new FulfillReferralAction(new ReferralSettingsService))->execute($fulfilledReferral);
        $fulfilledReferral->update(['status' => 'fulfilled']);

        Referral::create([
            'referrer_id' => $topReferrer->id,
            'lead_name' => 'Qualified Lead',
            'lead_email' => 'qualified-'.uniqid().'@client.com',
            'status' => 'qualified',
        ]);

        // Other referrer: one pending, one rejected
        Referral::create([
            'referrer_id' => $otherReferrer->id,
            'lead_name' => 'Pending Lead',
            'lead_email' => 'pending-'.uniqid().'@client.com',
            'status' => 'pending',
        ]);

        Referral::create([
            'referrer_id' => $otherReferrer->id,
            'lead_name' => 'Rejected Lead',
            'lead_email' => 'rejected-'.uniqid().'@client.com',
            'status' => 'rejected',
        ]);

        $dashboard = new ReferralAdminDashboard;

        ob_start();
        $dashboard->renderAnalytics();
        $html = ob_get_clean();

        expect($html)->toContain('Referral Analytics')
            ->toContain('Total Referrers')
            ->toContain('Total Reach')
            ->toContain('Reach')
            ->toContain('Lead')
            ->toContain('Top Referrer')
            ->toContain('Fulfilled Lead')
            ->toContain('Qualified Lead');
    });

    test('renders an empty state when there are no referrals yet', function () {
        // The suite shares one in-memory DB across tests; truncate so this check
        // reflects a genuinely empty state rather than rows left by earlier tests.
        Referral::query()->delete();

        $dashboard = new ReferralAdminDashboard;

        ob_start();
        $dashboard->renderAnalytics();
        $html = ob_get_clean();

        expect($html)->toContain('No referrals recorded yet.');
    });
});
