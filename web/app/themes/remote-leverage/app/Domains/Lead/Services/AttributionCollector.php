<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use Illuminate\Http\Request;

/**
 * Reads every attribution signal off the incoming request.
 *
 * This is the single definition of "what we collect", ported from the legacy Gravity Form
 * (form 20, "Lead Routing Form v2 - VA") which carried ~45 hidden fields fed by query string,
 * the HandL UTM Grabber plugin's cookies, and the browser.
 *
 * Three groups, and the third is the point:
 *
 *  - `NAMED` — parameters that have their own column on `rl_leads`. Queryable, indexed, synced.
 *  - `EXTRA` — parameters that are recorded but have no column: the HandL first-touch and
 *    metadata set. They go into the `attribution` JSON blob.
 *  - **Anything else.** A query parameter this class has never heard of is still captured, into
 *    the same blob under `unmapped`. That is what stops a new ad platform's click id being
 *    silently dropped between the day marketing starts using it and the day someone notices
 *    the column is missing. Promoting one later is a migration plus a line in `NAMED`; the
 *    historical data is already there.
 *
 * Every value is read query-string first, then cookie. The cookies are the site's own `rl_*` set,
 * written on every page by `TrackingHooks::injectAttributionCookies()`, so a visitor who browses
 * several pages — or comes back days later — before converting still carries what the current URL
 * no longer shows. That is the job the legacy HandL UTM Grabber did; it was a plugin on the old
 * site and did not come across at cutover (2026-09-19), so from then until 2026-09-26 nothing
 * wrote these cookies at all.
 *
 * HandL's own cookies (`utm_*`, `handl_*`) are still read, after ours, for browsers that last
 * visited the old site — except `fbclid`: see `LEGACY_COOKIES_NOT_READ`.
 */
