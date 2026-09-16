<?php

declare(strict_types=1);

namespace App\Domains\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person, across every identifier they have used — the "passport".
 *
 * A ban lives here rather than on an identifier, which is what makes it propagate: block the
 * profile once and every email, phone and device already attached to it is blocked, including
 * ones attached later.
 */
class LeadProfile extends Model
{
    protected $table = 'rl_lead_profiles';

    protected $fillable = [
        'uuid',
        'status',
        'blocked_at',
        'blocked_by',
        'block_reason',
        'merged_into_id',
        'merged_at',
        'lead_count',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'merged_at' => 'datetime',
    ];

    public function identifiers(): HasMany
    {
        return $this->hasMany(LeadIdentifier::class, 'profile_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'profile_id');
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    /**
     * Follow the merge chain to the profile that actually holds this identity now.
     *
     * Merges can chain — A merges into B, B later merges into C — and a stale pointer would
     * otherwise read a ban off a superseded row. Bounded rather than recursive so a cycle
     * introduced by a bad merge cannot hang a request; a cycle means the graph is already
     * wrong, and hanging would hide that rather than surface it.
     */
    public function canonical(int $maxDepth = 10): self
    {
        $profile = $this;
        $seen = [];

        while ($profile->merged_into_id !== null && count($seen) < $maxDepth) {
            if (in_array($profile->id, $seen, true)) {
                break;
            }

            $seen[] = $profile->id;
            $next = self::query()->find($profile->merged_into_id);

            if (! $next) {
                break;
            }

            $profile = $next;
        }

        return $profile;
    }
}
