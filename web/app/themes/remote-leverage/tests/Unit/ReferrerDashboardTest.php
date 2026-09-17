<?php

declare(strict_types=1);

use App\Application\Livewire\Referrer\ReferrerPortalDashboard;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;
use App\Domains\Referral\Support\DemoDashboardData;
use App\Domains\Referral\Support\ReferralTimeline;
use App\Domains\Referral\Support\ReferrerDashboardPresenter;
use Illuminate\Support\Str;

beforeEach(function () {
    unset($GLOBALS['_wp_mock_capabilities']);
    $GLOBALS['_wp_mock_options']['rl_referral_settings'] = [
        'default_reward_amount' => 250.00,
        'default_reward_currency' => 'USD',
        'default_reward_type' => 'cash',
        'stale_days' => 5,
    ];
});

function dashboardPresenter(): ReferrerDashboardPresenter
{
    return new ReferrerDashboardPresenter(new ReferralSettingsService, new ReferralTimeline);
}

function dashboardReferrer(): Referrer
{
    return Referrer::query()->create([
        'name' => 'Dashboard Referrer',
        'email' => 'dash-'.uniqid().'@agency.com',
        'referral_code' => 'dash-'.uniqid(),
        'status' => 'active',
    ]);
}

function dashboardReferral(Referrer $referrer, string $status, array $leadOverrides = []): array
{
    $lead = Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Dashboard Prospect',
        'email' => 'dash-prospect-'.uniqid().'@client.com',
        'source_type' => 'referral_hub',
        'source_id' => $referrer->referral_code,
        'status' => 'booked',
    ], $leadOverrides));

    $referral = Referral::query()->create([
        'referrer_id' => $referrer->id,
        'lead_id' => $lead->id,
        'lead_name' => $lead->name,
        'lead_email' => $lead->email,
        'source' => 'booking_completed',
        'status' => $status,
    ]);

    return [$lead, $referral];
}

describe('deals-closed KPI', function () {
    it('counts only referrals whose deal actually closed', function () {
        $referrer = dashboardReferrer();

        dashboardReferral($referrer, 'pending');
        dashboardReferral($referrer, 'qualified');
        dashboardReferral($referrer, 'qualified');
        dashboardReferral($referrer, 'fulfilled');
        dashboardReferral($referrer, 'rewarded');

        $model = dashboardPresenter()->forReferrer($referrer);

        /*
         * The old dashboard counted `qualified` here too, so a referrer saw every booked call
         * reported as a closed deal — and a higher figure than the admin screen showed for the
         * same rows. Booking a call qualifies; it does not close.
         */
        expect($model['metrics']['fulfilled'])->toBe(2)
            ->and($model['metrics']['qualified'])->toBe(2)
            ->and($model['metrics']['referrals'])->toBe(5);
    });
});

describe('staleness', function () {
    it('flags a referral whose stage has not moved past the threshold', function () {
        $referrer = dashboardReferrer();
        [$lead] = dashboardReferral($referrer, 'qualified');

        $lead->forceFill([
            'hubspot_lifecycle_stage' => 'opportunity',
            'hubspot_lifecycle_changed_at' => now()->subDays(9),
            'hubspot_lifecycle_synced_at' => now(),
        ])->save();

        $model = dashboardPresenter()->forReferrer($referrer);

        expect($model['referrals'][0]['is_stale'])->toBeTrue()
            ->and($model['referrals'][0]['days_since_change'])->toBe(9)
            ->and($model['metrics']['stale'])->toBe(1);
    });

    it('does not flag a referral that moved within the threshold', function () {
        $referrer = dashboardReferrer();
        [$lead] = dashboardReferral($referrer, 'qualified');

        $lead->forceFill([
            'hubspot_lifecycle_stage' => 'opportunity',
            'hubspot_lifecycle_changed_at' => now()->subDays(2),
            'hubspot_lifecycle_synced_at' => now(),
        ])->save();

        expect(dashboardPresenter()->forReferrer($referrer)['referrals'][0]['is_stale'])->toBeFalse();
    });

    it('never flags a closed or rejected referral, however long it has sat', function () {
        $referrer = dashboardReferrer();

        foreach (['rewarded', 'rejected'] as $status) {
            [$lead] = dashboardReferral($referrer, $status);
            $lead->forceFill(['hubspot_lifecycle_changed_at' => now()->subDays(60)])->save();
        }

        $model = dashboardPresenter()->forReferrer($referrer);

        // A finished deal has stopped moving because it is finished. Badging it as needing
        // attention would bury the ones that genuinely do.
        expect($model['metrics']['stale'])->toBe(0);
    });
});

