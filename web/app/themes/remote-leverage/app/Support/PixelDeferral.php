<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Which marketing pixels wait for the `rlDefer` flush, and for how long at most.
 *
 * Owned by Settings → Marketing Pixels (`PixelDeferralAdmin`), not by the environment. It was
 * `PIXEL_DEFER_VENDORS` until 2026-09-23, and that path failed silently: the value had to
 * travel GitHub secret → Secrets Manager → entrypoint export → config cache, any unrecognised
 * name was dropped without a word, and production shipped with nothing deferred while the
 * secret said otherwise. A settings screen shows what is actually in force, and a change is
 * live as soon as the page cache turns over, without a deploy.
 *
 * Until the screen is first saved, `config('pixels.defer')` supplies the defaults. Once saved,
 * the option wins — including an empty selection, which means "defer nothing".
 */
final class PixelDeferral
{
    public const VENDORS_OPTION = 'rl_pixel_defer_vendors';

    public const TIMEOUT_OPTION = 'rl_pixel_defer_timeout_ms';

    public const DEFAULT_TIMEOUT_MS = 6000;

    /**
     * Every vendor whose SDK fetch can be held back, in a stable order, with its screen label.
     *
     * Anything not listed here is ignored rather than half-honoured.
     *
     * @var array<string, string>
     */
    public const DEFERRABLE = [
        'meta' => 'Meta Pixel',
        'google_tag' => 'Google tag (GA4 / Ads)',
        'bing_uet' => 'Microsoft UET',
        'linkedin' => 'LinkedIn Insight',
        'tiktok' => 'TikTok Pixel',
        'openai' => 'OpenAI Pixel',
    ];

    /**
     * The vendors being deferred, in `DEFERRABLE` order.
     *
     * @return list<string>
     */
    public static function vendors(): array
    {
        $saved = function_exists('get_option') ? get_option(self::VENDORS_OPTION, false) : false;

        $configured = is_array($saved) ? $saved : (array) config('pixels.defer.vendors', []);
        $configured = array_map(static fn ($v): string => strtolower(trim((string) $v)), $configured);

        return array_values(array_intersect(array_keys(self::DEFERRABLE), $configured));
    }

    public static function isDeferred(string $vendor): bool
    {
        return in_array($vendor, self::vendors(), true);
    }

    /**
     * Upper bound on the wait before the flush, in milliseconds.
     */
    public static function timeoutMs(): int
    {
        $saved = function_exists('get_option') ? get_option(self::TIMEOUT_OPTION, false) : false;

        $value = is_numeric($saved)
            ? (int) $saved
            : (int) config('pixels.defer.timeout_ms', self::DEFAULT_TIMEOUT_MS);

        return max(0, $value);
    }
}
