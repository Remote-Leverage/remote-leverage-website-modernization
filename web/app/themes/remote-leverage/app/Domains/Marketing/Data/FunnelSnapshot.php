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
     * @param  array<string, string>  $accountIssues  Platform slug => what the platform says is wrong.
     * @param  array<string, string>  $spendUnreachable  Platform slug => why it could not be read.
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
        public ?float $spend = null,
        public array $unattributedByChannel = [],
        public array $accountIssues = [],
        public array $spendUnreachable = [],
    ) {}

    /** Bookings this alert can name a platform for. */
    public function attributedBookings(): int
    {
        return $this->bookings - $this->unattributedBookings();
    }

    /**
     * Bookings carrying neither a recognised `utm_source` nor a click ID.
     *
     * `direct` and `other` are the two buckets `LeadPlatform` puts those in, and because the
     * buckets partition the table these two plus the platforms always sum to the total — which
     * is what lets the message add them up in front of the reader without the rows disagreeing.
     */
    public function unattributedBookings(): int
    {
        return ($this->platforms['direct']->bookings ?? 0)
            + ($this->platforms['other']->bookings ?? 0);
    }

    /** Unattributed bookings as a share of all of them, 0 when nothing booked. */
    public function unattributedShare(): float
    {
        return $this->bookings > 0 ? $this->unattributedBookings() / $this->bookings : 0.0;
    }

    /**
     * Bookings a platform can be named for *and* shown to have paid for.
     *
     * The denominator every cost figure divides by. Distinct from
     * {@see self::attributedBookings()}, which answers the platform question and legitimately
     * includes organic traffic from a platform that also sells ads.
     */
    public function paidBookings(): int
    {
        $paid = 0;

        foreach ($this->platforms as $slice) {
            if (in_array($slice->slug, ['direct', 'other'], true)) {
                continue;
            }

            $paid += $slice->paidBookings();
        }

        return $paid;
    }

    /** Spend over the bookings we can show were paid for. The honest figure. */
    public function paidCpb(): ?float
    {
        return $this->spend !== null && $this->paidBookings() > 0
            ? $this->spend / $this->paidBookings()
            : null;
    }

    /** Spend over every booking, attributed or not. What the legacy alert printed alone. */
    public function blendedCpb(): ?float
    {
        return $this->spend !== null && $this->bookings > 0
            ? $this->spend / $this->bookings
            : null;
    }

    /**
     * Qualified bookings a platform can be named for *and* shown to have paid for.
     *
     * The mirror of {@see self::paidBookings()}, and it has to be, because qualified bookings are
     * a subset of bookings. Dividing spend by the full qualified count while dividing it by the
     * reduced booking count printed a cost per qualified booking *below* the cost per booking —
     * an impossibility, on a card whose entire value is being trustworthy.
     */
    public function paidQualified(): int
    {
        $paid = 0;

        foreach ($this->platforms as $slice) {
            if (in_array($slice->slug, ['direct', 'other'], true)) {
                continue;
            }

            $paid += $slice->paidQualified();
        }

        return $paid;
    }

    /** The qualified equivalent of {@see self::paidCpb()}, on the T10 definition. */
    public function paidCpqb(): ?float
    {
        return $this->spend !== null && $this->paidQualified() > 0
            ? $this->spend / $this->paidQualified()
            : null;
    }

    /** The qualified equivalent of {@see self::blendedCpb()}, on the T10 definition. */
    public function blendedCpqb(): ?float
    {
        return $this->spend !== null && $this->qualifiedT10 > 0
            ? $this->spend / $this->qualifiedT10
            : null;
    }

    /** Qualified bookings this alert can name a platform for. */
    public function attributedQualified(): int
    {
        return $this->qualifiedT10
            - ($this->platforms['direct']->qualified ?? 0)
            - ($this->platforms['other']->qualified ?? 0);
    }

    /**
     * How much cheaper the blended figure reads than the paid one, as a share.
     *
     * The single number that says how much the unattributed bookings are flattering the
     * headline. Null while there is no spend to divide.
     */
    public function blendedUnderstatement(): ?float
    {
        $paid = $this->paidCpb();
        $blended = $this->blendedCpb();

        if ($paid === null || $blended === null || $paid <= 0.0) {
            return null;
        }

        return ($paid - $blended) / $paid;
    }

    /**
     * Attributed bookings that cannot be shown to be paid.
     *
     * In practice: leads reached only through an `fbclid`, whose first-touch data says organic,
     * social or direct. They stay in the platform rows — Meta really did send them — and stay out
     * of the cost denominators, because Meta was not paid for them.
     */
    public function notProvenPaidBookings(): int
    {
        return $this->attributedBookings() - $this->paidBookings();
    }

    /** Is there a spend total that can be divided by anything? */
    public function hasSpend(): bool
    {
        return $this->spend !== null;
    }

    /**
     * Is any ad platform integration actually wired up, working or not?
     *
     * Distinct from {@see self::hasSpend()}, which is false both when nothing is connected and
     * when something is connected but broken. The message says different things in those two
     * cases: one is a phase not yet built, the other is an incident.
     */
    public function hasSpendIntegration(): bool
    {
        return $this->spend !== null || $this->spendUnreachable !== [] || $this->accountIssues !== [];
    }

    /** Does any connected platform report its own account as unhealthy? */
    public function hasAccountIssues(): bool
    {
        return $this->accountIssues !== [];
    }

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
                && ($slice->leads > 0 || $slice->bookings > 0 || $slice->spend !== null),
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
            'baseline' => $this->baseline,
            'warnings' => $this->warnings,
            'spend' => $this->spend,
            'unattributed_by_channel' => $this->unattributedByChannel,
            'account_issues' => $this->accountIssues,
            'spend_unreachable' => $this->spendUnreachable,
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
            baseline: (array) ($data['baseline'] ?? []),
            warnings: (array) ($data['warnings'] ?? []),
            spend: isset($data['spend']) ? (float) $data['spend'] : null,
            unattributedByChannel: (array) ($data['unattributed_by_channel'] ?? []),
            accountIssues: (array) ($data['account_issues'] ?? []),
            spendUnreachable: (array) ($data['spend_unreachable'] ?? []),
        );
    }
}
