<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

/**
 * Today's running totals, as at the moment they were read.
 *
 * Four sums and a clock reading. Deliberately not a {@see MarketingDay}: that class carries every
 * rate the card prints — CPL, CPB, CPQB, the previous-day comparison, the per-channel breakdown —
 * and all of them come from the data team's own arithmetic. This is the hours since midnight,
 * which their query does not report before 08:00 Eastern, and giving it the same shape would
 * invite somebody to divide two of these and call it a cost per booking.
 *
 * It exists for one line on the overnight card, under a closing report for the day before.
 */
readonly class PartialDay
{
    public function __construct(
        public string $date,
        public string $asOfEt,
        public int $leads,
        public int $appointments,
        public int $qualified,
        public ?float $spend,
        public ?float $cpl = null,
        public ?float $cpb = null,
        public ?float $cpqb = null,
        /** @var array<string, ChannelDay> Keyed by the alert's platform slug. */
        public array $channels = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $channels = [];

        // Same column-to-slug mapping MarketingDay uses, so the card says "Microsoft" for the
        // same thing whichever day it is describing.
        foreach (ChannelDay::COLUMNS as $column => $slug) {
            $channels[$slug] = ChannelDay::fromRow($column, $slug, $row);
        }

        return new self(
            channels: $channels,
            date: (string) ($row['date'] ?? ''),
            asOfEt: (string) ($row['as_of_et'] ?? ''),
            leads: (int) (float) ($row['total_leads'] ?? 0),
            appointments: (int) (float) ($row['total_appointments'] ?? 0),
            qualified: (int) (float) ($row['total_qualified'] ?? 0),
            spend: self::float($row['total_spend'] ?? null),
            cpl: self::float($row['cpl'] ?? null),
            cpb: self::float($row['cpb'] ?? null),
            cpqb: self::float($row['cpqb'] ?? null),
        );
    }

    private static function float(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $row = [
            'date' => $this->date,
            'as_of_et' => $this->asOfEt,
            'total_leads' => $this->leads,
            'total_appointments' => $this->appointments,
            'total_qualified' => $this->qualified,
            'total_spend' => $this->spend,
            'cpl' => $this->cpl,
            'cpb' => $this->cpb,
            'cpqb' => $this->cpqb,
        ];

        foreach (ChannelDay::COLUMNS as $column => $slug) {
            $row += ($this->channels[$slug] ?? new ChannelDay($slug, null, null, null))->toRow($column);
        }

        return $row;
    }

    /** The same day with each channel's counts filled in. See MarketingDay::withChannelCounts(). */
    public function withChannelCounts(?DaySupplement $supplement): self
    {
        if ($supplement === null || $supplement->date !== $this->date || $supplement->channelCounts === []) {
            return $this;
        }

        return self::fromRow(array_merge($this->toArray(), $supplement->channelCounts));
    }

    /**
     * Has anything actually happened yet?
     *
     * At 00:30 the row exists and is all zeroes, which is true but not worth a line of its own —
     * "0 leads, 0 bookings, $0.00" under a finished day reads as a fault rather than as a quiet
     * half hour.
     */
    public function hasActivity(): bool
    {
        return $this->leads > 0 || $this->appointments > 0 || ($this->spend ?? 0.0) > 0.0;
    }
}
