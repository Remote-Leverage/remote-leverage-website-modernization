<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Models;

use App\Domains\PartnerHub\Actions\RecordPartnershipProspectAction;
use App\Domains\PartnerHub\Actions\SubmitPartnershipProspectAction;
use App\Domains\PartnerHub\Support\PartnershipProspectMetrics;
use Illuminate\Database\Eloquent\Model;

/**
 * A company that asked to become a Remote Leverage partner.
 *
 * Written by {@see SubmitPartnershipProspectAction}, from the form at the foot of
 * `/become-a-partner/`, or by {@see RecordPartnershipProspectAction} for someone the team met
 * another way. These are **not leads** and not referrers: nothing here reaches HubSpot,
 * the ad platforms, the sales Slack stream or the referral programme. The partnerships team works
 * them from the Partners Hub → Prospects screen and their own channel.
 *
 * Not to be confused with `source_type = 'partnership'` in AttributionEngine, which means a lead
 * that a partner sent us. A prospect is the partner-to-be, before any of that.
 */
class PartnershipProspect extends Model
{
    /**
     * Every status a prospect can hold, in the order the conversation moves through them.
     *
     * `new` is the column default and what every submission starts as. The rest are set by hand
     * on the admin screen; nothing automatic promotes a prospect.
     */
    public const STATUSES = ['new', 'contacted', 'qualified', 'declined', 'converted'];

    /**
     * Where a row came from: the public form, or entered by hand on the Prospects screen.
     *
     * The column default is `form`, which is also what every row written before the column
     * existed was.
     */
    public const SOURCES = ['form' => 'Form', 'manual' => 'Manual entry'];

    protected $table = 'rl_partnership_prospects';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'company',
        'role',
        'organization_type',
        'monthly_revenue',
        'businesses_reached',
        'message',
        'notes',
        'status',
        'source',
        'booked_at',
        'calendly_event_uri',
        'calendly_invitee_uri',
        'landing_url',
        'referrer_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'ip_address',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'booked_at' => 'datetime',
    ];

    /**
     * The model has no default for `status` of its own, only the column does — and a freshly
     * created model does not re-read its row, so without this `$prospect->status` is null in
     * the very request that created it, which is the request the Slack card is built from.
     */
    protected $attributes = [
        'status' => 'new',
        'source' => 'form',
    ];

    /**
     * Every write drops the Overview's cached figures.
     *
     * On the model rather than beside each admin action, which is where the referral dashboard
     * does it, because most writes here do not come from the admin: the public form creates the
     * row and the Calendly embed stamps the booking on it. Invalidating only from the screen
     * would leave a new submission missing from the Overview for the cache's full lifetime.
     */
    protected static function booted(): void
    {
        static::saved(static fn () => PartnershipProspectMetrics::forget());
        static::deleted(static fn () => PartnershipProspectMetrics::forget());
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function hasBookedCall(): bool
    {
        return $this->booked_at !== null;
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[(string) $this->source] ?? (string) $this->source;
    }
}
