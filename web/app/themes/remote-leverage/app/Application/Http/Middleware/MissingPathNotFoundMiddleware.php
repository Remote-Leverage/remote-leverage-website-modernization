<?php

declare(strict_types=1);

namespace App\Application\Http\Middleware;

use Illuminate\Routing\Route;

/**
 * Forces a real 404 for pretty-permalink paths that WordPress failed to resolve.
 *
 * WHY THIS EXISTS
 *
 * `WP_Rewrite::$use_verbose_page_rules` is true on this site, because the permalink
 * structure (`/blog/%postname%/`) matches core's `/^[^%]*%(?:postname|category|tag|author)%/`
 * test. In verbose mode, `WP::parse_request()` does not trust the catch-all page rule —
 * it looks the page up first, and if it does not exist it `continue`s past the rule
 * (wp-includes/class-wp.php, "This is a verbose page match, let's check to be sure about it").
 *
 * When no later rule matches, WordPress is left with **no query vars at all**. An empty
 * query is the default home query, which returns posts — so `handle_404()` never fires and
 * the request renders the front page with `200 OK`.
 *
 * The effect is worse than a soft 404: every unresolved path serves a byte-for-byte copy of
 * the homepage, so crawlers can index unlimited duplicate URLs and every broken internal
 * link looks like it works.
 *
 * Requests that arrive with explicit query vars (`?p=`, `?pagename=`, feeds, REST) are
 * unaffected by the verbose-rule skip and already 404 correctly, so they are left alone.
 */
class MissingPathNotFoundMiddleware
{
    /**
     * Decide whether an unresolved request should be turned into a 404.
     *
     * @param  string  $requestPath  Raw request URI.
     * @param  string|null  $matchedRule  `WP::$matched_rule` — non-empty when a rewrite rule resolved the request.
     * @param  array<string, mixed>  $queryVars  `WP::$query_vars` as parsed.
     * @param  list<string>  $routeUris  Acorn/Laravel route paths, which WordPress never resolves.
     */
    public function shouldForce404(string $requestPath, ?string $matchedRule, array $queryVars, array $routeUris = []): bool
    {
        $path = trim((string) (parse_url($requestPath, PHP_URL_PATH) ?: $requestPath), '/');

        // The front page itself is legitimately an empty path.
        if ($path === '') {
            return false;
        }

        // A rewrite rule resolved the request: WordPress knows what this is.
        if (! empty($matchedRule)) {
            return false;
        }

        // Explicit query vars (?p=, ?pagename=, ?s=, feed, rest_route) bypass the verbose-rule
        // skip entirely and already 404 correctly when the target is missing.
        if (! empty($queryVars)) {
            return false;
        }

        // Paths served by Acorn as Laravel routes have no WordPress counterpart by design.
        return ! in_array($path, $routeUris, true);
    }

    /**
     * Run on `parse_request`, after Acorn has had its chance to dispatch a Laravel route.
     */
    public function handle(\WP $wp): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $shouldForce = $this->shouldForce404(
            (string) ($_SERVER['REQUEST_URI'] ?? ''),
            $wp->matched_rule ?? null,
            (array) $wp->query_vars,
            $this->routeUris(),
        );

        if (! $shouldForce) {
            return;
        }

        // `WP::send_headers()` reads this and sends a real 404 status; `WP_Query` calls
        // set_404() so the 404 template renders instead of the front page.
        $wp->query_vars = ['error' => '404'];
    }

    /**
     * Registered Acorn route paths, excluding Acorn's own catch-all "wordpress" route
     * (which matches everything and would make this guard a no-op).
     *
     * @return list<string>
     */
    protected function routeUris(): array
    {
        try {
            if (! function_exists('app') || ! app()->bound('router')) {
                return [];
            }

            return collect(app('router')->getRoutes()->getRoutes())
                ->reject(fn (Route $route) => $route->getName() === 'wordpress')
                ->map(fn (Route $route) => trim($route->uri(), '/'))
                ->values()
                ->all();
        } catch (\Throwable) {
            // Never let a routing lookup turn a page request into an error.
            return [];
        }
    }
}
