<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

/**
 * One platform's row in the cost alert.
 *
 * `bookingsViaClickId` is not decoration. It is the share of this platform's bookings that were
 * attributed by a click ID rather than by a tagged `utm_source`, and for Meta specifically it is
 * a caveat: `fbclid` is stamped on organic Facebook and Instagram clicks as well as paid ones,
 * so a Meta row propped up by click IDs may be counting traffic the ad account never paid for.
 * That understates Meta's cost per booking, which is the direction a budget decision gets made
 * in. Printing the split is what stops the number being read as firmer than it is.
 *
 * Spend is nullable throughout: phase 1 of this alert ships without any ad platform read
 * integration, so every slice has real booking counts and no cost. A null spend prints as
 * "not connected" rather than as $0.00 — a zero here would read as "we spent nothing on Google
 * today", which is a very different claim from "we cannot see what we spent".
 */
readonly class PlatformSlice
{
    public function __construct(
        public string $slug,
        public string $label,
        public int $leads,
        public int $bookings,
        public int $bookingsViaClickId,
        public int $qualified,
        public ?float $spend = null,
        public int $bookingsNotProvenPaid = 0,
        public int $qualifiedNotProvenPaid = 0,
    ) {}

    /**
     * Bookings that belong in a cost-per-booking denominator.
     *
     * Everything attributed to this platform, minus the ones reached only through a click ID
     * that does not prove a paid click and whose first-touch data does not back it up. On live
     * data that is `fbclid` and only `fbclid`: 29 of the 41 booked leads it newly credits to Meta
     * were organic or direct. Dividing Meta's ad spend by all 41 would report a cost per booking
     * under a third of the real one.
     */
    public function paidBookings(): int
    {
        return max(0, $this->bookings - $this->bookingsNotProvenPaid);
    }

    /**
     * Qualified bookings that belong in a cost-per-qualified-booking denominator.
     *
     * Has to exist, and has to be subtracted the same way {@see self::paidBookings()} is.
     * Qualified bookings are a subset of bookings, so cost per qualified booking can never be
     * lower than cost per booking — and dividing spend by the full qualified count while dividing
     * it by the reduced booking count produces exactly that impossibility on the card.
     */
    public function paidQualified(): int
    {
        return max(0, $this->qualified - $this->qualifiedNotProvenPaid);
    }

    /**
     * Cost per booking, over the bookings this platform can be shown to have paid for.
     *
     * {@see self::paidBookings()} rather than {@see self::$bookings} deliberately — see there for
     * the measurement that made the difference a threefold one.
     */
    public function cpb(): ?float
    {
        return $this->spend !== null && $this->paidBookings() > 0
            ? $this->spend / $this->paidBookings()
            : null;
    }

    /** Cost per qualified booking, over the qualified bookings this platform paid for. */
    public function cpqb(): ?float
    {
        return $this->spend !== null && $this->paidQualified() > 0
            ? $this->spend / $this->paidQualified()
            : null;
    }

    /**
     * Too few bookings for a cost figure to be a rate rather than an anecdote.
     *
     * See `marketing.cost_alert.small_sample`, and the Google row in the legacy alert this
     * replaces, where one booking produced a $737.46 "cost per booking".
     */
    public function isSmallSample(): bool
    {
        return $this->paidBookings() > 0
            && $this->paidBookings() < (int) config('marketing.cost_alert.small_sample', 3);
    }

    /**
     * The scalar form this slice is cached as. See {@see FunnelSnapshot::toArray()} for why the
     * cache holds arrays rather than serialised objects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'leads' => $this->leads,
            'bookings' => $this->bookings,
            'bookings_via_click_id' => $this->bookingsViaClickId,
            'qualified' => $this->qualified,
            'spend' => $this->spend,
            'bookings_not_proven_paid' => $this->bookingsNotProvenPaid,
            'qualified_not_proven_paid' => $this->qualifiedNotProvenPaid,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: (string) ($data['slug'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            leads: (int) ($data['leads'] ?? 0),
            bookings: (int) ($data['bookings'] ?? 0),
            bookingsViaClickId: (int) ($data['bookings_via_click_id'] ?? 0),
            qualified: (int) ($data['qualified'] ?? 0),
            spend: isset($data['spend']) ? (float) $data['spend'] : null,
            bookingsNotProvenPaid: (int) ($data['bookings_not_proven_paid'] ?? 0),
            qualifiedNotProvenPaid: (int) ($data['qualified_not_proven_paid'] ?? 0),
        );
    }
}
