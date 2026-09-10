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

        $response = Http::withBasicAuth($this->user(), $this->appPassword())
            ->acceptJson()
            ->post($url, $input);

        if ($response->failed()) {
            throw new RuntimeException(
                "Sync call to {$this->env}/{$abilityName} failed ({$response->status()}): {$response->body()}"
            );
        }

        return $response->json();
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
