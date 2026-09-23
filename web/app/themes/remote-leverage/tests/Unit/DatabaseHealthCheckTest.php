<?php

declare(strict_types=1);

use App\Infrastructure\Observability\Health\Checks\DatabaseHealthCheck;
use App\Infrastructure\Observability\Health\HealthStatus;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\DatabaseManager;

/*
 * DatabaseHealthCheck is the one check that probes live rather than reading
 * `rl_integration_calls` — see SelfEvaluatingHealthCheck for why. These tests run against the
 * real (in-memory sqlite) connection this suite already boots, so "healthy" is exercised for
 * real; the failure path is exercised by pointing the connection at something that cannot
 * possibly answer.
 */

beforeEach(function () {
    config(['health.database.degraded_latency_ms' => 200]);
});

test('is always configured — the database is not an optional credential', function () {
    expect((new DatabaseHealthCheck)->isConfigured())->toBeTrue();
});

test('a real successful query reports healthy with the measured latency in the reason', function () {
    $health = (new DatabaseHealthCheck)->probe();

    expect($health->status)->toBe(HealthStatus::Healthy)
        ->and($health->configured)->toBeTrue()
        ->and($health->reason)->toMatch('/^Connected in \d+ms$/')
        ->and($health->sampleSize)->toBe(1)
        ->and($health->failureRate)->toBe(0.0)
        ->and($health->lastCallAt)->not->toBeNull()
        ->and($health->lastError)->toBeNull();
});

test('a query slower than the threshold reports degraded, not healthy', function () {
    // The query is instant either way — what makes it "slow" is a threshold of 0, which
    // guarantees the measured latency is at or above it without needing to actually stall
    // anything.
    config(['health.database.degraded_latency_ms' => 0]);

    $health = (new DatabaseHealthCheck)->probe();

    expect($health->status)->toBe(HealthStatus::Degraded)
        ->and($health->reason)->toContain('at or above the 0ms threshold');
});

test('a connection failure reports down with a generic reason, never the raw exception', function () {
    /** @var DatabaseManager $manager */
    $manager = app('db');
    $originalDefault = $manager->getDefaultConnection();

    // A connection Capsule genuinely knows about, pointed at a host that cannot possibly
    // answer — DB::select() below resolves through this real Illuminate connection plumbing,
    // not through the app's own config() helper, which DatabaseManager never consults here.
    // A fresh Capsule bound to the same container shares its ['config'] object with the one
    // tests/stubs.php built, so this reaches the exact connection registry $manager reads.
    (new Manager(app()))->addConnection([
        'driver' => 'mysql',
        'host' => 'this-host-does-not-exist.invalid',
        'port' => 1,
        'database' => 'nope',
        'username' => 'nope',
        'password' => 'nope',
    ], 'broken');

    $manager->setDefaultConnection('broken');

    try {
        $health = (new DatabaseHealthCheck)->probe();
    } finally {
        $manager->setDefaultConnection($originalDefault);
        $manager->purge('broken');
    }

    expect($health->status)->toBe(HealthStatus::Down)
        ->and($health->reason)->toBe('Connection failed')
        ->and($health->lastError)->toBe('Connection failed')
        // The one thing this test exists to guard: whatever the driver's exception says
        // (host, user, port) must never reach the reason an unauthenticated caller can read.
        ->and($health->reason)->not->toContain('this-host-does-not-exist')
        ->and($health->reason)->not->toContain('nope');
})->skip(
    ! extension_loaded('pdo_mysql'),
    'requires the mysql PDO driver to attempt (and fail) a real connection'
);
