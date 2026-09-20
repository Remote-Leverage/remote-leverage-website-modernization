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
 */
readonly class ChannelDay
{
    public function __construct(
        public string $slug,
        public ?float $spend,
        public ?float $cpb,
        public ?float $cpqb,
    ) {}

    /** Did this channel spend anything on the day? A channel at zero is not worth a row. */
    public function isActive(): bool
    {
        return $this->spend !== null && $this->spend > 0.0;
    }
}
