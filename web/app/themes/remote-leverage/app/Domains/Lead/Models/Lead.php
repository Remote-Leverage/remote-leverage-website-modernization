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
        'weekly_hours',
        'start_date',
        'notes',
        'source_type',
        'source_id',
        'status',
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
