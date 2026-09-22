<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadQualification;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;

/**
 * Register a lead on a referrer's behalf, from whichever surface took the details.
 *
 * Two surfaces do this today and they must behave identically:
 *
 *  - `ReferrerPortalDashboard::submitDirectLead()` — the referrer, logged in, typing it
 *    themselves.
 *  - `SalesReferralForm` — a Remote Leverage sales rep at `/sales-referral`, typing it during
 *    a call while the referrer is on the phone (WR-126).
 *
 * They were going to be two copies of the same twenty lines, and the whole reason this class
 * exists is that the copies would not have stayed the same. The self-referral guard is a live
 * example: it shipped on the booking listener and the portal modal, and the modal's version
 * silently stopped working the moment a submission arrived without an email. A second
 * hand-written copy is a second place for that to happen.
 *
 * ## What it guarantees
 *
 * Every referred lead carries exactly what `MultistepBookingWizard` step 1 demands — first
 * name, last name, email, phone and a revenue band from `LeadQualification::REVENUE_BANDS`.
 * Not cosmetic: without a band, `LeadQualification::isT10()` answers "not qualified" for the
 * lead's whole life, because the question was never asked.
 *
 * And no referrer can be their own referred lead, on either surface.
 */
class SubmitReferredLeadAction
{
    /**
     * `rl_referrals.source` for a submission the referrer made themselves.
     */
    public const SOURCE_PORTAL = 'referrer_direct_submission';

    /**
     * `rl_referrals.source` for one a sales rep took during a call.
     *
     * A distinct value on purpose: these arrive from an unauthenticated URL, so "who typed
     * this" is worth being able to ask of the data later.
     */
    public const SOURCE_SALES = 'sales_rep_submission';

    public function __construct(
        protected CaptureLeadAction $captureLead,
    ) {}

    /**
     * The first thing wrong with a submission, or null when nothing is.
     *
     * Hand-rolled rather than Laravel's validator because both callers are Livewire components
     * reporting through a single error banner, and because `$this->validate()` resolves the
     * `livewire` container binding on its failure path — which the test harness does not
     * provide, so rules written that way cannot be tested at the point they reject.
     *
     * @param  array<string, string>  $fields
     */
    public static function validate(?Referrer $referrer, array $fields): ?string
    {
        $get = static fn (string $key): string => trim((string) ($fields[$key] ?? ''));

        if ($referrer === null) {
            return 'That referral code does not match an active referrer.';
        }

        if ($get('first_name') === '') {
            return 'Please enter the lead first name.';
        }

        if (mb_strlen($get('first_name')) > 60) {
            return 'That first name is too long.';
        }

        if ($get('last_name') === '') {
            return 'Please enter the lead last name.';
        }

        if (mb_strlen($get('last_name')) > 60) {
            return 'That last name is too long.';
        }

        $email = $get('email');

        if ($email === '') {
            return 'Please enter the lead email address.';
        }

        if (mb_strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'That does not look like a valid email address.';
        }

        $phone = $get('phone');

        if ($phone === '') {
            return 'Please enter the lead phone number.';
        }

        if (mb_strlen($phone) > 30) {
            return 'That phone number is too long.';
        }

        /*
         * A whitelist, not a hint. The band arrives from the browser, is stored verbatim on
         * `rl_leads.monthly_revenue`, and `LeadQualification::isT10()` compares against it
         * forever after — so a value outside the offered list would quietly misqualify the
         * lead rather than fail.
         */
        if (! in_array($fields['revenue'] ?? '', LeadQualification::REVENUE_BANDS, true)) {
            return 'Please select the lead monthly revenue band.';
        }

        /*
         * The self-referral guard, and the reason it can be unconditional: an email is
         * required above, so unlike the version this replaces there is no input shape that
         * skips it.
         */
        if (strtolower($email) === strtolower((string) $referrer->email)) {
            return 'You cannot submit yourself as a referred lead.';
        }

        return null;
    }

    /**
     * Capture the lead and record the referral. Assumes `validate()` already passed.
     *
     * @param  array<string, string>  $fields
     */
    public function execute(
        Referrer $referrer,
        array $fields,
        string $source = self::SOURCE_PORTAL,
        string $sourceForm = 'ReferrerPortalDirectLeadModal',
    ): Referral {
        $get = static fn (string $key): string => trim((string) ($fields[$key] ?? ''));

        $notes = $get('notes');
        $fullName = trim($get('first_name').' '.$get('last_name'));

        $lead = $this->captureLead->execute(LeadCaptureData::fromArray([
            'first_name' => $get('first_name'),
            'last_name' => $get('last_name'),
            'email' => $get('email'),
            'phone' => $get('phone'),
            'monthly_revenue' => $fields['revenue'] ?? '',
            'notes' => $notes ?: "Submitted on behalf of referrer {$referrer->referral_code}",
            'referral_code' => $referrer->referral_code,
            'extra_data' => [
                'source_form' => $sourceForm,
                'referrer_id' => $referrer->id,
            ],
        ]));

        return $this->recordReferral($referrer, $lead, $fullName, $fields, $source, $notes);
    }

    /**
     * @param  array<string, string>  $fields
     */
    protected function recordReferral(
        Referrer $referrer,
        Lead $lead,
        string $fullName,
        array $fields,
        string $source,
        string $notes,
    ): Referral {
        $get = static fn (string $key): string => trim((string) ($fields[$key] ?? ''));

        return $referrer->referrals()->create([
            /*
             * The actual foreign key. This was once recorded only in the note below, as prose,
             * so nothing could join on it — and when the prospect later booked, the
             * email-keyed lookup in HandleLeadBookingCompletedForReferrer failed to find this
             * row and inserted a duplicate referral beside it.
             */
            'lead_id' => $lead->id,
            'lead_name' => $fullName,
            'lead_email' => $get('email'),
            'lead_phone' => $get('phone'),

            /*
             * `landing_page` is deliberately left unset. There is no landing page for a
             * referral somebody typed into a form — the column means "the URL the prospect
             * arrived on", and `HandleLeadBookingCompletedForReferrer` fills it with the real
             * `$lead->landing_url` when they actually book. Both surfaces used to offer a
             * "Target service" picker whose value went here, which put a service name in a
             * column every other writer treats as a URL.
             */
            'source' => $source,

            /*
             * `pending`, never `qualified`. Somebody saying they will book is not a booking:
             * only `HandleLeadBookingCompletedForReferrer` promotes a referral, and only when
             * a booking actually completes. A sales rep typing this during a call is the least
             * appropriate place of all to shortcut that.
             */
            'status' => 'pending',
            'notes' => trim($notes."\n(lead #{$lead->id}, uuid {$lead->uuid})"),
        ]);
    }
}
