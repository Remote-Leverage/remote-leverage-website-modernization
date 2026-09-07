<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubSpotGateway
{
    protected ?string $accessToken;

    protected string $portalId;

    public function __construct()
    {
        $this->accessToken = env('HUBSPOT_ACCESS_TOKEN');
        $this->portalId = (string) env('HUBSPOT_PORTAL_ID', '');
    }

    /**
     * Sync or create a contact in HubSpot CRM.
     * Replaces the legacy gravityformshubspot (GF_HubSpot) integration.
     */
    public function syncContact(Lead $lead): ?string
    {
        if (! $this->accessToken) {
            Log::info("HubSpotGateway: Simulating sync for lead #{$lead->id} ({$lead->email}) - token not configured");

            return 'simulated_hs_'.uniqid();
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->post('https://api.hubapi.com/crm/v3/objects/contacts', [
                    'properties' => [
                        'email' => $lead->email,
                        'firstname' => $lead->first_name ?: $lead->name,
                        'lastname' => $lead->last_name,
                        'phone' => $lead->phone,
                        'company' => $lead->company,
                        'rl_source_type' => $lead->source_type,
                        'rl_source_id' => $lead->source_id,
                        'rl_role_needed' => $lead->role_needed,
                        'rl_weekly_hours' => $lead->weekly_hours,
                        'rl_lead_status' => $lead->status,
                    ],
                ]);

            if ($response->successful()) {
                $contactId = $response->json('id');
                Log::info("HubSpotGateway: Successfully synced lead #{$lead->id} as contact {$contactId}");

                return (string) $contactId;
            }

            Log::warning("HubSpotGateway: API error syncing lead #{$lead->id}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error("HubSpotGateway: Exception while syncing lead #{$lead->id}: ".$e->getMessage());

            return null;
        }
    }
}
