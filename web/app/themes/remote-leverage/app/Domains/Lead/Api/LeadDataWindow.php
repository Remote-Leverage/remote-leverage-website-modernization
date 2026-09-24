<?php

declare(strict_types=1);

namespace App\Domains\Lead\Api;

use Carbon\CarbonImmutable;
use Throwable;
use WP_Error;

/**
 * The `from` / `to` a data API caller asked for, validated once.
 *
 * ## Half-open
 *
 * `from` is included and `to` is not. A caller pulling day by day passes the same instant as one
 * window's `to` and the next one's `from`, and a lead captured on that exact second must land in
 * exactly one of them. The MCP `query-leads` ability is inclusive at both ends, which is fine for
 * a question and wrong for a pipeline: it would load the boundary row twice.
 *
 * ## Timezone
 *
 * An offset in the value is honoured. A value without one is New York wall-clock time, the same
 * zone the booking wizard, the cost alert and the BigQuery views use, so "2026-09-01" means the
 * business's September 1st and not UTC's, which starts four hours earlier. Everything is converted
 * to UTC before it reaches a query, because that is how the timestamps are stored.
 *
 * ## Strict format
 *
 * Only ISO 8601 dates and datetimes are accepted. Carbon would happily parse "yesterday" or
 * "last monday", and a window whose meaning depends on when the request ran is not something a
 * scheduled job should be able to ask for by accident.
 */
final readonly class LeadDataWindow
{
    public const TIMEZONE = 'America/New_York';

    /**
     * Longest window one request may cover. Pages bound the response size; this bounds the
     * booking lookup, which resolves every booked lead in the window before it pages.
     */
    public const MAX_DAYS = 366;

    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?(?:Z|[+-]\d{2}:?\d{2})?$/i';

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function parse(mixed $from, mixed $to): self|WP_Error
    {
        if (! is_string($from) || trim($from) === '' || ! is_string($to) || trim($to) === '') {
            return new WP_Error(
                'rl_data_missing_window',
                'Both `from` and `to` are required, as ISO 8601 (e.g. 2026-09-01T00:00:00-04:00).',
                ['status' => 400],
            );
        }

        $start = self::instant($from);
        $end = self::instant($to);

        foreach (['from' => $start, 'to' => $end] as $name => $instant) {
            if ($instant === null) {
                return new WP_Error(
                    'rl_data_invalid_datetime',
                    "`{$name}` is not an ISO 8601 date or datetime (e.g. 2026-09-01 or 2026-09-01T00:00:00Z).",
                    ['status' => 400],
                );
            }
        }

        if ($end->lessThanOrEqualTo($start)) {
            return new WP_Error('rl_data_empty_window', '`to` must be later than `from`.', ['status' => 400]);
        }

        if ($start->diffInDays($end, true) > self::MAX_DAYS) {
            return new WP_Error(
                'rl_data_window_too_long',
                'A window may cover at most '.self::MAX_DAYS.' days. Split a longer backfill into several requests.',
                ['status' => 400],
            );
        }

        return new self($start->utc(), $end->utc());
    }

    /** The window's start, as the database stores it. */
    public function fromSql(): string
    {
        return $this->from->format('Y-m-d H:i:s');
    }

    /** The window's (excluded) end, as the database stores it. */
    public function toSql(): string
    {
        return $this->to->format('Y-m-d H:i:s');
    }

    /**
     * Echoed back in every response, so a caller can see how its input was read — the one thing
     * that makes a timezone mistake visible at the first request instead of in a month-end total.
     *
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->format('Y-m-d\TH:i:s\Z'),
            'to' => $this->to->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private static function instant(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        /*
         * An unencoded `+` in a query string arrives as a space, so `…T00:00:00+02:00` reaches us
         * as `…T00:00:00 02:00`. Read literally that is not a valid offset, and a lenient parser
         * would drop it and shift the window by two hours without a word. Put the sign back.
         */
        $value = (string) preg_replace('/^(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?) (\d{2}:?\d{2})$/', '$1+$2', $value);

        if (preg_match(self::ISO_8601, $value) !== 1) {
            return null;
        }

        try {
            $instant = CarbonImmutable::parse($value, self::TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        // Carbon rolls 2026-02-30 over into March rather than refusing it.
        return $instant->format('Y-m-d') === substr($value, 0, 10) ? $instant : null;
    }
}
