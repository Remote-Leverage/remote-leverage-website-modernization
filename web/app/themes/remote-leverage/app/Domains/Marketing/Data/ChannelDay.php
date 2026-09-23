<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

/**
 * One channel's spend and cost, straight from the warehouse.
 *
 * Deliberately thinner than {@see PlatformSlice}, which carries lead and booking counts this
 * application derived itself. The warehouse's per-channel figures are spend and the two costs,
 * and inventing the rest from another source would produce a row whose numerator and denominator
 * came from different systems.
 *
 * The counts are the warehouse's too — the same view, summed by our supplement query, because the
 * data team's own query publishes each channel's costs but not the counts behind them. Null means
 * that read did not happen, which is not the same as zero.
 */
readonly class ChannelDay
{
    public function __construct(
        public string $slug,
        public ?float $spend,
        public ?float $cpb,
        public ?float $cpqb,
        public ?int $leads = null,
        public ?int $bookings = null,
        public ?int $qualified = null,
    ) {}

    /**
     * The warehouse column prefix for each slug, in the order the card lists them.
     *
     * One copy of the mapping every row reader here repeats, so the card, the leads filter and the
     * CSV export all say "Microsoft" for the view's "Bing".
     */
    public const COLUMNS = ['facebook' => 'meta', 'google' => 'google', 'bing' => 'microsoft'];

    /**
     * Build one channel from a row in the view's column shape: `facebook_spend`, `facebook_cpb`,
     * `facebook_bookings` and so on. Absent counts stay null.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(string $column, string $slug, array $row): self
    {
        $float = static fn (mixed $v): ?float => $v === null || $v === '' ? null : (float) $v;
        $int = static fn (mixed $v): ?int => $v === null || $v === '' ? null : (int) (float) $v;

        return new self(
            slug: $slug,
            spend: $float($row[$column.'_spend'] ?? null),
            cpb: $float($row[$column.'_cpb'] ?? null),
            cpqb: $float($row[$column.'_cpqb'] ?? null),
            leads: $int($row[$column.'_leads'] ?? null),
            bookings: $int($row[$column.'_bookings'] ?? null),
            qualified: $int($row[$column.'_qualified'] ?? null),
        );
    }

    /**
     * Back to the column shape {@see self::fromRow()} reads.
     *
     * @return array<string, int|float|null>
     */
    public function toRow(string $column): array
    {
        return [
            $column.'_spend' => $this->spend,
            $column.'_cpb' => $this->cpb,
            $column.'_cpqb' => $this->cpqb,
            $column.'_leads' => $this->leads,
            $column.'_bookings' => $this->bookings,
            $column.'_qualified' => $this->qualified,
        ];
    }

    /** Did this channel spend anything on the day? A channel at zero is not worth a row. */
    public function isActive(): bool
    {
        return $this->spend !== null && $this->spend > 0.0;
    }
}
