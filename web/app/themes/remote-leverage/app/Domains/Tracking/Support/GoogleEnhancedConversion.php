<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Support;

use App\Domains\Lead\Models\Lead;
use App\Domains\Tracking\Gateways\MetaConversionsApiClient;
use App\Infrastructure\WordPress\Hooks\ConversionHooks;

/**
 * The user-provided data half of Google Ads enhanced conversions, built from a captured lead.
 *
 * Google Ads reports "no tag pings" against a conversion action whose enhanced-conversions
 * setting expects an event snippet carrying a `transaction_id`. Until 2026-09-18 nothing in this
 * codebase sent one: `ConversionHooks` emitted `gtag('event','conversion',{send_to})` and nothing
 * else, so there was no key to join a conversion to anything and no user data to match on.
 *
 * The legacy site did not solve this either — verified against the 2026-08-27 backup. Its Google
 * Ads conversions were GTM tags on a `Page Path contains VAThankYou` trigger with no user data,
 * Site Kit's own `Enhanced_Conversions` class only reads `wp_get_current_user()` and so does
 * nothing for an anonymous booker, and the one `pys_google_ads` option left in the database is
 * from an uninstalled plugin with an empty `ads_ids`. So this is new work, not a port.
 *
 * ## Normalisation is load-bearing
 *
 * Google matches on the hash. A different normalisation produces a different hash and a silent
 * non-match — the same trap `MetaConversionsApiClient::normalize()` documents, and the reason
 * this is a separate class rather than a reuse of it: the two specs genuinely disagree. Meta
 * strips `+` from a phone number; Google requires E.164 *with* it.
 *
 * Deliberately NOT doing gmail dot-stripping. It is not in Google's spec for enhanced
 * conversions, and inventing a normalisation step is how a hash stops matching.
 *
 * @see ConversionHooks::injectConversions()
 */
class GoogleEnhancedConversion
{
    /**
     * Where the booking wizard leaves this for the thank-you page to pick up.
     *
     * Stored with `put` rather than `flash` on purpose. A flash is gone after one request, so a
     * reload of `/VAThankYou/` would re-fire the conversion with no `transaction_id` and Google
     * would count it twice — which is the exact thing the id exists to prevent. Persisting it for
     * the session makes a reload idempotent.
     */
    public const SESSION_KEY = 'rl_google_enhanced_conversion';

    /**
     * The dedupe key, shared with Meta's `event_id` so both platforms key off one identifier.
     *
     * @see MetaConversionsApiClient::eventId()
     */
    public static function transactionId(Lead $lead): string
    {
        return 'lead-'.($lead->uuid ?: $lead->id);
    }

    /**
     * @return array{transaction_id: string, user_data: array<string, mixed>}
     */
    public static function payload(Lead $lead): array
    {
        $userData = array_filter([
            'sha256_email_address' => self::hash(self::normalizeEmail((string) ($lead->email ?? ''))),
            'sha256_phone_number' => self::hash(self::normalizePhone($lead)),
        ], static fn (string $v): bool => $v !== '');

        $address = array_filter([
            'sha256_first_name' => self::hash(self::normalizeName((string) ($lead->first_name ?? ''))),
            'sha256_last_name' => self::hash(self::normalizeName((string) ($lead->last_name ?? ''))),
        ], static fn (string $v): bool => $v !== '');

        if ($address !== []) {
            $userData['address'] = $address;
        }

        return [
            'transaction_id' => self::transactionId($lead),
            'user_data' => $userData,
        ];
    }

    /**
     * Trim, then lowercase. Google's whole spec for an email address.
     */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * E.164: a leading `+`, the country code, then digits.
     *
     * `phone` is stored without a country code and `phone_country` holds it separately, so a bare
     * `phone` would be an unmatchable local number. Mirrors
     * `MetaConversionsApiClient::phoneWithCountry()` in how it decides the code is not already
     * there, and differs from it in keeping the `+` that Meta strips.
     */
    public static function normalizePhone(Lead $lead): string
    {
        $digits = preg_replace('/\D/', '', (string) ($lead->phone ?? '')) ?? '';

        if ($digits === '') {
            return '';
        }

        $country = preg_replace('/\D/', '', (string) ($lead->phone_country ?? '')) ?? '';

        if ($country !== '' && ! str_starts_with($digits, $country)) {
            $digits = $country.$digits;
        }

        return '+'.$digits;
    }

    /**
     * Trim, then lowercase. As with the email, no extra stripping Google does not ask for.
     */
    public static function normalizeName(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * Hex SHA-256, or an empty string for an empty input — never the hash of "".
     *
     * `hash('sha256', '')` is a perfectly valid-looking digest, and sending it would claim we
     * hold an identifier we do not have.
     */
    public static function hash(string $value): string
    {
        return $value === '' ? '' : hash('sha256', $value);
    }
}
