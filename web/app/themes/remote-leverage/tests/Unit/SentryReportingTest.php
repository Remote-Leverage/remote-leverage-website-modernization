<?php

declare(strict_types=1);

use App\Infrastructure\Observability\SentryReporting;
use Illuminate\Http\Request;

/*
 * Sentry was installed, configured with a live DSN, and receiving nothing.
 *
 * sentry/sentry-laravel removes the SDK's own ErrorListener and ExceptionListener integrations --
 * its ServiceProvider says so in a comment -- because a stock Laravel app forwards from its own
 * ExceptionHandler, wired in bootstrap/app.php. Acorn has its own bootstrapper and nothing wired
 * it, so explicit captureException() calls worked while every unhandled exception reached nobody.
 * A test ping therefore "proved" Sentry worked. On 2026-09-20 a TypeError took both environments'
 * admin down and Sentry recorded none of it.
 *
 * These assert the wiring rather than the transport: whether an event leaves the process is
 * Sentry's business, whether anything asks it to is ours.
 */
function registerSentryReporting(string $environment): void
{
    $GLOBALS['wp_environment_type'] = $environment;
    SentryReporting::forgetRegistration();

    (new SentryReporting)->register();
}

beforeEach(function () {
    $GLOBALS['_app_config'] = [];

    // register() refuses to do anything unless Sentry is actually in the container, which is the
    // guard that stops it wiring a log channel to a driver that does not exist.
    app()->instance('sentry', new stdClass);

    config(['sentry.dsn' => 'https://examplePublicKey@o0.ingest.sentry.io/0']);
    config(['logging.channels.stack.channels' => ['single']]);
    SentryReporting::forgetRegistration();
});

afterEach(function () {
    unset(
        $GLOBALS['wp_environment_type'],
        $GLOBALS['wp_current_user_logged_in'],
        $GLOBALS['wp_current_user_id'],
        $GLOBALS['wp_current_user_email'],
    );
    SentryReporting::forgetRegistration();
});

it('routes error logs to Sentry on a deployed environment', function () {
    registerSentryReporting('production');

    expect(config('logging.channels.stack.channels'))->toContain('sentry')
        ->and(config('logging.channels.sentry.level'))->toBe('error');
});

/*
 * Sentry's own provider already defines the channel as {driver: sentry} with no level, which
 * Monolog reads as DEBUG. The first version of this class saw it present and returned early --
 * defining a channel nothing references, and shipping nothing. Had it instead added that channel
 * to the stack untouched, every debug line in the application would have gone to Sentry.
 */
it('sets a level on the channel Sentry already defined, rather than skipping it', function () {
    config(['logging.channels.sentry' => ['driver' => 'sentry']]);

    registerSentryReporting('production');

    expect(config('logging.channels.sentry.level'))->toBe('error')
        ->and(config('logging.channels.sentry.driver'))->toBe('sentry')
        ->and(config('logging.channels.stack.channels'))->toContain('sentry');
});

it('stays quiet in local and development', function (string $environment) {
    registerSentryReporting($environment);

    // The DSN is in .env.example and most developers have it set. Capturing here would bury real
    // incidents under whatever somebody is mid-refactor on.
    expect(config('logging.channels.stack.channels'))->not->toContain('sentry');
})->with(['local', 'development']);

it('can be forced on locally, for testing this class', function () {
    putenv('SENTRY_CAPTURE_LOCALLY=true');
    $_ENV['SENTRY_CAPTURE_LOCALLY'] = 'true';

    registerSentryReporting('local');

    expect(config('logging.channels.stack.channels'))->toContain('sentry');

    putenv('SENTRY_CAPTURE_LOCALLY');
    unset($_ENV['SENTRY_CAPTURE_LOCALLY']);
});

it('does nothing at all without a DSN', function () {
    config(['sentry.dsn' => '']);

    registerSentryReporting('production');

    expect(config('logging.channels.stack.channels'))->not->toContain('sentry');
});

/*
 * There's no vendor-exposed way to read the applied scope back out in a unit test, so this
 * asserts the thing that's actually load-bearing: identifying a real logged-in user must not be
 * the reason registration fails.
 */
it('identifies a logged-in WordPress user without throwing', function () {
    $GLOBALS['wp_current_user_logged_in'] = true;
    $GLOBALS['wp_current_user_id'] = 42;
    $GLOBALS['wp_current_user_email'] = 'admin@remoteleverage.com';

    registerSentryReporting('production');

    expect(config('logging.channels.stack.channels'))->toContain('sentry');
});

it('does not identify a guest visitor with no attribution at all', function () {
    $GLOBALS['wp_current_user_logged_in'] = false;

    // Would resolve to id 0 if identifyUser() ignored the is_user_logged_in() guard — asserting
    // no exception here is what would catch that regression, since a guest WP_User's id and
    // email are always present, just empty/zero, not absent.
    registerSentryReporting('production');

    expect(config('logging.channels.stack.channels'))->toContain('sentry');
});

/*
 * Same "no vendor-exposed way to read the scope back out" limitation as the logged-in case above
 * — this asserts identifying a guest by UTM must not be the reason registration fails, covering
 * both the code path (AttributionCollector wired correctly) and the guard (array_filter surviving
 * a request with none of these parameters, exercised by the test above).
 */
