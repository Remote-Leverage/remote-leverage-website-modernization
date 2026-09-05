<?php

declare(strict_types=1);

namespace App\Domains\Referral\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rl_partners';

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
        'stripe_account_id',
        'status',
        'metadata',
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
     * Referrals associated with this partner.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'partner_id');
    }

    /**
     * Payouts associated with this partner.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'partner_id');
    }

    /**
     * Scope for active partners.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
