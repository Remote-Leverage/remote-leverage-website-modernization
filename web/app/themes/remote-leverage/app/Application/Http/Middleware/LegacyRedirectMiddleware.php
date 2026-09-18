<?php

declare(strict_types=1);

namespace App\Application\Http\Middleware;

class LegacyRedirectMiddleware
{
    /**
     * Is this map target an absolute external destination rather than a site-relative path?
     *
     * Only `http://` and `https://` absolute URLs qualify, and only when they carry a host —
     * so a protocol-relative `//evil.com`, a `javascript:` payload or a stray `https:/x`
     * typo is treated as a relative path and ends up under home_url(), never as an
     * off-site jump.
     */
    public static function isExternalTarget(string $target): bool
    {
        if (! preg_match('#^https?://#i', $target)) {
            return false;
        }

        return (string) (parse_url($target, PHP_URL_HOST) ?: '') !== '';
    }

    /**
     * Every external host named by the static redirect map.
     *
     * This is the only source of off-site redirect hosts. It is derived from config
     * exclusively — no request value ever reaches it — which is what keeps the absolute
     * targets below from being an open redirect.
     *
     * @param  array<string, string>  $map
     * @return list<string>
     */
    public static function externalHosts(array $map): array
    {
        $hosts = [];

        foreach ($map as $target) {
            if (self::isExternalTarget($target)) {
                $hosts[] = (string) parse_url($target, PHP_URL_HOST);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Does this site-relative target name a file rather than a permalink?
     *
     * Decided on the extension, which separates the two cleanly in this map: the social-kit
     * asset targets all end in one, and every page-slug target is bare.
     */
    public static function isFileTarget(string $target): bool
    {
        return preg_match('#\.[A-Za-z0-9]{2,5}$#', $target) === 1;
    }

    /**
     * Fall back to a case-insensitive lookup when the exact key misses.
     *
     * The GoDaddy install matched case-insensitively — every rule in its `301-redirects` table
     * carried `case_insensitive=enabled` — and real traffic depends on it. The /vastore5/
     * objection-handling script sends people to /Deposit/ and /Refund/ with a capital letter,
     * and the recruiting funnel's /apply link is pasted into job posts in whatever case the
     * poster typed. Exact matching turned all of those into 404s at cutover.
     *
     * Only the *key* is folded. Targets are returned with their original case because case is
     * load-bearing there: the social-kit asset paths resolve to real files on a case-sensitive
     * filesystem, and the paths of the absolute targets are case-sensitive to their host.
     *
     * `array_change_key_case()` is ASCII-only and locale-independent, so it lowercases the hex
     * of a percent-encoded key without touching the UTF-8 bytes behind it — which also makes
     * `%CA%BB` and `%ca%bb` match, as the RFC says they should.
     *
     * Folding happens only after the exact lookup misses, so a request that hits a key — or an
     * ordinary page view that hits none — still costs a single hash lookup.
     *
     * @param  array<string, string>  $map
     */
    private static function matchCaseInsensitively(string $path, array $map): ?string
    {
        return array_change_key_case($map, CASE_LOWER)[strtolower($path)] ?? null;
    }

    /**
     * Resolve a request path against the legacy redirect map (ADR-0006 § SEO & Risk Mitigation).
     * Preserves the query string (UTM parameters, cookies referral markers) on the target.
     *
     * The request only ever selects a *key*. The returned target is read from `$map` and from
     * nowhere else, so a URL supplied by the visitor — in the path, in the query string, in a
     * header — can never become the destination. Keep it that way: never merge request data
     * into `$map`, and never let a caller pass a target through.
     *
     * @param  array<string, string>  $map  Legacy path => canonical path (no leading slash), or
     *                                      an absolute http(s):// URL for a destination that
     *                                      lives off this site
     */
    public function resolve(string $requestPath, array $map, string $queryString = ''): ?string
    {
        $normalizedPath = trim(parse_url($requestPath, PHP_URL_PATH) ?: $requestPath, '/');

        $mapped = $map[$normalizedPath] ?? self::matchCaseInsensitively($normalizedPath, $map);

        if ($mapped === null) {
            return null;
        }

        if (self::isExternalTarget($mapped)) {
            // Absolute targets are emitted verbatim — trimming slashes here would corrupt the
            // scheme. A target may already carry its own query string, so pick the separator.
            if ($queryString === '') {
                return $mapped;
            }

            return $mapped.(str_contains($mapped, '?') ? '&' : '?').$queryString;
        }

        $target = '/'.trim($mapped, '/');
        // Permalinks are slashed. Emitting /hire-va-4 used to force a second
        // redirect_canonical hop — which is a PHP miss, and is what made
        // "the redirects are down" look the same as /hire-va-4 hanging.
        //
        // A file target is the exception, and slashing one is a 404: the 36 social-kit assets
        // resolve to real files, and /...PersonalBanner_01_4400x1100.jpg/ matches neither
        // `try_files $uri` nor `$uri/` in docker/nginx.conf, so it falls through to index.php.
        // Page slugs never carry an extension — all 192 of the others are bare.
        if ($target !== '/' && ! self::isFileTarget($target)) {
            $target .= '/';
        }

        return $queryString !== '' ? $target.'?'.$queryString : $target;
    }

    /**
     * Run on every frontend request (wired via `template_redirect` in app/setup.php,
     * since WordPress page requests are not routed through Acorn's HTTP kernel).
     */
    public function handle(): void
    {
        if (is_admin()) {
            return;
        }

        $map = config('redirects', []);

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $queryString = (string) parse_url($requestUri, PHP_URL_QUERY);

        $target = $this->resolve($requestUri, $map, $queryString);

        if ($target === null) {
            return;
        }

        if (self::isExternalTarget($target)) {
            // Still wp_safe_redirect(), not wp_redirect(): the allowlist is widened only by the
            // hosts the static map itself names, so even a bug upstream of here cannot send a
            // visitor to a host that is not written down in config/redirects.php.
            $allowed = self::externalHosts($map);

            add_filter('allowed_redirect_hosts', static fn (array $hosts): array => array_merge($hosts, $allowed));

            wp_safe_redirect($target, 301);
            exit;
        }

        wp_safe_redirect(home_url($target), 301);
        exit;
    }
}
