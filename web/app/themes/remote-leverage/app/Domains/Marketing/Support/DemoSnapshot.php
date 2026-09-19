<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use App\Domains\Lead\Services\LeadChannel;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Data\PlatformSlice;
use Carbon\CarbonImmutable;

/**
 * A fabricated snapshot, for seeing what a healthy card looks like.
 *
 * ## Why this exists rather than seeding rows
 *
 * Previewing the layout needs a day with spend, several platforms, a small-sample row and an
 * attribution gap. No local database has one — the copy developers work against is production
 * history with no ad spend attached, and "today" on it is empty. The alternatives were writing
 * fake leads into somebody's database and remembering to delete them, or this.
 *
 * ## The numbers are the legacy alert's, made coherent
 *
 * Spend, and the per-platform split, are lifted from the 2026-09-18 message this feature
 * replaces: $2,892.84 Meta, $737.46 Google, $101.94 Microsoft, $3,732.24 total. That is
 * deliberate. Anyone comparing the new card against the old one is holding the same numbers, so
 * the differences they see are differences in what the card *says about* them — paid against
 * blended, the attribution gap broken down by channel, the small-sample marker on Microsoft's
 * single booking — rather than differences in the data underneath.
 *
 * ## It is marked as an example on the card itself
 *
 * {@see self::NOTICE} goes at the top of the message. A card of plausible marketing figures
 * posted into a channel where people read real ones, with nothing saying it is fabricated, is a
 * worse outcome than no preview at all — somebody screenshots it, or acts on it.
 */
final class DemoSnapshot
{
    public const NOTICE = '*EXAMPLE CARD — every number below is fabricated.* '.
        'Posted with `marketing:cost-alert --demo` to show the layout with spend connected and a '.
        'healthy day. Nothing here came from the database.';

    public static function build(?CarbonImmutable $at = null): FunnelSnapshot
    {
        $timezone = (string) config('marketing.cost_alert.timezone', 'America/New_York');
        $at ??= CarbonImmutable::parse('2026-09-18 15:00:00', $timezone);

        /*
         * Meta carries one booking attributed by `fbclid` alone, so the card shows the
         * paid-versus-counted split that the whole LeadChannel gate exists for. Without a row
         * like it the preview would look tidier than the real thing ever does.
         */
        $platforms = [
            'meta' => new PlatformSlice(
                slug: 'meta', label: 'Meta (Facebook / Instagram)',
                leads: 212, bookings: 9, bookingsViaClickId: 2, qualified: 5,
                spend: 2892.84, bookingsNotProvenPaid: 1, qualifiedNotProvenPaid: 0,
            ),
            'google' => new PlatformSlice(
                slug: 'google', label: 'Google Ads',
                leads: 48, bookings: 4, bookingsViaClickId: 0, qualified: 3, spend: 737.46,
            ),
            'microsoft' => new PlatformSlice(
                slug: 'microsoft', label: 'Microsoft / Bing',
                leads: 12, bookings: 1, bookingsViaClickId: 0, qualified: 1, spend: 101.94,
            ),
            'direct' => new PlatformSlice(
                slug: 'direct', label: 'Direct / no UTM',
                leads: 38, bookings: 2, bookingsViaClickId: 0, qualified: 1,
            ),
            'other' => new PlatformSlice(
                slug: 'other', label: 'Other tagged source',
                leads: 4, bookings: 0, bookingsViaClickId: 0, qualified: 0,
            ),
        ];

        return new FunnelSnapshot(
            generatedAt: $at,
            timezone: $timezone,
            currency: (string) config('marketing.cost_alert.currency', 'USD'),
            dayElapsed: 0.63,
            leads: 314,
            bookings: 16,
            qualifiedT10: 10,

            /*
             * Suppressed, with a realistic coverage figure. The preview should show the state the
             * card is actually in today rather than an aspirational one — the HubSpot lifecycle
             * sync only polls referral-attached leads, so this line will read exactly like this
             * until that is widened.
             */
            qualifiedHubSpot: null,
            hubSpotCoverage: 0.12,

            platforms: $platforms,
            excludedVaLeads: 7,
            excludedVaBookings: 1,
            lastLeadMinutes: 6,
            lastBookingMinutes: 41,
            lastBookingName: 'Kayla Pinder',
            recentSampleBooked: 8,
            recentSampleSize: 10,
            trailingBookingRate: 0.84,
            trailingSampleSize: 1000,
            consultationsToday: 97,
            upcomingConsultations: ['Monday 21st' => 29, 'Tuesday 22nd' => 18],

            // Same hour, prior seven days. Today is running a little ahead on all three.
            baseline: ['days' => 7.0, 'leads' => 280.0, 'bookings' => 14.0, 'qualified' => 9.0],

            warnings: [],
            spend: 3732.24,
            unattributedByChannel: [
                LeadChannel::ORGANIC => 1,
                LeadChannel::SOCIAL => 1,
            ],
        );
    }
}
