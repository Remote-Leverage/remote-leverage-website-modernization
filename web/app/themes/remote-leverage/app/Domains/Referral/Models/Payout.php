<?php

declare(strict_types=1);

namespace App\Domains\Referral\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rl_payouts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'partner_id',
        'amount',
        'currency',
        'stripe_transfer_id',
        'status',
        'referral_ids',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'referral_ids' => 'array',
    ];

    /**
     * Partner associated with this payout.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
