<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\Lead;
use App\Domains\PartnerHub\Support\PartnerLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubSpotGateway
{
    /**
     * HubSpot's hard cap on `crm/v3/objects/contacts/batch/read` inputs.
     */
    public const BATCH_READ_LIMIT = 100;

    protected ?string $accessToken;

    protected string $portalId;

    /**
     * What the last `syncContact()` call did to the contact: `created`, `updated`, or null
     * when it did not get that far.
     *
     * `syncContact()` returns the same id either way — it POSTs, and falls through to a PATCH
     * on the 409 that says the email is already in the portal — so the caller cannot otherwise
     * tell a new contact from a returning one. The n8n `hubspot-lead-creation` flow wants both
     * but likes to know which, and reading it back off HubSpot would be a second round trip to
     * learn something this object already knew.
     *
     * Reset at the top of every `syncContact()`, so a failed sync cannot leave the previous
     * call's answer standing. Read it immediately after the call that produced it: the gateway
     * is a container singleton, so a second sync in the same request overwrites it.
     */
    protected ?string $lastContactAction = null;

    public function __construct(?LeadSettingsService $settings = null)
    {
        $settings ??= new LeadSettingsService;
        $configured = $settings->get();

        $this->accessToken = $configured['hubspot_access_token'] ?: env('HUBSPOT_ACCESS_TOKEN');
        $this->portalId = (string) ($configured['hubspot_portal_id'] ?: env('HUBSPOT_PORTAL_ID', ''));
    }

    /**
     * Whether an access token is present, without exposing it.
     */
    public function isConfigured(): bool
    {
        return (bool) $this->accessToken;
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
        $this->lastContactAction = null;

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
                $this->lastContactAction = 'created';

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
     * `created`, `updated`, or null — what the last `syncContact()` call did.
     *
     * See the property for why this is held rather than derived, and why it must be read
     * straight after the sync it describes.
     */
    public function lastContactAction(): ?string
    {
        return $this->lastContactAction;
    }

    /**
     * Read properties for up to `BATCH_READ_LIMIT` contacts in one call.
     *
     * Returns `contactId => [property => value]`, omitting any contact HubSpot did not return
     * (deleted, merged away, or never ours). Callers must treat an absent id as "unknown",
     * never as "the value is empty" — the difference decides whether a lead looks stale or
     * looks unsynced.
     *
     * Batched because the alternative is one HTTP round trip per lead on every cron tick.
     * HubSpot caps `batch/read` at 100 inputs, so the caller chunks and this asserts it.
     *
     * @param  array<int, string>  $contactIds
     * @param  array<int, string>  $properties
     * @return array<string, array<string, mixed>>
     */
    public function fetchContactProperties(array $contactIds, array $properties): array
    {
        $contactIds = array_values(array_unique(array_filter(array_map('strval', $contactIds))));

        if ($contactIds === [] || $properties === []) {
            return [];
        }

        if (! $this->accessToken) {
            Log::warning('HubSpotGateway: no access token configured — contact properties were NOT read.');

            return [];
        }

        if (count($contactIds) > self::BATCH_READ_LIMIT) {
            throw new \InvalidArgumentException(
                'HubSpotGateway::fetchContactProperties accepts at most '.self::BATCH_READ_LIMIT.' ids per call.'
            );
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post('https://api.hubapi.com/crm/v3/objects/contacts/batch/read', [
                    'properties' => array_values($properties),
                    'inputs' => array_map(static fn (string $id) => ['id' => $id], $contactIds),
                ]);
        } catch (\Throwable $e) {
            Log::error('HubSpotGateway: exception reading contact properties: '.$e->getMessage());

            return [];
        }

        /*
         * 207 is the one that matters here. HubSpot answers a partially-successful batch with
         * Multi-Status and a `results` array holding only the contacts it could read, which
         * `Http::successful()` reports as failure — treating it as one would throw away every
         * contact in a batch because one id had been deleted.
         */
        if (! $response->successful() && $response->status() !== 207) {
            Log::warning('HubSpotGateway: batch read failed', [
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
                'count' => count($contactIds),
            ]);

            return [];
        }

        $out = [];

        foreach ((array) $response->json('results', []) as $result) {
            if (! is_array($result) || empty($result['id'])) {
                continue;
            }

            $out[(string) $result['id']] = is_array($result['properties'] ?? null)
                ? $result['properties']
                : [];
        }

        return $out;
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
            $this->lastContactAction = 'updated';

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
        /*
         * `partner` carries two kinds of value: a partner code from a Partner Hub tracked link
         * (WR-73), and the free-text campaign tag the parameter held before that. A code that
         * resolves to an `rl_partner` post is an identifier and belongs in `partnership_id`; a
         * value that resolves to nothing is the label it always was and stays in `partner_name`
         * alone. See App\Domains\PartnerHub\Support\PartnerLink.
         */
        $partnerValue = trim((string) $lead->partner);
        $partnerName = $partnerValue === '' ? null : PartnerLink::nameForCode($partnerValue);

        $properties = [
            'firstname' => $lead->first_name ?: $lead->name,
            'lastname' => $lead->last_name,
            'phone' => $lead->phone,
            'company' => $lead->company,
            'country' => $lead->phone_country,

            // Attribution. Every key below was verified to exist in the portal on 2026-09-16;
            // HubSpot rejects the whole request if one does not, so this list is not a place to
            // guess. The previous `rl_*` names existed nowhere and would have 400'd every sync.
            'utm_source' => $lead->utm_source,
            'utm_medium' => $lead->utm_medium,
            'utm_campaign' => $lead->utm_campaign,
            'utm_term' => $lead->utm_term,
            'utm_content' => $lead->utm_content,
            'utm_id' => $lead->utm_id,
            'gclid' => $lead->gclid,
            'fbclid' => $lead->fbclid,
            'fbc' => $lead->fbc,
            /*
             * The other half of Meta's match pair, alongside `fbc` — see `event_source_url`
             * below for why both are mirrored here. `_fbp` has no column of its own, exactly
             * like `client_user_agent` below: read straight out of the `attribution` blob rather
             * than promoting it, since nothing here needs it as a query or filter column, only
             * as a value to mirror. Like every key in this map it must exist in the portal
             * before it ships; `dropUnknownProperties()` skips it quietly until then.
             */
            'fbp' => ((array) ($lead->attribution ?? []))['handl']['_fbp'] ?? null,
            'li_fat_id' => $lead->li_fat_id,
            'oppref' => $lead->oppref,
            /*
             * `partnership_id` did not exist in portal 243484989 when this shipped (checked
             * 2026-09-21 against the live schema: 488 contact properties, `partner_name` the
             * only partner one). `dropUnknownProperties()` therefore skips it and logs, and it
             * starts flowing the moment somebody creates it — no deploy. Create it as a
             * single-line text property under `conversioninformation`, beside `partner_name`.
             * The private app token cannot create it itself; see the note on
             * `event_source_url` below.
             */
            'partnership_id' => $partnerName === null ? null : $partnerValue,
            'partner_name' => $partnerName ?? ($partnerValue ?: null),
            'referrer_rewardful_id' => $lead->referral_code,
            'source' => $lead->data_source,
            /*
             * `intake_form` is a booleancheckbox in HubSpot, whose only valid values are the
             * strings 'true' and 'false'. The Lead stores the legacy Gravity Forms value 'yes',
             * and sending that rejected the **entire** request with a 400 — every other
             * property on it lost with it. That is the failure mode this mapping was always
             * one bad value away from, so the conversion belongs here at the boundary rather
             * than changing what the Lead records.
             */
            'intake_form' => $lead->intake_form ? 'true' : null,
            'ip_address' => $lead->ip_address,
            'schedule_link' => $lead->scheduler_link,
            'landing_page' => $lead->landing_page_base ?: $lead->landing_url,

            /*
             * The two fields Meta's Conversions API matches on, mirrored into HubSpot so the two
             * systems can be reconciled against each other on a per-contact basis — when Meta
             * reports a conversion nobody can find in the CRM, these are what you join on.
             *
             * Deliberately NOT folded into `landing_page`: that one prefers `landing_page_base`,
             * which is the URL with the query string stripped, and is the right thing for
             * grouping in HubSpot reporting. `event_source_url` is the full URL including
             * `fbclid` and the UTM set, byte-identical to what MetaConversionsApiClient sends,
             * because its whole value is being the same string on both sides.
             *
             * Like every key in this map, both must exist in the portal before this ships —
             * HubSpot 400s the entire request over one unknown property and every other field on
             * it is lost with it. Create them as single-line text under `conversioninformation`,
             * matching `fbc` / `landing_page`. The private app token could not create them
             * itself on 2026-09-18: it reads the schema fine but lacks
             * `crm.schemas.contacts.write`.
             */
            'event_source_url' => $lead->landing_url,
            'client_user_agent' => ((array) ($lead->attribution ?? []))['user_agent'] ?? null,

            /*
             * The MRR band the visitor selected, into HubSpot's **Annual** Revenue.
             *
             * That is the mapping the legacy Gravity Forms feed used, carried over deliberately
             * so the field keeps one meaning across the cutover — but it is worth knowing the
             * values are monthly ("$10k to $50k Per Month"), and the portal also has a
             * `monthly_revenue` property that would fit them. Changing it is a reporting
             * decision, not a code one: see docs/domains/lead.md.
             */
            'annualrevenue' => $lead->monthly_revenue,
        ];

        if ($includeEmail) {
            $properties = ['email' => $lead->email] + $properties;
        }

        // HubSpot treats an explicit null as "clear this property". Sending one would let a
        // later partial submission wipe attribution the first touch established.
        $properties = array_filter(
            $properties,
            static fn ($value) => $value !== null && $value !== '',
        );

        return $this->dropUnknownProperties($properties);
    }

    /**
     * Remove properties this portal does not define.
     *
     * HubSpot rejects the **entire** request with a 400 over a single unknown property, taking
     * every other field on it down with it. This map has hit that three times now — the `rl_*`
     * names, the `intake_form` value, and `event_source_url` / `client_user_agent` on
     * 2026-09-18 — and each time the symptom was every attribution field silently missing from
     * the CRM rather than an error anyone saw.
     *
     * Filtering against the live schema turns that class of mistake from "all attribution is
     * lost until someone reads the response body" into "the new field is quietly skipped until
     * it is created", and it means a property added in the portal starts flowing on its own
     * without a deploy.
     *
     * Cached for an hour: the schema changes on human timescales, and this must not add a round
     * trip to every lead. If the fetch fails we do not filter — that is exactly today's
     * behaviour, so a HubSpot outage cannot make this worse than it already was.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function dropUnknownProperties(array $properties): array
    {
        $known = $this->knownPropertyNames();

        if ($known === null) {
            return $properties;
        }

        $filtered = array_intersect_key($properties, array_flip($known));

        $dropped = array_diff(array_keys($properties), array_keys($filtered));

        if ($dropped !== []) {
            Log::warning(
                'HubSpotGateway: skipped propert'.(count($dropped) === 1 ? 'y' : 'ies').' missing from portal '
                .$this->portalId.': '.implode(', ', $dropped).'. Create them in HubSpot to start syncing them.'
            );
        }

        return $filtered;
    }

    /**
     * Every contact property name this portal defines, or null when it cannot be determined.
     *
     * @return list<string>|null
     */
    protected function knownPropertyNames(): ?array
    {
        // Key deliberately not prefixed `rl_`: AttributionCaptureTest greps this file for that
        // literal to catch invented HubSpot property names, and a cache key is not one.
        $cacheKey = 'hs_contact_props_'.md5($this->portalId);
        $cached = get_transient($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(8)
                ->connectTimeout(5)
                ->get('https://api.hubapi.com/crm/v3/properties/contacts', ['archived' => 'false']);

            if (! $response->successful()) {
                Log::warning('HubSpotGateway: could not read the contact property schema ('.$response->status().'); sending unfiltered.');

                return null;
            }

            $names = array_values(array_filter(array_map(
                static fn ($property): string => (string) ($property['name'] ?? ''),
                (array) $response->json('results', []),
            )));

            if ($names === []) {
                return null;
            }

            set_transient($cacheKey, $names, HOUR_IN_SECONDS);

            return $names;
        } catch (\Throwable $e) {
            Log::warning('HubSpotGateway: could not read the contact property schema: '.$e->getMessage());

            return null;
        }
    }
}
