<?php

declare(strict_types=1);

namespace App\Domains\Lead\Models;

use App\Domains\Lead\Data\LeadAudience;
use App\Infrastructure\Observability\IntegrationCall;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $table = 'rl_leads';

    protected $fillable = [
        'uuid',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_country',
        'company',
        'role_needed',
        // dynamic new fields
        'weekly_hours',
        'monthly_revenue',
        'start_date',
        'notes',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'referral_code',
        'landing_url',
        'referrer_url',
        'consent_at',
        'session_id',
        'posthog_session_id',
        'hubspot_contact_id',
        // Local mirror of the HubSpot contact's lifecycle stage. See the 2026_09_17_000003
        // migration and SyncHubSpotLifecycleAction.
        'hubspot_lifecycle_stage',
        'hubspot_lifecycle_changed_at',
        'hubspot_lifecycle_synced_at',
        'device_id',
        'utm_id',
        'li_fat_id',
        'fbc',
        'oppref',
        'partner',
        'data_source',
        'intake_form',
        'ip_address',
        'scheduler_link',
        'landing_page_base',
        'timezone',
        'submission_type',
        'attribution',
        'source_type',
        'source_id',
        'status',
        'booking_retry_count',
        'booking_next_retry_at',
        // Where this lead's Slack alert lives, so later events reply to it rather than
        // starting a new message. See the 2026_09_16_000007 migration.
        'slack_message_ts',
        'slack_channel_id',
        // something for dynamic properties
    ];

    protected $casts = [
        'booking_next_retry_at' => 'datetime',
        'consent_at' => 'datetime',
        'hubspot_lifecycle_changed_at' => 'datetime',
        'hubspot_lifecycle_synced_at' => 'datetime',
        // The HandL first-touch set plus any query parameter without a column of its own.
        // See App\Domains\Lead\Services\AttributionCollector.
        'attribution' => 'array',

        // The database hands back 0/1; without this every `=== true` check against it is false
        // and a blocked lead reads as unblocked.
        'is_blocked' => 'boolean',
    ];

    /**
     * Who this lead appears to be — a prospective client, or a possible VA applicant.
     *
     * The judgement itself lives in {@see LeadAudience}, which is also what carries the reason
     * the admin and the Slack alert print. Nothing routes on it; it is there so a human looks
     * twice at a lead the offer was never sold to.
     */
    public function audience(): LeadAudience
    {
        return LeadAudience::for($this);
    }

    /**
     * Link to this lead's PostHog session replay, or null when there is nothing to link to.
     *
     * Built from PostHog's own session id — `session_id` on this model is a UUID minted here
     * for internal correlation and means nothing to PostHog, so a link built from it would
     * always 404.
     *
     * Returns null rather than a broken link when the id or the project id is missing: a dead
     * link in an admin screen is worse than no link, because it looks like a PostHog problem.
     */
    public function posthogReplayUrl(): ?string
    {
        $sessionId = trim((string) $this->posthog_session_id);
        $projectId = trim((string) config('services.posthog.project_id', ''));

        if ($sessionId === '' || $projectId === '') {
            return null;
        }

        $appHost = rtrim((string) config('services.posthog.app_host', 'https://us.posthog.com'), '/');

        /*
         * A host configured without a scheme yields a relative URL, and Slack rejects a button
         * whose `url` is not absolute — taking the whole message with it, since that failure is
         * not an empty text object and so is not caught by SlackMessageRenderer's guard. The
         * default carries a scheme; this covers `POSTHOG_APP_HOST=us.posthog.com`.
         */
        if (! preg_match('#^https?://#i', $appHost)) {
            return null;
        }

        return "{$appHost}/project/{$projectId}/replay/".rawurlencode($sessionId);
    }

    /**
     * Link to this lead's person in PostHog, searched by email.
     *
     * The fallback for `posthogReplayUrl()`: a lead only carries a `posthog_session_id` when
     * the browser handed one over before submission, which it does not when PostHog is blocked,
     * loads slowly, or the visitor submits from a page load where the stamp never resolved — at
     * the time of writing that is most leads. Hiding the admin's PostHog card entirely in that
     * case reads as "this lead has no PostHog data", when what is true is "we do not know which
     * session it was". Searching by email lands on whatever PostHog does hold, including the
     * person's recordings.
     *
     * Unlike the replay link this cannot 404 on a bad id — a search with no match is an empty
     * result, not a broken link — so it only needs the project id.
     */
    public function posthogPersonUrl(): ?string
    {
        $email = trim((string) $this->email);
        $projectId = trim((string) config('services.posthog.project_id', ''));

        if ($email === '' || $projectId === '') {
            return null;
        }

        $appHost = rtrim((string) config('services.posthog.app_host', 'https://us.posthog.com'), '/');

        return "{$appHost}/project/{$projectId}/persons?q=".rawurlencode($email);
    }

    /**
     * Link to this lead's HubSpot contact record, or null when it has not synced.
     *
     * Needs both halves: the contact id and the portal id. Returning null when either is
     * missing keeps a dead link out of the Slack alert and the admin — a 404 there reads as a
     * broken integration rather than as "this lead never reached the CRM".
     */
    public function hubspotContactUrl(): ?string
    {
        $contactId = trim((string) $this->hubspot_contact_id);
        $portalId = trim((string) (config('services.hubspot.portal_id') ?: ''));

        if ($contactId === '' || $portalId === '') {
            return null;
        }

        // 0-1 is HubSpot's object-type id for contacts.
        return "https://app.hubspot.com/contacts/{$portalId}/record/0-1/{$contactId}";
    }

    /**
     * Get all activity log entries for this lead.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(LeadActivityLog::class, 'lead_id')->orderBy('created_at', 'asc');
    }

    /**
     * Every outbound integration call made on this lead's behalf.
     *
     * The counterpart to `activityLogs`: that records what each domain decided to do, this
     * records what actually crossed the wire when it did it.
     */
    public function integrationCalls(): HasMany
    {
        return $this->hasMany(IntegrationCall::class, 'lead_id')->orderBy('created_at', 'asc');
    }

    /**
     * Scope query to active un-abandoned leads.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['abandoned', 'canceled']);
    }
}
