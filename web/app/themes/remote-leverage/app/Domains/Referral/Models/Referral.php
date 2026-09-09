<?php

declare(strict_types=1);

namespace App\Domains\Referral\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
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
     * Scope for converted leads.
     */
    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }
}
