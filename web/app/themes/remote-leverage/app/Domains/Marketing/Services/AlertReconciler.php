<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Data\Finding;

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
     *     bookings_without_meeting?: array<int, string>,
     *     warehouse_bookings?: int|null,
     *     warehouse_unavailable?: bool,
     *     warehouse_age_minutes?: int|null,
     *     stale_platforms?: array<int, string>,
     *     spend_pending?: bool,
     *     report_date?: string|null,
     *     report_is_closing?: bool,
     * }  $facts
     * @return array<int, string> One sentence per finding, empty when everything ties up.
     */
    public function check(array $facts): array
    {
        return array_map(
            static fn (Finding $finding): string => $finding->text,
            $this->findings($facts),
        );
    }

    /**
     * The same findings, each carrying the key a dismissal is recorded against.
     *
     * This is the real method; {@see self::check()} is the text-only view of it, kept because
     * most callers and every assertion about wording only want the sentences.
     *
     * @param  array<string, mixed>  $facts
     * @return array<int, Finding>
     */
    public function findings(array $facts): array
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
            $findings[] = Finding::daily('bookings-without-leads', sprintf(
                '%d bookings today and not one new lead. Every one of them would have to be an older '.
                'lead booking late — possible, but this is also what a broken capture path looks like '.
                'while the calendar keeps working.',
                (int) $facts['bookings'],
            ));
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
            $findings[] = Finding::daily('booked-status-without-events', sprintf(
                '%d leads captured today are marked booked, but no booking event was recorded. Either '.
                'these are imported rows that never passed through the site, or booking logging has '.
                'stopped — every count and cost figure below is reading zero bookings either way.',
                (int) $facts['booked_by_status'],
            ));
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
            $findings[] = Finding::daily('platform-bookings-mismatch', sprintf(
                'Platform rows account for %d bookings but the total is %d. The per-platform costs '.
                'below are dividing spend by the wrong denominator.',
                (int) ($facts['platform_bookings'] ?? 0),
                (int) ($facts['bookings'] ?? 0),
            ));
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
            $findings[] = Finding::daily('stale-leads', sprintf(
                'No new lead for %dm during the working day, past the %dm threshold. Check the forms '.
                'and the ad accounts are still live.',
                $lastLead,
                $stale,
            ));
        }

        /*
         * The warehouse could not be read.
         *
         * Everything on the cost half comes from it, so this is not a degraded card, it is half a
         * card — and the half that is missing is the reason the alert exists. Named explicitly so
         * the gap where the money should be does not read as a day with no spend.
         */
        if (($facts['warehouse_unavailable'] ?? false) === true) {
            $findings[] = Finding::daily(
                'warehouse-unavailable',
                'The marketing warehouse did not answer, so spend, cost per booking and the '.
                'channel breakdown are all missing. The funnel figures below come from this site and are unaffected.',
            );
        }

        /*
         * Bookings this site confirmed that no provider issued a meeting for.
         *
         * The first finding here that is about a person rather than a number, and deliberately so
         * — it is the only one somebody can act on within the hour, by picking up a phone. Each
         * of these leads reached `booked`, was shown a confirmation, and has nothing on any
         * calendar; the consultant does not know they are coming and they do not know nobody is
         * expecting them.
         *
         * Named rather than counted for the same reason `lastBookingName` is: "3 bookings have no
         * meeting" is a number nobody can chase, and three names are three calls.
         *
         * Placed first because it outranks every cost figure on the card. Between 19 and 21
         * September five of these accumulated unnoticed, and the one that surfaced them did so
         * only because a warehouse gap was being investigated for an unrelated reason.
         */
        /*
         * One finding per person, not one listing everybody.
         *
         * A grouped sentence cannot be dismissed: the dismissal is per lead — you have rung
         * Marvin, not "the three of them" — and a sentence naming all three would go on naming a
         * dismissed one. So each is its own finding, keyed on the lead, and each disappears when
         * it has been dealt with.
         */
        foreach ((array) ($facts['bookings_without_meeting'] ?? []) as $leadId => $name) {
            $name = is_string($name) ? trim($name) : '';

            if ($name === '') {
                continue;
            }

            $findings[] = Finding::settled(
                'booking-no-meeting:'.$leadId,
                sprintf(
                    '%s is marked booked with no meeting on any calendar. Nothing was created at Calendly '.
                    'or Google, so no consultant is expecting them and they have been told otherwise. '.
                    'This needs a call, not a fix.',
                    $name,
                ),
            );
        }

        /*
         * The warehouse's booking count against this site's own.
         *
         * ## Why this is no longer a symmetric gap check
         *
         * It used to fire whenever the two differed by more than a quarter, and say that one of
         * them must be wrong. On 2026-09-21 at 11:05 it reported 4 against 10 and neither number
         * was wrong: the ten reconciled against the four exactly, record by record, once you knew
         * what each side counts.
         *
         * `vw_mkt_home_daily.bookings` does not read this site. It counts **RecruitCRM deals
         * created** that day, from `vw_mkt_deals`. This site counts leads whose first
         * `LeadBookingCompleted` lands that day. Four things separate them, all of them ordinary:
         *
         *   - a 4–8 minute lag from a site booking to the deal existing, so the newest bookings
         *     are always missing from the warehouse on a day-to-date card;
         *   - **a repeat booker opens no new deal.** Three of that morning's ten already had
         *     deals from 1, 7 and 18 September, so the warehouse counted them on those days;
         *   - two lead rows for one person are two bookings here and one deal there;
         *   - internal and test bookings are booked on the site and never reach the CRM.
         *
         * And it runs the other way too: deals are created in RecruitCRM by hand, which this site
         * never sees. So `warehouse < site` on a day in progress is the normal shape, and a check
         * that fires on the normal shape teaches people to skip the warnings — which is the thing
         * this class exists to prevent.
         *
         * ## What is still worth saying
         *
         * Two asymmetric cases, both of which mean a pipeline rather than a definition:
         *
         *   - the warehouse is materially *ahead* of the site. The lag only runs one way, so this
         *     is not the lag. Either booking logging here has stopped, or a batch of deals was
         *     created outside the site;
         *   - the warehouse is at zero while this site has been booking all day. That is the CRM
         *     intake having stopped, and every cost figure on the card divides by it.
         */
        $warehouseBookings = $facts['warehouse_bookings'] ?? null;
        $siteBookings = (int) ($facts['bookings'] ?? 0);

        if ($warehouseBookings !== null) {
            $warehouseBookings = (int) $warehouseBookings;

            /*
             * Name the day rather than saying "today".
             *
             * Before 08:00 Eastern the warehouse reports yesterday closed and this card follows
             * it, so "today" would be false on exactly the cards most likely to surprise someone.
             */
            $when = ($facts['report_is_closing'] ?? false) && ($facts['report_date'] ?? '') !== ''
                ? 'on '.$facts['report_date']
                : 'today';

            /*
             * A quarter, floored at three, so a quiet morning where one booking differs does not
             * cry wolf and a busy day where forty do is caught.
             */
            $tolerance = max(3, (int) ceil(max($warehouseBookings, $siteBookings) * 0.25));

            if ($warehouseBookings - $siteBookings > $tolerance) {
                $findings[] = Finding::daily('warehouse-ahead-of-site', sprintf(
                    'The warehouse reports %d bookings %s and this site recorded only %d. The warehouse counts '.
                    'RecruitCRM deals, which normally lag this site rather than lead it, so a surplus there is '.
                    'either booking logging having stopped here or deals created outside the site.',
                    $warehouseBookings,
                    $when,
                    $siteBookings,
                ));
            } elseif ($warehouseBookings === 0 && $siteBookings > $tolerance) {
                $findings[] = Finding::daily('warehouse-reports-nothing', sprintf(
                    'The warehouse reports no bookings %s while this site recorded %d. The two count different '.
                    'things — deals created against bookings taken — but not one deal against %d of them means '.
                    'the CRM intake has stopped, and every cost per booking above divides by zero.',
                    $when,
                    $siteBookings,
                    $siteBookings,
                ));
            }
        }

        /*
         * How old the warehouse's own copy is.
         *
         * Distinct from the "as of" on the card, which is when this application *asked* — that
         * keeps ticking forward whether or not the pipeline behind it is still running. A stalled
         * ETL therefore presents as a card full of frozen figures wearing a current timestamp,
         * which is the one failure mode nothing else here can see.
         *
         * Ninety minutes because the alert runs hourly and the extract runs more often than that;
         * a gap wider than one cycle means something has stopped rather than merely lagged.
         */
        $age = $facts['warehouse_age_minutes'] ?? null;
        $maxAge = (int) config('marketing.cost_alert.max_warehouse_age_minutes', 90);

        if (is_int($age) && $age > $maxAge) {
            $findings[] = Finding::daily('warehouse-stale', sprintf(
                'The warehouse last refreshed %s ago. Every cost figure above is from that moment, '.
                'not from now, however recent the timestamp on this card looks.',
                self::humanMinutes($age),
            ));
        }

        /*
         * An ad platform that has not finished reporting.
         *
         * Spend arrives short, so cost per booking comes out *better* than it is — the most
         * dangerous direction for a cost alert to be wrong in, because nobody questions good news.
         * The warehouse flags Meta, Google and Bing separately; the card used to read only Meta.
         */
        $stalePlatforms = (array) ($facts['stale_platforms'] ?? []);

        if ($stalePlatforms !== []) {
            $findings[] = Finding::daily('platforms-still-reporting', sprintf(
                '%s still reporting, so spend is understated and every cost per booking above is '.
                'lower than the real one. Treat them as a floor.',
                self::andList($stalePlatforms),
            ));
        } elseif (($facts['spend_pending'] ?? false) === true) {
            $findings[] = Finding::daily(
                'spend-pending',
                'Spend for this day is still settling, so the cost figures above may move.',
            );
        }

        return $findings;
    }

    /** "95 minutes" / "3h 10m", whichever reads faster at a glance. */
    private static function humanMinutes(int $minutes): string
    {
        if ($minutes < 120) {
            return $minutes.' minutes';
        }

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    /**
     * "Meta", "Meta and Google", "Meta, Google and Microsoft".
     *
     * @param  array<int, string>  $items
     */
    private static function andList(array $items): string
    {
        if (count($items) === 1) {
            return $items[0].' is';
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last.' are';
    }
}
