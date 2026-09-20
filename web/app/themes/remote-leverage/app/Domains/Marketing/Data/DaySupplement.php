<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

use Carbon\CarbonImmutable;

/**
 * The parts of the reported day the data team's query does not return.
 *
 * Kept apart from {@see MarketingDay} on purpose. That class is their answer and every field on it
 * is a figure the business reports; this is context about that answer — how fresh it is, which ad
 * platforms were still reporting when it was taken, and the funnel above the lead. Merging them
 * would blur which numbers are definitions and which are ours.
 */
readonly class DaySupplement
{
    public function __construct(
        public string $date,
        public ?CarbonImmutable $extractedAt,
        public bool $metaStale,
        public bool $googleStale,
        public bool $bingStale,
        public bool $spendPending,
        public int $impressions,
        public int $clicks,
        public int $landingPageViews,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            date: (string) ($row['date'] ?? ''),
            extractedAt: self::moment($row['extracted_at'] ?? null),
            metaStale: (bool) (int) ($row['meta_stale'] ?? 0),
            googleStale: (bool) (int) ($row['google_stale'] ?? 0),
            bingStale: (bool) (int) ($row['bing_stale'] ?? 0),
            spendPending: (bool) (int) ($row['spend_pending'] ?? 0),
            impressions: (int) (float) ($row['impressions'] ?? 0),
            clicks: (int) (float) ($row['clicks'] ?? 0),
            landingPageViews: (int) (float) ($row['landing_page_views'] ?? 0),
        );
    }

    /**
     * The zone `extracted_at` is recorded in.
     *
     * BigQuery hands back a DATETIME as a naive string with no zone, so this has to be asserted
     * somewhere, and getting it wrong is silent: read as UTC it reported the extract as four hours
     * old at the exact moment it was taken, and the freshness check duly warned about a pipeline
     * that was working perfectly.
     *
     * It is `America/New_York`, matching the `as_of_et` the same view computes. Measured rather
     * than assumed: a row read at 05:44 ET carried `extracted_at` of 05:44:07.
     */
    private const EXTRACTED_AT_ZONE = 'America/New_York';

    private static function moment(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, self::EXTRACTED_AT_ZONE);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Minutes since the warehouse last pulled, or null when it did not say. */
    public function ageInMinutes(?CarbonImmutable $now = null): ?int
    {
        if ($this->extractedAt === null) {
            return null;
        }

        // Carbon 3 returns a float here, and the difference is reported in whole minutes.
        return max(0, (int) $this->extractedAt->diffInMinutes($now ?? CarbonImmutable::now()));
    }

    /**
     * Which ad platforms were still catching up when this was taken.
     *
     * @return array<int, string>
     */
    public function stalePlatforms(): array
    {
        return array_values(array_filter([
            $this->metaStale ? 'Meta' : null,
            $this->googleStale ? 'Google' : null,
            $this->bingStale ? 'Microsoft' : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'extracted_at' => $this->extractedAt?->toIso8601String(),
            'meta_stale' => (int) $this->metaStale,
            'google_stale' => (int) $this->googleStale,
            'bing_stale' => (int) $this->bingStale,
            'spend_pending' => (int) $this->spendPending,
            'impressions' => $this->impressions,
            'clicks' => $this->clicks,
            'landing_page_views' => $this->landingPageViews,
        ];
    }
}
