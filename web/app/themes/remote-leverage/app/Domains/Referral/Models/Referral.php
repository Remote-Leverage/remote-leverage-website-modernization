<?php

declare(strict_types=1);

namespace App\Domains\Referral\Models;

use App\Domains\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    /**
     * Every status this table is allowed to hold, in funnel order.
     *
     * Single source of truth shared by the admin screen and the referrer portal. The two used
     * to carry their own lists and they disagreed: the portal branched on `closed_won` and
     * scoped on `converted`, neither of which anything ever writes, so those branches were
     * dead while a genuinely fulfilled referral fell through to "Pending Review".
     */
    public const STATUSES = ['pending', 'qualified', 'fulfilled', 'rewarded', 'rejected'];

    /**
     * The statuses that mean the deal actually closed and a reward is owed.
     *
     * `qualified` is NOT one of them — that only means the prospect booked a call. See
     * FulfillReferralAction for why the distinction is the whole design.
     */
    public const FULFILLED_STATUSES = ['fulfilled', 'rewarded'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rl_referrals';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'referrer_id',
        'referrer_user_id',
        'lead_id',
        'lead_name',
        'lead_email',
        'lead_phone',
        'landing_page',
        'source',
        'status',
        'notes',
    ];

    /**
     * Referrer this referral is attributed to.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class, 'referrer_id');
    }

    /**
     * The lead this referral refers to.
     *
     * Nullable: referrals predating the `lead_id` column may not have resolved to a lead
     * during backfill, and a referral can be recorded for a prospect who never submitted a
     * form. Callers must handle null rather than assuming the relation loads.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Rewards associated with this referral.
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'referral_id');
    }

    /**
     * Scope for pending leads.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for referrals whose deal closed.
     *
     * Replaces `scopeConverted`, which filtered on a `converted` status that no code path in
     * this application has ever written — so it matched nothing, every time it was called.
     */
    public function scopeFulfilled($query)
    {
        return $query->whereIn('status', self::FULFILLED_STATUSES);
    }
}
