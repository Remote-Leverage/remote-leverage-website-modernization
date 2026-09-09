<?php

declare(strict_types=1);

namespace App\Application\Http\Middleware;

class LegacyRedirectMiddleware
{
    /**
     * Resolve a request path against the legacy redirect map (ADR-0006 § SEO & Risk Mitigation).
     * Preserves the query string (UTM parameters, cookies referral markers) on the target.
     *
     * @param  array<string, string>  $map  Legacy path => canonical path (no leading slash)
     */
    public function resolve(string $requestPath, array $map, string $queryString = ''): ?string
    {
        $normalizedPath = trim(parse_url($requestPath, PHP_URL_PATH) ?: $requestPath, '/');

        if (! isset($map[$normalizedPath])) {
            return null;
        }

        $target = '/'.trim($map[$normalizedPath], '/');

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

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $queryString = (string) parse_url($requestUri, PHP_URL_QUERY);

        $target = $this->resolve($requestUri, config('redirects', []), $queryString);

        if ($target !== null) {
            wp_safe_redirect(home_url($target), 301);
            exit;
        }
    }
}
