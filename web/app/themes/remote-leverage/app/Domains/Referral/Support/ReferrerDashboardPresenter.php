<?php

declare(strict_types=1);

namespace App\Domains\Referral\Support;

use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Support\Collection;

/**
 * Assemble everything the referrer portal renders into one view model.
 *
 * Exists so the portal has exactly one shape to render and the demo fixture has exactly one
 * shape to imitate — see DemoDashboardData. A Livewire component that built this inline would
 * make the demo toggle a second rendering path, and the two would drift.
 */
class ReferrerDashboardPresenter
{
    /**
     * How many stale referrals the "needs attention" rail lists before it stops. A worklist
     * longer than this is a filtered table, not a rail — the pipeline's own "Needs a nudge"
     * filter is the right tool at that point.
     */
    public const ATTENTION_LIMIT = 5;

    /**
     * How many entries the merged activity feed shows.
     */
    public const ACTIVITY_LIMIT = 8;

    public function __construct(
        protected ReferralSettingsService $settings,
        protected ReferralTimeline $timeline,
    ) {}

    /**
     * @return array{metrics: array<string, int>, earnings: array<string, mixed>, referrals: array<int, array<string, mixed>>, stale_days: int}
     */
    public function forReferrer(Referrer $referrer): array
    {
        $staleDays = (int) ($this->settings->get()['stale_days'] ?? ReferralSettingsService::DEFAULT_STALE_DAYS);

        /*
         * `with` on all three relations, not lazy access inside the map below. The previous
         * dashboard loaded rewards eagerly but reached for everything else per row; with the
         * timeline added that becomes a query per referral per page view.
         */
        $referrals = $referrer->referrals()
            ->with(['rewards', 'lead'])
            ->orderByDesc('created_at')
            ->get();

        $logsByLead = $this->activityLogsFor($referrals);

        $rows = $referrals
            ->map(fn (Referral $referral) => $this->row($referral, $logsByLead, $staleDays))
            ->values()
            ->all();

        return [
            'metrics' => [
                'reach' => ReferralClick::query()->where('referrer_id', $referrer->id)->count(),
                'referrals' => $referrals->count(),
                'qualified' => $referrals->where('status', 'qualified')->count(),
                /*
                 * Fulfilled means the deal closed — `fulfilled` or `rewarded` and nothing else.
                 * This KPI used to count `qualified` too, so a referrer saw every booked call
                 * reported as a closed deal and a higher number than the admin screen showed
                 * for the same data.
                 */
                'fulfilled' => $referrals->whereIn('status', Referral::FULFILLED_STATUSES)->count(),
                'stale' => count(array_filter($rows, static fn (array $row) => $row['is_stale'])),
            ],
            'earnings' => $this->earnings($referrals),
            'referrals' => $rows,
            'needs_attention' => $this->needsAttention($rows),
            'recent_activity' => $this->recentActivity($rows),
            'stale_days' => $staleDays,
        ];
    }

    /**
     * The stale referrals themselves, not a count of them.
     *
     * A referrer cannot act on "3". The right rail lists who has gone quiet and for how long,
     * longest first, because that is the only part of this dashboard that asks them to do
     * something.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function needsAttention(array $rows): array
    {
        $stale = array_values(array_filter($rows, static fn (array $row) => $row['is_stale']));

        usort($stale, static fn (array $a, array $b) => ($b['days_since_change'] ?? 0) <=> ($a['days_since_change'] ?? 0));

        return array_slice($stale, 0, self::ATTENTION_LIMIT);
    }

    /**
     * One merged feed across every referral, answering "what changed since I last looked".
     *
     * The per-referral timelines are each behind a click, so without this there is no way to
     * see movement without opening rows one at a time.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function recentActivity(array $rows): array
    {
        $entries = [];

        foreach ($rows as $row) {
            foreach ($row['timeline'] as $entry) {
                $entries[] = $entry + [
                    'referral_id' => $row['id'],
                    'name' => $row['name'],
                ];
            }
        }

        // ISO-8601 with a consistent offset sorts correctly as a string, and every value here
        // is produced by the same Carbon instance.
        usort($entries, static fn (array $a, array $b) => strcmp((string) $b['iso'], (string) $a['iso']));

        return array_slice($entries, 0, self::ACTIVITY_LIMIT);
    }

    /**
     * One query for every referral's activity, grouped by lead.
     *
     * @param  Collection<int, Referral>  $referrals
     * @return Collection<int, Collection<int, LeadActivityLog>>
     */
    protected function activityLogsFor(Collection $referrals): Collection
    {
        $leadIds = $referrals->pluck('lead_id')->filter()->unique()->values();

        if ($leadIds->isEmpty()) {
            return collect();
        }

        return LeadActivityLog::query()
            ->whereIn('lead_id', $leadIds)
            ->whereIn('event_type', array_keys(ReferralTimeline::MAP))
            ->orderBy('created_at')
            ->get()
            ->groupBy('lead_id');
    }