class AttributionCollector
{
    /**
     * The click parameters one visit carries, remembered as a set in `rl_<param>` cookies.
     *
     * A visit that arrives with any of them replaces the whole set, so a later click carrying
     * only an `fbclid` does not inherit an older campaign's UTMs.
     *
     * @var string[]
     */
    public const LAST_TOUCH = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'gclid', 'fbclid', 'msclkid', 'li_fat_id', 'wbraid', 'gbraid',
    ];

    /**
     * The UTMs of the first visit that carried any click parameter, kept in `rl_ft_<param>` and
     * never overwritten. Read into the HandL-compatible `first_utm_*` fields.
     *
     * @var string[]
     */
    public const FIRST_TOUCH = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * When the remembered `fbclid` was clicked, in milliseconds — what an `fbc` built from it has
     * to carry. See `clickTimeMs()`.
     */
    public const FBCLID_TIME_COOKIE = 'rl_fbclid_ts';

    /**
     * Legacy cookies that are never read, even as a last resort.
     *
     * HandL's `fbclid` is frozen at whatever the old site last wrote, carries no click time, and
     * an `fbc` synthesised from it would stamp that old click with today's date — telling Meta a
     * click from weeks ago just happened. That is how a 28 August click came to be attached to
     * two 25 September bookings. Meta's own `_fbc` and our `rl_fbclid` both carry the time.
     *
     * @var string[]
     */
    public const LEGACY_COOKIES_NOT_READ = ['fbclid'];

    /**
     * Our own cookie for a NAMED column or EXTRA key, read ahead of anything HandL left behind.
     */
    public static function ownCookieFor(string $key): ?string
    {
        if (in_array($key, self::LAST_TOUCH, true)) {
            return 'rl_'.$key;
        }

        if (str_starts_with($key, 'first_') && in_array(substr($key, 6), self::FIRST_TOUCH, true)) {
            return 'rl_ft_'.substr($key, 6);
        }

        return [
            'handl_landing_page' => 'rl_landing_page',
            'handl_original_ref' => 'rl_original_ref',
        ][$key] ?? null;
    }

    /**
     * Parameters with a first-class column, as `column => [source keys in priority order]`.
     *
     * @var array<string, string[]>
     */
    public const NAMED = [
        'utm_source' => ['utm_source', 'handl_utm_source'],
        'utm_medium' => ['utm_medium', 'handl_utm_medium'],
        'utm_campaign' => ['utm_campaign', 'handl_utm_campaign'],
        'utm_term' => ['utm_term', 'handl_utm_term'],
        'utm_content' => ['utm_content', 'handl_utm_content'],
        'utm_id' => ['utm_id'],
        'gclid' => ['gclid'],
        'fbclid' => ['fbclid'],

        /*
         * Promoted out of EXTRA on 2026-09-19. It is now a selection criterion rather than
         * audit trail: LeadPlatform falls back to click IDs when `utm_source` is missing, and
         * a value living in the JSON blob cannot be queried the same way on MySQL and on the
         * SQLite the tests run. `wbraid` and `gbraid` stay in EXTRA below — they are Google
         * click IDs too, but iOS-only and rare enough that `gclid` already covers the paid
         * Google traffic that matters to the cost alert.
         */
        'msclkid' => ['msclkid'],
        'li_fat_id' => ['li_fat_id'],
        'fbc' => ['_fbc', 'fbc'],
        'oppref' => ['oppref'],
        'partner' => ['partner'],
        'scheduler_link' => ['scheduler_link'],
        'landing_page_base' => ['handl_landing_page_base'],

        /*
         * Our own visitor cookie. First-party and long-lived, so it survives the ad-blockers
         * that remove PostHog's — which matters because that traffic is disproportionately the
         * traffic worth recognising on a return visit.
         */
        'device_id' => ['rl_vid'],
    ];

    /**
     * Recorded, but without a column of their own — the HandL first-touch and metadata set.
     *
     * These are audit trail rather than selection criteria: useful when reconstructing where a
     * lead came from, never used to filter or route, so a JSON blob is the right home.
     *
     * @var string[]
     */
    public const EXTRA = [
        'first_utm_source', 'first_utm_medium', 'first_utm_campaign',
        'first_utm_term', 'first_utm_content',
        'wbraid', 'gbraid', '_fbp', 'gaclientid',
        'traffic_source', 'first_traffic_source',
        'organic_source', 'organic_source_str',
        'handl_original_ref', 'handl_landing_page', 'handl_ip',
        'handl_ref', 'handl_ref_domain', 'handl_url', 'handl_url_base', 'handlID',
    ];

    /**
     * Query parameters that are never attribution and would only add noise to `unmapped`.
     *
     * WordPress routing, pagination, cache-busters and the referral codes the Referral domain
     * already reads into `referral_code`.
     *
     * @var string[]
     */
    public const IGNORED = [
        'p', 'page', 'page_id', 'paged', 'preview', 'preview_id', 'preview_nonce',
        's', 'cat', 'tag', 'author', 'feed', 'attachment_id', 'replytocom',
        'ver', 'v', 'nocache', 'cache', 'fbclid_hash',
        'via', 'ref', 'r', 'tab',
    ];

    /** Cap on a single stored value, so a hostile or broken URL cannot bloat the row. */
    private const MAX_VALUE_LENGTH = 500;

    /** Cap on how many unrecognised parameters are kept from one request. */
    private const MAX_UNMAPPED = 25;

    /**
     * Collect everything from the request.
     *
     * @return array{named: array<string, string>, attribution: array<string, mixed>}
     */
    public function collect(?Request $request = null): array
    {
        $request ??= $this->currentRequest();

        if (! $request instanceof Request) {
            return ['named' => [], 'attribution' => []];
        }

        $named = [];

        foreach (self::NAMED as $column => $keys) {
            $value = $this->firstOf($request, $keys, self::ownCookieFor($column));

            if ($value !== null) {
                $named[$column] = $value;
            }
        }

        $extra = [];

        foreach (self::EXTRA as $key) {
            $value = $this->firstOf($request, [$key], self::ownCookieFor($key));

            if ($value !== null) {
                $extra[$key] = $value;
            }
        }

        $attribution = [];

        if ($extra !== []) {
            $attribution['handl'] = $extra;
        }

        $unmapped = $this->unmapped($request);

        if ($unmapped !== []) {
            $attribution['unmapped'] = $unmapped;
        }

        $userAgent = $this->clean((string) $request->userAgent());

        if ($userAgent !== null) {
            $attribution['user_agent'] = $userAgent;
        }

        /*
         * Derive `fbc` from a bare `fbclid` when the cookie is not there yet.
         *
         * `_fbc` is written by Meta's own pixel JS, so it only exists from the *second* request
         * of a session onward — a visitor who lands from an ad and submits on that same page view
         * has `fbclid` in the URL and no cookie. That is not an edge case: it is the fast
         * converter, and it is why the legacy data shows fbc present on 52 of 59 fbclid leads
         * even with HandL doing the capture.
         *
         * Meta's documented format is `fb.<subdomainIndex>.<creationTimeMs>.<fbclid>`, and it
         * accepts one we assemble ourselves — the click id is the part that matters. Doing it
         * here rather than only at send time means the `fbc` column is populated for every
         * consumer (the CAPI client, the admin, and any downstream automation), not just for the
         * one that happened to know how to reconstruct it.
         *
         * Flagged in `attribution['fbc_synthetic']` either way. `CaptureLeadAction::resolveFbc()`
         * reads this to decide what is safe to overwrite later: a synthetic value is a stand-in
         * and gets replaced the moment something better shows up (a live cookie, a real value
         * from a later mount); a real one, once stored, is never replaced by anything.
         */
        if (($named['fbc'] ?? '') === '') {
            if (($named['fbclid'] ?? '') !== '') {
                $named['fbc'] = sprintf('fb.1.%d.%s', $this->clickTimeMs($request), $named['fbclid']);
                $attribution['fbc_synthetic'] = true;
            }
        } else {
            $attribution['fbc_synthetic'] = false;
        }

        return ['named' => $named, 'attribution' => $attribution];
    }

    /**
     * The client IP, preferring the forwarded header this stack sits behind.
     *
     * Behind CloudFront/Cloudflare `REMOTE_ADDR` is the edge node, which is the same for
     * thousands of visitors and useless as attribution.
     */
    public function ipAddress(?Request $request = null): ?string
    {
        $request ??= $this->currentRequest();

        if (! $request instanceof Request) {
            return null;
        }

        $forwarded = (string) $request->header('X-Forwarded-For', '');

        if ($forwarded !== '') {
            // Left-most entry is the original client; the rest are proxies.
            $first = trim(explode(',', $forwarded)[0]);

            if ($first !== '') {
                return substr($first, 0, 45);
            }
        }

        $ip = $request->ip();

        return $ip === null ? null : substr($ip, 0, 45);
    }

    /**
     * Query parameters this class does not recognise.
     *
     * @return array<string, string>
     */
    protected function unmapped(Request $request): array
    {
        $known = array_merge(
            array_merge(...array_values(self::NAMED)),
            self::EXTRA,
            self::IGNORED,
        );

        $unmapped = [];

        foreach ($request->query() as $key => $value) {
            if (! is_string($key) || in_array($key, $known, true)) {
                continue;
            }

            // Only scalars: an array parameter is a form post pattern, not attribution.
            if (! is_scalar($value)) {
                continue;
            }

            $clean = $this->clean((string) $value);

            if ($clean === null) {
                continue;
            }

            $unmapped[substr($key, 0, 100)] = $clean;

            if (count($unmapped) >= self::MAX_UNMAPPED) {
                break;
            }
        }

        return $unmapped;
    }

    /**
     * First non-empty value among these keys: query string, then our own cookie, then legacy ones.
     *
     * @param  string[]  $keys
     */
    protected function firstOf(Request $request, array $keys, ?string $ownCookie = null): ?string
    {
        foreach ($keys as $key) {
            $value = $request->query($key);

            if (is_scalar($value)) {
                $clean = $this->clean((string) $value);

                if ($clean !== null) {
                    return $clean;
                }
            }
        }

        $keys = array_values(array_filter(
            [$ownCookie, ...array_diff($keys, self::LEGACY_COOKIES_NOT_READ)],
            static fn (?string $key): bool => $key !== null,
        ));

        foreach ($keys as $key) {
            $value = $request->cookie($key);

            if (is_scalar($value)) {
                $clean = $this->clean((string) $value);

                if ($clean !== null) {
                    return $clean;
                }
            }
        }

        /*
         * Raw $_COOKIE as a last resort.
         *
         * `$request->cookie()` reads the bag Laravel built for *this* Request object. Lead capture
         * runs from a Livewire XHR and from WordPress hooks, where the Request is not always the
         * one that was captured from the live superglobals — and anything that decrypts cookies
         * drops a third-party one like `_fbp` rather than passing it through, because it cannot
         * be decrypted and is treated as tampered.
         *
         * The cookies that matter most here are exactly the ones Laravel never set: `_fbp` and
         * `_fbc` are written by Meta's pixel JS in the browser. Missing one is invisible — the
         * lead saves fine and Meta just matches fewer people — so it is worth reading them from
         * the place the browser actually put them.
         */
        foreach ($keys as $key) {
            $value = $_COOKIE[$key] ?? null;

            if (is_scalar($value)) {
                $clean = $this->clean((string) $value);

                if ($clean !== null) {
                    return $clean;
                }
            }
        }

        return null;
    }

    /**
     * When the `fbclid` this request carries was clicked, in milliseconds.
     *
     * From the URL it is this page load, so now. Remembered in `rl_fbclid`, it is the time
     * `rl_fbclid_ts` recorded on the landing page, which can be days back — and an `fbc` has to
     * say when the click happened, not when the form was sent. Anything implausible (missing, in
     * the future, older than Meta's 90-day `_fbc` lifetime) falls back to now.
     */
    protected function clickTimeMs(Request $request): int
    {
        $now = (int) round(microtime(true) * 1000);

        if ($this->clean((string) $request->query('fbclid', '')) !== null) {
            return $now;
        }

        $stamp = $request->cookie(self::FBCLID_TIME_COOKIE) ?? $_COOKIE[self::FBCLID_TIME_COOKIE] ?? null;

        if (! is_scalar($stamp) || ! ctype_digit((string) $stamp)) {
            return $now;
        }

        $stamp = (int) $stamp;

        return $stamp > $now || $stamp < $now - 90 * 24 * 60 * 60 * 1000 ? $now : $stamp;
    }

    /**
     * Trim, drop empties, strip control characters, and cap the length.
     */
    protected function clean(string $value): ?string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, self::MAX_VALUE_LENGTH);
    }

    protected function currentRequest(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
    }
}
