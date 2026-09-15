<?php

declare(strict_types=1);

namespace App\Domains\Sync;

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
        $response = Http::withBasicAuth($this->user(), $this->appPassword())
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Sync call to {$this->env}/{$abilityName} failed ({$response->status()}): {$response->body()}"
            );
        }

        return $response->json();
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