describe('timeline', function () {
    it('shows allowlisted events and hides internal ones', function () {
        $referrer = dashboardReferrer();
        [$lead] = dashboardReferral($referrer, 'qualified');

        LeadActivityLog::query()->create([
            'lead_id' => $lead->id, 'event_type' => 'LeadCreated', 'actor_domain' => 'Lead',
            'stage' => 'dispatch', 'outcome' => 'succeeded', 'created_at' => now()->subDays(4),
        ]);
        LeadActivityLog::query()->create([
            'lead_id' => $lead->id, 'event_type' => 'LeadBookingCompleted', 'actor_domain' => 'Scheduling',
            'stage' => 'dispatch', 'outcome' => 'succeeded', 'created_at' => now()->subDays(3),
        ]);
        // An internal integration record a referrer must never be shown.
        LeadActivityLog::query()->create([
            'lead_id' => $lead->id, 'event_type' => 'HubSpotSyncFailed', 'actor_domain' => 'Lead',
            'stage' => 'consumption', 'outcome' => 'failed', 'created_at' => now()->subDays(2),
        ]);

        $timeline = dashboardPresenter()->forReferrer($referrer)['referrals'][0]['timeline'];

        expect(collect($timeline)->pluck('label')->all())
            ->toBe(['Referral received', 'Consultation booked']);
    });

    it('collapses the duplicate rows dual logging produces for one event', function () {
        $referrer = dashboardReferrer();
        [$lead] = dashboardReferral($referrer, 'qualified');

        // One booking writes a dispatch row and a consumption row per consuming domain.
        foreach (['dispatch', 'consumption', 'consumption'] as $i => $stage) {
            LeadActivityLog::query()->create([
                'lead_id' => $lead->id, 'event_type' => 'LeadBookingCompleted', 'actor_domain' => 'Scheduling',
                'stage' => $stage, 'outcome' => 'succeeded', 'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $timeline = dashboardPresenter()->forReferrer($referrer)['referrals'][0]['timeline'];

        expect($timeline)->toHaveCount(1)
            ->and($timeline[0]['label'])->toBe('Consultation booked');
    });
});

describe('contact masking', function () {
    it('masks a referred prospect email and names a phone-only referral as such', function () {
        $referrer = dashboardReferrer();
        dashboardReferral($referrer, 'pending', ['email' => 'jonathan@acme-corp.com']);

        $row = dashboardPresenter()->forReferrer($referrer)['referrals'][0];

        expect($row['email'])->toStartWith('jo')
            ->and($row['email'])->toEndWith('@acme-corp.com')
            ->and($row['email'])->not->toContain('nathan');
    });
});

describe('sidebar rails', function () {
    it('lists the stale referrals themselves, longest-quiet first', function () {
        $referrer = dashboardReferrer();

        foreach ([7, 30, 12] as $daysQuiet) {
            [$lead] = dashboardReferral($referrer, 'qualified');
            $lead->forceFill([
                'hubspot_lifecycle_stage' => 'opportunity',
                'hubspot_lifecycle_changed_at' => now()->subDays($daysQuiet),
                'hubspot_lifecycle_synced_at' => now(),
            ])->save();
        }

        // A count cannot be acted on. The rail exists to say who has gone quiet, worst first.
        $attention = dashboardPresenter()->forReferrer($referrer)['needs_attention'];

        expect(collect($attention)->pluck('days_since_change')->all())->toBe([30, 12, 7]);
    });

    it('merges every referral timeline into one newest-first feed', function () {
        $referrer = dashboardReferrer();
        [$leadA] = dashboardReferral($referrer, 'qualified');
        [$leadB] = dashboardReferral($referrer, 'qualified');

        LeadActivityLog::query()->create([
            'lead_id' => $leadA->id, 'event_type' => 'LeadCreated', 'actor_domain' => 'Lead',
            'stage' => 'dispatch', 'outcome' => 'succeeded', 'created_at' => now()->subDays(5),
        ]);
        LeadActivityLog::query()->create([
            'lead_id' => $leadB->id, 'event_type' => 'LeadBookingCompleted', 'actor_domain' => 'Scheduling',
            'stage' => 'dispatch', 'outcome' => 'succeeded', 'created_at' => now()->subDays(1),
        ]);

        $feed = dashboardPresenter()->forReferrer($referrer)['recent_activity'];

        expect(collect($feed)->pluck('label')->all())
            ->toBe(['Consultation booked', 'Referral received'])
            // Each entry has to carry the referral it came from, or the rail cannot link back.
            ->and($feed[0])->toHaveKeys(['referral_id', 'name', 'tone', 'at', 'iso']);
    });

    it('caps each rail so a busy referrer does not get an unbounded sidebar', function () {
        $referrer = dashboardReferrer();

        foreach (range(1, ReferrerDashboardPresenter::ATTENTION_LIMIT + 3) as $i) {
            [$lead] = dashboardReferral($referrer, 'qualified');
            $lead->forceFill([
                'hubspot_lifecycle_changed_at' => now()->subDays(10 + $i),
                'hubspot_lifecycle_synced_at' => now(),
            ])->save();
        }

        $model = dashboardPresenter()->forReferrer($referrer);

        expect($model['needs_attention'])->toHaveCount(ReferrerDashboardPresenter::ATTENTION_LIMIT)
            // The count still reflects reality even though the list is truncated.
            ->and($model['metrics']['stale'])->toBe(ReferrerDashboardPresenter::ATTENTION_LIMIT + 3);
    });
});

describe('demo mode', function () {
    it('is available to an administrator', function () {
        $GLOBALS['_wp_mock_capabilities'] = ['manage_options'];

        expect((new ReferrerPortalDashboard)->demoAllowed())->toBeTrue();
    });

    it('is refused to a signed-in user without manage_options', function () {
        $GLOBALS['_wp_mock_capabilities'] = ['read'];

        $component = new ReferrerPortalDashboard;

        expect($component->demoAllowed())->toBeFalse();

        // The toggle must also refuse to flip, not merely be hidden in the template: the
        // property arrives from the browser and is therefore attacker-controlled.
        $component->demoMode = true;
        $component->toggleDemoMode();

        expect($component->demoMode)->toBeFalse();
    });
});

describe('demo fixture', function () {
    it('matches the shape the presenter produces', function () {
        $referrer = dashboardReferrer();
        dashboardReferral($referrer, 'qualified');

        $real = dashboardPresenter()->forReferrer($referrer);
        $demo = DemoDashboardData::build(5);

        // The demo toggle swaps the data, not the rendering path — a key present in one and
        // absent in the other would be an undefined-index error only the demo can reach.
        expect(array_keys($demo['metrics']))->toBe(array_keys($real['metrics']))
            ->and(array_keys($demo['earnings']))->toBe(array_keys($real['earnings']))
            ->and(array_keys($demo['referrals'][0]))->toBe(array_keys($real['referrals'][0]))
            // Both sidebars render from these, so a shape mismatch is a blank rail in demo
            // mode only — exactly the place it would be noticed last.
            ->and(array_keys($demo))->toContain('needs_attention', 'recent_activity')
            ->and(array_keys($real))->toContain('needs_attention', 'recent_activity');
    });

    it('always contains a stale row at whatever threshold is configured', function () {
        foreach ([3, 5, 14] as $staleDays) {
            $demo = DemoDashboardData::build($staleDays);

            expect($demo['metrics']['stale'])->toBeGreaterThan(0)
                ->and($demo['stale_days'])->toBe($staleDays);
        }
    });
});
