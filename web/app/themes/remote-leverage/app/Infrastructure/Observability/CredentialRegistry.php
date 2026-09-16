<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use Illuminate\Support\Facades\Log;

/**
 * Maps a credential to the account it belongs to, so a log entry can name it.
 *
 * A fingerprint distinguishes one token from another but says nothing about which is which. The
 * question actually being asked of these logs is "whose token is this?" — because the answer
 * determines what happens next. `admin@remoteleverage.com` is a person to contact about a rate
 * limit; `sha256:1b7d40e2` is the start of a search through four tokens to find out who to
 * contact.
 *
 * Domains register a resolver rather than a fixed table, because credentials are edited at
 * runtime through wp-admin and a table built at boot would be stale the moment someone adds an
 * account.
 *
 * ## The one hard rule for a resolver
 *
 * **It must not make a network call.** Resolvers run while recording an outbound HTTP call, so a
 * resolver that fetched anything would trigger a request, which would trigger a recording, which
 * would run the resolver. Read from cache, options or config only.
 */
class CredentialRegistry
{
    /** @var array<string, string> sha256 of the secret => label */
    protected array $labels = [];

    /** @var array<int, callable(): array<string, string>> */
    protected array $resolvers = [];

    protected bool $resolved = false;

    /**
     * Register a resolver returning `[secret => label]`.
     *
     * Called lazily and at most once per request.
     */
    public function registerResolver(callable $resolver): void
    {
        $this->resolvers[] = $resolver;
        $this->resolved = false;
    }

    /**
     * Register one credential directly.
     */
    public function register(string $secret, string $label): void
    {
        $secret = trim($secret);
        $label = trim($label);

        if ($secret === '' || $label === '') {
            return;
        }

        $this->labels[$this->key($secret)] = $label;
    }

    /**
     * The label for a credential, or null if nothing claims it.
     */
    public function labelFor(string $secret): ?string
    {
        $secret = trim($secret);

        if ($secret === '') {
            return null;
        }

        $this->resolveOnce();

        return $this->labels[$this->key($secret)] ?? null;
    }

    /**
     * Forget everything, so the next lookup re-runs the resolvers.
     *
     * Called when the token pool is edited: a label cached from before the edit would attribute
     * calls to the account that used to hold that slot.
     */
    public function flush(): void
    {
        $this->labels = [];
        $this->resolved = false;
    }

    protected function resolveOnce(): void
    {
        if ($this->resolved) {
            return;
        }

        // Set before running, so a resolver that somehow triggers a lookup cannot recurse.
        $this->resolved = true;

        foreach ($this->resolvers as $resolver) {
            try {
                foreach ((array) $resolver() as $secret => $label) {
                    $this->register((string) $secret, (string) $label);
                }
            } catch (\Throwable $e) {
                // A domain that cannot name its credentials is not a reason to lose the log entry.
                Log::warning('CredentialRegistry: resolver failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Key by hash rather than by the secret itself, so the registry is not an in-memory
     * collection of live credentials keyed for easy retrieval.
     */
    protected function key(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
