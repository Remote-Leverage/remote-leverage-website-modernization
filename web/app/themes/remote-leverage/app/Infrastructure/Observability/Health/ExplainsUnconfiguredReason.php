<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health;

/**
 * A check that knows something more specific than "No credential configured" to say when
 * `isConfigured()` is false.
 *
 * `IntegrationHealthChecker`'s generic reason is right for most integrations — a blank env var
 * is a blank env var. Google is not: BigQuery has two independent ways to be half set up (an
 * OAuth sign-in with no billing project named, or a service account key that names none either —
 * see `BigQueryClient::misconfiguration()`), and "no credential configured" is actively
 * misleading for an operator who *did* sign in and is looking at exactly that state. A check
 * implements this to say which of its own failure modes applies instead.
 */
interface ExplainsUnconfiguredReason extends IntegrationHealthCheck
{
    /**
     * Why `isConfigured()` is false, in words a diagnostics page or a Sentry issue can show
     * directly. Only ever consulted when `isConfigured()` already returned false.
     */
    public function unconfiguredReason(): string;
}
