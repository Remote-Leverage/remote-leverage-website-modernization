<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Events\LeadFormSubmitted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\IdentityResolver;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use App\Infrastructure\Observability\IntegrationCallRecorder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CaptureLeadAction
{
    /**
     * Widths of the columns holding values we do not author.
     *
     * Ad platforms decide how long their click ids are and change that without notice. Under
     * `STRICT_TRANS_TABLES` an overlong value does not truncate, it aborts the INSERT — so an
     * unbounded third-party string is not a tracking inconvenience, it is a lost customer. A
     * 212-character `fbclid` against `varchar(150)` was dropping every paid-social lead on
     * 2026-09-17, partial capture included, with no row left behind to show for it.
     *
     * The 2026_09_17_000001 migration widens these; clamping here is the half that keeps
     * working when the next value outgrows the new width too. Losing the tail of a click id
     * costs one attribution join. Losing the row costs the lead.
     *
     * This map is only the fallback floor — `columnLimits()` reads the real widths off the
     * table and prefers those, so widening a column later needs no edit here and a column
     * added without being listed here is still protected.
     *
     * Only bounded columns appear here. `landing_url`, `referrer_url`, `notes`,
     * `scheduler_link` and `landing_page_base` are TEXT and cannot overflow this way.
     *
     * @var array<string, int>
     */
    private const COLUMN_LIMITS = [
        'fbclid' => 512,
        'gclid' => 512,
        'fbc' => 512,
        'li_fat_id' => 255,
        'utm_source' => 255,
        'utm_medium' => 255,
        'utm_campaign' => 255,
        'utm_term' => 255,
        'utm_content' => 255,
        'utm_id' => 255,
        'oppref' => 255,
        'partner' => 255,
        'referral_code' => 100,
        'session_id' => 100,
        'monthly_revenue' => 100,
        'phone_country' => 5,
        'data_source' => 100,
        'intake_form' => 50,
        'ip_address' => 45,
        'timezone' => 64,
        'submission_type' => 50,
        'device_id' => 64,
        'posthog_session_id' => 100,
    ];

    public function __construct(
        protected AttributionEngine $attributionEngine,
        protected PhoneValidationService $phoneValidator,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Resolved once per process, because it costs an information_schema query.
     *
     * @var array<string, int>|null
     */
    private static ?array $resolvedColumnLimits = null;

    /**
     * The real character limit of every bounded column on the leads table.
     *
     * Read from the table rather than hardcoded, so this cannot drift away from the schema —
     * which is the failure mode that produced the original bug in a slower form: the code knew
     * `fbclid` existed and had no idea how much of it would fit. Whatever the column actually
     * is today is what gets enforced, so widening it again later needs no change here, and a
     * new attribution column is covered the moment it is added.
     *
     * Falls back to the constant map where introspection is unavailable — notably the bare
     * container the unit tests build, which has no live connection.
     *
     * @return array<string, int>
     */
    private function columnLimits(): array
    {
        if (self::$resolvedColumnLimits !== null) {
            return self::$resolvedColumnLimits;
        }

        $limits = self::COLUMN_LIMITS;

        try {
            foreach (Schema::getColumns((new Lead)->getTable()) as $column) {
                // MySQL reports the full type, e.g. "varchar(512)". TEXT columns have no
                // length here and need no clamp.
                if (preg_match('/^(?:var)?char\((\d+)\)/i', (string) ($column['type'] ?? ''), $matches)) {
                    $limits[(string) $column['name']] = (int) $matches[1];
                }
            }
        } catch (\Throwable) {
            // Introspection is best-effort; the constant map is the floor, not the only source.
        }

        return self::$resolvedColumnLimits = $limits;
    }

    /**
     * Trim every bounded column to what its schema can actually hold.
     *
     * Multibyte-aware: the limits are column *character* lengths, and `mb_substr` is what
     * matches how MySQL counts them. Cutting on bytes would both over-trim a UTF-8 campaign
     * name and risk splitting a character.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function clampToColumnLimits(array $attributes): array
    {
        foreach ($this->columnLimits() as $column => $limit) {
            if (! isset($attributes[$column]) || ! is_string($attributes[$column])) {
                continue;
            }

            if (mb_strlen($attributes[$column]) > $limit) {
                $attributes[$column] = mb_substr($attributes[$column], 0, $limit);
            }
        }

        return $attributes;
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
         */
        foreach ($data->attributionNamed as $column => $value) {
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
