<?php

declare(strict_types=1);

/**
 * The session bridge in web/app/mu-plugins/rl-mcp-session-bridge.php.
 *
 * It lives outside the theme because it hooks rest_pre_dispatch, which fires on
 * requests that never boot a theme. It is tested here because this is the only
 * suite CI runs.
 *
 * The bridge is temporary — it stands in for a CloudFront policy that does not
 * forward Mcp-Session-Id — so the tests that matter most are the ones pinning
 * what it refuses. Two in particular are what stop it rotting open: it must go
 * inert the moment a real header arrives, and it must never bridge `initialize`,
 * which is the call that mints the session in the first place.
 */
require_once dirname(__DIR__, 4).'/mu-plugins/rl-mcp-session-bridge.php';

const MCP_ROUTE = '/mcp/mcp-adapter-default-server';

/**
 * @param  array<string, mixed>|null  $body
 */
function shouldBridge(
    string $route = MCP_ROUTE,
    string $method = 'POST',
    ?string $header = null,
    ?array $body = ['method' => 'tools/list'],
): bool {
    return rl_mcp_session_bridge_should_bridge(
        $route,
        $method,
        $header,
        static fn (): ?array => $body,
    );
}

/**
 * @return array<string, array<string, mixed>>
 */
function sessionsFixture(int $now): array
{
    return [
        'older' => ['created_at' => $now - 900, 'last_activity' => $now - 600],
        'newest' => ['created_at' => $now - 300, 'last_activity' => $now - 60],
        'stale' => ['created_at' => $now - 9000, 'last_activity' => $now - 8000],
    ];
}

it('bridges an ordinary MCP call whose header was stripped', function () {
    expect(shouldBridge())->toBeTrue();
});

it('goes inert as soon as a real header survives the hop', function (string $header) {
    expect(shouldBridge(header: $header))->toBeFalse();
})->with(['3f2a', '  3f2a  ']);

it('still bridges when the header arrives empty rather than absent', function (string $header) {
    expect(shouldBridge(header: $header))->toBeTrue();
})->with(['', '   ']);

it('never bridges initialize, which is what mints the session', function () {
    expect(shouldBridge(body: ['method' => 'initialize']))->toBeFalse();
});

it('bridges every other JSON-RPC method', function (string $method) {
    expect(shouldBridge(body: ['method' => $method]))->toBeTrue();
})->with(['tools/list', 'tools/call', 'resources/list', 'prompts/list', 'ping']);

it('refuses GET, which is reserved for SSE and carries no body', function () {
    expect(shouldBridge(method: 'GET'))->toBeFalse();
});

it('bridges DELETE, because terminating a session needs a real id', function () {
    expect(shouldBridge(method: 'DELETE', body: null))->toBeTrue();
});

it('bridges when the body is absent or undecodable', function (?array $body) {
    expect(shouldBridge(body: $body))->toBeTrue();
})->with([[null], [[]]]);

it('refuses any route outside the MCP namespace', function (string $route) {
    expect(shouldBridge(route: $route))->toBeFalse();
})->with([
    '/wp/v2/posts',
    '/wp-abilities/v1/abilities/app%2Flist-pages/run',
    '/mcp-adapter/discover-abilities',
    '/not-mcp/thing',
    '',
]);

it('matches the MCP namespace with or without a leading slash', function (string $route) {
    expect(rl_mcp_session_bridge_is_mcp_route($route))->toBeTrue();
})->with([MCP_ROUTE, 'mcp/mcp-adapter-default-server', '/mcp/second-server']);

it('picks the most recently active session', function () {
    $now = 1_700_000_000;

    expect(rl_mcp_session_bridge_pick(sessionsFixture($now), $now, DAY_IN_SECONDS))
        ->toBe('newest');
});

it('skips sessions the validator is about to expire', function () {
    $now = 1_700_000_000;

    // A timeout short enough to expire every entry but the newest.
    expect(rl_mcp_session_bridge_pick(sessionsFixture($now), $now, 120))
        ->toBe('newest');
});

it('returns null rather than a session that would be rejected', function () {
    $now = 1_700_000_000;

    expect(rl_mcp_session_bridge_pick(sessionsFixture($now), $now, 30))->toBeNull();
});

it('returns null when the user has no sessions at all', function () {
    expect(rl_mcp_session_bridge_pick([], 1_700_000_000, DAY_IN_SECONDS))->toBeNull();
});

it('ignores malformed session rows instead of trusting them', function (array $sessions) {
    expect(rl_mcp_session_bridge_pick($sessions, 1_700_000_000, DAY_IN_SECONDS))->toBeNull();
})->with([
    'no last_activity' => [['a' => ['created_at' => 1_700_000_000]]],
    'string timestamp' => [['a' => ['last_activity' => '1700000000']]],
    'row is not an array' => [['a' => 'nope']],
    'empty session id' => [['' => ['last_activity' => 1_700_000_000]]],
]);
