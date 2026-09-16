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
            // Timeout, because this is called from a Livewire round trip. Laravel's default
            // is 30s, so an unreachable PostHog would hold a keystroke response open for
            // half a minute. Analytics must never be able to stall the form.
            $response = Http::timeout(3)->connectTimeout(2)->post("{$this->host}/capture/", [
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
            // `/flags/?v=2`, not the deprecated `/decide/?v=3` PostHog superseded. The shapes
            // differ: /decide returned a flat `featureFlags` map of key => value, /flags
            // returns `flags` as key => {enabled, variant, …}. Both are read below, because a
            // self-hosted instance may still be on the older response.
            $response = Http::timeout(3)->connectTimeout(2)->post("{$this->host}/flags/?v=2", [
                'api_key' => $this->apiKey,
                'distinct_id' => $distinctId,
                'person_properties' => $personProperties,
            ]);

            if ($response->successful()) {
                $flag = $response->json("flags.{$flagKey}");

                if (is_array($flag)) {
                    return (bool) ($flag['enabled'] ?? false);
                }

                if ($flag !== null) {
                    return (bool) $flag;
                }

                // Legacy shape.
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
