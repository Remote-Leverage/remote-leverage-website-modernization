<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Actions;

use App\Domains\Tracking\Gateways\PostHogClient;

class EvaluateVariantAction
{
    public function __construct(
        protected PostHogClient $postHogClient
    ) {}

    /**
     * Determine if a user qualifies for a feature flag variant.
     */
    public function execute(string $flagKey, ?string $distinctId = null, array $properties = []): bool
    {
        $id = $distinctId ?? ($_COOKIE['ph_distinct_id'] ?? 'anon_'.session_id());

        return $this->postHogClient->isFeatureEnabled($flagKey, $id, $properties);
    }
}
