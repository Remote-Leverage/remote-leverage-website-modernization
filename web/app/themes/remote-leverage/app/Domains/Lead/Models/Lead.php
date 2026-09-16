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
