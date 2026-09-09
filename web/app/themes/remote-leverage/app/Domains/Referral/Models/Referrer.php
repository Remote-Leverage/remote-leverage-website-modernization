<?php

declare(strict_types=1);

namespace App\Domains\Referral\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referrer extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rl_referrers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'referral_code',
        'company',
        'password',
        'stripe_account_id',
        'status',
        'metadata',
    ];

    /**
     * The attributes hidden from array/JSON serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Referrals associated with this referrer.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * Payouts associated with this referrer.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'referrer_id');
    }

    /**
     * Scope for active referrers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
