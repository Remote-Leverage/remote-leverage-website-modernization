<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

use Carbon\CarbonImmutable;

/**
 * Everything the cost alert knows at one moment, already reconciled.
 *
 * ## Paid and blended are both here on purpose
 *
 * The legacy alert divided total spend by *all* bookings — including the ones carrying no
 * platform at all — while computing each platform's cost per booking over only the bookings that
 * platform could be credited with. Two different metrics printed as if they were one. On the day
 * this was rebuilt from, that made the headline cost per booking read $266.59 when the
 * paid-attributed figure was $339.29, a 27% understatement, and the message had nothing on it to
 * say so.
 *
 * So both are carried, and the renderer prints both. The gap between them is the size of the
 * attribution debt, and naming it is the only thing that ever gets it paid down.
 *
 * ## VA applicants are excluded
 *
 * People looking for VA work fill in the client intake form regularly. They are not customers,
 * they must not sit in the denominator of a cost per booking, and {@see self::$excludedVaLeads}
 * reports how many were removed so the exclusion stays auditable rather than invisible.
 *
 * The judgement comes from `LeadAudience`, whose own docblock calls it "a prompt, not a verdict"
 * — it reads a phone country, and a genuine client dialling in from outside the US and Canada
 * trips the same rule. This alert is the first thing in the codebase to *act* on that judgement
 * rather than merely display it, and the direction of the error matters: wrongly excluding a
 * real client removes a booking from the denominator and makes cost per booking look worse than
 * it is. That is the safe direction, and the count is printed so a number that looks wrong can
 * be checked.
 */
