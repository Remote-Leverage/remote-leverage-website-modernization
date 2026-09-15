<?php

declare(strict_types=1);

/**
 * The body-credential bridge in web/app/mu-plugins/rl-sync-body-auth.php.
 *
 * It lives outside the theme because it has to run before determine_current_user,
 * which is long before a theme boots. It is still tested here because this is the
 * only suite CI runs, and an unguarded version of this file would widen the way
 * into an endpoint that writes rows and files.
 *
 * Every gate therefore gets a test for what it refuses, not only for what it lets
 * through. The bridge is temporary; these tests are what stop it rotting open
 * while it waits to be deleted.
 */
require_once dirname(__DIR__, 4).'/mu-plugins/rl-sync-body-auth.php';

/**
 * A request that passes every gate, so each test can spoil exactly one thing.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bodyAuthServer(array $overrides = []): array
{
    return array_merge([
        'REQUEST_METHOD' => 'POST',
        'HTTPS' => 'on',
        'CONTENT_TYPE' => 'application/json',
        'REQUEST_URI' => '/wp-json/wp-abilities/v1/abilities/app%2Fexport-transfer-batch/run',
    ], $overrides);
}

function bodyAuthBody(mixed $auth = ['user' => 'sync-service', 'password' => 'abcd efgh ijkl']): string
{
    $payload = ['input' => ['session_id' => 'abc']];

    if ($auth !== null) {
        $payload['_rl_sync_auth'] = $auth;
    }

    return (string) json_encode($payload);
}

/**
 * @param  array<string, mixed>  $server
 * @return array{user: string, password: string}|null
 */
function resolveBodyAuth(array $server, string $body = '', string $env = 'staging'): ?array
{
    return rl_sync_body_auth_credentials($server, static fn (): string => $body, $env);
}

it('injects the credential for a well-formed abilities request', function () {
    expect(resolveBodyAuth(bodyAuthServer(), bodyAuthBody()))
        ->toBe(['user' => 'sync-service', 'password' => 'abcd efgh ijkl']);
});

it('accepts the plain-permalink form of the same route', function () {
    $server = bodyAuthServer([
        'REQUEST_URI' => '/?rest_route=/wp-abilities/v1/abilities/app%2Fbegin-transfer/run',
    ]);

    expect(resolveBodyAuth($server, bodyAuthBody()))->not->toBeNull();
});

it('refuses outside the environments sync may run in', function (string $env) {
    expect(resolveBodyAuth(bodyAuthServer(), bodyAuthBody(), $env))->toBeNull();
})->with(['production', 'PRODUCTION', '', 'developmnet', 'prod', 'test']);

it('allows only the environments sync may run in', function (string $env) {
    expect(resolveBodyAuth(bodyAuthServer(), bodyAuthBody(), $env))->not->toBeNull();
})->with(['development', 'local', 'staging', 'STAGING']);

it('refuses any method other than POST', function (string $method) {
    $server = bodyAuthServer(['REQUEST_METHOD' => $method]);

    expect(resolveBodyAuth($server, bodyAuthBody()))->toBeNull();
})->with(['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS']);

it('refuses a request that is not over HTTPS', function (mixed $https) {
    $server = bodyAuthServer(['HTTPS' => $https]);

    expect(resolveBodyAuth($server, bodyAuthBody()))->toBeNull();
})->with(['', 'off', 'OFF']);

it('refuses when HTTPS is absent entirely', function () {
    $server = bodyAuthServer();
    unset($server['HTTPS']);

    expect(resolveBodyAuth($server, bodyAuthBody()))->toBeNull();
});

it('refuses any path outside the sync abilities endpoint', function (string $uri) {
    $server = bodyAuthServer(['REQUEST_URI' => $uri]);

    expect(resolveBodyAuth($server, bodyAuthBody()))->toBeNull();
})->with([
    '/wp-json/wp/v2/posts',
    '/wp-json/wp/v2/users/me',
    '/wp-login.php',
    '/',
    '/wp-json/',
    '/?rest_route=/wp/v2/posts',
    '/wp-json/wp-abilities/v1/abilities',
    '/evil/wp-json/wp-abilities/v1/abilities/x/run',
]);

it('never displaces a credential that actually arrived', function (string $key) {
    $server = bodyAuthServer([$key => 'someone-else']);

    expect(resolveBodyAuth($server, bodyAuthBody()))->toBeNull();
})->with(['PHP_AUTH_USER', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION']);

it('refuses a body that is not JSON', function (string $contentType) {
    $server = bodyAuthServer(['CONTENT_TYPE' => $contentType]);

    expect(resolveBodyAuth($server, bodyAuthBody()))->toBeNull();
})->with([
    'multipart/form-data; boundary=x',
    'application/x-www-form-urlencoded',
    'text/plain',
    '',
]);

it('refuses a body carrying no credential', function (mixed $auth) {
    expect(resolveBodyAuth(bodyAuthServer(), bodyAuthBody($auth)))->toBeNull();
})->with([
    null,
    fn () => ['user' => 'sync-service'],
    fn () => ['password' => 'abcd efgh ijkl'],
    fn () => ['user' => '', 'password' => 'abcd efgh ijkl'],
    fn () => ['user' => 'sync-service', 'password' => ''],
    fn () => ['user' => ['array'], 'password' => 'abcd efgh ijkl'],
    fn () => ['user' => 'sync-service', 'password' => 12345],
    fn () => 'not-an-array',
]);

it('refuses an unparseable or empty body', function (string $body) {
    expect(resolveBodyAuth(bodyAuthServer(), $body))->toBeNull();
})->with(['', '{', 'null', '[]', 'not json at all']);

it('does not touch the input stream unless every cheap gate has passed', function () {
    $read = false;

    $body = function () use (&$read): string {
        $read = true;

        return bodyAuthBody();
    };

    // A plain front-end GET: the common case, where reading php://input would
    // be both pointless and, on a multipart request, destructive.
    rl_sync_body_auth_credentials(
        bodyAuthServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
        $body,
        'staging',
    );

    expect($read)->toBeFalse();
});