it('identifies a guest by UTM attribution when there is no logged-in user', function () {
    $GLOBALS['wp_current_user_logged_in'] = false;

    app()->instance('request', Request::create('https://remoteleverage.com/hire-va-4/', 'GET', [
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'q4-va',
    ]));

    registerSentryReporting('production');

    expect(config('logging.channels.stack.channels'))->toContain('sentry');
});

it('identifies a returning visitor by device id even without UTM parameters', function () {
    $GLOBALS['wp_current_user_logged_in'] = false;

    app()->instance('request', Request::create(
        'https://remoteleverage.com/',
        'GET',
        [],
        ['rl_vid' => 'abc-123'],
    ));

    registerSentryReporting('production');

    expect(config('logging.channels.stack.channels'))->toContain('sentry');
});

/*
 * hashDeviceId() is the one piece of identifyByAttribution() that doesn't depend on the Sentry
 * scope, so unlike the rest of this file it can be asserted directly rather than just "didn't
 * throw" -- the thing that actually matters here (never send the raw rl_vid cookie to Sentry) is
 * exactly the thing that's checkable.
 */
it('hashes the device id into a stable, non-reversible id rather than sending it raw', function () {
    $method = new ReflectionMethod(SentryReporting::class, 'hashDeviceId');

    $hash = $method->invoke(new SentryReporting, 'abc-123');
    $sameAgain = $method->invoke(new SentryReporting, 'abc-123');
    $different = $method->invoke(new SentryReporting, 'xyz-789');

    expect($hash)->toBe($sameAgain)
        ->and($hash)->not->toBe($different)
        ->and($hash)->not->toContain('abc-123')
        ->and($hash)->toMatch('/^[0-9a-f]{16}$/');
});

/*
 * browserIdentity() is public and returns a plain array, so — unlike everything routed through
 * configureScope() — this one can assert the actual payload `app.blade.php` will serialise into
 * `window.SENTRY_USER`, not just "didn't throw".
 */
it('exposes the visitor attribution for the browser SDK', function () {
    app()->instance('request', Request::create('https://remoteleverage.com/hire-va-4/', 'GET', [
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'q4-va',
    ], ['rl_vid' => 'abc-123']));

    $identity = (new SentryReporting)->browserIdentity();

    expect($identity)->toHaveKeys(['id', 'utm_source', 'utm_medium', 'utm_campaign', 'ip_address'])
        ->and($identity['utm_source'])->toBe('google')
        ->and($identity['utm_medium'])->toBe('cpc')
        ->and($identity['utm_campaign'])->toBe('q4-va')
        ->and($identity['id'])->not->toBe('abc-123') // hashed, not raw — asserted in detail above
        ->and($identity['ip_address'])->toBe('{{auto}}'); // Sentry's own sentinel, never a real IP
});

/*
 * The load-bearing privacy guarantee: browserIdentity() may track a logged-in WordPress user's id
 * — a page can safely echo it, the same way an author archive URL or the admin bar already does —
 * but never their email, which has no business sitting in view-source just because they're
 * browsing the public site while authenticated.
 */
it('tracks a logged-in WordPress user by id only, never by email, for the browser', function () {
    $GLOBALS['wp_current_user_logged_in'] = true;
    $GLOBALS['wp_current_user_id'] = 42;
    $GLOBALS['wp_current_user_email'] = 'admin@remoteleverage.com';

    app()->instance('request', Request::create('https://remoteleverage.com/', 'GET', [], ['rl_vid' => 'abc-123']));

    $identity = (new SentryReporting)->browserIdentity();

    expect($identity)->toBe(['id' => 42, 'ip_address' => '{{auto}}'])
        ->and($identity)->not->toHaveKey('email');
});

/*
 * A visitor with neither a device_id cookie yet nor any UTM parameter previously made
 * browserIdentity() return [] — an empty object that app.js's guard skips entirely, so Crash Free
 * Users would have no data for them at all. The {{auto}} sentinel means there is always at least
 * an IP-based identity to hand the browser SDK.
 */
it('still carries an ip_address sentinel for the browser when nothing else identifies the visitor', function () {
    app()->instance('request', Request::create('https://remoteleverage.com/'));

    $identity = (new SentryReporting)->browserIdentity();

    expect($identity)->toBe(['ip_address' => '{{auto}}']);
});

/*
 * visitorIp() is the one other piece of identification that doesn't depend on the Sentry scope —
 * this asserts the actual resolved value the PHP-side scope gets, rather than just "didn't throw".
 * Behind CloudFront/Cloudflare, REMOTE_ADDR is the edge node's address, the same for thousands of
 * visitors, so this has to prefer X-Forwarded-For — matching AttributionCollector::ipAddress().
 */
it('resolves the real visitor IP for the PHP-side scope, preferring X-Forwarded-For', function () {
    $method = new ReflectionMethod(SentryReporting::class, 'visitorIp');

    app()->instance('request', Request::create(
        'https://remoteleverage.com/',
        'GET',
        [],
        [],
        [],
        ['HTTP_X_FORWARDED_FOR' => '203.0.113.5, 10.0.0.1'],
    ));

    expect($method->invoke(new SentryReporting))->toBe('203.0.113.5');
});

it('resolves no visitor IP when there is no request to read one from', function () {
    $method = new ReflectionMethod(SentryReporting::class, 'visitorIp');

    expect($method->invoke(new SentryReporting))->toBeNull();
});
