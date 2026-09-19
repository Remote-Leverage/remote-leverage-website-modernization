<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Contracts;

use App\Domains\Marketing\Data\AdSpendReading;
use Carbon\CarbonImmutable;

/**
 * One ad platform's answer to "what did we spend today, and is the account healthy".
 *
 * An interface with one implementation today is usually premature. It is not here, because the
 * shape of the answer is the hard-won part and there are two more platforms coming that must not
 * be allowed to invent their own. Specifically: {@see AdSpendReading} makes "we could not reach
 * the platform" a different value from "we spent nothing", and a source that blurs those two
 * turns an outage into a cost per booking of zero.
 */
interface AdSpendSource
{
    /** The `LeadPlatform` slug this source reports for, so a reading can be joined to its slice. */
    public function platform(): string;

    /** Is every credential present? A source that is not configured is skipped, not failed. */
    public function isConfigured(): bool;

    /**
     * Spend for one local day, plus whatever the platform will say about the account's health.
     *
     * Never throws. A network failure, a rejected token or a malformed response all come back as
     * an unreachable reading carrying the reason, because the caller's job is to report what is
     * missing rather than to fall over.
     */
    public function read(CarbonImmutable $day): AdSpendReading;
}
