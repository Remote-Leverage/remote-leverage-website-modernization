<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Gateways;

use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            /*
             * 8s/5s, not the 3s/2s this carried until 2026-09-18.
             *
             * The tight budget was set when this ran inside a Livewire round trip, where it
             * genuinely could stall the form. Every caller now defers to after the response, so
             * the only thing a short timeout buys is lost events — and it did: a cold TLS
             * handshake to us.i.posthog.com measured over 3s from this host, while a warm one
             * takes 0.3s. Each PHP request opens a fresh connection, so "cold" is the common
             * case, and the failure was invisible because nothing reads the return value.
             */
            $response = Http::timeout(8)->connectTimeout(5)->post("{$this->host}/capture/", [
                'api_key' => $this->apiKey,
                'event' => $event->event,
                'distinct_id' => $event->distinctId,
                'properties' => $event->properties,
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $event->timestamp ?? time()),
            ]);

            if (! $response->successful()) {
                // A rejected event used to return false and say nothing anywhere. PostHog
                // answers a bad key or a malformed payload with a 4xx and a reason; losing it
                // means the only symptom is an empty funnel.
                Log::error(sprintf(
                    'PostHogClient Capture Rejected: %s returned %d for [%s] — %s',
                    $this->host,
                    $response->status(),
                    $event->event,
                    Str::limit($response->body(), 200),
                ));

                return false;
            }

            return true;
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