    /**
     * @param  Collection<int, Collection<int, LeadActivityLog>>  $logsByLead
     * @return array<string, mixed>
     */
    protected function row(Referral $referral, Collection $logsByLead, int $staleDays): array
    {
        $lead = $referral->lead;
        $logs = $referral->lead_id ? ($logsByLead[$referral->lead_id] ?? collect()) : collect();

        $lastChange = $lead?->hubspot_lifecycle_changed_at ?? $referral->updated_at;
        $daysSinceChange = $lastChange ? (int) $lastChange->diffInDays(now()) : null;

        return [
            'id' => $referral->id,
            'date' => $referral->created_at?->format('M j, Y') ?? '',
            'name' => $referral->lead_name ?: 'Confidential Contact',
            'email' => $this->maskEmail($referral->lead_email),
            'status' => $referral->status ?? 'pending',
            'stage' => $lead?->hubspot_lifecycle_stage
                ? ReferralTimeline::humaniseStage($lead->hubspot_lifecycle_stage)
                : null,
            'payout' => (float) $referral->rewards->sum('amount'),
            'currency' => (string) ($referral->rewards->first()->currency ?? 'USD'),
            'days_since_change' => $daysSinceChange,
            'last_change_at' => $lastChange?->format('M j, Y'),
            'is_stale' => $this->isStale($referral, $daysSinceChange, $staleDays),
            'awaiting_sync' => $lead !== null && $lead->hubspot_lifecycle_synced_at === null,
            'timeline' => $this->timeline->build($logs),
        ];
    }

    /**
     * A referral is stale when its deal has not moved in `$staleDays` and still could.
     *
     * Terminal statuses are never stale: a closed or rejected deal has stopped moving because
     * it is finished, and badging that as needing attention would bury the ones that do.
     */
    protected function isStale(Referral $referral, ?int $daysSinceChange, int $staleDays): bool
    {
        if ($daysSinceChange === null || $daysSinceChange < $staleDays) {
            return false;
        }

        return ! in_array($referral->status, [...Referral::FULFILLED_STATUSES, 'rejected'], true);
    }

    /**
     * @param  Collection<int, Referral>  $referrals
     * @return array<string, mixed>
     */
    protected function earnings(Collection $referrals): array
    {
        $rewards = $referrals->flatMap(fn (Referral $referral) => $referral->rewards);

        return [
            'due' => (float) $rewards->where('status', 'due')->sum('amount'),
            'issued' => (float) $rewards->where('status', 'issued')->sum('amount'),
            'total' => (float) $rewards->sum('amount'),
            'currency' => (string) ($rewards->first()->currency ?? 'USD'),
        ];
    }

    /**
     * Partially mask a referred prospect's address.
     *
     * A referrer who submitted the lead already knows it, but the portal is reachable with one
     * shared password and this table is the only place the address is echoed back — so it is
     * shown as recognisable, not as copyable. A synthesised internal address (phone-only
     * submissions) has nothing to show and says so.
     */
    protected function maskEmail(?string $email): string
    {
        $email = trim((string) $email);

        if ($email === '' || str_ends_with($email, '@remoteleverage.internal')) {
            return 'Phone contact only';
        }

        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $email;
        }

        $visible = mb_substr($user, 0, 2);

        return $visible.str_repeat('•', max(mb_strlen($user) - 2, 1)).'@'.$domain;
    }
}
