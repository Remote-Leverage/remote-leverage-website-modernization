<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Domains\Marketing\Gateways\BigQueryClient;
use App\Domains\Marketing\Support\WarehouseOAuth;
use App\Infrastructure\Observability\Health\ExplainsUnconfiguredReason;

/**
 * `googleapis.com` traffic. BigQuery is the only Google API this theme calls, so its
 * configuration is what answers "is Google configured" — see `config('observability.
 * integration_calls.hosts')`, which classifies every `*.googleapis.com` call as `google`.
 *
 * `isConfigured()` stays a plain gate on `BigQueryClient::isConfigured()` — the same check
 * `marketingDay()` and friends already use to decide whether to query at all, so a health check
 * and an actual read of the warehouse can never disagree about whether it's set up. What
 * `WarehouseOAuth` adds is the *reason* when it isn't: `BigQueryClient::misconfiguration()`
 * already distinguishes "nobody signed in" from "signed in, but no billing project named" — the
 * second is a real, common state (see its own docblock) that a bare "No credential configured"
 * would misreport as if the sign-in had never happened. `WarehouseOAuth::connectedAccount()`
 * names *who* signed in, so an operator does not have to go find that out separately.
 */
class GoogleHealthCheck implements ExplainsUnconfiguredReason
{
    public function __construct(
        protected BigQueryClient $client = new BigQueryClient,
    ) {}

    public function integration(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google (BigQuery)';
    }

    /**
     * `warehouse`, not `big_query` — "BigQuery" is the vendor's own product name, and using it
     * as the alias would name the vendor exactly as much as `google` did. This theme already
     * calls this "the marketing warehouse" everywhere else (`WarehouseOAuth`,
     * `config('marketing.warehouse.*')`), so this reuses that term rather than inventing a
     * second name for the same thing.
     */
    public function alias(): string
    {
        return 'warehouse';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function unconfiguredReason(): string
    {
        $problem = ucfirst($this->client->misconfiguration() ?? 'no Google credential is configured');
        $account = WarehouseOAuth::connectedAccount();

        // Only ever set when the "does not name a project" branch of $problem applies — naming
        // who signed in is the detail that message itself has no way to include.
        return $account !== '' ? "{$problem} ({$account})" : $problem;
    }
}
