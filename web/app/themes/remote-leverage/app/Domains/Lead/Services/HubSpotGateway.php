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

    public function __construct(?LeadSettingsService $settings = null)
    {
        $settings ??= new LeadSettingsService;
        $configured = $settings->get();

        $this->accessToken = $configured['hubspot_access_token'] ?: env('HUBSPOT_ACCESS_TOKEN');
        $this->portalId = (string) ($configured['hubspot_portal_id'] ?: env('HUBSPOT_PORTAL_ID', ''));
    }

    /**
     * Sync or create a contact in HubSpot CRM.
     * Replaces the legacy gravityformshubspot (GF_HubSpot) integration.
     *
     * Returns the HubSpot contact id, or null when the sync did not happen. **Null is the
     * only failure signal** — this used to return `'simulated_hs_'.uniqid()` when no token
     * was configured, which every caller read as success. `LeadServiceProvider` logs the
     * activity as `succeeded` on a truthy return, so an unconfigured environment produced a
     * clean audit trail of contacts that were never created.
     */
    public function syncContact(Lead $lead): ?string
    {
        if (! $this->accessToken) {
            Log::warning("HubSpotGateway: no access token configured — lead #{$lead->id} ({$lead->email}) was NOT synced.");

            return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->post('https://api.hubapi.com/crm/v3/objects/contacts', [
                    'properties' => $this->propertiesFor($lead),
                ]);

            if ($response->successful()) {
                $contactId = $response->json('id');
                Log::info("HubSpotGateway: Successfully synced lead #{$lead->id} as contact {$contactId}");

                return (string) $contactId;
            }

            // A returning lead is the normal case, not an error: HubSpot answers 409 with the
            // id of the contact that already holds this email. Creating contacts only, as
            // this did, meant every repeat enquiry failed and the CRM never saw the updated
            // role, hours or status.
            if ($response->status() === 409) {
                $existingId = $this->existingContactId($response->body());

                if ($existingId !== null) {
                    return $this->updateContact($lead, $existingId);
                }

                Log::warning("HubSpotGateway: 409 for lead #{$lead->id} but no existing id in the response.", [
                    'body' => $response->body(),
                ]);

                return null;
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

    /**
     * Update the contact that already owns this email.
     */
    protected function updateContact(Lead $lead, string $contactId): ?string
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(10)
            ->patch("https://api.hubapi.com/crm/v3/objects/contacts/{$contactId}", [
                // Email is deliberately omitted: it is the match key, and PATCHing it onto an
                // existing contact is how you collide with a *different* contact's address.
                'properties' => $this->propertiesFor($lead, includeEmail: false),
            ]);

        if ($response->successful()) {
            Log::info("HubSpotGateway: Updated existing contact {$contactId} from lead #{$lead->id}");

            return $contactId;
        }

        Log::warning("HubSpotGateway: failed to update existing contact {$contactId} for lead #{$lead->id}", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    /**
     * Pull the existing contact id out of a 409 body.
     *
     * HubSpot reports it in prose — `"Contact already exists. Existing ID: 12345"` — rather
     * than as a field, so this reads it from the message and falls back to any top-level id.
     */
    protected function existingContactId(string $body): ?string
    {
        if (preg_match('/Existing ID:\s*(\d+)/i', $body, $matches) === 1) {
            return $matches[1];
        }

        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['id']) && is_scalar($decoded['id'])) {
            return (string) $decoded['id'];
        }

        return null;
    }

    /**
     * The contact property map, shared by create and update.
     *
     * The `rl_*` keys are custom properties and must exist in the portal; HubSpot rejects the
     * whole request if one is unknown.
     *
     * @return array<string, mixed>
     */
    protected function propertiesFor(Lead $lead, bool $includeEmail = true): array
    {
        $properties = [
            'firstname' => $lead->first_name ?: $lead->name,
            'lastname' => $lead->last_name,
            'phone' => $lead->phone,
            'company' => $lead->company,
            'rl_source_type' => $lead->source_type,
            'rl_source_id' => $lead->source_id,
            'rl_role_needed' => $lead->role_needed,
            'rl_weekly_hours' => $lead->weekly_hours,
            'rl_lead_status' => $lead->status,
        ];

        if ($includeEmail) {
            $properties = ['email' => $lead->email] + $properties;
        }

        return $properties;
    }
}
