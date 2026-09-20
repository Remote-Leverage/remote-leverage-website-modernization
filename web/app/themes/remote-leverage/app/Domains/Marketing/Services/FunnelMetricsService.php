<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Services;

use App\Domains\Lead\Data\LeadAudience;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadChannel;
use App\Domains\Lead\Services\LeadPlatform;
use App\Domains\Lead\Services\LeadQualification;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Data\MarketingDay;
use App\Domains\Marketing\Data\PlatformSlice;
use App\Domains\Marketing\Gateways\BigQueryClient;
use App\Domains\Marketing\Support\AlertWindow;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Everything the marketing cost alert reports, computed once.
 *
 * ## "Today" is the ad account's day, not the server's
 *
 * Timestamps are stored in UTC. Ad platforms bill and report in the ad account's timezone. If
 * the two are allowed to drift apart, spend is counted over one window and bookings over
 * another, and every cost figure is wrong by however much crossed the boundary — silently, and
 * worst at the ends of the day when someone is most likely to be looking. So the day boundaries
 * are computed in `marketing.cost_alert.timezone` and converted to UTC before they touch a
 * query, explicitly, every time.
 *
 * ## Classification happens in PHP, filtering happens in SQL
 *
 * The rows are narrowed in SQL — VA applicants out, the date window applied — and then the few
 * hundred that survive are bucketed by platform in PHP with `LeadPlatform::for()`. The
 * alternative is nine `LeadPlatform::apply()` queries per figure, and it buys nothing: the two
 * implementations are pinned against each other by a parity matrix, so they cannot disagree,
 * and only the PHP side can also report *how* a lead was attributed, which the alert prints.
 *
 * ## A booking is an event, not a status
 *
 * `status = 'booked'` is a lead's current state and carries no timestamp, so it cannot answer
 * "how many booked today". The activity log can: the earliest `LeadBookingCompleted` row for a
 * lead is when that lead booked. `MIN()` rather than a count of rows because three listeners —
 * Referral, Slack and the outgoing webhook — each log the same event under their own
 * `actor_domain`, and counting rows would report one booking as three.
 *
 * The consequence worth knowing: a lead created yesterday that books today counts as today's
 * booking and yesterday's lead. That is correct for cost per booking, and it is why the booking
 * count is not simply a filter over today's leads.
 *
 * The trailing booking *rate* uses `status = 'booked'` instead, deliberately. It asks a
 * lead-level question that needs no timestamp, and the status is the only thing the imported
 * Gravity Forms rows carry — they predate the activity log entirely.
 *
 * That gap is large and worth knowing before it surprises someone: on the database as of
 * 2026-09-19, 2,571 leads carry `status = 'booked'` and three of them have a booking log. The
 * other 2,568 were imported from Gravity Forms and never passed through this application. So a
 * snapshot pointed at a historical date reads almost zero, and that is correct rather than
 * broken — those bookings have no timestamp to be counted on. Everything booked through the site
 * is logged: `CalendlyWebhookController` writes a `LeadBookingCompleted` row for every
 * `invitee.created` it processes, before the event even dispatches.
 */
class FunnelMetricsService
{
    /** Hard cap on rows pulled into PHP for classification, per window. */
    private const MAX_ROWS = 5000;

    /** How many of the newest leads the short-run booking rate looks at. */
    private const RECENT_SAMPLE = 10;

    /** How many of the newest leads the trailing booking rate looks at. */
    private const TRAILING_SAMPLE = 1000;

    public function __construct(
        private readonly ?CalendlyClient $calendly = null,
        private readonly ?CalendlyEventTypeRoleResolver $roles = null,
        private readonly ?AlertReconciler $reconciler = null,
        private readonly ?BigQueryClient $warehouse = null,
    ) {}

    /** Where the dashboard widget's copy of the snapshot lives. */
    public const CACHE_KEY = 'rl_marketing_funnel_snapshot';

    /** How long a warmed snapshot stays readable. Comfortably longer than the hourly refresh. */
    public const CACHE_TTL = 10800;

