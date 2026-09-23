<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Events\LeadFormSubmitted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\FbcResolver;
use App\Domains\Lead\Services\IdentityResolver;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadColumnLimits;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use App\Domains\Referral\Services\ReferralSettingsService;
use App\Infrastructure\Observability\IntegrationCallRecorder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CaptureLeadAction
{
    public function __construct(
        protected AttributionEngine $attributionEngine,
        protected PhoneValidationService $phoneValidator,
        protected LeadActivityLogger $activityLogger,
        protected ?FbcResolver $fbcResolver = null,
    ) {
        // Optional and self-defaulting: this action is constructed by hand in seven tests, and
        // a required fourth argument would break every one of them for no benefit.
        $this->fbcResolver ??= new FbcResolver;
    }

    /**
     * The real character limit of every bounded column on the leads table.
     *
     * Delegates to {@see LeadColumnLimits}, which owns the map and the schema introspection so
     * that the Gravity backfill clamps against exactly the same widths this action does. Kept as
     * a method here because it is the seam the overflow test reflects on.
     *
     * @return array<string, int>
     */
    private function columnLimits(): array
    {
        return LeadColumnLimits::limits();
    }

    /**
     * The referral welcome discount owed to a lead from this source, or null.
     *
     * Null for organic and paid traffic, and when the notice is switched off — in both cases no
     * offer was ever made, and recording one would invent a promise nobody owes.
     */
    private function referralWelcomeDiscount(?string $sourceType): ?int
    {
        if (! in_array($sourceType, ['referral_hub', 'partnership'], true)) {
            return null;
        }

        try {
            $settings = app(ReferralSettingsService::class)->get();

            if (empty($settings['visitor_notice_enabled'])) {
                return null;
            }

            $amount = (int) ($settings['visitor_discount_amount'] ?? 0);

            return $amount > 0 ? $amount : null;
        } catch (\Throwable $e) {
            // Reading a setting must never cost a lead.
            Log::warning('CaptureLeadAction: could not resolve the referral welcome discount: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Trim every bounded column to what its schema can actually hold.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function clampToColumnLimits(array $attributes): array
    {
        return LeadColumnLimits::clamp($attributes);
    }

    /**
     * What to store for `fbc` on this write, or null to leave the column exactly as it is.
     *
     * The rules themselves live in {@see FbcResolver} — upgrade-only, never overwrite a
     * confirmed-real value, only accept a cookie that agrees with this lead's click — because a
     * refused visitor is recorded by `RecordBouncedLeadAction` on a path that never reaches this
     * action, and two copies of these rules would drift apart under the Conversions API.
     *
     * @return array{value: string, synthetic: bool}|null
     */
    private function resolveFbc(?Lead $lead, array $existingAttribution, LeadCaptureData $data): ?array
    {
        return $this->fbcResolver->resolve(
            attributionNamed: $data->attributionNamed,
            attribution: $data->attribution,
            fbclid: $data->fbclid ?: $lead?->fbclid,
            storedFbc: $lead?->fbc,
            storedIsSynthetic: (bool) ($existingAttribution['fbc_synthetic'] ?? true),
        );
    }

    /**
     * Capture, validate, attribute, and persist a new prospective lead.
     */
    public function execute(LeadCaptureData $data): Lead
    {
        // 1. Extension point: dispatch LeadFormSubmitted before persistence
        Event::dispatch(new LeadFormSubmitted($data));

        // 2. Validate & format phone number if provided
        $formattedPhone = null;
        $phoneCountry = $data->phoneCountry;
        if (! empty($data->phone)) {
            $validation = $this->phoneValidator->validateAndFormat($data->phone, $data->phoneCountry ?? 'US');
            if ($validation['isValid']) {
                $formattedPhone = $validation['e164'];
                $phoneCountry = $validation['countryCode'];
            } else {
                // Fallback to cleaned raw string if parsing fails
                $formattedPhone = trim($data->phone);
            }
        }

        $cookie = function_exists('request') && request() ? request()->cookie('rl_referrer') : ($_COOKIE['rl_referrer'] ?? null);

        $attribution = $this->attributionEngine->resolveLeadSource(
            referralSlug: $data->referralCode,
            utmSource: $data->utmSource,
            utmCampaign: $data->utmCampaign,
            cookie: $cookie
        );

        // 4. Extract first and last name
        $nameParts = explode(' ', trim($data->name), 2);
        $firstName = $data->firstName ?: ($nameParts[0] ?? '');
        $lastName = $data->lastName ?: ($nameParts[1] ?? '');

        // 5. Persist or update the Lead entity
        $lead = null;
        $leadId = $data->extraData['lead_id'] ?? null;
        if (! empty($leadId)) {
            $lead = Lead::query()->find($leadId);
        }

        $leadAttributes = [
            'name' => trim($data->name),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower(trim($data->email)),
            'phone' => $formattedPhone ?: $lead?->phone,
            'phone_country' => $phoneCountry ?: $lead?->phone_country,
            'company' => $data->company ?: $lead?->company,
            'role_needed' => $data->roleNeeded ?: $lead?->role_needed,
            'weekly_hours' => $data->weeklyHours ?: $lead?->weekly_hours,
            'monthly_revenue' => $data->monthlyRevenue ?: $lead?->monthly_revenue,
            'start_date' => $data->startDate ?: $lead?->start_date,
            'notes' => $data->notes ?: $lead?->notes,
            'utm_source' => $data->utmSource ?: $lead?->utm_source,
            'utm_medium' => $data->utmMedium ?: $lead?->utm_medium,
            'utm_campaign' => $data->utmCampaign ?: $lead?->utm_campaign,
            'utm_term' => $data->utmTerm ?: $lead?->utm_term,
            'utm_content' => $data->utmContent ?: $lead?->utm_content,
            'gclid' => $data->gclid ?: $lead?->gclid,
            'fbclid' => $data->fbclid ?: $lead?->fbclid,
            'referral_code' => $attribution['sourceID'] ?? ($data->referralCode ?: $lead?->referral_code),
            'landing_url' => $data->landingUrl ?: $lead?->landing_url,
            'referrer_url' => $data->referrerUrl ?: $lead?->referrer_url,
            'session_id' => $data->sessionId ?: $lead?->session_id,
            // Stamped once and never cleared: a later submission that omits the
            // tick must not erase consent the lead already gave.
            'consent_at' => $data->consent ? ($lead?->consent_at ?? now()) : $lead?->consent_at,
            'source_type' => $attribution['sourceType'] ?: ($lead?->source_type ?? 'organic'),
            'source_id' => $attribution['sourceID'] ?: $lead?->source_id,
            'status' => ! empty($data->preferredSlot) ? 'booking_pending' : ($lead?->status ?? 'captured'),
        ];

        /*
         * The rest of the attribution, applied with the same first-write-wins rule as the
         * fields above: a later submission that arrives without a click id must not erase the
         * one the first touch carried. `AttributionCollector` decides which columns exist here,
         * so a new parameter needs no change in this action.
         *
         * `fbc` is excluded — it does not follow first-write-wins at all, and gets its own
         * resolution below instead. See `resolveFbc()`.
         */
        foreach ($data->attributionNamed as $column => $value) {
            if ($column === 'fbc') {
                continue;
            }

            if (! array_key_exists($column, $leadAttributes)) {
                $leadAttributes[$column] = ($value !== '' && $value !== null)
                    ? $value
                    : ($lead?->{$column} ?? null);
            }
        }

        // Merge rather than replace, so the JSON blob accumulates across touches. Existing keys
        // win for the same reason: the first value seen is the acquisition one.
        $existingAttribution = is_array($lead?->attribution) ? $lead->attribution : [];

        if ($data->attribution !== [] || $existingAttribution !== []) {
            $leadAttributes['attribution'] = array_replace($data->attribution, $existingAttribution);
        }

        /*
         * `fbc` gets its own rule, upgrade-only: a synthetic value is a stand-in and is
         * replaced the moment something that actually agrees with the click shows up, but a
         * confirmed real one is never touched again. See resolveFbc().
         */
        $fbcResolution = $this->resolveFbc($lead, $existingAttribution, $data);

        if ($fbcResolution !== null) {
            $leadAttributes['fbc'] = $fbcResolution['value'];
            $leadAttributes['attribution'] = array_merge(
                $leadAttributes['attribution'] ?? $existingAttribution,
                ['fbc_synthetic' => $fbcResolution['synthetic']],
            );
        }

        /*
         * Record the referral welcome discount this lead is entitled to.
         *
         * Nothing in this codebase applies a discount — there is no coupon or checkout-credit
         * mechanism — so the offer shown to a referred visitor is honoured by hand. Without
         * this the only person who knows a discount was promised is the prospect, and the sales
         * team hears about it for the first time on the call. It goes in the attribution blob
         * rather than a column of its own, and is deliberately NOT sent to HubSpot: an unknown
         * property name there rejects the entire contact sync (see HubSpotGateway::propertiesFor).
         *
         * Stamped from the lead's resolved source, not from whether the notice was rendered:
         * the durable fact is "this lead came through a referral, so the referral offer
         * applies", which holds whether or not the visitor happened to see the banner.
         */
        $referralDiscount = $this->referralWelcomeDiscount($leadAttributes['source_type'] ?? null);

        if ($referralDiscount !== null) {
            $attribution = $leadAttributes['attribution'] ?? $existingAttribution;
            // First-write-wins, like every other attribution value: the amount promised at
            // acquisition is the one owed, even if the setting changes later.
            $attribution['referral_discount_offered'] ??= $referralDiscount;
            $leadAttributes['attribution'] = $attribution;
        }

        // Last thing before the write, so it also covers the attribution columns merged in
        // above — which are exactly the ones carrying third-party values.
        $leadAttributes = $this->clampToColumnLimits($leadAttributes);

        if ($lead) {
            $lead->update($leadAttributes);
        } else {
            $lead = Lead::query()->create(array_merge([
                'uuid' => (string) Str::uuid(),
            ], $leadAttributes));
        }

        /*
         * Resolve the identity graph before anything downstream runs.
         *
         * Order matters: this sets `is_blocked` on the lead, and the listeners that must
         * suppress for a blocked person read that column. Resolving after the event would let
         * the Slack alert and the CRM sync fire first, which is the whole thing a shadow ban is
         * for. It never throws and never bans — see IdentityResolver.
         */
        app(IdentityResolver::class)->resolve($lead);
        $lead->refresh();

        /*
         * Attach every outbound integration call made from here on to this lead, so the HubSpot
         * sync and the Slack post that the event below triggers are recoverable from the lead's
         * own timeline rather than only from a global firehose.
         */
        app(IntegrationCallRecorder::class)->forLead($lead->id);

        // 6. Dual Logging: Stage 1 (Dispatch log for LeadCreated)
        $this->activityLogger->logDispatch(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Lead',
            payload: [
                'email' => $lead->email,
                'source_type' => $lead->source_type,
                'source_id' => $lead->source_id,
                'preferred_slot' => $data->preferredSlot,
                'monthly_revenue' => $lead->monthly_revenue,
                'utm_source' => $lead->utm_source,
                'utm_campaign' => $lead->utm_campaign,
                'gclid' => $lead->gclid,
                'fbclid' => $lead->fbclid,
                'session_id' => $lead->session_id,
                'landing_url' => $lead->landing_url,
            ],
            description: "Lead #{$lead->id} captured and stamped with source [{$lead->source_type}:{$lead->source_id}]"
        );

        // 7. Dispatch LeadCreated lifecycle event
        Event::dispatch(new LeadCreated(
            lead: $lead,
            context: [
                'preferred_slot' => $data->preferredSlot,
                'timezone' => $data->timezone ?? 'America/New_York',
                'extra_data' => $data->extraData,
            ]
        ));

        return $lead;
    }
}
