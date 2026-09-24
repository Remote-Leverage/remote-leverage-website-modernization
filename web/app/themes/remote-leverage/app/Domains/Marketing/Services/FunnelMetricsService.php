<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Services;

use App\Domains\Lead\Data\LeadAudience;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadBookings;
use App\Domains\Lead\Services\LeadChannel;
use App\Domains\Lead\Services\LeadPlatform;
use App\Domains\Lead\Services\LeadQualification;
use App\Domains\Marketing\Data\Finding;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Data\PlatformSlice;
use App\Domains\Marketing\Gateways\BigQueryClient;
use App\Domains\Marketing\Support\AlertWindow;
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
        private readonly ?AlertReconciler $reconciler = null,
        private readonly ?BigQueryClient $warehouse = null,
        private readonly ?FindingDismissals $dismissals = null,
    ) {}

    /** Where the dashboard widget's copy of the snapshot lives. */
    public const CACHE_KEY = 'rl_marketing_funnel_snapshot';

    /** How long a warmed snapshot stays readable. Comfortably longer than the hourly refresh. */
    public const CACHE_TTL = 10800;

    /**
     * The last warmed snapshot, or null when there is not one yet.
     *
     * **This never computes.** That is the whole point of it. {@see self::snapshot()} runs about
     * twenty-five queries and two or three warehouse round trips; it is the right cost for an
     * hourly job and a wholly unacceptable one for a wp-admin widget that renders on every
     * dashboard load.
     *
     * It used to compute on a miss, via `Cache::remember()`. Two things made that worse than it
     * looks. The cache never actually hit — see {@see FunnelSnapshot::toArray()} for why — so
     * "on a miss" meant "every time"; and the work it fell back to was dominated by Calendly,
     * measured between 1.1 and 10.0 seconds *for the same call*. The admin dashboard therefore
     * took 9 to 16 seconds to finish loading, unpredictably, which is exactly how it was
     * reported: slow "sometimes". The Calendly calls are gone — the consultation counts come from
     * the warehouse now — but the read path stays pure regardless: the remaining work is still
     * twenty-five queries and a warehouse the widget has no business waiting on.
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
     * runs, and recomputing would mean another round of warehouse queries for figures that are
     * seconds old.
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
         * This method is 25-odd queries and two or three warehouse round trips. A caller watching
         * it needs to know which of those it is waiting on — "Reading the marketing warehouse"
         * and "Counting leads and bookings" fail for entirely different reasons and are fixed by
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
         * Read first, because everything below depends on which day it says it is reporting: before
         * 08:00 Eastern it reports the previous day closed, and the card then goes and fetches
         * today separately.
         */
        $marketingDay = ($this->warehouse ?? new BigQueryClient)->marketingDay();

        /*
         * On an overnight closing report the headline is yesterday, finished — which says nothing
         * about the hours since midnight. The alert this replaced showed those hours around the
         * clock, and people read the overnight cards to see whether the morning had started, so
         * the figures are fetched and printed beneath the closing block.
         *
         * Only then. From 08:00 the headline is already today and a second copy of it would be
         * the same numbers twice.
         */
        /*
         * Freshness and the funnel above the lead, for the day just reported. Asked for by date so
         * it can never describe a different day from the headline.
         */
        $todaySoFar = $marketingDay?->isClosing() === true
            ? ($this->warehouse ?? new BigQueryClient)->todaySoFar()
            : null;

        /*
         * Freshness, staleness and reach for the day the card is *about* — today whenever there is
         * a today-so-far row, the reported day otherwise. Asking for the reported day overnight
         * would put yesterday's impressions under a headline describing this morning.
         */
        $subjectDate = $todaySoFar?->date ?? $marketingDay?->date ?? '';

        $supplement = $subjectDate !== ''
            ? ($this->warehouse ?? new BigQueryClient)->supplement($subjectDate)
            : null;

        /*
         * Each channel's counts, onto the day they belong to.
         *
         * The data team's query publishes a channel's spend and costs but not how many bookings
         * those costs were divided by, so the supplement sums them from the same view. Overnight
         * the closed day needs its own read — the supplement above is about this morning — which
         * is one extra query for the hours the card carries two days.
         */
        if ($marketingDay?->isClosing() === true) {
            $marketingDay = $marketingDay->withChannelCounts(
                ($this->warehouse ?? new BigQueryClient)->supplement($marketingDay->date),
            );
            $todaySoFar = $todaySoFar?->withChannelCounts($supplement);
        } else {
            $marketingDay = $marketingDay?->withChannelCounts($supplement);
        }

        /*
                 * Today, at every hour.
                 *
                 * This followed the warehouse's reported day for a while, so that an overnight card whose
                 * headline was yesterday-closed had matching funnel figures. The card is now about today
                 * at every hour — the closed day survives as a single reference line — so the two are
                 * aligned again by both being today, which is also what they were before any of this.
                 */
        $dayStart = $now->startOfDay();
        $fromUtc = $dayStart->utc();
        $toUtc = $now->utc();

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
         * Hoisted out of the constructor call below so it can be announced before it runs. This
         * used to be six Calendly round trips and the slowest thing here by an order of
         * magnitude; it is now one warehouse query covering all three days at once.
         */
        $step('Reading the consultation calendar load', 70);

        [$consultationsToday, $upcomingConsultations] = $this->consultationLoad($now);

        $step('Averaging the previous days', 85);

        $baseline = $this->baseline($now);

        $platformBookings = array_sum(array_map(
            static fn (PlatformSlice $slice): int => $slice->bookings,
            $platforms,
        ));

        $found = ($this->reconciler ?? new AlertReconciler)->findings([
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
            'warehouse_bookings' => $todaySoFar?->appointments ?? $marketingDay?->appointments,

            /*
             * Bookings this site confirmed and no provider ever issued a meeting for. Read off
             * our own activity log rather than against the warehouse, for the reason in
             * {@see self::bookingsWithoutMeeting()}: "no RecruitCRM deal" is mostly people who
             * are fine, and "no meeting identifier" is nobody who is.
             */
            'bookings_without_meeting' => $this->bookingsWithoutMeeting($fromUtc, $toUtc),

            /*
             * How old the warehouse's own copy is, and which ad platforms were still catching up
             * when it was taken. Both are about whether the figures above can be trusted, which is
             * the reconciler's entire job.
             */
            'warehouse_age_minutes' => $supplement?->ageInMinutes($now),
            'stale_platforms' => $supplement?->stalePlatforms() ?? [],
            'spend_pending' => $supplement?->spendPending ?? false,

            /*
             * Which day both halves now cover, so the reconciler can name it instead of saying
             * "today" about a card that, before 08:00 Eastern, is about yesterday.
             */
            'report_date' => $subjectDate,
            'report_is_closing' => false,
            'warehouse_unavailable' => $marketingDay === null,
        ]);

        /*
         * Findings somebody has already dealt with drop out here, once, so the alert and the
         * dashboard cannot disagree about what is outstanding. The dismissed ones travel on to
         * the widget, which shows them muted — dropping them entirely would make a dismissal
         * unreviewable and unundoable.
         */
        $partitioned = ($this->dismissals ?? new FindingDismissals)->partition($found, $now);

        $warnings = array_map(
            static fn (Finding $finding): string => $finding->text,
            $partitioned['live'],
        );

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
            findings: array_map(
                static fn (Finding $finding): array => [
                    'key' => $finding->key,
                    'text' => $finding->text,
                    'permanent' => $finding->permanent,
                ],
                $partitioned['live'],
            ),
            dismissedFindings: $partitioned['dismissed'],

            marketingDay: $marketingDay,
            todaySoFar: $todaySoFar,
            supplement: $supplement,

            unattributedByChannel: $this->unattributedByChannel($bookedLeads),
            bookingsByLandingPage: $this->bookingsBy($bookedLeads, fn (Lead $lead): string => $this->landingPath($lead)),
            bookingsByCampaign: $this->bookingsBy(
                $bookedLeads,
                static fn (Lead $lead): string => trim((string) $lead->utm_campaign) ?: '(no campaign)',
            ),
        );
    }

    /**
     * Leads captured in a window, excluding likely VA applicants.
     *
     * @return Collection<int, Lead>
     */
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
     * The definition lives in {@see LeadBookings} because the data API reports bookings too, and
     * the data team reconciles its numbers against this alert's.
     *
     * @return array<int, int>
     */
    private function bookedLeadIdsBetween(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        return array_keys(LeadBookings::firstBookedBetween($fromUtc, $toUtc));
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
     * Today's bookings and qualified bookings, grouped by whatever `$key` says, busiest first.
     *
     * Every booking, paid or not: the question this answers is which pages and campaigns the
     * bookings came through, and dropping the organic ones would make a page look quieter than it
     * is. Qualified is this site's own definition, the same one the rest of the card names.
     *
     * @param  Collection<int, Lead>  $bookedLeads
     * @param  callable(Lead): string  $key
     * @return array<string, array{bookings: int, qualified: int}>
     */
    private function bookingsBy(Collection $bookedLeads, callable $key): array
    {
        $groups = [];

        foreach ($bookedLeads as $lead) {
            $group = $key($lead);

            $groups[$group] ??= ['bookings' => 0, 'qualified' => 0];
            $groups[$group]['bookings']++;

            if (LeadQualification::isT10($lead)) {
                $groups[$group]['qualified']++;
            }
        }

        uksort($groups, static fn (string $a, string $b): int => [$groups[$b]['bookings'], $groups[$b]['qualified'], $a]
            <=> [$groups[$a]['bookings'], $groups[$a]['qualified'], $b]);

        return $groups;
    }

    /**
     * The path a lead landed on, without host or query string.
     *
     * The query string is mostly UTM and click IDs, which would give every paid visit its own
     * row. The host is dropped because the apex and `www.` are the same page.
     */
    private function landingPath(Lead $lead): string
    {
        $url = trim((string) $lead->landing_url);

        if ($url === '') {
            return '(not recorded)';
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : $path.'/';
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
     * The card reports today at every hour, so the comparison is always same-hour. It briefly
     * took whole days as well, for the period when an overnight card's headline was a finished
     * day; that is now a single reference line with no average beside it.
     *
     * @param  CarbonImmutable  $anchor  End of the window being compared, in the report timezone.
     * @return array<string, float|null>
     */
    private function baseline(CarbonImmutable $anchor): array
    {
        $days = max(1, (int) config('marketing.cost_alert.baseline_days', 7));

        $leads = [];
        $bookings = [];
        $qualified = [];

        for ($back = 1; $back <= $days; $back++) {
            $then = $anchor->subDays($back);
            $fromUtc = $then->startOfDay()->utc();
            $toUtc = $then->utc();

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

    /**
     * Bookings this site confirmed that no provider ever issued a meeting for.
     *
     * ## Why this is read off our own log rather than against the warehouse
     *
     * The obvious version of this question is "which of today's bookings has no RecruitCRM
     * deal", and it is the wrong one: three of the four reasons a booking legitimately has no
     * deal are ordinary — a repeat booker reuses the deal opened weeks ago, two lead rows for
     * one person share a deal, internal bookings never reach the CRM. A list built that way is
     * mostly people who are fine, which is how a warning stops being read.
     *
     * A booking with no meeting *identifier* is not ambiguous. Every real one carries something
     * the provider issued — a `https://api.calendly.com/...` invitee uri, or a Google event id —
     * and it is what anybody would use to find, move or cancel the meeting. When that is absent
     * there is nothing on any calendar, whatever the lead's status says.
     *
     * ## The prefixes
     *
     * These are the identifiers this application minted for itself when a provider gave it
     * nothing, each from a `uniqid()` call site in `BookMeetingAction` or `RouteInstantCallAction`.
     * A lead carrying one was told a consultant was expecting them. Five did between 19 and 21
     * September; the last, at 10:54 ET on the 21st, is the reason this exists.
     *
     * They are matched rather than removed from history because the rows are already written —
     * and because a list keyed on the fabrication is exact, where one keyed on "no deal" is not.
     *
     * @return array<int, string> Lead name keyed by lead id, newest booking first. Keyed because
     *                            a dismissal is recorded per lead — see Finding.
     */
    private function bookingsWithoutMeeting(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        $ids = $this->bookedLeadIdsBetween($fromUtc, $toUtc);

        if ($ids === []) {
            return [];
        }

        /*
         * Exactly the three rows that can carry a meeting: the wizard's first attempt and the
         * retry ladder both log `LeadCreated`/Scheduling, and an instant call logs
         * `InstantLiveCall`/Scheduling. `LeadBookingCompleted` rows are written by the Slack,
         * webhook and referral listeners and never hold a payload, so matching on actor alone
         * would read every booking as meetingless.
         *
         * Taking the newest per lead is what keeps a landed retry honest: the first attempt's
         * failure is a separate row, and the attempt that succeeded carries the real id.
         *
         * A lead with none of these rows is deliberately not flagged. That is a booking made at
         * Calendly directly and reported back by webhook — the meeting demonstrably exists,
         * because Calendly is the one that told us about it.
         */
        $newest = LeadActivityLog::query()
            ->where('actor_domain', 'Scheduling')
            ->where('stage', 'consumption')
            ->where('outcome', 'succeeded')
            ->whereIn('event_type', ['LeadCreated', 'InstantLiveCall'])
            ->whereIn('lead_id', $ids)
            ->orderByDesc('created_at')
            ->get(['lead_id', 'payload'])
            ->unique('lead_id');

        $unverified = $newest
            ->filter(static function (LeadActivityLog $log): bool {
                $payload = $log->payload ?? [];

                /*
                 * Two keys, because the two paths that book never agreed on one. The wizard and
                 * the retry ladder write `meeting_id`; `RouteInstantCallAction` writes
                 * `invitee_uri` and no `meeting_id` at all. Reading only the first would report
                 * every instant live call as a booking with no meeting — a warning that fires on
                 * the healthy case, which is the exact failure this class is meant to avoid.
                 */
                $meetingId = trim((string) ($payload['meeting_id'] ?? $payload['invitee_uri'] ?? ''));

                if ($meetingId === '') {
                    return true;
                }

                // Every `uniqid()` that has ever reached a `meeting_id`. A real Calendly
                // identifier is a `https://api.calendly.com/...` uri, so none of these can
                // collide with one.
                foreach (['gcal_', 'cal_', 'cal_dedup_', 'live_'] as $minted) {
                    if (str_starts_with($meetingId, $minted)) {
                        return true;
                    }
                }

                return $meetingId === 'deduplicated';
            })
            ->pluck('lead_id')
            ->all();

        if ($unverified === []) {
            return [];
        }

        return Lead::query()
            ->whereIn('id', $unverified)
            ->orderByDesc('created_at')
            ->pluck('name', 'id')
            ->map(static fn ($name): string => trim((string) $name))
            ->filter(static fn (string $name): bool => $name !== '')
            ->all();
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
     * Calendar load for today and the next two business days, in one warehouse query.
     *
     * Not the same thing as bookings created today, and the legacy alert printed the two under
     * names close enough to be read as one — "Total New Appts: 14" beside "Consultation
     * Appointments Today: 97". This is the calendar's load; that is the day's production.
     *
     * ## Why this is not Calendly any more
     *
     * It was, until 2026-09-20: `CalendlyClient::countBookedEvents()`, twice per day, over the
     * event-type URIs in the `t0` and `t10` roles. The account has ten active event types named a
     * VA Hiring Consultation — the two automated `(A)` tiers the booking wizard writes into, a
     * shared manual `(M)` type, and one per sales rep — so the card counted two of ten and
     * reported roughly three quarters of the calendar as the whole of it. On 2026-09-21 it said 80
     * against a true 112, and sales read the gap as the calendar emptying out and moved to switch
     * ads off. The failure was silent because every part of it worked: both tiers answered, the
     * total was internally consistent, and nothing in the count knew the other eight types existed.
     *
     * Widening the role list would have fixed that day and not the next one — a rep's event type
     * is created in Calendly, not here, so the list is wrong again the moment somebody is hired.
     * `gold.sales_consultation_meetings` matches on the event *name* across every type, drops
     * cancelled and rescheduled-away meetings, and unions in the consultations that only ever
     * existed in Google Calendar. See resources/sql/consultation-calendar-load.sql.
     *
     * ## One query, three days
     *
     * The Calendly version cost two round trips per day and six per snapshot, and was the slowest
     * thing in it by an order of magnitude — measured between 1.1 and 10.0 seconds for the same
     * call. This is one query for all three days, about 1.5 seconds warm, and it takes the page
     * cap, the token pool and the time budget out of this path entirely.
     *
     * ## Missing means unavailable; zero means zero
     *
     * The query LEFT JOINs the days it was asked about, so a day the warehouse answered for is
     * present even when nothing is booked on it. A key that is absent therefore means the
     * warehouse could not be read, and is rendered as "unavailable" rather than as zero — an
     * empty calendar and an unreadable one must not read the same in a channel where an empty
     * calendar means a revenue stop. Today legitimately reads 0 at weekends.
     *
     * Resolved per day rather than all-or-nothing, which is the opposite of what the Calendly
     * version did. There it was right: the two tiers were *summed*, so a missing tier made the
     * total silently wrong. These are three independent figures printed side by side, so one
     * unreadable day costs that day and not the other two.
     *
     * @return array{0: int|null, 1: array<string, int|null>} Today, then "Monday 21st" => count.
     */
    private function consultationLoad(CarbonImmutable $now): array
    {
        $upcoming = $this->nextBusinessDays($now, 2);

        $load = ($this->warehouse ?? new BigQueryClient)->consultationLoad(array_map(
            static fn (CarbonImmutable $day): string => $day->toDateString(),
            array_merge([$now], $upcoming),
        )) ?? [];

        $labelled = [];

        foreach ($upcoming as $day) {
            // "Monday 21st". The year is not in it: nobody reading a daily card needs it.
            $labelled[$day->format('l jS')] = $load[$day->toDateString()] ?? null;
        }

        return [$load[$now->toDateString()] ?? null, $labelled];
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
     * Named explicitly because this pulls rows into PHP. `notes` is the large column that is
     * deliberately absent; `attribution`, `referrer_url` and `landing_url` are large too but
     * `LeadChannel` reads the first two and the landing-page breakdown the third.
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

            // The per-landing-page and per-campaign breakdown.
            'landing_url',
            'utm_campaign',
        ];
    }
}
