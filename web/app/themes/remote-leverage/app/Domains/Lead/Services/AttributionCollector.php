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
 * Every value is read query-string first, then cookie. HandL writes its cookies on the landing
 * page, so a visitor who browses several pages before converting still carries first-touch data
 * the current URL no longer shows.
 */
class AttributionCollector
{
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
            $value = $this->firstOf($request, $keys);

            if ($value !== null) {
                $named[$column] = $value;
            }
        }

        $extra = [];

        foreach (self::EXTRA as $key) {
            $value = $this->firstOf($request, [$key]);

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
         */
        if (($named['fbc'] ?? '') === '' && ($named['fbclid'] ?? '') !== '') {
            $named['fbc'] = sprintf('fb.1.%d.%s', (int) round(microtime(true) * 1000), $named['fbclid']);
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
     * First non-empty value among these keys, query string before cookie.
     *
     * @param  string[]  $keys
     */
    protected function firstOf(Request $request, array $keys): ?string
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
