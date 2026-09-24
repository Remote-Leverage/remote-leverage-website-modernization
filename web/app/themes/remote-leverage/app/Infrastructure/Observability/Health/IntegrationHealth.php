<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health;

/**
 * One integration's status, and the evidence behind it.
 *
 * Carries the numbers a badge cannot, deliberately — "down" on its own is the start of an
 * investigation, not the end of one, and `reason` is what lets the diagnostics page or a
 * console command say why without a second query.
 */
final class IntegrationHealth
{
    public function __construct(
        public readonly string $integration,
        public readonly string $label,
        public readonly HealthStatus $status,
        public readonly bool $configured,
        public readonly string $reason,
        public readonly int $sampleSize,
        public readonly ?float $failureRate,
        public readonly int $consecutiveFailures,
        public readonly ?\DateTimeInterface $lastCallAt,
        public readonly ?string $lastError,
    ) {}

    /**
     * @return array{integration: string, label: string, status: string, configured: bool,
     *     reason: string, sample_size: int, failure_rate: float|null, consecutive_failures: int,
     *     last_call_at: string|null, last_error: string|null}
     */
    public function toArray(): array
    {
        return [
            'integration' => $this->integration,
            'label' => $this->label,
            'status' => $this->status->value,
            'configured' => $this->configured,
            'reason' => $this->reason,
            'sample_size' => $this->sampleSize,
            'failure_rate' => $this->failureRate,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_call_at' => $this->lastCallAt?->format(DATE_ATOM),
            'last_error' => $this->lastError,
        ];
    }

    /**
     * The subset of `toArray()` safe to hand to an unauthenticated caller — `/api/health/
     * integrations`, which anyone can reach with no credential of their own.
     *
     * `reason` and `last_error` are withheld, not sanitised: `reason` can name a real identity
     * (`GoogleHealthCheck` names the signed-in Google account when relevant — exactly what an
     * operator wants from the CLI or the dashboard, and exactly what a spear-phishing attempt
     * wants from a public endpoint), and `last_error` is `rl_integration_calls.error_message`
     * verbatim — `IntegrationCallRecorder` redacts headers, bodies and URLs before storing a
     * call, but never that column, so a connection-level failure can carry a raw driver
     * exception message with an internal hostname in it. `configured` stays: whether a
     * credential exists is not identifying on its own, and an uptime monitor needs it to tell
     * "not set up" from "set up and failing".
     *
     * @return array{integration: string, label: string, status: string, configured: bool,
     *     sample_size: int, failure_rate: float|null, consecutive_failures: int,
     *     last_call_at: string|null}
     */
    public function toPublicArray(): array
    {
        return [
            'integration' => $this->integration,
            'label' => $this->label,
            'status' => $this->status->value,
            'configured' => $this->configured,
            'sample_size' => $this->sampleSize,
            'failure_rate' => $this->failureRate,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_call_at' => $this->lastCallAt?->format(DATE_ATOM),
        ];
    }
}
