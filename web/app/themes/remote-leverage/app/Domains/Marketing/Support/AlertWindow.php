<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use Carbon\CarbonImmutable;

/**
 * The hours the cost alert reports on, and the hours somebody is there to act on it.
 *
 * Two windows, because two questions were being answered by one and the answers have come apart:
 *
 * - {@see self::contains()} — may the card post now? The card is now wanted round the clock, so
 *   this defaults to every hour. An overnight card is a record of the night, read in the morning.
 * - {@see self::staffed()} — is anybody at a desk? `AlertReconciler` asks this before calling a
 *   long lead silence an incident. Six quiet hours at 4am is the night; the same gap at 2pm is a
 *   broken form.
 *
 * They were deliberately the same value, and the docblock here used to say so: an alert that
 * fires at 3am to complain nobody has filled in a form since midnight is exactly the failure
 * mode. That reasoning still holds — it is why `staffed()` exists rather than the silence check
 * simply being dropped. What changed is that posting and staffing stopped being the same
 * question, and keeping one value for both would have meant choosing which of the two to get
 * wrong.
 */
final class AlertWindow
{
    /**
     * Is the scheduled alert allowed to post in this environment?
     *
     * `MARKETING_COST_ALERT_ENABLED` wins outright when it is set at all. Otherwise the answer is
     * whether this environment is in `marketing.cost_alert.environments`, which is production
     * alone by default — see that config entry for the empty card that posted itself into the
     * sales channel from a laptop.
     */
    public static function enabledHere(): bool
    {
        $explicit = config('marketing.cost_alert.enabled');

        if ($explicit !== null) {
            return (bool) $explicit;
        }

        $environments = (array) config('marketing.cost_alert.environments', ['production']);

        $current = function_exists('wp_get_environment_type')
            ? (string) \wp_get_environment_type()
            : (string) (env('WP_ENV') ?: 'production');

        return in_array($current, $environments, true);
    }

    public static function from(): int
    {
        return (int) config('marketing.cost_alert.window.from', 0);
    }

    public static function to(): int
    {
        return (int) config('marketing.cost_alert.window.to', 23);
    }

    public static function staffedFrom(): int
    {
        return (int) config('marketing.cost_alert.staffed.from', 9);
    }

    public static function staffedTo(): int
    {
        return (int) config('marketing.cost_alert.staffed.to', 18);
    }

    /** May the card post in this hour? */
    public static function contains(CarbonImmutable $localNow): bool
    {
        return self::within($localNow, self::from(), self::to());
    }

    /**
     * Is somebody at a desk in this hour?
     *
     * Only for judging whether silence is a problem. It must never gate posting — a card that
     * skipped the quiet hours would hide precisely the nights worth looking at in the morning.
     */
    public static function staffed(CarbonImmutable $localNow): bool
    {
        return self::within($localNow, self::staffedFrom(), self::staffedTo());
    }

    /**
     * Inclusive of both ends: a `to` of 18 means the 18:00 hour is still inside.
     *
     * A window whose end is before its start wraps around midnight — `from` 18, `to` 9 is an
     * overnight desk, not a configuration error. Read as a plain range it would be empty, and the
     * failure is silent in both directions: the card never posts, and the stale-lead check never
     * fires because it is gated on being inside the window.
     */
    private static function within(CarbonImmutable $localNow, int $from, int $to): bool
    {
        $hour = $localNow->hour;

        return $from <= $to
            ? $hour >= $from && $hour <= $to
            : $hour >= $from || $hour <= $to;
    }
}
