<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

/**
 * One row of the data team's marketing day, typed.
 *
 * Everything on the cost half of the alert now comes from here rather than from three ad platform
 * clients and this application's own attribution. The reason is not that the warehouse is more
 * accurate — it may or may not be — but that it is what the rest of the business reports on. A
 * Slack card that disagrees with the dashboard the data team maintains is wrong by definition,
 * whichever number is closer to the truth.
 *
 * ## Two things the column names hide
 *
 * `cpb` and `cpb_all` are the same expression: total spend over *all* bookings, unattributed ones
 * included. `cpb_paid` divides paid spend by paid bookings. Those are the blended and paid figures
 * this alert has always separated, and the view computing both independently is the strongest
 * evidence that the separation is real rather than this codebase's opinion.
 *
 * `unclassified` means `NOT is_paid_channel`, which is broader than "we could not attribute it" —
 * organic, direct and email all land there. That is why the legacy alert said 21.4%: it is the
 * share of bookings that did not come from paid, not the share we failed to identify.
 *
 * ## The reporting window
 *
 * Before 08:00 Eastern the query reports *yesterday, closed*; after it, *today so far*. That is a
 * better default than always reporting today, which at 09:00 has almost nothing in it.
 * {@see self::isClosing()} is how the card says which one the reader is looking at.
 */
readonly class MarketingDay
{
    /**
     * @param  array<string, ChannelDay>  $channels  Keyed by the alert's platform slug.
     */
    public function __construct(
        public string $date,
        public string $reportKind,
        public string $asOfEt,
        public bool $spendIsComplete,
        public int $appointments,
        public int $qualified,
        public int $leads,
        public ?float $spend,
        public array $channels,
        public ?float $cpl,
        public ?float $cpbAll,
        public ?float $cpqbAll,
        public ?float $cpbPaid,
        public ?float $cpqbPaid,
        public int $unclassifiedLeads,
        public int $unclassifiedAppointments,
        public int $unclassifiedQualified,
        public ?float $unclassifiedShare,
        public ?string $previousDate,
        public ?float $previousSpend,
        public ?int $previousAppointments,
        public ?float $previousCpb,
        public ?float $previousCpqb,
    ) {}

    /** A closed day, rather than a day still in progress. */
    public function isClosing(): bool
    {
        return strtoupper($this->reportKind) === 'CLOSING';
    }

    /**
     * Is the previous-day comparison a fair one?
     *
     * Only on a closing report. Comparing two hours of today against a full previous day makes
     * every morning look like a collapse, which is how a comparison stops being read. The view
     * supplies `prev_d` on both kinds; the card only prints it on one.
     */
    public function hasFairComparison(): bool
    {
        return $this->isClosing() && $this->previousAppointments !== null;
    }

    /**
     * Build from a BigQuery result row, which arrives as strings whatever the declared type.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $channels = [];

        /*
         * The view's channel names, mapped onto the slugs the rest of this domain uses. Keeping
         * `LeadPlatform`'s vocabulary means the card, the leads filter and the CSV export all
         * still say "Microsoft" for the same thing.
         */
        foreach (ChannelDay::COLUMNS as $column => $slug) {
            $channels[$slug] = ChannelDay::fromRow($column, $slug, $row);
        }

        return new self(
            date: (string) ($row['Date'] ?? $row['date'] ?? ''),
            reportKind: (string) ($row['report_kind'] ?? ''),
            asOfEt: (string) ($row['as_of_et'] ?? ''),
            spendIsComplete: self::bool($row['spend_is_complete'] ?? null),
            appointments: self::int($row['total_appointments'] ?? null),
            qualified: self::int($row['total_qualified'] ?? null),
            leads: self::int($row['total_leads'] ?? null),
            spend: self::float($row['total_spend'] ?? null),
            channels: $channels,
            cpl: self::float($row['cpl'] ?? null),
            cpbAll: self::float($row['cpb_all'] ?? null),
            cpqbAll: self::float($row['cpqb_all'] ?? null),
            cpbPaid: self::float($row['cpb_paid'] ?? null),
            cpqbPaid: self::float($row['cpqb_paid'] ?? null),
            unclassifiedLeads: self::int($row['unclassified_leads'] ?? null),
            unclassifiedAppointments: self::int($row['unclassified_appointments'] ?? null),
            unclassifiedQualified: self::int($row['unclassified_qualified'] ?? null),
            unclassifiedShare: self::float($row['unclassified_appointments_share'] ?? null),
            previousDate: isset($row['prev_date']) ? (string) $row['prev_date'] : null,
            previousSpend: self::float($row['prev_spend'] ?? null),
            previousAppointments: isset($row['prev_appointments']) ? self::int($row['prev_appointments']) : null,
            previousCpb: self::float($row['prev_cpb'] ?? null),
            previousCpqb: self::float($row['prev_cpqb'] ?? null),
        );
    }

    /**
     * Back to the row shape, so a snapshot holding one can be cached and rebuilt.
     *
     * Deliberately the same keys {@see self::fromRow()} reads, rather than a second format — one
     * mapping to keep in step instead of two.
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        $row = [
            'Date' => $this->date,
            'report_kind' => $this->reportKind,
            'as_of_et' => $this->asOfEt,
            'spend_is_complete' => $this->spendIsComplete,
            'total_appointments' => $this->appointments,
            'total_qualified' => $this->qualified,
            'total_leads' => $this->leads,
            'total_spend' => $this->spend,
            'cpl' => $this->cpl,
            'cpb_all' => $this->cpbAll,
            'cpqb_all' => $this->cpqbAll,
            'cpb_paid' => $this->cpbPaid,
            'cpqb_paid' => $this->cpqbPaid,
            'unclassified_leads' => $this->unclassifiedLeads,
            'unclassified_appointments' => $this->unclassifiedAppointments,
            'unclassified_qualified' => $this->unclassifiedQualified,
            'unclassified_appointments_share' => $this->unclassifiedShare,
            'prev_date' => $this->previousDate,
            'prev_spend' => $this->previousSpend,
            'prev_appointments' => $this->previousAppointments,
            'prev_cpb' => $this->previousCpb,
            'prev_cpqb' => $this->previousCpqb,
        ];

        foreach (ChannelDay::COLUMNS as $column => $slug) {
            $row += ($this->channels[$slug] ?? new ChannelDay($slug, null, null, null))->toRow($column);
        }

        return $row;
    }

    /**
     * The same day with each channel's counts filled in from our supplement read.
     *
     * The data team's query publishes per-channel spend and costs but not the counts; the
     * supplement sums them from the same view for the same date. Refuses a supplement for a
     * different day, because a count from Monday beside a cost from Tuesday is not a figure.
     */
    public function withChannelCounts(?DaySupplement $supplement): self
    {
        if ($supplement === null || $supplement->date !== $this->date || $supplement->channelCounts === []) {
            return $this;
        }

        return self::fromRow(array_merge($this->toRow(), $supplement->channelCounts));
    }

    /**
     * Null stays null; everything else becomes a float.
     *
     * The distinction is load-bearing all through this alert: `SAFE_DIVIDE` returns null when the
     * denominator is zero, and a cost per booking of null means "nothing booked", where 0.0 would
     * mean "each booking was free".
     */
    private static function float(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private static function int(mixed $value): int
    {
        return $value === null || $value === '' ? 0 : (int) $value;
    }

    /** BigQuery sends booleans as the strings "true" and "false" over REST. */
    private static function bool(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
