<?php

/**
 * Plugin Name: RL Sync body-credential bridge
 * Description: Restores Basic credentials that CloudFront strips from sync requests, so WordPress can authenticate them normally.
 * Version: 1.0.0
 *
 * TEMPORARY. Remove once the CloudFront distribution forwards the Authorization
 * header to this origin. Tracked as the sibling of the /livewire-* header hack
 * in docker/nginx.conf, which is the same misconfiguration worked around once
 * already. Target for removal: 2026-10-15.
 *
 * Why this exists
 * ---------------
 * CloudFront removes the Authorization header before forwarding to the origin
 * unless a cache or origin request policy carries it. This distribution does
 * not, so every WordPress Application Password request arrives unauthenticated
 * and Settings -> Environment Sync cannot reach staging at all.
 *
 * What this does, and deliberately does not do
 * -------------------------------------------
 * It moves the credential from a header CloudFront drops into a request body
 * field CloudFront always forwards, and hands it to WordPress through the same
 * superglobals a normal Basic request would have populated.
 *
 * It performs NO verification of its own. wp_authenticate_application_password()
 * still does the lookup, the hash comparison, the rate limiting and the
 * application_password_failed_authentication hook. A caller without a valid
 * application password gains nothing here that it would not have gained by
 * sending the same credential in a header.
 */

declare(strict_types=1);

if (! function_exists('rl_sync_body_auth_is_abilities_request')) {
    /**
     * Whether this request targets the sync abilities endpoint.
     *
     * Matched against the raw request URI rather than a parsed REST route,
     * because mu-plugins load long before the REST server exists.
     *
     * @param  array<string, mixed>  $server
     */
    function rl_sync_body_auth_is_abilities_request(array $server): bool
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '');

        if ($uri === '') {
            return false;
        }

        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '');

        if ($path !== '' && str_starts_with($path, '/wp-json/wp-abilities/v1/abilities/')) {
            return true;
        }

        // The plain-permalink form of the same route.
        $query = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');

        if ($query === '') {
            return false;
        }

        parse_str($query, $args);

        $route = (string) ($args['rest_route'] ?? '');

        return $route !== '' && str_starts_with($route, '/wp-abilities/v1/abilities/');
    }
}

if (! function_exists('rl_sync_body_auth_credentials')) {
    /**
     * Credentials to inject, or null when any gate refuses.
     *
     * The body is read through a callable so it is only pulled off the input
     * stream once every cheap gate has already passed. On an ordinary front-end
     * request nothing is read at all.
     *
     * @param  array<string, mixed>  $server
     * @param  callable(): string  $body
     * @return array{user: string, password: string}|null
     */
    function rl_sync_body_auth_credentials(array $server, callable $body, string $env): ?array
    {
        // Mirrors App\Domains\Sync\SyncEnvironment: an allowlist, so an absent
        // or misspelled WP_ENV refuses rather than defaulting open. Duplicated
        // rather than imported because mu-plugins load before the theme.
        if (! in_array(strtolower($env), ['development', 'local', 'staging'], true)) {
            return null;
        }

        if (strtoupper((string) ($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return null;
        }

        // A genuine credential always wins. A body field must never be able to
        // displace one, or this becomes a way to talk over the real header
        // rather than a stand-in for a missing one.
        foreach (['PHP_AUTH_USER', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (! empty($server[$key])) {
                return null;
            }
        }

        // Application Passwords are an HTTPS-only feature in core, and this
        // must not become the one path that quietly relaxes that.
        $https = strtolower((string) ($server['HTTPS'] ?? ''));

        if ($https === '' || $https === 'off') {
            return null;
        }

        if (! rl_sync_body_auth_is_abilities_request($server)) {
            return null;
        }

        // JSON only. Reading php://input on a multipart request consumes a
        // stream PHP cannot rewind, which would corrupt any upload.
        if (! str_contains(strtolower((string) ($server['CONTENT_TYPE'] ?? '')), 'application/json')) {
            return null;
        }

        $raw = $body();

        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['_rl_sync_auth']) || ! is_array($decoded['_rl_sync_auth'])) {
            return null;
        }

        $user = $decoded['_rl_sync_auth']['user'] ?? null;
        $password = $decoded['_rl_sync_auth']['password'] ?? null;

        if (! is_string($user) || ! is_string($password) || $user === '' || $password === '') {
            return null;
        }

        return ['user' => $user, 'password' => $password];
    }
}

// Guarded so the pure functions above can be loaded by the test suite without
// the bootstrap firing against the test process's own superglobals.
if (defined('ABSPATH')) {
    $rl_sync_body_auth = rl_sync_body_auth_credentials(
        $_SERVER,
        static fn (): string => (string) file_get_contents('php://input'),
        defined('WP_ENV') ? (string) WP_ENV : 'production',
    );

    if ($rl_sync_body_auth !== null) {
        $_SERVER['PHP_AUTH_USER'] = $rl_sync_body_auth['user'];
        $_SERVER['PHP_AUTH_PW'] = $rl_sync_body_auth['password'];
    }

    unset($rl_sync_body_auth);
}
