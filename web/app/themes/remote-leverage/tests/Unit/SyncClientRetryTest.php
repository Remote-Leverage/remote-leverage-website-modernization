<?php

declare(strict_types=1);

use App\Domains\Sync\SyncClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * The retry loop around a sync call.
 *
 * A media push is roughly 1,600 requests, so a container rolling over or one
 * dropped connection part-way through must not discard everything already
 * sent — that is exactly how the first real mirror push was lost.
 *
 * Driven through a subclass rather than Http::fake, because the Http facade
 * keeps a resolved instance across tests in this suite and a stubbed sequence
 * leaks into whatever runs next. The pusher and puller tests fake this client
 * the same way.
 */
class ScriptedSyncClient extends SyncClient
{
    public int $attempts = 0;

    /**
     * @param  array<int, int|ConnectionException>  $script  What each attempt does.
     */
    public function __construct(private array $script, string $env = 'staging')
    {
        parent::__construct($env);
    }

    protected function post(string $url, array $payload): Response
    {
        $this->attempts++;

        $next = array_shift($this->script) ?? 200;

        if ($next instanceof ConnectionException) {
            throw $next;
        }

        return new Response(new PsrResponse(
            $next,
            ['Content-Type' => 'application/json'],
            (string) json_encode($next === 200 ? ['ok' => true, 'written' => 25] : ['error' => 'boom']),
        ));
    }
}

beforeEach(function () {
    config([
        'rl-sync.environments.staging' => [
            'url' => 'https://staging.example.test',
            'user' => 'sync-service',
            'app_password' => 'secret',
            'body_auth' => false,
        ],
        'rl-sync.retries' => 4,
    ]);
});

it('retries a 503 and succeeds once the target comes back', function () {
    $client = new ScriptedSyncClient([503, 503, 200]);

    expect($client->run('app/receive-media-file', ['x' => 1]))
        ->toBe(['ok' => true, 'written' => 25])
        ->and($client->attempts)->toBe(3);
});

it('retries a dropped connection', function () {
    $client = new ScriptedSyncClient([new ConnectionException('timed out'), 200]);

    expect($client->run('app/receive-transfer-chunk'))->toBe(['ok' => true, 'written' => 25])
        ->and($client->attempts)->toBe(2);
});

it('gives up after the configured number of attempts', function () {
    config(['rl-sync.retries' => 3]);
    $client = new ScriptedSyncClient([503, 503, 503, 503, 503]);

    expect(fn () => $client->run('app/receive-media-file'))
        ->toThrow(RuntimeException::class, 'after 3 attempts');

    expect($client->attempts)->toBe(3);
});

it('never retries a 4xx, because a refusal is not a blip', function () {
    $client = new ScriptedSyncClient([403, 200]);

    // A 403 is the target saying no. Retrying it only delays the error and
    // obscures which call was actually rejected.
    expect(fn () => $client->run('app/receive-media-file'))
        ->toThrow(RuntimeException::class, '403');

    expect($client->attempts)->toBe(1);
});

it('gives up immediately on a connection failure when retries are disabled', function () {
    config(['rl-sync.retries' => 1]);
    $client = new ScriptedSyncClient([new ConnectionException('timed out')]);

    expect(fn () => $client->run('app/begin-transfer'))
        ->toThrow(RuntimeException::class, 'after 1 attempts');

    expect($client->attempts)->toBe(1);
});

it('reports the failing status in the message', function () {
    $client = new ScriptedSyncClient([500, 500, 500, 500]);

    expect(fn () => $client->run('app/begin-transfer'))
        ->toThrow(RuntimeException::class, 'failed (500');
});
