<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

/**
 * What "qualified" means, in the two senses this business uses the word.
 *
 * ## Why this class exists
 *
 * The T10 definition was a four-element array literal repeated in five files — the leads
 * dashboard (four times), the marketing dashboard (three), the booking wizard, its Blade view
 * and a scheduling listener. Every one of them decides whether a person sees a different
 * calendar, a different badge or a different number, and they were kept in step by hand. Adding
 * a revenue band to the form meant finding all twelve occurrences, and missing one meant a lead
 * routed to the T10 calendar while the dashboard counted them as T0.
 *
 * The cost alert would have been the thirteenth. It divides ad spend by this number, so a drift
 * here is a wrong cost-per-qualified-booking, which is a number somebody moves budget on.
 *
 * ## Two definitions, deliberately both
 *
 * **T10** is self-reported: the revenue band the visitor picked on the intake form. It is
 * available on every lead the moment it is captured, it is what the whole site already routes
 * on, and it is what the legacy Slack alert's CPQB was computed from — so it is the only
 * definition that keeps the new series comparable to the old one.
 *
 * **HubSpot lifecycle** is what sales actually concluded. It is the better definition of
 * qualified and the worse one to rely on, for a reason that is not obvious from this class:
 *
 * > `hubspot_lifecycle_stage` is only populated for leads attached to an open referral.
 * > `SyncHubSpotLifecycleAction::pendingLeads()` scopes the poll that way on purpose, to keep a
 * > rate-limited API's quota off rows nothing reads.
 *
 * So a HubSpot-qualified count over all leads today is not merely incomplete, it is a count of
 * referrals wearing the label "qualified". {@see self::hubspotCoverage()} is how a caller finds
 * that out, and the cost alert refuses to print the figure when coverage is too thin rather than
 * printing a confident number built from 3% of the table. Widening the sync is what makes this
 * definition usable; until then it is wired, measured and honest about being empty.
 */
final class LeadQualification
{
    /**
     * The revenue bands that are *below* the T10 threshold.
     *
     * Expressed as the exclusions rather than the inclusions because that is how the rule has
     * always been written, and the reason is sound: the bands above $10k have been relabelled
     * more than once, and a whitelist silently drops a band the form starts emitting tomorrow.
     * A blacklist fails the other way — a new band counts as qualified until someone says
     * otherwise, which is the error that gets noticed.
     *
     * `<10k` and `under_10k` are the legacy Gravity Forms spellings. They are still in the
     * imported rows and must keep matching.
     *
     * @var array<int, string>
     */
    /**
     * Every monthly-revenue band a lead can report, in display order.
     *
     * Hoisted out of `multistep-booking-wizard.blade.php` on 2026-09-22, when the referrer
     * portal's direct-submission modal was brought up to the main form's requirements and
     * needed the same list. Two copies of a question's answers is how one form starts
     * offering a band the other cannot qualify.
     *
     * The wizard's two short-label maps (`'$0 to $5k Per Month' => '$0k to $5k'`, around lines
     * 634 and 676 of that view) are keyed on these strings and must keep matching. They map a
     * band to a compact label rather than defining which bands exist, so they are left where
     * they are.
     *
     * The values are user-visible copy *and* stored data — `monthly_revenue` on `rl_leads`
     * holds them verbatim, `isT10()` compares against them, and `SUB_T10_BANDS` below carries
     * the legacy Gravity Forms spellings for the same reason. Editing one is a data migration,
     * not a copy change.
     *
     * @var array<int, string>
     */
    public const REVENUE_BANDS = [
        '$0 to $5k Per Month',
        '$5k to $10k Per Month',
        '$10k to $50k Per Month',
        '$50k-$100k Per Month',
        '$100k+ Per Month',
    ];

    public const SUB_T10_BANDS = [
        '$0 to $5k Per Month',
        '$5k to $10k Per Month',
        '<10k',
        'under_10k',
    ];

    /**
     * Is this lead T10 — did it report a revenue band, and one at or above $10k?
     *
     * A lead with no band at all is *not* qualified. That is the existing behaviour on both
     * dashboards and it is the conservative reading: an unanswered question is not a yes.
     */
    public static function isT10(Lead $lead): bool
    {
        $band = trim((string) $lead->monthly_revenue);

        return $band !== '' && ! in_array($band, self::SUB_T10_BANDS, true);
    }

    /**
     * The SQL mirror of {@see self::isT10()}.
     *
     * @param  Builder  $query
     */
    public static function constrainT10($query): void
    {
        $query->whereNotNull('monthly_revenue')
            ->where('monthly_revenue', '!=', '')
            ->whereNotIn('monthly_revenue', self::SUB_T10_BANDS);
    }

    /**
     * A fresh lead query already narrowed to T10.
     *
     * Saves every caller that just wants the count from building a query and threading it
     * through {@see self::constrainT10()} on a separate line, which is the shape the call sites
     * this class replaced all had.
     *
     * @return Builder
     */
    public static function t10Query()
    {
        $query = Lead::query();

        self::constrainT10($query);

        return $query;
    }

    /**
     * HubSpot lifecycle stages that count as qualified, lowercased.
     *
     * Config rather than a constant because the ladder is a portal convention: a team that stops
     * at `salesqualifiedlead` and one that works `opportunity` mean different things by the word,
     * and that is a settings-shaped fact, not a code-shaped one.
     *
     * @return array<int, string>
     */
    public static function hubspotQualifiedStages(): array
    {
        $stages = (array) config('marketing.qualified.hubspot_stages', []);

        return array_values(array_filter(array_map(
            static fn ($stage): string => strtolower(trim((string) $stage)),
            $stages,
        )));
    }

    /** Has this lead reached a HubSpot stage that counts as qualified? */
    public static function isHubSpotQualified(Lead $lead): bool
    {
        $stage = strtolower(trim((string) $lead->hubspot_lifecycle_stage));

        return $stage !== '' && in_array($stage, self::hubspotQualifiedStages(), true);
    }

    /**
     * The SQL mirror of {@see self::isHubSpotQualified()}.
     *
     * @param  Builder  $query
     */
    public static function constrainHubSpotQualified($query): void
    {
        $stages = self::hubspotQualifiedStages();

        if ($stages === []) {
            // No configured ladder means nothing qualifies. `1 = 0` rather than leaving the
            // query untouched: an unconfigured definition must return nothing, not everything.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereNotNull('hubspot_lifecycle_stage')
            ->whereRaw(
                'LOWER(TRIM(hubspot_lifecycle_stage)) IN ('.implode(', ', array_fill(0, count($stages), '?')).')',
                $stages,
            );
    }

    /**
     * How much of a set of leads the HubSpot definition can actually speak for.
     *
     * Returns the number of leads carrying any lifecycle stage and the number in the set. The
     * caller decides what to do with a low ratio; see the class docblock for why it will be low.
     *
     * The clone matters — this runs alongside the counts the same builder produces, and adding
     * a `whereNotNull` to the caller's query instead of a copy of it would silently narrow every
     * figure computed after it.
     *
     * @param  Builder  $query
     * @return array{covered: int, total: int, ratio: float}
     */
    public static function hubspotCoverage($query): array
    {
        $total = (clone $query)->count();

        $covered = (clone $query)
            ->whereNotNull('hubspot_lifecycle_stage')
            ->where('hubspot_lifecycle_stage', '!=', '')
            ->count();

        return [
            'covered' => $covered,
            'total' => $total,
            'ratio' => $total > 0 ? $covered / $total : 0.0,
        ];
    }
}
