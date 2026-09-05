<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Gateways;

use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostHogClient
{
    protected ?string $apiKey;

    protected string $host;

    public function __construct()
    {
        $this->apiKey = config('services.posthog.api_key');
        $this->host = rtrim(config('services.posthog.host', 'https://us.i.posthog.com'), '/');
    }

    /**
     * Capture an analytics event in PostHog.
     */
    public function capture(AnalyticsEventData $event): bool
    {
        if (! $this->apiKey) {
            return false;
        }

        try {
            $response = Http::post("{$this->host}/capture/", [
                'api_key' => $this->apiKey,
                'event' => $event->event,
                'distinct_id' => $event->distinctId,
                'properties' => $event->properties,
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $event->timestamp ?? time()),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('PostHogClient Capture Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Evaluate a feature flag variant for a distinct user.
     */
    public function isFeatureEnabled(string $flagKey, string $distinctId, array $personProperties = []): bool
    {
        if (! $this->apiKey) {
            return false;
        }

        try {
            $response = Http::post("{$this->host}/decide/?v=3", [
                'api_key' => $this->apiKey,
                'distinct_id' => $distinctId,
                'person_properties' => $personProperties,
            ]);

            if ($response->successful()) {
                $flags = $response->json('featureFlags', []);

                return ! empty($flags[$flagKey]);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('PostHogClient Flag Error: '.$e->getMessage());

            return false;
        }
    }
}
