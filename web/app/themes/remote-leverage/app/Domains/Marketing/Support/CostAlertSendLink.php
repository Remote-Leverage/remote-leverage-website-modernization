<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

/**
 * The "Send new alert" button on the cost alert card: a link that posts a fresh card.
 *
 * ## Why a link and not an interactive button
 *
 * An interactive button would keep the press inside Slack, and it was the obvious build. It is
 * also the one this app backed away from on 2026-09-21 — the interactivity request URL is a
 * property of the Slack *app*, not of an environment, every environment shares the one app, and
 * turning it on needs a signing secret, an endpoint that acknowledges every button press on every
 * card, and a Slack settings change. A link needs none of that: it works the moment the card
 * posts, in every environment, which is the house rule in `config/slack-notifications.php`.
 *
 * ## The signature is the permission
 *
 * Whoever holds the link can use it, and nothing checks further. That is the same model the
 * removed interactive buttons had — the channel is already the list of people trusted with these
 * figures — and asking for a WordPress login would turn the button into a longer route to the
 * dashboard button it was asked for instead of.
 *
 * What the signature buys is that the URL cannot be guessed or forged: it is an HMAC over the
 * expiry, keyed on the install's auth salt, so it is only ever minted by the card. It expires so
 * that a forwarded or pasted copy stops working on its own, and a week is long enough that the
 * last card of a Friday still works on a Monday morning.
 *
 * Pressing an old card's button posts a card with *today's* figures, not that card's day. The page
 * says so; the card itself already carries the hour it was read at.
 */
final class CostAlertSendLink
{
    /** Where the page lives, relative to the site root. Matches `routes/web.php`. */
    public const PATH = 'cost-alert/send';

    public const TTL_SECONDS = 7 * 86400;

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    /**
     * The absolute URL for the card's button, or an empty string when one cannot be built.
     *
     * Empty drops the button through its `_when` guard. That is the only safe answer: Slack
     * rejects a button with a relative or empty `url`, and it rejects the *whole message* with it,
     * so a malformed link here would cost the card rather than the button.
     */
    public static function url(?int $now = null): string
    {
        $key = self::key();

        if ($key === '' || ! function_exists('home_url')) {
            return '';
        }

        $base = (string) home_url('/'.self::PATH);

        if (preg_match('#^https?://#i', $base) !== 1) {
            return '';
        }

        $expires = ($now ?? time()) + self::TTL_SECONDS;

        return $base.'?'.http_build_query([
            'expires' => $expires,
            'sig' => self::signature($expires, $key),
        ]);
    }

    /**
     * Null when the link is good, otherwise why not: {@see self::INVALID} or {@see self::EXPIRED}.
     *
     * The signature is checked before the expiry, so an expiry somebody typed in never gets as
     * far as being compared with the clock — "expired" is only ever said about a link this site
     * actually minted.
     */
    public static function check(mixed $expires, mixed $signature, ?int $now = null): ?string
    {
        $key = self::key();

        if ($key === '' || ! is_scalar($expires) || ! is_string($signature)) {
            return self::INVALID;
        }

        $expires = (string) $expires;

        if (preg_match('/^\d{1,12}$/', $expires) !== 1) {
            return self::INVALID;
        }

        if (! hash_equals(self::signature((int) $expires, $key), $signature)) {
            return self::INVALID;
        }

        if ((int) $expires < ($now ?? time())) {
            return self::EXPIRED;
        }

        return null;
    }

    /**
     * Truncated to 32 hex characters, which is still 128 bits. Same shape as ReferralLink's.
     */
    private static function signature(int $expires, string $key): string
    {
        return substr(hash_hmac('sha256', 'cost-alert-send|'.$expires, $key), 0, 32);
    }

    /**
     * A secret stable for the install and never shipped to a browser.
     *
     * No hardcoded fallback, unlike ReferralLink's. A preview link that anyone could forge costs
     * nothing; a link that anyone could forge posts to a channel people read, so an install with
     * no key gets no button rather than a guessable one.
     */
    private static function key(): string
    {
        if (function_exists('wp_salt')) {
            return (string) wp_salt('auth');
        }

        return (string) (config('app.key') ?: '');
    }
}
