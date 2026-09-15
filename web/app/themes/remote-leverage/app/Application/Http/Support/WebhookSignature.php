<?php

declare(strict_types=1);

namespace App\Application\Http\Support;

/**
 * Shared HMAC-SHA256 webhook signature verification.
 *
 * Stripe and Calendly both sign with the same scheme — a `t=<unix>,v1=<hex>` header over
 * `"{timestamp}.{raw body}"` — so one verifier serves both rather than each controller
 * carrying its own copy of the parsing and the constant-time compare.
 *
 * Callers are expected to treat a missing secret as a refusal, not as a reason to skip
 * verification. See `assertConfigured()`.
 */
final class WebhookSignature
{
    /** Reject events older than this, as the providers' own libraries do. */
    public const TOLERANCE = 300;

    /**
     * Verify a signature header against the raw request body.
     *
     * @param  string  $payload  The raw request body, exactly as received.
     * @param  string  $header  The provider's signature header value.
     * @param  string  $secret  The configured signing secret.
     * @return string|null Null when the signature is valid; otherwise the reason it is not.
     */
    public static function verify(
        string $payload,
        string $header,
        string $secret,
        int $tolerance = self::TOLERANCE,
    ): ?string {
        if ($secret === '') {
            // Defence in depth: a caller that forgets assertConfigured() must not fall through
            // to a comparison against an empty key, which would otherwise be forgeable.
            return 'No signing secret configured.';
        }

        if ($header === '') {
            return 'Missing signature header.';
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            $kv = explode('=', $part, 2);

            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if ($timestamp === '' || $signature === '') {
            return 'Invalid signature header format.';
        }

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return 'Webhook timestamp outside tolerance window.';
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        // Constant-time: a timing-variant compare leaks the expected digest byte by byte.
        if (! hash_equals($expected, $signature)) {
            return 'Webhook signature does not match.';
        }

        return null;
    }

    /**
     * Build a signature header for a payload — used by the test suite, and useful for
     * replaying a captured event locally without going through the provider.
     */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp.'.'.$payload, $secret));
    }
}
