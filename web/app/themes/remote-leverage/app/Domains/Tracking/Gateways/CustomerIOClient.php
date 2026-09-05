<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Gateways;

use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Data\UserProfileData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomerIOClient
{
    protected ?string $siteId;

    protected ?string $apiKey;

    public function __construct()
    {
        $this->siteId = config('services.customer_io.site_id');
        $this->apiKey = config('services.customer_io.api_key');
    }

    /**
     * Identify or update a customer profile in Customer.io.
     */
    public function identify(UserProfileData $profile): bool
    {
        if (! $this->siteId || ! $this->apiKey) {
            Log::debug('CustomerIOClient: Missing site_id or api_key');

            return false;
        }

        try {
            $attributes = array_merge([
                'email' => $profile->email,
                'name' => $profile->name,
                'referral_code' => $profile->referralCode,
            ], $profile->traits ?? []);

            $response = Http::withBasicAuth($this->siteId, $this->apiKey)
                ->put("https://track.customer.io/api/v1/customers/{$profile->identifier}", array_filter($attributes));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('CustomerIOClient Identify Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Track a customer event in Customer.io.
     */
    public function track(AnalyticsEventData $event): bool
    {
        if (! $this->siteId || ! $this->apiKey) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->siteId, $this->apiKey)
                ->post("https://track.customer.io/api/v1/customers/{$event->distinctId}/events", [
                    'name' => $event->event,
                    'data' => $event->properties,
                    'timestamp' => $event->timestamp,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('CustomerIOClient Track Error: '.$e->getMessage());

            return false;
        }
    }
}
