<?php

declare(strict_types=1);

namespace App\Domains\Lead\Data;

use App\Domains\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a lead appears to be: a prospective client, or someone applying for VA work.
 *
 * People looking for work fill in the client intake form regularly, and the tell is the phone
 * number — the offer is sold to US and Canadian businesses, so a phone from anywhere else is
 * worth a second look before anyone spends time booking a consultation.
 *
 * This is a **prompt, not a verdict**. A genuine international client trips the same rule, which
 * is why the label says "Possible VA", why the reason travels with it, and why nothing in the
 * pipeline branches on it: no lead is dropped, downgraded or routed differently because of this
 * value. It exists so a human looks twice.
 *
 * It lives here rather than in the admin screen because the same judgement is now made in two
 * places — the leads dashboard and the Slack alert — and two copies of a heuristic drift into
 * two different answers about the same lead.
 */
readonly class LeadAudience
{
    /** Phone countries the offer is actually sold into. */
    private const HOME_COUNTRIES = ['US', 'CA'];

    public function __construct(
        public bool $possibleVirtualAssistant,
        public ?string $phoneCountry = null,
    ) {}

    /**
     * Read a lead's audience off what it was captured with.
     */
    public static function for(Lead $lead): self
    {
        $country = strtoupper(trim((string) $lead->phone_country));
        $country = $country === '' ? null : $country;

        /*
         * A referred lead is a client, full stop.
         *
         * Referrers introduce people they know are hiring — that is the whole basis of the
         * programme and what they are paid for. Tagging one of those as a possible applicant
         * over a phone country is not a borderline call, it is wrong, and it is the referrer's
         * introduction it undermines.
         */
        if (self::wasReferred($lead)) {
            return new self(false, $country);
        }

        return new self(self::phoneIsAwayFromHome($lead, $country), $country);
    }

    /**
     * Narrow a lead query to one side of the same judgement.
     *
     * `$mode` is 'clients' to exclude possible applicants, 'va' to show only them; anything else
     * is a no-op, because a filter nobody chose must not hide rows.
     *
     * This is the one part of the class that is allowed to restate the heuristic, and only
     * because a per-row PHP call cannot be paginated — filtering 3,969 leads in PHP means
     * loading all of them to show twenty. The restatement is the risk: SQL that drifts from
     * {@see self::for()} gives two answers about the same lead, one on the badge and one in the
     * filter that is supposed to hide it. LeadAttributionFilterTest walks a fixture matrix and
     * fails if any row is classified differently by the two.
     *
     * @param  Builder  $query
     */
    public static function constrain($query, string $mode): void
    {
        $mode = strtolower(trim($mode));

        if (! in_array($mode, ['clients', 'va'], true)) {
            return;
        }

        $isVa = static function ($q) {
            /*
             * Mirrors wasReferred(): a referred lead is never flagged, whatever the phone says.
             *
             * COALESCE rather than whereNotIn: the column is NOT NULL DEFAULT 'organic' today,
             * but SQL's `NULL NOT IN (...)` is NULL rather than true, so the day it is made
             * nullable those rows would drop off the VA side while PHP went on flagging them.
             */
            $q->whereRaw("COALESCE(source_type, '') NOT IN ('referral_hub', 'partnership')")
                ->whereRaw("TRIM(COALESCE(referral_code, '')) = ''")
                ->where(static function ($phone) {
                    // Mirrors phoneIsAwayFromHome(): the ISO-2 when we have it...
                    $phone->whereRaw("TRIM(COALESCE(phone_country, '')) <> '' AND UPPER(TRIM(phone_country)) NOT IN ('US', 'CA')")
                        // ...and only otherwise, the dial code of a number already in E.164.
                        ->orWhereRaw("TRIM(COALESCE(phone_country, '')) = '' AND TRIM(COALESCE(phone, '')) LIKE '+%' AND TRIM(COALESCE(phone, '')) NOT LIKE '+1%'");
                });
        };

        $mode === 'va'
            ? $query->where($isVa)
            : $query->whereNot($isVa);
    }

    /**
     * The badge text, or null when there is nothing to flag.
     */
    public function label(): ?string
    {
        return $this->possibleVirtualAssistant ? 'Possible VA' : null;
    }

    /**
     * One sentence of why, for a tooltip or a Slack context line. Null when nothing is flagged.
     *
     * Names the country when we have it: "outside the US and Canada" tells a salesperson that a
     * rule fired, where "from CO" tells them what to expect on the call.
     */
    public function note(): ?string
    {
        if (! $this->possibleVirtualAssistant) {
            return null;
        }

        $origin = $this->phoneCountry !== null
            ? "from {$this->phoneCountry}"
            : 'from outside the US and Canada';

        return "Phone number is {$origin} — this may be someone looking for VA work rather than hiring.";
    }

    /**
     * Did this lead arrive through the referral programme or a partnership?
     *
     * Both halves are checked because they are set independently: `source_type` is the channel
     * the attribution resolved to, and `referral_code` is the code that was actually carried.
     * A lead with a code but an organic source_type has still been introduced by somebody.
     */
    private static function wasReferred(Lead $lead): bool
    {
        if (in_array($lead->source_type, ['referral_hub', 'partnership'], true)) {
            return true;
        }

        return trim((string) $lead->referral_code) !== '';
    }

    /**
     * Is the phone number from outside the countries the offer is sold into?
     *
     * `phone_country` is the ISO-2 the form collected and is the reliable signal. The dial-code
     * fallback only reads a number already in E.164; anything else cannot be attributed to a
     * country without guessing, and a wrong guess here costs a real client a tag that says they
     * might not be one, so it declines to answer.
     */
    private static function phoneIsAwayFromHome(Lead $lead, ?string $country): bool
    {
        if ($country !== null) {
            return ! in_array($country, self::HOME_COUNTRIES, true);
        }

        $phone = (string) preg_replace('/[^0-9+]/', '', (string) $lead->phone);

        if (! str_starts_with($phone, '+')) {
            return false;
        }

        // +1 is the US, Canada and the Caribbean — close enough to home to leave untagged.
        return ! str_starts_with($phone, '+1');
    }
}
