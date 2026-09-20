<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Services;

/**
 * Checks the alert's own numbers against each other before it is allowed to say them.
 *
 * ## Why this exists
 *
 * The alert this replaces shipped, on 2026-09-18 at 15:00, these lines together:
 *
 *     Total New Appts: 14
 *     Total Spend:     $3,732.24
 *     Last lead:       1064 min ago     (17h 44m — the previous evening)
 *     Booking rate, last 1000 leads: 84%
 *
 * Fourteen appointments created that day, $3,732 spent to create them, and no lead since the
 * night before — while 84% of leads historically book. Those numbers do not describe the same
 * day. Something upstream was stale, and the alert printed it in full confidence because nothing
 * in it compared one figure against another. A number nobody can check is worse than a missing
 * number: the missing one gets chased.
 *
 * So the message carries its own audit. When a check fails the finding goes at the top, in the
 * reader's face, rather than the alert quietly shipping figures it has reason to doubt.
 *
 * ## What is not checked here
 *
 * Anything the type system or `LeadPlatform`'s partition property already guarantees. The
 * platform slices summing to the total is asserted by a test over the query builder, which is
 * a better place for it than a runtime check that can only report a bug after it has shipped.
 * These are the checks that depend on live data and can only be made at send time.
 */
class AlertReconciler
{
    /**
     * @param  array{
     *     leads: int,
     *     bookings: int,
     *     last_lead_minutes: int|null,
     *     last_booking_minutes: int|null,
     *     platform_bookings: int,
     *     within_window: bool,
     *     booked_by_status?: int,
     *     warehouse_bookings?: int|null,
     *     warehouse_unavailable?: bool,
     * }  $facts
     * @return array<int, string> One sentence per finding, empty when everything ties up.
     */
    public function check(array $facts): array
    {
        $findings = [];

        $lastLead = $facts['last_lead_minutes'] ?? null;
        $lastBooking = $facts['last_booking_minutes'] ?? null;

        /*
         * The 2026-09-18 failure.
         *
         * A whole day of bookings and not one new lead. Every booking that day would have to have
         * come from a lead captured before the day began, on a day carrying $3,732 of ad spend
         * and a lead-to-booking rate near one to one. That is what a dead capture path looks like
         * while the calendar keeps working, and it is the check that would have caught it.
         *
         * Note what is deliberately *not* checked: whether the newest booking is newer than the
         * newest lead. That reads like an impossibility and is not one — an hour with no new
         * leads in which an older lead books produces exactly that, and it is a completely
         * ordinary afternoon. Only the same *person* has to arrive before they can book, and this
         * function never sees a person. A check that fires on ordinary days is worse than no
         * check: it teaches people that the warnings at the top of the message are noise.
         */
        if (($facts['leads'] ?? 0) === 0 && ($facts['bookings'] ?? 0) > 0) {
            $findings[] = sprintf(
                '%d bookings today and not one new lead. Every one of them would have to be an older '.
                'lead booking late — possible, but this is also what a broken capture path looks like '.
                'while the calendar keeps working.',
                (int) $facts['bookings'],
            );
        }

        /*
         * Leads that say they are booked, on a day with no booking events at all.
         *
         * The two are different measures and are allowed to differ: `status` is a lead's current
         * state with no timestamp, the event is when it happened, and a lead captured yesterday
         * that books today belongs to different days under each. But *zero* events on a day where
         * leads are marked booked is not a difference in definition, it is the booking log not
         * being written — and the booking count, every cost figure that divides by it, and the
         * whole platform table all read zero while looking entirely healthy.
         *
         * This is not hypothetical. On the database as it stands, 40 leads captured on 17
         * September carry `status = 'booked'` and the entire activity log holds six booking rows.
         * Those are imported Gravity Forms rows that never passed through this application, which
         * is benign history — but a Calendly webhook that stopped writing its log would produce
         * exactly the same silence, and nothing else would report it.
         */
        if (($facts['booked_by_status'] ?? 0) > 0 && ($facts['bookings'] ?? 0) === 0) {
            $findings[] = sprintf(
                '%d leads captured today are marked booked, but no booking event was recorded. Either '.
                'these are imported rows that never passed through the site, or booking logging has '.
                'stopped — every count and cost figure below is reading zero bookings either way.',
                (int) $facts['booked_by_status'],
            );
        }

        /*
         * The rows on the card have to add up to the total printed above them.
         *
         * Cheap, and narrower than it looks: both sides are counted from the same collection in
         * one PHP pass, so today this can only fire if `LeadPlatform::for()` returns a slug that
         * `options()` does not list. It is *not* the guard on the SQL buckets partitioning the
         * table — `LeadAttributionFilterTest` is, and a runtime check could only report that
         * after it had already shipped a wrong number. This is here so that a future change to
         * how the slices are built cannot quietly desynchronise the table from its own total.
         */
        if (($facts['platform_bookings'] ?? 0) !== ($facts['bookings'] ?? 0)) {
            $findings[] = sprintf(
                'Platform rows account for %d bookings but the total is %d. The per-platform costs '.
                'below are dividing spend by the wrong denominator.',
                (int) ($facts['platform_bookings'] ?? 0),
                (int) ($facts['bookings'] ?? 0),
            );
        }

        /*
         * Silence during the working day.
         *
         * Reported as a finding rather than as the neutral statistic the legacy alert printed it
         * as. Outside the reporting window an overnight gap is just the night, so the check is
         * gated on being inside it.
         */
        $stale = (int) config('marketing.cost_alert.stale_lead_minutes', 180);

        if (($facts['within_window'] ?? false) && $lastLead !== null && $lastLead > $stale) {
            $findings[] = sprintf(
                'No new lead for %dm during the working day, past the %dm threshold. Check the forms '.
                'and the ad accounts are still live.',
                $lastLead,
                $stale,
            );
        }

        /*
         * The warehouse could not be read.
         *
         * Everything on the cost half comes from it, so this is not a degraded card, it is half a
         * card — and the half that is missing is the reason the alert exists. Named explicitly so
         * the gap where the money should be does not read as a day with no spend.
         */
        if (($facts['warehouse_unavailable'] ?? false) === true) {
            $findings[] = 'The marketing warehouse did not answer, so spend, cost per booking and the '.
                'channel breakdown are all missing. The funnel figures below come from this site and are unaffected.';
        }

        /*
         * The warehouse's booking count against this site's own.
         *
         * They are allowed to differ — different definitions, different load times, and the site
         * excludes likely VA applicants where the warehouse may not. A wide gap is different: the
         * card reports the warehouse's number, so if the site recorded materially fewer or more,
         * one of the two is wrong and nobody should be dividing spend by either until it is known
         * which.
         *
         * A quarter, floored at three, so a quiet morning where one booking differs does not cry
         * wolf and a busy day where forty do is caught.
         */
        $warehouseBookings = $facts['warehouse_bookings'] ?? null;
        $siteBookings = (int) ($facts['bookings'] ?? 0);

        if ($warehouseBookings !== null) {
            $gap = abs((int) $warehouseBookings - $siteBookings);
            $tolerance = max(3, (int) ceil(max((int) $warehouseBookings, $siteBookings) * 0.25));

            if ($gap > $tolerance) {
                /*
                 * Name the day rather than saying "today".
                 *
                 * Before 08:00 Eastern the warehouse reports yesterday closed and this card
                 * follows it, so "today" would be false on exactly the cards most likely to
                 * surprise someone. It was also what made this warning unreadable when the two
                 * halves covered different days: it shouted that one source was wrong when the
                 * real answer was that they were not being compared.
                 */
                $when = ($facts['report_is_closing'] ?? false) && ($facts['report_date'] ?? '') !== ''
                    ? 'on '.$facts['report_date']
                    : 'today';

                $findings[] = sprintf(
                    'The warehouse reports %d bookings %s and this site recorded %d. The figures above are '.
                    'the warehouse\'s; a gap this wide means one of the two is wrong.',
                    (int) $warehouseBookings,
                    $when,
                    $siteBookings,
                );
            }
        }

        return $findings;
    }
}
