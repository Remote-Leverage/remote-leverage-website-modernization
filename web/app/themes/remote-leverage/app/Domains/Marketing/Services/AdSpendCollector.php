<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Contracts\AdSpendSource;
use App\Domains\Marketing\Data\AdSpendReading;
use Carbon\CarbonImmutable;

/**
 * Asks every configured ad platform what it spent, and decides what may be totalled.
 *
 * ## A partial total is worse than no total
 *
 * The rule this class exists to enforce. If Meta answers and Google does not, the sum of what
 * came back is not "today's spend" — it is today's spend minus an unknown amount, and dividing it
 * by today's bookings produces a cost per booking that is too low by that unknown amount. Too
 * low is the dangerous direction: it is the reading on which somebody decides the channel is
 * working and spends more.
 *
 * So {@see self::total()} returns null the moment any configured source failed, and
 * {@see self::unreachable()} names which, for the message to print instead. Per-platform figures
 * from the sources that did answer are still reported, because those are complete in themselves.
 *
 * A source that is simply *not configured* is not a failure. Phase 2 ships Meta alone; Google and
 * Microsoft are absent rather than broken, and an alert that refused to total until all three
 * existed would report nothing for months.
 */
class AdSpendCollector
{
    /** @param array<int, AdSpendSource> $sources */
    public function __construct(private readonly array $sources = []) {}

    /**
     * Read every configured source for one local day.
     *
     * @return array<string, AdSpendReading> keyed by platform slug
     */
    public function collect(CarbonImmutable $day): array
    {
        $readings = [];

        foreach ($this->sources as $source) {
            if (! $source->isConfigured()) {
                continue;
            }

            $readings[$source->platform()] = $source->read($day);
        }

        return $readings;
    }

    /**
     * The total, or null when any configured source could not be reached.
     *
     * Also null when nothing is configured at all — there is no spend to report, which is phase
     * 1's permanent state and renders as "not connected" rather than as $0.00.
     *
     * @param  array<string, AdSpendReading>  $readings
     */
    public function total(array $readings): ?float
    {
        /*
         * An account the platform itself says is disabled, unsettled or under review is treated
         * the same as one that did not answer.
         *
         * It answered, and what it said was 0.00 — which is true, and useless, and dangerous. A
         * suspended account produced a card reading `Spend $0.00`, `CPB $0.00` and, because zero
         * is not greater than any target, `CPB is on target`. The reconciliation warning at the
         * top said the account was disabled while the figures underneath congratulated it, and
         * the figures are what people read.
         */
        if ($readings === [] || $this->unreachable($readings) !== [] || $this->accountIssues($readings) !== []) {
            return null;
        }

        $total = 0.0;

        foreach ($readings as $reading) {
            $total += (float) $reading->spend;
        }

        return $total;
    }

    /**
     * Platforms that were asked and did not answer, as `slug => reason`.
     *
     * @param  array<string, AdSpendReading>  $readings
     * @return array<string, string>
     */
    public function unreachable(array $readings): array
    {
        $failed = [];

        foreach ($readings as $slug => $reading) {
            if (! $reading->reachable) {
                $failed[$slug] = $reading->error ?? 'unknown error';
            }
        }

        return $failed;
    }

    /**
     * Platforms reporting a problem with the account itself, as `slug => what the platform said`.
     *
     * Separate from {@see self::unreachable()} because they mean opposite things. An unreachable
     * platform is our problem — a token, a network. An account issue is a working integration
     * faithfully reporting that the ad account is disabled, unsettled or under review, which is
     * both more urgent and entirely outside this codebase.
     *
     * @param  array<string, AdSpendReading>  $readings
     * @return array<string, string>
     */
    public function accountIssues(array $readings): array
    {
        $issues = [];

        foreach ($readings as $slug => $reading) {
            if ($reading->hasAccountIssue()) {
                $issues[$slug] = (string) $reading->accountIssue;
            }
        }

        return $issues;
    }

    /**
     * Platforms whose own reporting timezone is not the one this alert counts bookings in.
     *
     * A mismatch means spend is summed over one window and bookings over another, and nothing
     * about the resulting cost per booking looks wrong. It is reported as a finding rather than
     * silently corrected, because the right correction depends on which of the two is the mistake
     * and only a human knows that.
     *
     * @param  array<string, AdSpendReading>  $readings
     * @return array<string, string>
     */
    public function timezoneMismatches(array $readings, string $expected): array
    {
        $mismatched = [];

        foreach ($readings as $slug => $reading) {
            if ($reading->timezone !== null && $reading->timezone !== '' && $reading->timezone !== $expected) {
                $mismatched[$slug] = (string) $reading->timezone;
            }
        }

        return $mismatched;
    }
}
