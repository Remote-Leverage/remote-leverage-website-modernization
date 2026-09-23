<?php

declare(strict_types=1);

namespace App\Domains\Lead\Models;

use App\Domains\Lead\Actions\RecordBouncedLeadAction;
use Illuminate\Database\Eloquent\Model;

/**
 * A booking-form submission that never became a lead because the email check refused it.
 *
 * Written by {@see RecordBouncedLeadAction}, read by the Bounced
 * Leads admin screen. Nothing else consumes it: these rows are explicitly *not* leads and must
 * stay out of exports, KPIs and CRM sync.
 */
class BouncedLead extends Model
{
    public $timestamps = false;

    protected $table = 'rl_bounced_leads';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'reason',
        'checked_by',
        'ip_address',
        'posthog_session_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'referral_code',
        'gclid',
        'fbclid',
        'msclkid',
        'fbc',
        'fbc_synthetic',
        'landing_url',
        'context',
        'attempts',
        'created_at',
        'last_seen_at',
    ];

    protected $casts = [
        'context' => 'array',
        'fbc_synthetic' => 'boolean',
        'attempts' => 'integer',
        'created_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Which gate produced the verdict, in the order EmailValidationService applies them.
     *
     * Keys match the `checked_by` it returns.
     */
    public const GATE_LABELS = [
        'format' => 'Malformed address',
        'blacklist' => 'Address blacklist',
        'domain_validator' => 'Domain list',
        'zerobounce' => 'ZeroBounce',
    ];

    /**
     * Human labels for every `reason` EmailValidationService can reject with.
     *
     * ZeroBounce statuses are not enumerated here — they arrive as `zerobounce_<status>` and
     * are labelled from the status itself, so removing one from
     * the blocked list (as `do_not_mail` was) needs no change here,
     * and adding one cannot leave a row with a blank label.
     */
    public const REASON_LABELS = [
        'malformed' => 'Not a valid address',
        'blacklisted_email' => 'Blacklisted address',
        'blocked_domain' => 'Blocked domain',
        'domain_not_allowed' => 'Domain not on allow list',
    ];

    public function reasonLabel(): string
    {
        $reason = (string) $this->reason;

        if (isset(self::REASON_LABELS[$reason])) {
            return self::REASON_LABELS[$reason];
        }

        if (str_starts_with($reason, 'zerobounce_')) {
            return 'ZeroBounce: '.str_replace('_', ' ', substr($reason, strlen('zerobounce_')));
        }

        return ucfirst(str_replace('_', ' ', $reason));
    }

    public function gateLabel(): string
    {
        return self::GATE_LABELS[(string) $this->checked_by] ?? (string) $this->checked_by;
    }
}
