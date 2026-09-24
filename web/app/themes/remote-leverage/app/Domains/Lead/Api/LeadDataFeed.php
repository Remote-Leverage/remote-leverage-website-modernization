<?php

declare(strict_types=1);

namespace App\Domains\Lead\Api;

use App\Domains\Lead\Export\LeadExportColumns;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadBookings;
use Illuminate\Database\Eloquent\Builder;

/**
 * The rows behind `/wp-json/rl-data/v1/{leads,bookings}`, a page at a time.
 *
 * ## Fields
 *
 * A row is {@see LeadExportColumns::record()}: the admin CSV export's catalogue, keyed. Reusing
 * it is the point — the data team and whoever pulls a CSV from wp-admin are looking at the same
 * lead, and two field lists written separately would drift the first time a column was added to
 * one of them.
 *
 * ## Paging
 *
 * Keyset on the lead id, ascending, rather than an offset. An offset over a window that is still
 * filling (a `to` in the future) shifts by one every time a lead arrives, and the page boundary
 * then skips a row or serves one twice. Walking up from the last id seen cannot do either: a lead
 * that arrives mid-pull has a higher id than anything served, so it lands on a later page.
 *
 * ## What a booking is
 *
 * A lead whose *first* `LeadBookingCompleted` falls in the window, via {@see LeadBookings} —
 * the same definition the cost alert counts, so the two reconcile. A lead that booked, cancelled
 * and rebooked is one booking, at the first time. Its current state, cancelled included, is the
 * `status` field on the row.
 *
 * Soft-deleted leads are left out of both, as they are from the admin export by default.
 */
class LeadDataFeed
{
    public const DEFAULT_LIMIT = 500;

    public const MAX_LIMIT = 1000;

    /**
     * Leads captured in the window.
     *
     * @param  array<int, string>  $groups
     * @return array<string, mixed>
     */
    public function leads(LeadDataWindow $window, array $groups, int $cursor = 0, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = Lead::query()
            ->where('created_at', '>=', $window->fromSql())
            ->where('created_at', '<', $window->toSql());

        $total = (clone $query)->count();

        [$leads, $next] = $this->page($query, $groups, $cursor, $limit);

        return $this->envelope(
            $window,
            $groups,
            $total,
            $next,
            array_map(static fn (Lead $lead): array => LeadExportColumns::record($lead, $groups), $leads),
        );
    }

    /**
     * Leads whose first booking falls in the window, each with the time it was made.
     *
     * @param  array<int, string>  $groups
     * @return array<string, mixed>
     */
    public function bookings(LeadDataWindow $window, array $groups, int $cursor = 0, int $limit = self::DEFAULT_LIMIT): array
    {
        $bookedAt = LeadBookings::firstBookedBetween($window->from, $window->to, includeEnd: false);

        $query = Lead::query()->whereIn('id', array_keys($bookedAt));

        // Counted through the lead query rather than as count($bookedAt), so a booked lead that
        // has since been deleted is missing from the total exactly as it is missing from the pages.
        $total = $bookedAt === [] ? 0 : (clone $query)->count();

        [$leads, $next] = $bookedAt === [] ? [[], null] : $this->page($query, $groups, $cursor, $limit);

        return $this->envelope(
            $window,
            $groups,
            $total,
            $next,
            array_map(
                static fn (Lead $lead): array => ['booked_at_utc' => $bookedAt[(int) $lead->id] ?? null]
                    + LeadExportColumns::record($lead, $groups),
                $leads,
            ),
        );
    }

    /**
     * One page, and the cursor for the next or null when this was the last.
     *
     * Asks for one row more than it returns: whether that row exists is the whole answer to "is
     * there another page", without a second count query per request.
     *
     * @param  array<int, string>  $groups
     * @return array{0: array<int, Lead>, 1: int|null}
     */
    private function page(Builder $query, array $groups, int $cursor, int $limit): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $query->orderBy('id')->limit($limit + 1);

        if ($cursor > 0) {
            $query->where('id', '>', $cursor);
        }

        if ($relations = LeadExportColumns::eagerLoad($groups)) {
            $query->with($relations);
        }

        if ($counts = LeadExportColumns::withCount($groups)) {
            $query->withCount($counts);
        }

        $leads = $query->get()->all();

        if (count($leads) <= $limit) {
            return [$leads, null];
        }

        array_pop($leads);

        return [$leads, (int) end($leads)->id];
    }

    /**
     * @param  array<int, string>  $groups
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function envelope(LeadDataWindow $window, array $groups, int $total, ?int $next, array $rows): array
    {
        return [
            'window' => $window->toArray(),
            'groups' => array_values(array_intersect(
                LeadExportColumns::groupSlugs(),
                array_merge(LeadExportColumns::mandatoryGroups(), $groups),
            )),
            'total' => $total,
            'count' => count($rows),
            'next_cursor' => $next,
            'data' => $rows,
        ];
    }
}
