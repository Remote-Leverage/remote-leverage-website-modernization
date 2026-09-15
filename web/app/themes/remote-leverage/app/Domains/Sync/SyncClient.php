<?php

declare(strict_types=1);

namespace App\Domains\Sync;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Calls a remote environment's Abilities REST API
 * (/wp-json/wp-abilities/v1/abilities/{name}/run), authenticated as the
 * dedicated "sync-service" Application Password user configured per
 * environment in config/rl-sync.php.
 */
class SyncClient
{
    public function __construct(private readonly string $env)
    {
        if (config("rl-sync.environments.{$env}") === null) {
            throw new InvalidArgumentException("Unknown sync environment: {$env}");
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(string $abilityName, array $input = []): array
    {
        $url = rtrim($this->baseUrl(), '/')."/wp-json/wp-abilities/v1/abilities/{$abilityName}/run";

        // The wp-abilities/v1 run endpoint expects the ability's own input
        // wrapped in an "input" envelope, not passed as the raw POST body.
        $payload = ['input' => $input];

        // A sibling of "input", never inside it: the endpoint reads only the
        // envelope it registered, so an extra top-level key passes through
        // untouched instead of being schema-validated as ability input.
        if ($this->bodyAuthEnabled()) {
            $payload['_rl_sync_auth'] = [
                'user' => $this->user(),
                'password' => $this->appPassword(),
            ];
        }

        // The header is still sent regardless. The body copy is a fallback for
        // a CDN that strips it, not a replacement, so this keeps working
        // unchanged the moment the header starts arriving again.
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->post($url, $payload);
            } catch (ConnectionException $e) {
                // A dropped or timed-out connection says nothing about whether
                // the target applied the call, so this is only safe to retry
                // because every ability it fronts is idempotent: row chunks are
                // upserts, a clean is guarded by the session's own flag, and a
                // file chunk is checksummed and written to a temporary name.
                if ($this->shouldRetry($attempt)) {
                    $this->backOff($attempt);

                    continue;
                }

                throw new RuntimeException(
                    "Sync call to {$this->env}/{$abilityName} failed after {$attempt} attempts: ".$e->getMessage(),
                    previous: $e,
                );
            }

            if ($response->failed()) {
                // 502/503/504 is the container rolling over or PHP-FPM briefly
                // saturated, not a rejection — and a media push is ~1,600 calls,
                // so treating one as fatal means a deploy or a moment of load
                // discards the whole transfer. A 4xx is a real refusal and is
                // never retried.
                if ($response->serverError() && $this->shouldRetry($attempt)) {
                    $this->backOff($attempt);

                    continue;
                }

                throw new RuntimeException(
                    "Sync call to {$this->env}/{$abilityName} failed ({$response->status()}"
                    .($attempt > 1 ? ", after {$attempt} attempts" : '').'): '.$response->body()
                );
            }

            return $response->json();
        }
    }

    /**
     * Perform one request.
     *
     * Separated from the retry loop around it so tests can drive the loop
     * through a subclass, the way the pusher and puller tests already fake this
     * client — the Http facade is not available to that suite.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function post(string $url, array $payload): Response
    {
        return Http::withBasicAuth($this->user(), $this->appPassword())
            ->acceptJson()
            ->timeout($this->timeout())
            ->post($url, $payload);
    }

    private function shouldRetry(int $attempt): bool
    {
        return $attempt < max(1, (int) config('rl-sync.retries', 4));
    }

    /**
     * Wait before retrying, backing off so a target that is genuinely restarting
     * is given longer each time rather than being hammered while it boots.
     */
    private function backOff(int $attempt): void
    {
        usleep(min(8_000_000, 500_000 * (2 ** ($attempt - 1))));
    }

    /**
     * How long to wait for the target to finish one call.
     *
     * Guzzle defaults to 30s, which suits a request that writes 25 rows and is
     * wrong for the ones that do not: the first chunk of a dataset also carries
     * that dataset's clean, and a rollback replays an entire session in one
     * call. Neither is a hung request at 30s, but both were being abandoned as
     * though they were, leaving a session open on the target.
     *
     * Raising this cannot exceed what the CDN in front of the target allows —
     * if CloudFront gives up first the response is its 504, not this timeout.
     */
    private function timeout(): int
    {
        return max(1, (int) config('rl-sync.request_timeout', 60));
    }

    /**
     * Whether to also carry the credential in the request body.
     *
     * TEMPORARY, and opt-in per environment — see config/rl-sync.php and
     * web/app/mu-plugins/rl-sync-body-auth.php. Refused outright for
     * production so the opposite half of the bridge can never be reached
     * there even if someone sets the env var.
     */
    private function bodyAuthEnabled(): bool
    {
        if ($this->env === SyncEnvironment::PRODUCTION || SyncEnvironment::isProduction()) {
            return false;
        }

        return filter_var(
            config("rl-sync.environments.{$this->env}.body_auth", false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private function baseUrl(): string
    {
        return $this->requireConfig('url');
    }

    private function user(): string
    {
        return $this->requireConfig('user');
    }

    private function appPassword(): string
    {
        return $this->requireConfig('app_password');
    }

    private function requireConfig(string $key): string
    {
        $value = config("rl-sync.environments.{$this->env}.{$key}");

        if (empty($value)) {
            throw new RuntimeException(
                "Missing rl-sync config for environment \"{$this->env}\": {$key}. Check your .env."
            );
        }

        return $value;
    }
}
