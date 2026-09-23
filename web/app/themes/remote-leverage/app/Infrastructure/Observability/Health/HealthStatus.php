<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health;

/**
 * The three states a call site actually needs to branch on.
 *
 * Not a wider ladder like `AvailabilityHealthMonitor`'s bands: that one is about how much
 * capacity is left, this is about whether the integration can be reached at all, and a
 * finer scale would invite guessing at distinctions the underlying data cannot support.
 */
enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Degraded => 'Degraded',
            self::Down => 'Down',
        };
    }

    /**
     * `rl-badge-ok` / `rl-badge-busy` / `rl-badge-bad` — the three-state palette
     * `AdminDesignSystem` already ships, reused rather than adding a fourth set of colors
     * for the same three meanings.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Healthy => 'rl-badge-ok',
            self::Degraded => 'rl-badge-busy',
            self::Down => 'rl-badge-bad',
        };
    }

    /**
     * Worst wins. Used to fold several integrations into one headline badge — a
     * diagnostics banner that says "all healthy" while one integration is down would be
     * the exact kind of blind spot this system exists to remove.
     */
    public static function worstOf(HealthStatus $a, HealthStatus $b): HealthStatus
    {
        $rank = [self::Healthy->value => 0, self::Degraded->value => 1, self::Down->value => 2];

        return $rank[$a->value] >= $rank[$b->value] ? $a : $b;
    }
}