    /**
     * The last warmed snapshot, or null when there is not one yet.
     *
     * **This never computes.** That is the whole point of it. {@see self::snapshot()} runs about
     * twenty-five queries and, when Calendly is configured, up to four network round trips; it is
     * the right cost for an hourly job and a wholly unacceptable one for a wp-admin widget that
     * renders on every dashboard load.
     *
     * It used to compute on a miss, via `Cache::remember()`. Two things made that worse than it
     * looks. The cache never actually hit — see {@see FunnelSnapshot::toArray()} for why — so
     * "on a miss" meant "every time"; and the work it fell back to is dominated by Calendly,
     * measured between 1.1 and 10.0 seconds *for the same call*. The admin dashboard therefore
     * took 9 to 16 seconds to finish loading, unpredictably, which is exactly how it was
     * reported: slow "sometimes".
     *
     * So the read path is now pure. {@see self::warmCache()} does the computing, on the hourly
     * cron tick that was already running, and a widget with nothing to show says so.
     *
     * The scheduled alert deliberately does *not* read this. It runs once an hour and its whole
     * job is to be current; reading an old cache to publish an hourly number is how a figure that
     * looks live turns out to predate the thing someone is asking about.
     */
    public function cachedSnapshot(): ?FunnelSnapshot
    {
        try {
            $data = Cache::get(self::CACHE_KEY);

            return is_array($data) ? FunnelSnapshot::fromArray($data) : null;
        } catch (\Throwable $e) {
            /*
             * An unreadable cache must not take the widget down with it. Null renders as "not
             * computed yet", which is true, rather than as a broken panel.
             */
            Log::warning('FunnelMetricsService: snapshot cache unreadable', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Compute a snapshot and store it for {@see self::cachedSnapshot()} to serve.
     *
     * Skips the work when the stored copy is younger than `$minAgeSeconds`, which is what keeps
     * the hourly tick from computing twice: inside the alert's reporting window
     * `SendCostAlertAction` has already computed and stored a fresh snapshot by the time this
     * runs, and recomputing would mean four more Calendly and Meta round trips for figures that
     * are seconds old.
     */
    public function warmCache(int $ttlSeconds = self::CACHE_TTL, int $minAgeSeconds = 1800): ?FunnelSnapshot
    {
        $existing = $this->cachedSnapshot();

        if ($existing !== null && $existing->generatedAt->diffInSeconds(CarbonImmutable::now()) < $minAgeSeconds) {
            return $existing;
        }

        try {
            $snapshot = $this->snapshot();
        } catch (\Throwable $e) {
            /*
             * Leave whatever is already cached in place. A stale card with a visible timestamp is
             * more use than an empty one, and the widget prints how old its figures are.
             */
            Log::error('FunnelMetricsService: could not warm the snapshot cache', [
                'error' => $e->getMessage(),
            ]);

            return $existing;
        }

        $this->store($snapshot, $ttlSeconds);

        return $snapshot;
    }

    /** Put a freshly computed snapshot where the dashboard widget will find it. */
    public function store(FunnelSnapshot $snapshot, int $ttlSeconds = self::CACHE_TTL): void
    {
        try {
            Cache::put(self::CACHE_KEY, $snapshot->toArray(), $ttlSeconds);
        } catch (\Throwable $e) {
            /*
             * Failing to cache is not failing. The caller already has its snapshot; only the
             * widget's copy is lost, and it will be rewritten on the next tick.
             */
            Log::warning('FunnelMetricsService: could not store the snapshot', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  CarbonImmutable|null  $now  Overridable so a test can pin the clock.
     */
    public function snapshot(?CarbonImmutable $now = null, ?callable $onProgress = null): FunnelSnapshot
    {
        /*
         * Named steps rather than a spinner.
         *
         * This method is 25-odd queries and, with Calendly configured, up to four network round
         * trips measured between 1.1 and 10.0 seconds. A caller watching it needs to know which
         * of those it is waiting on — "Reading the marketing warehouse" and "Fetching
         * consultations from Calendly" fail for entirely different reasons and are fixed by
         * different people. The percentages are rough and deliberately so; they order the steps,
         * they do not predict them.
         */
        $step = static function (string $label, int $percent) use ($onProgress): void {
            if ($onProgress !== null) {
                $onProgress($label, $percent);
            }
        };

        $timezone = (string) config('marketing.cost_alert.timezone', 'UTC');
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);

        $step('Reading the marketing warehouse', 10);

        /*
         * The cost half, from the data team's warehouse rather than from this application.
         * Null when it cannot be read, which makes the card say so instead of rendering zeros.
         *
         * Read *before* the window is chosen, because it is what chooses the window. See
         * reportingWindow().
         */
        $marketingDay = ($this->warehouse ?? new BigQueryClient)->marketingDay();

        [$fromUtc, $toUtc] = $this->reportingWindow($now, $marketingDay);

        $step('Counting leads and bookings', 30);

        $leads = $this->clientLeadsBetween($fromUtc, $toUtc);
        $bookedLeads = $this->clientLeadsBookedBetween($fromUtc, $toUtc);

        /*
         * Counted in SQL rather than taken from the collection above, which is capped at
         * MAX_ROWS. The cap protects PHP memory during classification; it must not become a
         * ceiling on a number the card reports. Past the cap the two would silently disagree —
         * `leads` frozen at 5,000 while `excludedVaLeads`, a real count, kept climbing.
         */
        $leadCount = $this->clientLeads()->whereBetween('created_at', [$fromUtc, $toUtc])->count();

        $step('Attributing leads to platforms', 45);

        $platforms = $this->slice($leads, $bookedLeads);

        $qualifiedT10 = $bookedLeads->filter(
            static fn (Lead $lead): bool => LeadQualification::isT10($lead)
        )->count();

        $step('Reading HubSpot lifecycle stages', 55);

        [$qualifiedHubSpot, $hubSpotCoverage] = $this->hubSpotQualified($bookedLeads);

        $lastLeadMinutes = $this->minutesSince($now, $this->lastLeadAt());
        [$lastBookingMinutes, $lastBookingName] = $this->lastBooking($now);

        $step('Measuring the trailing booking rate', 62);

        [$recentBooked, $recentSize] = $this->bookingRate(self::RECENT_SAMPLE);
        [$trailingBooked, $trailingSize] = $this->bookingRate(self::TRAILING_SAMPLE);

        /*
         * Hoisted out of the constructor call below so each can be announced before it runs.
         * Calendly is the slowest thing here by an order of magnitude and the one most worth
         * naming while somebody waits on it.
         */
        $step('Fetching consultations from Calendly', 70);

        $consultationsToday = $this->consultationsOn($now);
        $upcomingConsultations = $this->upcomingConsultations($now);

        $step('Averaging the previous days', 85);

        $baseline = $this->baseline(
            $toUtc->setTimezone($now->timezone),
            $marketingDay?->isClosing() ?? false,
        );

        $platformBookings = array_sum(array_map(
            static fn (PlatformSlice $slice): int => $slice->bookings,
            $platforms,
        ));

        $warnings = ($this->reconciler ?? new AlertReconciler)->check([
            'leads' => $leadCount,
            'bookings' => $bookedLeads->count(),
            'last_lead_minutes' => $lastLeadMinutes,
            'last_booking_minutes' => $lastBookingMinutes,
            'platform_bookings' => $platformBookings,

            /*
             * The cohort measure, purely as a cross-check on the event measure. Never reported as
             * a figure — the two answer different questions and presenting them as one number is
             * the definition drift this whole class exists to catch.
             */
            'booked_by_status' => $this->clientLeads()
                ->whereBetween('created_at', [$fromUtc, $toUtc])
                ->where('status', 'booked')
                ->count(),
            /*
             * Staffed hours, not posting hours. The card now posts round the clock; a six-hour
             * lead gap is only an incident when there was somebody there to notice it.
             */
            'within_window' => AlertWindow::staffed($now),
            /*
             * The warehouse's booking count against this application's own. They are allowed to
             * differ a little — different definitions of a booking, different load times — but a
             * wide gap means one of the two is wrong and the card is reporting the warehouse's.
             */
            'warehouse_bookings' => $marketingDay?->appointments,

            /*
             * Which day both halves now cover, so the reconciler can name it instead of saying
             * "today" about a card that, before 08:00 Eastern, is about yesterday.
             */
            'report_date' => $marketingDay?->date,
            'report_is_closing' => $marketingDay?->isClosing() ?? false,
            'warehouse_unavailable' => $marketingDay === null,
        ]);

        return new FunnelSnapshot(
            generatedAt: $now,
            timezone: $timezone,
            currency: (string) config('marketing.cost_alert.currency', 'USD'),
            dayElapsed: $this->dayElapsed($now),
            leads: $leadCount,
            bookings: $bookedLeads->count(),
            qualifiedT10: $qualifiedT10,
            qualifiedHubSpot: $qualifiedHubSpot,
            hubSpotCoverage: $hubSpotCoverage,
            platforms: $platforms,
            excludedVaLeads: $this->countVaLeadsBetween($fromUtc, $toUtc),
            excludedVaBookings: $this->countVaBookingsBetween($fromUtc, $toUtc),
            lastLeadMinutes: $lastLeadMinutes,
            lastBookingMinutes: $lastBookingMinutes,
            lastBookingName: $lastBookingName,
            recentSampleBooked: $recentBooked,
            recentSampleSize: $recentSize,
            trailingBookingRate: $trailingSize > 0 ? $trailingBooked / $trailingSize : 0.0,
            trailingSampleSize: $trailingSize,
            consultationsToday: $consultationsToday,
            upcomingConsultations: $upcomingConsultations,
            baseline: $baseline,
            warnings: $warnings,

            marketingDay: $marketingDay,

            unattributedByChannel: $this->unattributedByChannel($bookedLeads),
        );
    }

    /**
     * Leads captured in a window, excluding likely VA applicants.
     *
     * @return Collection<int, Lead>
     */
    /**
     * The window this site's half of the card covers, in UTC.
     *
     * Normally today so far. But the warehouse decides which day the card is about, and before
     * 08:00 Eastern it deliberately reports *yesterday, closed* rather than a handful of hours of
     * today — `IF(hour_et < 8, DATE_SUB(today, INTERVAL 1 DAY), today)` in
     * resources/sql/marketing-home-daily.sql. When it does, this side has to follow it or the two
     * halves of one card describe two different days.
     *
     * That is not hypothetical and it is not subtle. A card fired at 03:52 on 2026-09-20 put the
     * warehouse's closed 19 Sep (97 leads, 63 bookings) beside this site's 3h52m of 20 Sep (7 and
     * 3), and the reconciler correctly shouted that one of them had to be wrong. Neither was. On
     * matched days the two agreed to within 28%. Every figure was real and they were not about
     * the same day.
     *
     * It also made the headline arithmetic meaningless: spend for one day over bookings for
     * another is not a cost per booking.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function reportingWindow(CarbonImmutable $now, ?MarketingDay $day): array
    {
        if ($day === null || ! $day->isClosing() || $day->date === '') {
            return [$now->startOfDay()->utc(), $now->utc()];
        }

        try {
            $start = CarbonImmutable::parse($day->date, $now->timezone)->startOfDay();
        } catch (\Throwable $e) {
            /*
             * An unparseable date is the view changing shape under us. Falling back to today is
             * wrong by a day at worst; guessing at the string is wrong in ways nobody can see.
             */
            Log::warning('FunnelMetricsService: the warehouse reported an unreadable date', [
                'date' => $day->date,
                'error' => $e->getMessage(),
            ]);

            return [$now->startOfDay()->utc(), $now->utc()];
        }

        /*
         * Capped at now, so a warehouse that somehow reports today as CLOSING cannot ask for a
         * window running into the future — that would read as a quiet day rather than an error.
         */
        $end = $start->endOfDay();

        return [$start->utc(), $end->greaterThan($now) ? $now->utc() : $end->utc()];
    }

    private function clientLeadsBetween(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): Collection
    {
        return $this->clientLeads()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->orderByDesc('created_at')
            ->limit(self::MAX_ROWS)
            ->get($this->columns());
    }

    /**
     * Leads whose *first* booking event falls in a window, excluding likely VA applicants.
     *
     * Two queries rather than a join: the grouped `HAVING MIN(created_at)` gives the lead ids
     * that booked in the window, and the second applies the audience filter and fetches the
     * columns classification needs. Joining would mean restating `LeadAudience`'s SQL against an
     * aliased table, which is the drift this codebase has already been bitten by once.
     *
     * @return Collection<int, Lead>
     */
    private function clientLeadsBookedBetween(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): Collection
    {
        $ids = $this->bookedLeadIdsBetween($fromUtc, $toUtc);

        if ($ids === []) {
            return new Collection;
        }

        return $this->clientLeads()
            ->whereIn('id', $ids)
            ->limit(self::MAX_ROWS)
            ->get($this->columns());
    }

    /**
     * Lead ids whose earliest `LeadBookingCompleted` log lands in the window.
     *
     * @return array<int, int>
     */
    private function bookedLeadIdsBetween(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        return LeadActivityLog::query()
            ->where('event_type', 'LeadBookingCompleted')
            ->groupBy('lead_id')
            ->havingRaw('MIN(created_at) >= ?', [$fromUtc->format('Y-m-d H:i:s')])
            ->havingRaw('MIN(created_at) <= ?', [$toUtc->format('Y-m-d H:i:s')])
            ->pluck('lead_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Bucket leads and bookings by platform.
     *
     * Every slug `LeadPlatform` knows about gets a slice, including the empty ones, so the
     * renderer can rely on `direct` and `other` being present when it computes the attribution
     * gap rather than defending against their absence.
     *
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, Lead>  $bookedLeads
     * @return array<string, PlatformSlice>
     */
    private function slice(Collection $leads, Collection $bookedLeads): array
    {
        $tally = [];

        foreach (array_keys(LeadPlatform::options()) as $slug) {
            $tally[$slug] = [
                'leads' => 0, 'bookings' => 0, 'via_click' => 0,
                'qualified' => 0, 'not_proven_paid' => 0, 'qualified_not_proven_paid' => 0,
            ];
        }

        foreach ($leads as $lead) {
            $tally[LeadPlatform::for($lead)]['leads']++;
        }

        foreach ($bookedLeads as $lead) {
            $slug = LeadPlatform::for($lead);

            $tally[$slug]['bookings']++;

            if (LeadPlatform::resolution($lead) === LeadPlatform::BY_CLICK_ID) {
                $tally[$slug]['via_click']++;
            }

            $qualified = LeadQualification::isT10($lead);

            if ($qualified) {
                $tally[$slug]['qualified']++;
            }

            /*
             * Every booking is asked whether it was paid for — not only the ones reached by a
             * click ID.
             *
             * This check was originally gated on `BY_CLICK_ID`, on the theory that `fbclid` was
             * the only way an organic visit could land in a paid platform's row. The live table
             * says otherwise: organic Instagram bio-link traffic arrives tagged
             * `utm_source=instagram`, resolves by UTM, and was going straight into Meta's cost
             * denominator. That population is forty times the click-ID one.
             *
             * Both counters move together. A qualified booking nobody paid for has to come out of
             * the CPQB denominator as well, or cost per *qualified* booking prints lower than cost
             * per booking — which cannot happen, qualified bookings being a subset.
             */
            if (! LeadChannel::isPaid($lead)) {
                $tally[$slug]['not_proven_paid']++;

                if ($qualified) {
                    $tally[$slug]['qualified_not_proven_paid']++;
                }
            }
        }

        $slices = [];

        foreach ($tally as $slug => $counts) {
            $slices[$slug] = new PlatformSlice(
                slug: $slug,
                label: LeadPlatform::label($slug),
                leads: $counts['leads'],
                bookings: $counts['bookings'],
                bookingsViaClickId: $counts['via_click'],
                qualified: $counts['qualified'],

                /*
                 * No spend on a slice any more. These counts are this application's own view of
                 * its leads, kept for the reconciler and the admin widget; the money and the
                 * costs come from the warehouse and are reported from there.
                 */
                spend: null,
                bookingsNotProvenPaid: $counts['not_proven_paid'],
                qualifiedNotProvenPaid: $counts['qualified_not_proven_paid'],
            );
        }

        return $slices;
    }

    /**
     * What the bookings carrying no platform actually were.
     *
     * The single most useful line the legacy alert never had. It reported "21.4% of today's
     * appointments" as an unexplained gap, which reads as broken tracking and gets escalated as
     * one. On the live table it is nothing of the sort: of 395 unattributed booked leads, 392
     * carry HandL first-touch data saying organic, social or direct, and only 6 say paid.
     *
     * Knowing that turns the line from "our attribution is broken" into "a sixth of our bookings
     * are free", which is a completely different conversation and a much better one.
     *
     * @param  Collection<int, Lead>  $bookedLeads
     * @return array<string, int>
     */
    private function unattributedByChannel(Collection $bookedLeads): array
    {
        $counts = [];

        foreach ($bookedLeads as $lead) {
            if (! in_array(LeadPlatform::for($lead), [LeadPlatform::DIRECT, LeadPlatform::OTHER], true)) {
                continue;
            }

            $channel = LeadChannel::for($lead);
            $counts[$channel] = ($counts[$channel] ?? 0) + 1;
        }

        // Fixed order, so the same channel is in the same place every time somebody reads it.
        $ordered = [];

        foreach (LeadChannel::ALL as $channel) {
            if (($counts[$channel] ?? 0) > 0) {
                $ordered[$channel] = $counts[$channel];
            }
        }

        return $ordered;
    }

    /**
     * The HubSpot-qualified count, and how much of the sample it can speak for.
     *
     * Returns a null count when coverage is under the configured floor. That is the expected
     * state today and it is not a bug: `SyncHubSpotLifecycleAction` only polls leads attached to
     * an open referral, so the stage is populated for a slice that is both tiny and
     * self-selected. Printing a confident number off it would be worse than printing none.
     *
     * @param  Collection<int, Lead>  $bookedLeads
     * @return array{0: int|null, 1: float}
     */
    private function hubSpotQualified(Collection $bookedLeads): array
    {
        $total = $bookedLeads->count();

        if ($total === 0) {
            return [null, 0.0];
        }

        $covered = $bookedLeads->filter(
            static fn (Lead $lead): bool => trim((string) $lead->hubspot_lifecycle_stage) !== ''
        )->count();

        $coverage = $covered / $total;

        if ($coverage < (float) config('marketing.qualified.hubspot_min_coverage', 0.5)) {
            return [null, $coverage];
        }

        $qualified = $bookedLeads->filter(
            static fn (Lead $lead): bool => LeadQualification::isHubSpotQualified($lead)
        )->count();

        return [$qualified, $coverage];
    }

    /**
     * Averages over the prior N days, for the "vs average" deltas.
     *
     * Normally same-*hour*, not same day, and that is the whole point. Bookings lag the spend
     * that produced them, so a cost per booking read at 15:00 is structurally different from one
     * read at midnight. Comparing a mid-afternoon figure against a full-day average compares two
     * different things and reliably makes the afternoon look bad.
     *
     * On a closing report the reverse holds and the rule has to invert. The figure being compared
     * is then a complete day, so the baseline has to be complete days too — measuring 111 leads
     * for a finished Saturday against a 7-day average taken at 03:52 gives "+489% vs average",
     * which is the same mistake as the one this method exists to avoid, pointing the other way.
     *
     * @param  CarbonImmutable  $anchor  End of the window being compared, in the report timezone.
     * @param  bool  $wholeDays  Whether to measure each prior day in full.
     * @return array<string, float|null>
     */
    private function baseline(CarbonImmutable $anchor, bool $wholeDays = false): array
    {
        $days = max(1, (int) config('marketing.cost_alert.baseline_days', 7));

        $leads = [];
        $bookings = [];
        $qualified = [];

        for ($back = 1; $back <= $days; $back++) {
            $then = $anchor->subDays($back);
            $fromUtc = $then->startOfDay()->utc();
            $toUtc = ($wholeDays ? $then->endOfDay() : $then)->utc();

            $leads[] = $this->clientLeads()
                ->whereBetween('created_at', [$fromUtc, $toUtc])
                ->count();

            $booked = $this->clientLeadsBookedBetween($fromUtc, $toUtc);

            $bookings[] = $booked->count();
            $qualified[] = $booked->filter(
                static fn (Lead $lead): bool => LeadQualification::isT10($lead)
            )->count();
        }

        return [
            'days' => (float) $days,
            'leads' => $this->mean($leads),
            'bookings' => $this->mean($bookings),
            'qualified' => $this->mean($qualified),
        ];
    }

    /** @param array<int, int> $values */
    private function mean(array $values): ?float
    {
        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * How many of the newest N client leads have booked.
     *
     * Uses `status` rather than the activity log — see the class docblock. The ids are pulled
     * and counted separately because `limit()` and an aggregate do not compose in one query.
     *
     * @return array{0: int, 1: int}
     */
    private function bookingRate(int $sample): array
    {
        $ids = $this->clientLeads()
            ->orderByDesc('created_at')
            ->limit($sample)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return [0, 0];
        }

        $booked = Lead::query()
            ->whereIn('id', $ids)
            ->where('status', 'booked')
            ->count();

        return [$booked, count($ids)];
    }

    private function lastLeadAt(): ?CarbonImmutable
    {
        $lead = $this->clientLeads()->orderByDesc('created_at')->first(['created_at']);

        return $lead?->created_at ? CarbonImmutable::parse($lead->created_at) : null;
    }

    /**
     * When the newest client booking happened, and who it was.
     *
     * The name is what makes the line checkable by a human — "4h 29m ago" is a number nobody can
     * verify, and "Kayla Pinder, 4h 29m ago" is one somebody in the channel either recognises or
     * immediately questions.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function lastBooking(CarbonImmutable $now): array
    {
        $log = LeadActivityLog::query()
            ->where('event_type', 'LeadBookingCompleted')
            ->whereIn('lead_id', $this->clientLeads()->select('id'))
            ->orderByDesc('created_at')
            ->first(['lead_id', 'created_at']);

        if ($log === null) {
            return [null, null];
        }

        $name = Lead::query()->whereKey($log->lead_id)->value('name');

        return [
            $this->minutesSince($now, CarbonImmutable::parse($log->created_at)),
            $name !== null && trim((string) $name) !== '' ? (string) $name : null,
        ];
    }

    private function minutesSince(CarbonImmutable $now, ?CarbonImmutable $then): ?int
    {
        return $then === null ? null : (int) $then->diffInMinutes($now, absolute: true);
    }

    private function countVaLeadsBetween(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): int
    {
        $query = Lead::query()->whereBetween('created_at', [$fromUtc, $toUtc]);

        LeadAudience::constrain($query, 'va');

        return $query->count();
    }

    private function countVaBookingsBetween(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): int
    {
        $ids = $this->bookedLeadIdsBetween($fromUtc, $toUtc);

        if ($ids === []) {
            return 0;
        }

        $query = Lead::query()->whereIn('id', $ids);

        LeadAudience::constrain($query, 'va');

        return $query->count();
    }

    /**
     * Meetings sitting on the calendar for a given local day, across both revenue tiers.
     *
     * Not the same thing as bookings created today, and the legacy alert printed the two under
     * names close enough to be read as one — "Total New Appts: 14" beside "Consultation
     * Appointments Today: 97". This is the calendar's load; that is the day's production.
     *
     * Null on any failure, and null is rendered as "unavailable" rather than as zero. Calendly
     * is a network call inside a scheduled job, and an empty calendar and an unreachable one
     * must not read the same in a channel where an empty calendar means a revenue stop.
     */
    private function consultationsOn(CarbonImmutable $day): ?int
    {
        if ($this->calendly === null || $this->roles === null) {
            return null;
        }

        $fromIso = $day->startOfDay()->utc()->toIso8601String();
        $toIso = $day->endOfDay()->utc()->toIso8601String();

        $total = 0;
        $answered = 0;
        $asked = 0;

        foreach (['t0', 't10'] as $role) {
            try {
                $uri = (string) $this->roles->get($role);

                if ($uri === '') {
                    continue;
                }

                $asked++;
                $count = $this->calendly->countBookedEvents($uri, $fromIso, $toIso);

                if ($count !== null) {
                    $total += $count;
                    $answered++;
                }
            } catch (\Throwable $e) {
                Log::warning('FunnelMetricsService: Calendly consultation count failed', [
                    'role' => $role,
                    'day' => $day->toDateString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        /*
         * Every tier has to answer, or the figure is withheld.
         *
         * Summing whichever tiers happened to reply and presenting it as the calendar's load is
         * the same mistake AdSpendCollector exists to prevent one domain over: T10 answers with 4
         * while T0's token pool is exhausted, and the card prints "4 consultations today" with no
         * caveat when the real number is 4 plus every T0 meeting. A partial total is worse than
         * none, because none gets chased.
         */
        return $asked > 0 && $answered === $asked ? $total : null;
    }

    /**
     * Calendar load for the next business days, labelled by date.
     *
     * Two of them, which is what a Friday afternoon needs: "next business day" on a Friday means
     * Monday, and the day after it is Tuesday, and neither is obvious from a card that only says
     * "next business day". Naming them removes the arithmetic from the reader.
     *
     * Each day costs two Calendly round trips, so this takes the snapshot from four to six. That
     * is paid by the hourly job rather than by a page load — the dashboard widget reads the
     * cached copy the job warms, which is why {@see self::cachedSnapshot()} never computes.
     *
     * @return array<string, int|null>
     */
    private function upcomingConsultations(CarbonImmutable $now): array
    {
        $counts = [];

        foreach ($this->nextBusinessDays($now, 2) as $day) {
            // "Monday 21st". The year is not in it: nobody reading a daily card needs it.
            $counts[$day->format('l jS')] = $this->consultationsOn($day);
        }

        return $counts;
    }

    /**
     * The next `$count` days that are not weekends. Public holidays are not modelled, so a bank
     * holiday shows as a quiet day rather than being skipped.
     *
     * @return array<int, CarbonImmutable>
     */
    private function nextBusinessDays(CarbonImmutable $now, int $count): array
    {
        $days = [];
        $day = $now;

        while (count($days) < $count) {
            $day = $day->addDay();

            if (! $day->isWeekend()) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * Share of the local calendar day elapsed, for pacing partial-day spend.
     *
     * Measured against this day's real length rather than a hardcoded 86,400. On the two days a
     * year the clocks move, a fixed divisor puts the figure out by about 4% — and on the
     * fall-back day it reaches 100% an hour before midnight, which makes a card sent at 23:00
     * claim the day is over.
     */
    private function dayElapsed(CarbonImmutable $now): float
    {
        $start = $now->startOfDay();
        $length = $start->diffInSeconds($start->addDay(), absolute: true);

        return $length > 0
            ? min(1.0, $start->diffInSeconds($now, absolute: true) / $length)
            : 0.0;
    }

    /**
     * The base query: every lead the offer is actually sold to.
     *
     * @return Builder
     */
    private function clientLeads()
    {
        $query = Lead::query();

        LeadAudience::constrain($query, 'clients');

        return $query;
    }

    /**
     * The columns classification needs, and no more.
     *
     * Named explicitly because this pulls rows into PHP. `notes` and `landing_url` are the large
     * columns that are deliberately absent; `attribution` and `referrer_url` are large too but
     * `LeadChannel` reads both, so they are the price of knowing whether a booking was paid for.
     *
     * @return array<int, string>
     */
    private function columns(): array
    {
        return [
            'id',
            'name',
            'created_at',
            'status',
            'utm_source',

            // LeadChannel's primary signal. Omitting it does not fail loudly — every booking
            // simply reads as not-proven-paid and every cost denominator collapses to zero.
            'utm_medium',
            'gclid',
            'fbclid',
            'msclkid',
            'li_fat_id',
            'monthly_revenue',
            'hubspot_lifecycle_stage',

            // Read by LeadChannel. `attribution` is the large one, and the only place the
            // paid/organic distinction exists for a lead that arrived with no UTM.
            'attribution',
            'referrer_url',
        ];
    }
}
