<?php

declare(strict_types=1);

namespace App\Application\Http\Support;

/**
 * Slack request signature verification.
 *
 * Separate from `WebhookSignature` because Slack's scheme is a different shape, not a different
 * secret: the timestamp arrives in its own header rather than inside the signature header, the
 * signed string is `v0:{timestamp}:{body}` rather than `{timestamp}.{body}`, and the digest is
 * prefixed `v0=`. Folding it into the shared verifier would have meant a parameterised format
 * string that no longer says what either provider actually sends.
 *
 * The endpoint this protects can block a person and change a lead's status from an unsigned
 * POST, so callers treat a missing secret as a refusal rather than as permission to skip
 * verification. See `SlackInteractionController`.
 */
final class SlackSignature
{
    /**
     * Slack's own recommended window. Anything older is a replay of a captured request.
     */
    public const TOLERANCE = 300;

    public const TIMESTAMP_HEADER = 'X-Slack-Request-Timestamp';

    public const SIGNATURE_HEADER = 'X-Slack-Signature';

    /**
     * Verify a Slack signature against the raw request body.
     *
     * @param  string  $payload  The raw request body, exactly as received. Slack signs the
     *                           url-encoded form body byte for byte, so anything that has been
     *                           through a form parser and re-encoded will not match.
     * @return string|null Null when the signature is valid; otherwise the reason it is not.
     */
    public static function verify(
        string $payload,
        string $timestamp,
        string $signature,
        string $secret,
        int $tolerance = self::TOLERANCE,
    ): ?string {
        if ($secret === '') {
            // Defence in depth: a caller that forgets the configuration check must not fall
            // through to a comparison against an empty key, which would be forgeable.
            return 'No signing secret configured.';
        }

        if ($timestamp === '' || $signature === '') {
            return 'Missing signature headers.';
        }

        if (! ctype_digit($timestamp)) {
            return 'Invalid signature timestamp.';
        }

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return 'Request timestamp outside tolerance window.';
        }

        $expected = self::sign($payload, $secret, (int) $timestamp);

        // Constant-time: a timing-variant compare leaks the expected digest byte by byte.
        if (! hash_equals($expected, $signature)) {
            return 'Request signature does not match.';
        }

        return null;
    }

    /**
     * Build the `v0=` signature for a payload — used by the test suite, and useful for replaying
     * a captured interaction locally without going through Slack.
     */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$payload, $secret);
    }
}
