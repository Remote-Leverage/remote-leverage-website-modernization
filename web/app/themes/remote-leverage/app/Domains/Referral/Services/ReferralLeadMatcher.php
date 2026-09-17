<?php

declare(strict_types=1);

namespace App\Domains\Referral\Services;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadProfile;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;

/**
 * Find the referral a given lead belongs to, and keep the two joined by id.
 *
 * Matching on `lead_email` alone — which is all this domain had before `rl_referrals.lead_id`
 * existed — is wrong in the ordinary case, not just at the edges. `CaptureLeadAction` creates a
 * **new** Lead row on every submission; it never updates one. So a prospect a referrer submits
 * by hand and the same prospect filling in the form themselves a week later are two Lead rows,
 * and a phone-only submission's row carries a synthesised `@remoteleverage.internal` address
 * that matches nothing at all.
 *
 * What ties those rows together is already built: `LeadProfile`, the identity "passport" that
 * `IdentityResolver` attaches every lead to. Matching through the profile is therefore the
 * accurate join, and it is why this lives in a service rather than being inlined into the one
 * listener that used to do it — the portal, the booking listener and the cancellation listener
 * must all agree on what "the same person" means, or they attribute to different rows.
 */
class ReferralLeadMatcher
{
    /**
     * Every lead id belonging to the same person as `$lead`.
     *
     * Falls back to just this lead when identity resolution has not run or found nothing —
     * never to an empty array, which as a `whereIn` would silently match every referral.
     *
     * @return array<int, int>
     */
    public function leadIdsForPersonOf(Lead $lead): array
    {
        if (! $lead->profile_id) {
            return [$lead->id];
        }

        $profile = LeadProfile::query()->find($lead->profile_id)?->canonical();

        if (! $profile) {
            return [$lead->id];
        }

        $ids = Lead::query()
            ->where('profile_id', $profile->id)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return $ids === [] ? [$lead->id] : array_values(array_unique([...$ids, $lead->id]));
    }

    /**
     * The referrer's existing referral for this person, or null.
     *
     * Tried in descending order of confidence: the identity graph first, then the legacy email
     * match so rows that predate `lead_id` (and any the backfill could not resolve) still
     * attribute. The email arm deliberately ignores blank and synthesised internal addresses —
     * those are not identities, and treating them as one would collapse every phone-only
     * referral onto whichever row happened to be first.
     */
    public function findForLead(Referrer $referrer, Lead $lead): ?Referral
    {
        $byIdentity = Referral::query()
            ->where('referrer_id', $referrer->id)
            ->whereIn('lead_id', $this->leadIdsForPersonOf($lead))
            ->orderBy('id')
            ->first();

        if ($byIdentity) {
            return $byIdentity;
        }

        $email = strtolower(trim((string) $lead->email));

        if ($email === '' || str_ends_with($email, '@remoteleverage.internal')) {
            return null;
        }

        return Referral::query()
            ->where('referrer_id', $referrer->id)
            ->whereNull('lead_id')
            ->whereRaw('LOWER(lead_email) = ?', [$email])
            ->orderBy('id')
            ->first();
    }

    /**
     * Backfill `lead_id` on a referral matched the legacy way, so the next lookup takes the
     * identity path above and the referrer portal can reach the lead's activity timeline.
     */
    public function link(Referral $referral, Lead $lead): Referral
    {
        if ($referral->lead_id === null) {
            $referral->forceFill(['lead_id' => $lead->id])->save();
        }

        return $referral;
    }
}
