<?php

declare(strict_types=1);

namespace App\Domains\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One identifier — an email, a phone, a device — attached to a profile.
 *
 * Stored hashed. `strength` decides whether a match may merge two profiles on its own; see
 * IdentityResolver.
 */
class LeadIdentifier extends Model
{
    protected $table = 'rl_lead_identifiers';

    protected $fillable = [
        'profile_id',
        'type',
        'value_hash',
        'value_preview',
        'strength',
        'first_seen_at',
        'last_seen_at',
        'seen_count',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(LeadProfile::class, 'profile_id');
    }
}
