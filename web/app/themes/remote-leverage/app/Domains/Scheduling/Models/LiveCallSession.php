<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class LiveCallSession extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rl_live_call_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'session_id',
        'calendly_event_uri',
        'calendly_invitee_uri',
        'meeting_url',
        'status',
        'visitor_email',
    ];
}
