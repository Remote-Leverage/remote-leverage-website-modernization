<?php

/**
 * Plugin Name: RL MCP session bridge
 * Description: Re-attaches the Mcp-Session-Id header that CloudFront strips, so MCP calls after initialize can resolve their session.
 * Version: 1.0.0
 *
 * TEMPORARY. Remove once the CloudFront distribution forwards Mcp-Session-Id to
 * this origin, exactly as the sibling rl-sync-body-auth.php is waiting to be
 * removed for the Authorization half of the same misconfiguration.
 * Target for removal: 2026-10-15. Tracked as known-issues.md item 20.
 *
 * Why this exists
 * ---------------
 * The MCP HTTP transport issues a session id on `initialize` and expects it back
 * as an Mcp-Session-Id header on every subsequent call. The distribution in front
 * of this origin forwards Authorization but not Mcp-Session-Id, so `initialize`
 * succeeds and everything after it fails with "Missing Mcp-Session-Id header" —
 * which in a Claude session looks like an empty tool list and no error at all.
 *
 * known-issues.md item 20 recorded that there was no application-side workaround,
 * on the grounds that the adapter reads the header straight off the request, has
 * no query-string or body fallback, and exposes no stateless mode. All three are
 * true. The conclusion does not follow, because the fallback does not have to
 * live in the adapter: HttpRequestContext reads the header off the WP_REST_Request,
 * and rest_pre_dispatch hands us that same object after authentication has
 * resolved and before the route callback builds the context.
 *
 * What this does, and deliberately does not do
 * --------------------------------------------
 * It restores a session the authenticated user already established, and nothing
 * else. It never mints one — a client that never called `initialize`, or whose
 * session has expired, gets the adapter's own error so it reinitializes rather
 * than silently continuing against a session conjured on its behalf.
 *
 * It performs NO authorization of its own and weakens none. The session was never
 * the authorization boundary: sessions are keyed per user, the Authorization
 * header still authenticates every request, and each ability's own permission()
 * check still runs. A caller gains nothing here that it would not have gained by
 * echoing back the header it was issued.
 *
 * The one real consequence: because sessions are per user and this picks the
 * user's most recent live one, two clients authenticating as the same WordPress
 * user share a transport session. That is acceptable for ai-content-agent, whose
 * sessions carry only the initialize handshake parameters, and it is another
 * reason to prefer fixing the distribution over keeping this.
 */

declare(strict_types=1);

if (! function_exists('rl_mcp_session_bridge_is_mcp_route')) {
    /**
     * Whether this REST route belongs to the MCP transport.
     *
     * Matched on the namespace rather than the full route, because the server
     * route segment is configurable (mcp-adapter-default-server is only the
     * default) and a second registered server should be bridged too.
     */
    function rl_mcp_session_bridge_is_mcp_route(string $route): bool
    {
        $route = '/'.ltrim($route, '/');

        return str_starts_with($route, '/mcp/');
    }
}

if (! function_exists('rl_mcp_session_bridge_should_bridge')) {
    /**
     * Whether a session id should be restored onto this request.
     *
     * The body is read through a callable so it is only decoded once the cheap
     * gates have passed; an ordinary front-end request never reaches it.
     *
     * @param  callable(): (array<string, mixed>|null)  $body
     */
    function rl_mcp_session_bridge_should_bridge(
        string $route,
        string $method,
        ?string $existingHeader,
        callable $body
    ): bool {
        if (! rl_mcp_session_bridge_is_mcp_route($route)) {
            return false;
        }

        // A header that survived the hop always wins. This must be a stand-in
        // for a stripped header, never a way to talk over one that arrived —
        // otherwise it would keep masking the problem after CloudFront is fixed,
        // and this file is meant to become a no-op on that day.
        if ($existingHeader !== null && trim($existingHeader) !== '') {
            return false;
        }

        // GET is reserved for SSE and carries no JSON-RPC body to inspect.
        // DELETE terminates a session and needs a real id, so it is bridged.
        if (strtoupper($method) === 'GET') {
            return false;
        }

        $decoded = $body();

        if (! is_array($decoded)) {
            return true;
        }

        // initialize is the one call that needs no session, because it is what
        // creates one. Bridging it would hand the handshake a stale id.
        return ($decoded['method'] ?? null) !== 'initialize';
    }
}

if (! function_exists('rl_mcp_session_bridge_pick')) {
    /**
     * The user's most recently active session that has not expired.
     *
     * Mirrors SessionManager::validate_session's expiry arithmetic so this does
     * not hand back an id the validator is about to reject. Where the two ever
     * disagree the validator wins, and the client reinitializes — which is the
     * safe direction for them to disagree in.
     *
     * @param  array<string, array<string, mixed>>  $sessions
     */
    function rl_mcp_session_bridge_pick(array $sessions, int $now, int $inactivityTimeout): ?string
    {
        $newestId = null;
        $newestActivity = null;

        foreach ($sessions as $sessionId => $session) {
            if (! is_string($sessionId) || $sessionId === '' || ! is_array($session)) {
                continue;
            }

            $lastActivity = $session['last_activity'] ?? null;

            if (! is_int($lastActivity)) {
                continue;
            }

            if ($lastActivity + $inactivityTimeout < $now) {
                continue;
            }

            if ($newestActivity === null || $lastActivity > $newestActivity) {
                $newestId = $sessionId;
                $newestActivity = $lastActivity;
            }
        }

        return $newestId;
    }
}

if (! function_exists('rl_mcp_session_bridge_dispatch')) {
    /**
     * Restore the stripped header before the MCP route callback reads it.
     *
     * @param  mixed  $result  Non-null when an earlier filter already answered.
     * @param  mixed  $server  The REST server, unused.
     * @return mixed
     */
    function rl_mcp_session_bridge_dispatch($result, $server, $request)
    {
        if ($result !== null) {
            return $result;
        }

        if (! $request instanceof WP_REST_Request) {
            return $result;
        }

        $sessionManager = 'WP\\MCP\\Transport\\Infrastructure\\SessionManager';

        if (! class_exists($sessionManager)) {
            return $result;
        }

        $shouldBridge = rl_mcp_session_bridge_should_bridge(
            (string) $request->get_route(),
            (string) $request->get_method(),
            $request->get_header('Mcp-Session-Id'),
            static fn (): ?array => $request->get_json_params()
        );

        if (! $shouldBridge) {
            return $result;
        }

        // Authentication has already run by rest_pre_dispatch, so an application
        // password has resolved to a real user by now. Anonymous requests are
        // left alone to collect the adapter's own unauthorized error.
        $userId = get_current_user_id();

        if (! $userId) {
            return $result;
        }

        $timeout = (int) apply_filters('mcp_adapter_session_inactivity_timeout', DAY_IN_SECONDS);

        $sessionId = rl_mcp_session_bridge_pick(
            $sessionManager::get_all_user_sessions($userId),
            time(),
            $timeout
        );

        if ($sessionId === null) {
            return $result;
        }

        $request->set_header('Mcp-Session-Id', $sessionId);

        return $result;
    }
}

// Guarded so the pure functions above can be loaded by the test suite without
// registering a hook against the test process, matching rl-sync-body-auth.php.
if (defined('ABSPATH')) {
    add_filter('rest_pre_dispatch', 'rl_mcp_session_bridge_dispatch', 10, 3);
}
