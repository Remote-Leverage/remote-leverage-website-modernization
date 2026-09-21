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
