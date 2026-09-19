<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Data;

/**
 * What one ad platform said when asked about one day.
 *
 * ## Unreachable is not zero
 *
 * The distinction this type exists to preserve. "Google spent nothing today" and "we could not
 * ask Google what it spent" are different facts with opposite consequences: the first means the
 * campaigns are paused, the second means every cost figure computed from the total is understated
 * by an unknown amount. A `?float` on its own loses that, and the loss is silent — a null
 * coalesced to 0.0 somewhere downstream reports a cost per booking that looks fine.
 *
 * So `spend` is only meaningful when {@see self::$reachable} is true, and {@see self::$error}
 * carries the reason when it is not, for the message to name.
 *
 * ## The timezone is carried, not assumed
 *
 * Ad platforms report in the ad account's own timezone. This application computes "today" in
 * `marketing.cost_alert.timezone`. If somebody changes one and not the other, spend is summed
 * over a window that does not match the bookings it is divided by, and the error is largest at
 * the ends of the day — exactly when the number is most likely to be read. So the source reports
 * the timezone the platform actually used and the collector compares it, rather than both sides
 * trusting a config value nobody has checked since it was written.
 */
readonly class AdSpendReading
{
    private function __construct(
        public string $platform,
        public bool $reachable,
        public ?float $spend = null,
        public ?string $currency = null,
        public ?string $timezone = null,
        public ?string $error = null,
        public ?string $accountIssue = null,
    ) {}

    public static function of(
        string $platform,
        float $spend,
        ?string $currency = null,
        ?string $timezone = null,
        ?string $accountIssue = null,
    ): self {
        return new self($platform, true, $spend, $currency, $timezone, null, $accountIssue);
    }

    /** The platform could not be asked, or would not answer. */
    public static function unreachable(string $platform, string $error): self
    {
        return new self($platform, false, null, null, null, $error);
    }

    /** Does the platform itself say something is wrong with the account? */
    public function hasAccountIssue(): bool
    {
        return $this->accountIssue !== null && $this->accountIssue !== '';
    }
}
