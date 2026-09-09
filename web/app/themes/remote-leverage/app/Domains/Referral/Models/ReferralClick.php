<?php

declare(strict_types=1);

namespace App\Domains\Referral\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralClick extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rl_referral_clicks';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'referrer_id',
        'referrer_user_id',
        'referrer_slug',
        'landing_page',
        'ip_address',
        'user_agent',
        'referer_url',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];
}
