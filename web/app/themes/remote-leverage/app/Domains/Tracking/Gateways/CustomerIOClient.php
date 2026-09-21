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

        // Same rule as track(): a PUT to an unknown id is a create, so an identifier that is
        // not an email is a profile nothing can reach. See that method for the full history.
        if (! filter_var($profile->identifier, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $attributes = array_merge([
                'email' => $profile->email,
                'name' => $profile->name,
                'referral_code' => $profile->referralCode,
            ], $profile->traits ?? []);

            $response = Http::withBasicAuth($this->siteId, $this->apiKey)
                ->timeout(3)
                ->connectTimeout(2)
                ->put("https://track.customer.io/api/v1/customers/{$profile->identifier}", array_filter($attributes));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('CustomerIOClient Identify Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Track a customer event in Customer.io.
     *
     * **This endpoint creates the customer when the id is unknown.** That is why the identifier
     * is checked here rather than trusted from the caller: an id that is not an email is not a
     * neutral placeholder, it is a new, emailless person no campaign can ever match. Three
     * callers were minting them — the booking wizard fell back to the Laravel session id, which
     * produced one 40-character profile per visitor per session across the front page, every
     * post and the booking footer, and `CheckoutTelemetry` and the Calendly webhook pooled
     * everyone they could not name into literal `anonymous` and `unknown` profiles.
     *
     * Customer.io is keyed to email throughout this codebase — `identify()` is called with it
     * and the lead-scoring map is built on it — so anything else has no reader. The event is
     * dropped rather than sent under a stand-in; `RecordBehaviorEventAction` still gives it to
     * PostHog, which is built to hold anonymous identities and is where that traffic belongs.
     */
    public function track(AnalyticsEventData $event): bool
    {
        if (! $this->siteId || ! $this->apiKey) {
            return false;
        }

        if (! filter_var($event->distinctId, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->siteId, $this->apiKey)
                ->timeout(3)
                ->connectTimeout(2)
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
