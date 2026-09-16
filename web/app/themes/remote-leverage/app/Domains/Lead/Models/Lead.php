<?php

declare(strict_types=1);

namespace App\Domains\Lead\Models;

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
        // something for dynamic properties
    ];

    protected $casts = [
        'booking_next_retry_at' => 'datetime',
        'consent_at' => 'datetime',
        // The HandL first-touch set plus any query parameter without a column of its own.
        // See App\Domains\Lead\Services\AttributionCollector.
        'attribution' => 'array',
    ];

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

        return "{$appHost}/project/{$projectId}/replay/".rawurlencode($sessionId);
    }

    /**
     * Get all activity log entries for this lead.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(LeadActivityLog::class, 'lead_id')->orderBy('created_at', 'asc');
    }

    /**
     * Scope query to active un-abandoned leads.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['abandoned', 'canceled']);
    }
}
