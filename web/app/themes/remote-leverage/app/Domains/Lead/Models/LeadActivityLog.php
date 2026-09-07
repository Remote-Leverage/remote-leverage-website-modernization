<?php

declare(strict_types=1);

namespace App\Domains\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivityLog extends Model
{
    public $timestamps = false;

    protected $table = 'rl_lead_activity_logs';

    protected $fillable = [
        'lead_id',
        'event_type',
        'actor_domain',
        'stage',
        'outcome',
        'description',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
