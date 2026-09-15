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

        if (! isset($map[$normalizedPath])) {
            return null;
        }

        $mapped = $map[$normalizedPath];

        if (self::isExternalTarget($mapped)) {
            // Absolute targets are emitted verbatim — trimming slashes here would corrupt the
            // scheme. A target may already carry its own query string, so pick the separator.
            if ($queryString === '') {
                return $mapped;
            }

            return $mapped.(str_contains($mapped, '?') ? '&' : '?').$queryString;
        }

        $target = '/'.trim($mapped, '/');

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
