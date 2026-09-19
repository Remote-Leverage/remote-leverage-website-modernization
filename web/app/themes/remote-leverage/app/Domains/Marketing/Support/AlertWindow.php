<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use Carbon\CarbonImmutable;

/**
 * The hours the cost alert reports on.
 *
 * Its own class because two things need the same answer for different reasons and a copy in each
 * would drift: the action uses it to decide whether to run at all, and `AlertReconciler` uses it
 * to decide whether a long silence is an incident or just the night. Those falling out of step
 * would produce an alert that fires at 3am to complain that nobody has filled in a form since
 * midnight.
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
        return (int) config('marketing.cost_alert.window.from', 9);
    }

    public static function to(): int
    {
        return (int) config('marketing.cost_alert.window.to', 18);
    }

    /**
     * Inclusive of both ends: a `to` of 18 means the 18:00 hour still reports.
     *
     * A window whose end is before its start wraps around midnight — `from` 18, `to` 9 is an
     * overnight desk, not a configuration error. Read as a plain range it would be empty, and the
     * failure is silent in both directions: the card never posts, and the stale-lead check never
     * fires because it is gated on being inside the window.
     */
    public static function contains(CarbonImmutable $localNow): bool
    {
        $from = self::from();
        $to = self::to();
        $hour = $localNow->hour;

        return $from <= $to
            ? $hour >= $from && $hour <= $to
            : $hour >= $from || $hour <= $to;
    }
}
