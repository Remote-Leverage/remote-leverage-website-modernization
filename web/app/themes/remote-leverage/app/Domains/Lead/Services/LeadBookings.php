<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Api\LeadDataFeed;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Marketing\Services\FunnelMetricsService;
use Carbon\CarbonImmutable;

/**
 * When a lead booked, read off the activity log.
 *
 * `status = 'booked'` is a lead's current state and carries no timestamp, so it cannot say when a
 * booking happened. The activity log can: the earliest `LeadBookingCompleted` row for a lead is
 * when that lead booked. `MIN()` rather than a count of rows because three listeners — Referral,
 * Slack and the outgoing webhook — each log the same event under their own `actor_domain`, and
 * counting rows would report one booking as three.
 *
 * One definition, shared by the cost alert ({@see FunnelMetricsService}) and the data API
 * ({@see LeadDataFeed}). The data team reconciles the two against each other, so if they
 * disagreed about what a booking is the difference would be read as lost data rather than as two
 * queries.
 *
 * Leads imported from Gravity Forms predate the activity log and have no booking row at all, so a
 * window before the cutover correctly reads almost empty. See FunnelMetricsService for the count.
 */
final class LeadBookings
{
    public const EVENT = 'LeadBookingCompleted';

    /**
     * Lead id => first booking time (UTC, `Y-m-d H:i:s`), for leads whose first booking lands in
     * the window.
     *
     * `$includeEnd` exists because the two callers want different windows. The cost alert asks
     * for a closed day ending at 23:59:59; the data API takes half-open windows so that adjacent
     * pulls meet exactly, and a booking logged on the boundary second belongs to one of them.
     *
     * @return array<int, string>
     */
    public static function firstBookedBetween(CarbonImmutable $from, CarbonImmutable $to, bool $includeEnd = true): array
    {
        return LeadActivityLog::query()
            ->selectRaw('lead_id, MIN(created_at) AS booked_at')
            ->where('event_type', self::EVENT)
            ->groupBy('lead_id')
            ->havingRaw('MIN(created_at) >= ?', [$from->utc()->format('Y-m-d H:i:s')])
            ->havingRaw('MIN(created_at) '.($includeEnd ? '<=' : '<').' ?', [$to->utc()->format('Y-m-d H:i:s')])
            ->toBase()
            ->pluck('booked_at', 'lead_id')
            ->mapWithKeys(static fn ($bookedAt, $leadId): array => [(int) $leadId => (string) $bookedAt])
            ->all();
    }
}
