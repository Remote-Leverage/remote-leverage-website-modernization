<?php

declare(strict_types=1);

namespace App\Domains\Referral\Support;

use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Support\Carbon;

/**
 * A fabricated but complete referrer dashboard, for demonstrating the portal.
 *
 * Lives in the codebase rather than in the database on purpose: it ships and versions with the
 * UI it exists to show, it cannot be mistaken for real money owed to a real person, and a
 * database refresh neither loses it nor leaves stray referral rows behind. Nothing here is
 * ever written — the toggle swaps the view model, it does not seed.
 *
 * **Only an administrator can turn this on**, and the guard is enforced in the Livewire
 * component on every render rather than in the view, because a public property is attacker-
 * controlled. See ReferrerPortalDashboard::demoAllowed().
 *
 * Every date is relative to now, so the fixture never ages into looking broken: the stale row
 * is always exactly stale, and the closed deal always closed last week.
 */
class DemoDashboardData
{
    /**
     * Build the same view model shape ReferrerDashboardPresenter produces.
     *
     * @return array{metrics: array<string, int>, earnings: array<string, mixed>, referrals: array<int, array<string, mixed>>, stale_days: int, is_demo: bool}
     */
    public static function build(?int $staleDays = null): array
    {
        $staleDays ??= ReferralSettingsService::DEFAULT_STALE_DAYS;

        $referrals = [
            self::row(
                id: 9001,
                staleDays: $staleDays,
                createdDaysAgo: 3,
                name: 'Marcus Webb',
                email: 'ma••••@northpeak.io',
                status: 'qualified',
                stage: 'Sales Qualified',
                payout: 0.0,
                daysSinceChange: 1,
                timeline: [
                    [3, 'Referral received', 'info'],
                    [2, 'Moved to Lead', 'info'],
                    [1, 'Consultation booked', 'success'],
                    [1, 'Moved to Sales Qualified', 'info'],
                ],
            ),
            self::row(
                id: 9002,
                staleDays: $staleDays,
                createdDaysAgo: 12,
                name: 'Priya Raghunathan',
                email: 'pr••••••@lumenclinic.com',
                status: 'rewarded',
                stage: 'Customer',
                payout: 250.0,
                daysSinceChange: 6,
                timeline: [
                    [12, 'Referral received', 'info'],
                    [11, 'Consultation booked', 'success'],
                    [9, 'Moved to Opportunity', 'info'],
                    [6, 'Moved to Customer', 'info'],
                    [6, 'Deal closed — reward earned', 'success'],
                ],
            ),
            self::row(
                id: 9003,
                staleDays: $staleDays,
                createdDaysAgo: 9,
                // The point of the fixture: a referral that has stopped moving. Its
                // daysSinceChange is pinned to the configured threshold so the stale badge
                // demonstrates at whatever the setting is, not only at the default of five.
                name: 'Dade Okonkwo',
                email: 'da••••@fieldstone.co',
                status: 'qualified',
                stage: 'Opportunity',
                payout: 0.0,
                daysSinceChange: $staleDays + 2,
                timeline: [
                    [9, 'Referral received', 'info'],
                    [8, 'Consultation booked', 'success'],
                    [$staleDays + 2, 'Moved to Opportunity', 'info'],
                ],
            ),
            self::row(
                id: 9004,
                staleDays: $staleDays,
                createdDaysAgo: 1,
                name: 'Helena Vos',
                email: 'Phone contact only',
                status: 'pending',
                stage: null,
                payout: 0.0,
                daysSinceChange: 1,
                awaitingSync: true,
                timeline: [
                    [1, 'Referral received', 'info'],
                ],
            ),
            self::row(
                id: 9005,
                staleDays: $staleDays,
                createdDaysAgo: 21,
                name: 'Tobias Lindqvist',
                email: 'to••••••@arcadia-group.se',
                status: 'fulfilled',
                stage: 'Customer',
                payout: 250.0,
                daysSinceChange: 8,
                timeline: [
                    [21, 'Referral received', 'info'],
                    [20, 'Consultation booked', 'success'],
                    [14, 'Moved to Opportunity', 'info'],
                    [8, 'Moved to Customer', 'info'],
                    [8, 'Deal closed — reward earned', 'success'],
                ],
            ),
            self::row(
                id: 9006,
                staleDays: $staleDays,
                createdDaysAgo: 16,
                name: 'Renata Alcázar',
                email: 're••••@brightmile.mx',
                status: 'rejected',
                stage: null,
                payout: 0.0,
                daysSinceChange: 13,
                timeline: [
                    [16, 'Referral received', 'info'],
                    [15, 'Consultation booked', 'success'],
                    [13, 'Consultation canceled', 'error'],
                ],
            ),
        ];

        return [
            'metrics' => [
                'reach' => 418,
                'referrals' => count($referrals),
                // Derived, not typed in. Hardcoded counts drift the moment a row above is
                // edited, and a demo whose KPIs disagree with its own table undermines the
                // thing it is being shown to demonstrate.
                'qualified' => count(array_filter($referrals, static fn (array $r) => $r['status'] === 'qualified')),
                'fulfilled' => count(array_filter(
                    $referrals,
                    static fn (array $r) => in_array($r['status'], ['fulfilled', 'rewarded'], true),
                )),
                'stale' => count(array_filter($referrals, static fn (array $row) => $row['is_stale'])),
            ],
            'earnings' => [
                // One reward still due, one already issued — so the earnings panel shows both
                // states rather than a single total. Totals derive from the rows for the same
                // reason the KPIs above do.
                'due' => (float) array_sum(array_map(
                    static fn (array $r) => $r['status'] === 'fulfilled' ? $r['payout'] : 0.0,
                    $referrals,
                )),
                'issued' => (float) array_sum(array_map(
                    static fn (array $r) => $r['status'] === 'rewarded' ? $r['payout'] : 0.0,
                    $referrals,
                )),
                'total' => (float) array_sum(array_column($referrals, 'payout')),
                'currency' => 'USD',
            ],
            'referrals' => $referrals,
            'stale_days' => $staleDays,
            'is_demo' => true,
        ];
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: string}>  $timeline  [daysAgo, label, tone]
     * @return array<string, mixed>
     */
    protected static function row(
        int $staleDays,
        int $id,
        int $createdDaysAgo,
        string $name,
        string $email,
        string $status,
        ?string $stage,
        float $payout,
        int $daysSinceChange,
        array $timeline,
        bool $awaitingSync = false,
    ): array {
        return [
            'id' => $id,
            'date' => Carbon::now()->subDays($createdDaysAgo)->format('M j, Y'),
            'name' => $name,
            'email' => $email,
            'status' => $status,
            'stage' => $stage,
            'payout' => $payout,
            'currency' => 'USD',
            'days_since_change' => $daysSinceChange,
            'last_change_at' => Carbon::now()->subDays($daysSinceChange)->format('M j, Y'),
            // Mirrors ReferrerDashboardPresenter::isStale(): terminal statuses never go stale.
            'is_stale' => $daysSinceChange >= $staleDays
                && ! in_array($status, ['fulfilled', 'rewarded', 'rejected'], true),
            'awaiting_sync' => $awaitingSync,
            'timeline' => array_map(static fn (array $entry) => [
                'at' => Carbon::now()->subDays($entry[0])->format('M j, Y'),
                'iso' => Carbon::now()->subDays($entry[0])->toIso8601String(),
                'label' => $entry[1],
                'tone' => $entry[2],
            ], $timeline),
        ];
    }
}
