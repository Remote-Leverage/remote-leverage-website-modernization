<?php

declare(strict_types=1);

namespace App\Domains\Referral\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rl_referral_rewards';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'referrer_id',
        'referrer_user_id',
        'referral_id',
        'reward_type',
        'amount',
        'currency',
        'status',
        'description',
        'issued_at',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Referral lead associated with this reward.
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }
}
