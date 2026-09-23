<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use DateTimeZone;
use Illuminate\Http\Request;

/**
 * Where a lead actually is, inferred without asking them.
 *
 * `phone_country` is not this: a US number says nothing about where its owner sits, and plenty
 * of our clients run their business line through one from abroad. So three independent signals,
 * each stored as-is so the inference can be re-run later:
 *
 *  - `ip_country` — the edge's own GeoIP lookup, from `CloudFront-Viewer-Country` (or
 *    Cloudflare's `CF-IPCountry`). Free, no database to keep current. Wrong behind a VPN or a
 *    corporate egress.
 *  - `browser_timezone` — the IANA zone the browser reports. A VPN does not move it, which is
 *    what makes it the cross-check. Wrong for someone who keeps their clock on a client's zone.
 *  - `browser_language` — `navigator.language`. Weak: most people are `en-US` wherever they are,
 *    so it is only ever the last resort.
 *
 * The two browser values arrive as the `rl_tz` / `rl_lang` cookies written by
 * `TrackingHooks::injectVisitorCookie()`, so every form gets them without a hidden field of its
 * own.
 *
 * `country` is the verdict and `country_source` says how it was reached. When IP and timezone
 * disagree the timezone wins, because the usual cause is a VPN, and the disagreement is kept
 * visible as `timezone_over_ip` rather than resolved silently.
 *
 * Read this at submit time, from the request that carries the form post — never from a page
 * render. A rendered page can be served from the HTML cache, and the header on it would be the
 * first visitor's country, not this one's.
 */
class LeadGeoSignals
{
    /** Edge headers carrying a viewer country, in priority order. */
    public const IP_COUNTRY_HEADERS = ['CloudFront-Viewer-Country', 'CF-IPCountry'];

    public const TIMEZONE_COOKIE = 'rl_tz';

    public const LANGUAGE_COOKIE = 'rl_lang';

    /** Codes an edge sends for "no real country": unknown, Tor, anonymous proxy, satellite. */
    private const NOT_A_COUNTRY = ['XX', 'T1', 'A1', 'A2', 'O1', 'EU', 'AP'];

    /**
     * Every signal present on the request, plus the inferred country, as `column => value`.
     *
     * Only non-empty values are returned, so this merges straight into `attribution_named`
     * without overwriting a value an earlier touch already recorded.
     *
     * @return array<string, string>
     */
    public function collect(?Request $request = null): array
    {
        $request ??= app()->bound('request') ? app('request') : null;

        if (! $request instanceof Request) {
            return [];
        }

        $ipCountry = $this->ipCountry($request);
        $timezone = $this->timezone($request);
        $language = $this->language($request);

        [$country, $source] = $this->infer(
            $ipCountry,
            $timezone === null ? null : $this->timezoneCountry($timezone),
            $language === null ? null : $this->languageCountry($language),
        );

        return array_filter([
            'ip_country' => $ipCountry,
            'browser_timezone' => $timezone,
            'browser_language' => $language,
            'country' => $country,
            'country_source' => $source,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @return array{0: ?string, 1: ?string} country and how it was reached
     */
    public function infer(?string $ipCountry, ?string $timezoneCountry, ?string $languageCountry = null): array
    {
        if ($ipCountry !== null && $timezoneCountry !== null) {
            return $ipCountry === $timezoneCountry
                ? [$ipCountry, 'ip+timezone']
                : [$timezoneCountry, 'timezone_over_ip'];
        }

        if ($timezoneCountry !== null) {
            return [$timezoneCountry, 'timezone'];
        }

        if ($ipCountry !== null) {
            return [$ipCountry, 'ip'];
        }

        if ($languageCountry !== null) {
            return [$languageCountry, 'language'];
        }

        return [null, null];
    }

    public function ipCountry(Request $request): ?string
    {
        foreach (self::IP_COUNTRY_HEADERS as $header) {
            $code = $this->countryCode((string) $request->header($header, ''));

            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    /**
     * The country a timezone belongs to, from the tz database PHP ships with.
     *
     * Null for zones with no country — `UTC`, `Etc/GMT+5` — which is what a privacy-hardened
     * browser reports, and which must not be read as a location.
     */
    public function timezoneCountry(string $timezone): ?string
    {
        try {
            $location = (new DateTimeZone($timezone))->getLocation();
        } catch (\Throwable) {
            return null;
        }

        return $this->countryCode((string) ($location['country_code'] ?? ''));
    }

    /** The region subtag of a language tag: `en-PH` is `PH`, a bare `en` is nothing. */
    public function languageCountry(string $language): ?string
    {
        if (preg_match('/^[a-z]{2,3}(?:-[A-Za-z]{4})?-([A-Za-z]{2})\b/', $language, $m) !== 1) {
            return null;
        }

        return $this->countryCode($m[1]);
    }

    protected function timezone(Request $request): ?string
    {
        $value = $this->cookie($request, self::TIMEZONE_COOKIE);

        // A cookie is visitor-controlled, so only a zone PHP itself recognises is kept.
        if ($value === null || ! in_array($value, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC), true)) {
            return null;
        }

        return $value;
    }

    protected function language(Request $request): ?string
    {
        $value = $this->cookie($request, self::LANGUAGE_COOKIE);

        if ($value === null || preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8}){0,3}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * The cookie from the request, falling back to the raw superglobal.
     *
     * Same reason as `AttributionCollector::firstOf()`: these are written by our own JS and
     * never by Laravel, and a Request built for a Livewire XHR or a WordPress hook does not
     * always carry them.
     */
    protected function cookie(Request $request, string $name): ?string
    {
        $value = $request->cookie($name);

        if (! is_scalar($value) || trim((string) $value) === '') {
            $value = $_COOKIE[$name] ?? null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(rawurldecode((string) $value));

        return $value === '' ? null : mb_substr($value, 0, 64);
    }

    protected function countryCode(string $value): ?string
    {
        $value = strtoupper(trim($value));

        if (preg_match('/^[A-Z]{2}$/', $value) !== 1 || in_array($value, self::NOT_A_COUNTRY, true)) {
            return null;
        }

        return $value;
    }
}