readonly class FunnelSnapshot
{
    /**
     * @param  array<string, PlatformSlice>  $platforms  Keyed by platform slug, in dropdown order.
     * @param  array<string, float|null>  $baseline  Same-hour averages over the prior N days.
     * @param  array<int, string>  $warnings  Reconciliation findings, empty when everything ties up.
     * @param  array<string, int>  $unattributedByChannel  LeadChannel slug => bookings, highest first.
     * @param  array<string, int|null>  $upcomingConsultations  "Monday 21st" => meetings, in date order.
     */
    public function __construct(
        public CarbonImmutable $generatedAt,
        public string $timezone,
        public string $currency,
        public float $dayElapsed,
        public int $leads,
        public int $bookings,
        public int $qualifiedT10,
        public ?int $qualifiedHubSpot,
        public float $hubSpotCoverage,
        public array $platforms,
        public int $excludedVaLeads,
        public int $excludedVaBookings,
        public ?int $lastLeadMinutes,
        public ?int $lastBookingMinutes,
        public ?string $lastBookingName,
        public int $recentSampleBooked,
        public int $recentSampleSize,
        public float $trailingBookingRate,
        public int $trailingSampleSize,
        public ?int $consultationsToday,

        /*
         * The next business days, in order, as `"Monday 21st" => count`. Labelled rather than
         * counted off, because "next business day" is a phrase the reader has to resolve against
         * today's date and a weekend, and on a Friday afternoon they will resolve it wrong.
         */
        public array $upcomingConsultations,
        public array $baseline,
        public array $warnings,

        /**
         * The same live findings, each with the key a dismissal is recorded against.
         *
         * Parallel to `$warnings` rather than replacing it: the card and every assertion about
         * wording want the sentences, and only the dashboard needs something to hang a button on.
         *
         * @var array<int, array{key: string, text: string}>
         */
        public array $findings = [],

        /**
         * Findings somebody has already dealt with, carried so the dashboard can show them muted
         * with an undo. They are computed exactly like the live ones and then set aside; a
         * dismissal that removed them outright would be unreviewable.
         *
         * @var array<int, array<string, mixed>>
         */
        public array $dismissedFindings = [],
        public array $unattributedByChannel = [],

        /*
         * The data team's marketing day: spend, channel attribution, and the booking and lead
         * counts that divide into them. Null when the warehouse could not be read, which makes the
         * cost half of the card unavailable rather than wrong.
         */
        public ?MarketingDay $marketingDay = null,

        /**
         * Today's running totals, set only on an overnight closing report — see
         * FunnelMetricsService::snapshot(). Null the rest of the day, when the headline figures
         * are already today's and a second set of them would be the same numbers twice.
         */
        public ?PartialDay $todaySoFar = null,

        /** Freshness, per-platform staleness and the funnel above the lead. Null when unreadable. */
        public ?DaySupplement $supplement = null,

        /**
         * Today's bookings from this site's own records, grouped by the page the lead landed on
         * and by `utm_campaign`, busiest first. The warehouse is by channel and day only, so this
         * is the one place either breakdown exists.
         *
         * @var array<string, array{bookings: int, qualified: int}>
         */
        public array $bookingsByLandingPage = [],

        /** @var array<string, array{bookings: int, qualified: int}> */
        public array $bookingsByCampaign = [],
    ) {}

    /**
     * Platform slices worth printing: the real platforms, in order, that saw anything today.
     *
     * `direct` and `other` are deliberately not among them — they are reported once, together,
     * as the attribution gap, rather than as two more rows that look like ad platforms.
     *
     * @return array<int, PlatformSlice>
     */
    public function reportablePlatforms(): array
    {
        return array_values(array_filter(
            $this->platforms,
            static fn (PlatformSlice $slice): bool => ! in_array($slice->slug, ['direct', 'other'], true)
                && ($slice->leads > 0 || $slice->bookings > 0),
        ));
    }

    /**
     * The scalar form this snapshot is cached as.
     *
     * Deliberately not `serialize()`. Laravel unserializes every cache payload with
     * `allowed_classes` taken from `cache.serializable_classes`, which Acorn ships as `false` to
     * close off gadget chains if `APP_KEY` ever leaks. That applies to *every* store — file,
     * database and Redis alike — so an object written to the cache comes back as
     * `__PHP_Incomplete_Class`, and the `: FunnelSnapshot` return type on the old
     * `cachedSnapshot()` turned that into a TypeError its own catch swallowed.
     *
     * The effect was silent and expensive: the cache never once hit, and every wp-admin dashboard
     * load recomputed the snapshot — measured at 9 to 16 seconds, almost all of it the two
     * Calendly calls in {@see FunnelMetricsService::consultationsOn()}, whose latency varied
     * tenfold between runs. The only trace was a `snapshot cache unavailable` warning in the log.
     *
     * Caching scalars keeps that hardening at its safe default rather than opening an allow-list
     * that a future nested DTO would quietly fall out of, re-introducing the same silent bug.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt->toIso8601String(),
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'day_elapsed' => $this->dayElapsed,
            'leads' => $this->leads,
            'bookings' => $this->bookings,
            'qualified_t10' => $this->qualifiedT10,
            'qualified_hub_spot' => $this->qualifiedHubSpot,
            'hub_spot_coverage' => $this->hubSpotCoverage,
            'platforms' => array_map(
                static fn (PlatformSlice $slice): array => $slice->toArray(),
                $this->platforms,
            ),
            'excluded_va_leads' => $this->excludedVaLeads,
            'excluded_va_bookings' => $this->excludedVaBookings,
            'last_lead_minutes' => $this->lastLeadMinutes,
            'last_booking_minutes' => $this->lastBookingMinutes,
            'last_booking_name' => $this->lastBookingName,
            'recent_sample_booked' => $this->recentSampleBooked,
            'recent_sample_size' => $this->recentSampleSize,
            'trailing_booking_rate' => $this->trailingBookingRate,
            'trailing_sample_size' => $this->trailingSampleSize,
            'consultations_today' => $this->consultationsToday,
            'upcoming_consultations' => $this->upcomingConsultations,
            'marketing_day' => $this->marketingDay?->toRow(),
            'today_so_far' => $this->todaySoFar?->toArray(),
            'supplement' => $this->supplement?->toArray(),
            'baseline' => $this->baseline,
            'warnings' => $this->warnings,
            'findings' => $this->findings,
            'dismissed_findings' => $this->dismissedFindings,
            'unattributed_by_channel' => $this->unattributedByChannel,
            'bookings_by_landing_page' => $this->bookingsByLandingPage,
            'bookings_by_campaign' => $this->bookingsByCampaign,
        ];
    }

    /**
     * Rebuild a snapshot from {@see self::toArray()}.
     *
     * `platforms` is rebuilt keyed by slug rather than through `array_map`, because the key order
     * *is* the dropdown order the renderer prints in and a reindex would silently reorder the
     * card.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $timezone = (string) ($data['timezone'] ?? 'UTC');

        $platforms = [];

        foreach ((array) ($data['platforms'] ?? []) as $slug => $slice) {
            $platforms[$slug] = PlatformSlice::fromArray((array) $slice);
        }

        return new self(
            generatedAt: CarbonImmutable::parse((string) $data['generated_at'])->setTimezone($timezone),
            timezone: $timezone,
            currency: (string) ($data['currency'] ?? 'USD'),
            dayElapsed: (float) ($data['day_elapsed'] ?? 0.0),
            leads: (int) ($data['leads'] ?? 0),
            bookings: (int) ($data['bookings'] ?? 0),
            qualifiedT10: (int) ($data['qualified_t10'] ?? 0),
            qualifiedHubSpot: isset($data['qualified_hub_spot']) ? (int) $data['qualified_hub_spot'] : null,
            hubSpotCoverage: (float) ($data['hub_spot_coverage'] ?? 0.0),
            platforms: $platforms,
            excludedVaLeads: (int) ($data['excluded_va_leads'] ?? 0),
            excludedVaBookings: (int) ($data['excluded_va_bookings'] ?? 0),
            lastLeadMinutes: isset($data['last_lead_minutes']) ? (int) $data['last_lead_minutes'] : null,
            lastBookingMinutes: isset($data['last_booking_minutes']) ? (int) $data['last_booking_minutes'] : null,
            lastBookingName: isset($data['last_booking_name']) ? (string) $data['last_booking_name'] : null,
            recentSampleBooked: (int) ($data['recent_sample_booked'] ?? 0),
            recentSampleSize: (int) ($data['recent_sample_size'] ?? 0),
            trailingBookingRate: (float) ($data['trailing_booking_rate'] ?? 0.0),
            trailingSampleSize: (int) ($data['trailing_sample_size'] ?? 0),
            consultationsToday: isset($data['consultations_today']) ? (int) $data['consultations_today'] : null,
            upcomingConsultations: (array) ($data['upcoming_consultations'] ?? []),
            marketingDay: is_array($data['marketing_day'] ?? null)
                ? MarketingDay::fromRow($data['marketing_day'])
                : null,
            todaySoFar: is_array($data['today_so_far'] ?? null)
                ? PartialDay::fromRow($data['today_so_far'])
                : null,
            supplement: is_array($data['supplement'] ?? null)
                ? DaySupplement::fromRow($data['supplement'])
                : null,
            baseline: (array) ($data['baseline'] ?? []),
            warnings: (array) ($data['warnings'] ?? []),
            findings: (array) ($data['findings'] ?? []),
            dismissedFindings: (array) ($data['dismissed_findings'] ?? []),
            unattributedByChannel: (array) ($data['unattributed_by_channel'] ?? []),
            bookingsByLandingPage: (array) ($data['bookings_by_landing_page'] ?? []),
            bookingsByCampaign: (array) ($data['bookings_by_campaign'] ?? []),
        );
    }
}
