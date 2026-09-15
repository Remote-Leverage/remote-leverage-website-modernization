<?php

declare(strict_types=1);

use App\Domains\Sync\SyncClient;

/**
 * The sending half of the temporary body-credential bridge.
 *
 * Only the decision to attach the credential is covered here: the HTTP facade
 * is not available to this suite, so the request itself is exercised by the
 * puller and pusher tests instead. The decision is the part worth pinning down
 * anyway, because it is what keeps the credential out of request bodies on any
 * environment that has no business seeing it.
 */
function setBodyAuthWpEnv(?string $env): void
{
    if ($env === null) {
        unset($_ENV['WP_ENV'], $_SERVER['WP_ENV']);
        putenv('WP_ENV');

        return;
    }

    $_ENV['WP_ENV'] = $env;
    $_SERVER['WP_ENV'] = $env;
    putenv('WP_ENV='.$env);
}

/**
 * @param  array<string, mixed>  $staging
 */
function bodyAuthClient(array $staging, string $target = 'staging'): SyncClient
{
    config([
        'rl-sync.environments.staging' => array_merge([
            'url' => 'https://staging.example.com',
            'user' => 'sync-service',
            'app_password' => 'abcd efgh ijkl',
        ], $staging),
        'rl-sync.environments.production' => [
            'url' => 'https://example.com',
            'user' => 'sync-service',
            'app_password' => 'abcd efgh ijkl',
            'body_auth' => true,
        ],
    ]);

    return new SyncClient($target);
}

function bodyAuthIsEnabled(SyncClient $client): bool
{
    $method = new ReflectionMethod($client, 'bodyAuthEnabled');
    $method->setAccessible(true);

    return (bool) $method->invoke($client);
}

beforeEach(fn () => setBodyAuthWpEnv('development'));
afterEach(fn () => setBodyAuthWpEnv(null));

it('attaches the credential when the target opts in', function (mixed $flag) {
    expect(bodyAuthIsEnabled(bodyAuthClient(['body_auth' => $flag])))->toBeTrue();
})->with([true, 'true', '1', 1, 'on', 'yes']);

it('does not attach it unless the target opts in', function (mixed $flag) {
    expect(bodyAuthIsEnabled(bodyAuthClient(['body_auth' => $flag])))->toBeFalse();
})->with([false, 'false', '0', 0, '', 'nonsense']);

it('defaults to off when the environment says nothing', function () {
    expect(bodyAuthIsEnabled(bodyAuthClient([])))->toBeFalse();
});

it('refuses to attach it when targeting production, whatever the config says', function () {
    expect(bodyAuthIsEnabled(bodyAuthClient(['body_auth' => true], 'production')))->toBeFalse();
});

it('refuses to attach it when running in production, whatever the target', function () {
    setBodyAuthWpEnv('production');

    expect(bodyAuthIsEnabled(bodyAuthClient(['body_auth' => true])))->toBeFalse();
});
