<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Lead\Services\LeadChannel;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Services\FunnelMetricsService;

/**
 * The marketing cost alert, on the wp-admin dashboard.
 *
 * ## Why the same numbers appear in two places
 *
 * Slack is the only surface that reaches someone who is not looking, and it is also the one that
 * silently does nothing when a bot token is missing from an environment. The dashboard is the one
 * that answers "what is it *right now*", which is the question you actually have when somebody
 * forwards you a screenshot of this morning's card and asks whether it got better.
 *
 * That is the same reasoning `AvailabilityHealthMonitor` uses for its three surfaces, and the
 * same trap: two renderings of one idea drift into two different answers. So they do not compute
 * anything. Both take a {@see FunnelSnapshot} from {@see FunnelMetricsService} and lay it out —
 * every definition, every suppression rule, every reconciliation check lives once, in the domain.
 * If this widget and the Slack card ever disagree it is a rendering bug, not a measurement one.
 *
 * ## It reads the cache, the alert does not
 *
 * A snapshot is about twenty-five queries and, with Calendly configured, several network calls.
 * That is fine hourly and unacceptable on every admin page load, so this reads
 * `cachedSnapshot()` and says how old the figures are. The footer carries that age rather than
 * hiding it: an hour-old number presented as live is the same class of mistake as the stale
 * "Last lead" line that made the legacy alert untrustworthy.
 *
 * `cachedSnapshot()` never computes — it returns null when the hourly warm has not run yet, and
 * this renders that as a plain "not computed yet" line. Falling back to computing here is what
 * the widget used to do, and with the cache silently broken it meant every dashboard load waited
 * 9 to 16 seconds on Calendly. An empty card that resolves itself within the hour is a far
 * smaller problem than an admin screen that hangs.
 */
class MarketingCostAlertWidget
{
    public function __construct(private readonly FunnelMetricsService $metrics) {}

    /**
     * Mount the card.
     *
     * Called from `MarketingServiceProvider` inside its own `wp_dashboard_setup` hook, at
     * priority 1000 — one past `MarketingDashboard`'s 999. That class removes the stock widgets
     * and adds its own on the same hook; running after it keeps this card below them rather than
     * racing for position in the column.
     */
    public function addWidget(): void
    {
        wp_add_dashboard_widget(
            'rl_dashboard_cost_alert',
            'Marketing Cost Alert',
            [$this, 'render'],
            null,
            null,
            'normal',
            'high',
        );
    }

    public function render(): void
    {
        $snapshot = $this->metrics->cachedSnapshot();

        if ($snapshot === null) {
            /*
             * Not an error. The hourly job warms this; until its first run of the environment's
             * life there is genuinely nothing to show, and saying so beats both a blank card and
             * a spurious "see the error log".
             */
            echo '<p class="rl-dash-kpi-meta">No figures yet &mdash; the hourly marketing job has not run since this environment last started. This card fills in on its next run.</p>';

            return;
        }

        $this->renderWarnings($snapshot);
        $this->renderHeadline($snapshot);
        $this->renderPlatforms($snapshot);
        $this->renderFooter($snapshot);
    }

    /**
     * The reconciliation findings, above everything.
     *
     * Same placement as the Slack card and for the same reason: their entire purpose is to stop
     * someone acting on the numbers underneath them.
     */
    private function renderWarnings(FunnelSnapshot $snapshot): void
    {
        if ($snapshot->warnings === []) {
            return;
        }

        ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 14px;margin-bottom:16px;">
            <div class="rl-dash-kpi-label" style="color:#b91c1c !important;">Check before trusting these numbers</div>
            <ul style="margin:8px 0 0;padding-left:18px;font-size:12px;color:#7f1d1d;">
                <?php foreach ($snapshot->warnings as $warning) { ?>
                    <li style="margin-bottom:4px;"><?php echo esc_html($warning); ?></li>
                <?php } ?>
            </ul>
        </div>
        <?php
    }

    private function renderHeadline(FunnelSnapshot $snapshot): void
    {
        /*
         * The trailing rate, not today's bookings over today's leads. See funnelLines() in
         * SendCostAlertAction: the same-day ratio looks like a conversion rate, is not one, and
         * disagreed with the real one by a factor of seventeen on the example card.
         */
        $bookingRate = $snapshot->trailingSampleSize > 0
            ? round($snapshot->trailingBookingRate * 100).'% of leads book, trailing '.$snapshot->trailingSampleSize
            : 'No booking rate yet';

        ?>
        <div class="rl-dash-kpi-grid">
            <div class="rl-dash-kpi-card">
                <div class="rl-dash-kpi-header"><span class="rl-dash-kpi-label">Leads today</span></div>
                <div class="rl-dash-kpi-number"><?php echo esc_html((string) $snapshot->leads); ?></div>
                <div class="rl-dash-kpi-meta"><?php echo esc_html($this->delta($snapshot->leads, $snapshot->baseline['leads'] ?? null)); ?></div>
            </div>

            <div class="rl-dash-kpi-card">
                <div class="rl-dash-kpi-header"><span class="rl-dash-kpi-label">Bookings today</span></div>
                <div class="rl-dash-kpi-number"><?php echo esc_html((string) $snapshot->bookings); ?></div>
                <div class="rl-dash-kpi-meta"><?php echo wp_kses_post($bookingRate); ?></div>
            </div>

            <div class="rl-dash-kpi-card">
                <div class="rl-dash-kpi-header">
                    <span class="rl-dash-kpi-label">Qualified</span>
                    <span class="rl-dash-badge-dark">T10</span>
                </div>
                <div class="rl-dash-kpi-number"><?php echo esc_html((string) $snapshot->qualifiedT10); ?></div>
                <div class="rl-dash-kpi-meta"><?php echo esc_html($this->hubSpotNote($snapshot)); ?></div>
            </div>

            <div class="rl-dash-kpi-card">
                <div class="rl-dash-kpi-header"><span class="rl-dash-kpi-label">Cost per booking</span></div>
                <div class="rl-dash-kpi-number">
                    <?php echo esc_html($snapshot->hasSpend() ? $this->money($snapshot->paidCpb(), $snapshot->currency) : '&mdash;'); ?>
                </div>
                <div class="rl-dash-kpi-meta">
                    <?php
                    /*
                     * Phase 1 has no ad platform read integration, so this tile has no number to
                     * show. It is still rendered, with the reason, rather than hidden: a missing
                     * tile reads as a metric nobody tracks, and a dash with an explanation reads
                     * as one that is coming.
                     */
                    echo esc_html(match (true) {
                        $snapshot->hasSpend() => 'Paid, attributed bookings only',
                        $snapshot->spendUnreachable !== [] => 'Suppressed: '
                            .implode(', ', array_keys($snapshot->spendUnreachable)).' did not answer',
                        default => 'No ad platform connected yet',
                    });
        ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * The attribution gap, then the per-platform table.
     *
     * The gap gets its own line above the table because it is the number that decides whether
     * the table is worth reading, and in the alert this replaces it was a footnote underneath.
     */
    private function renderPlatforms(FunnelSnapshot $snapshot): void
    {
        $slices = $snapshot->reportablePlatforms();

        if ($snapshot->bookings > 0 && $snapshot->unattributedBookings() > 0) {
            $channels = [];

            foreach ($snapshot->unattributedByChannel as $channel => $count) {
                $channels[] = $count.' '.LeadChannel::label($channel);
            }
            ?>
            <p class="rl-dash-kpi-meta" style="margin:0 0 10px;">
                <strong><?php echo esc_html((string) $snapshot->unattributedBookings()); ?></strong>
                of <?php echo esc_html((string) $snapshot->bookings); ?> bookings
                (<?php echo esc_html(number_format($snapshot->unattributedShare() * 100, 1)); ?>%)
                carry no platform &mdash; no recognised UTM and no click ID. They are in the totals above
                and in none of the rows below.
                <?php if ($channels !== []) { ?>
                    First-touch data says these were: <?php echo esc_html(implode(', ', $channels)); ?>.
                <?php } ?>
            </p>
            <?php
        }

        if ($slices === []) {
            return;
        }

        $withSpend = $snapshot->hasSpend();
        $viaClickId = 0;
        $smallSample = false;

        ?>
        <table class="rl-dash-table">
            <thead>
                <tr>
                    <th>Platform</th>
                    <th>Leads</th>
                    <th>Booked</th>
                    <th>Qualified</th>
                    <?php if ($withSpend) { ?>
                        <th>Spend</th>
                        <th>CPB</th>
                        <th>CPQB</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slices as $slice) {
                    $viaClickId += $slice->bookingsViaClickId;
                    $smallSample = $smallSample || $slice->isSmallSample();
                    ?>
                    <tr>
                        <td><?php echo esc_html($slice->label); ?></td>
                        <td><?php echo esc_html((string) $slice->leads); ?></td>
                        <td>
                            <?php echo esc_html((string) $slice->bookings); ?>
                            <?php if ($slice->isSmallSample()) { ?>
                                <span title="Too few bookings for the cost figures to be a rate">*</span>
                            <?php } ?>
                            <?php if ($slice->bookingsNotProvenPaid > 0) { ?>
                                <span class="rl-dash-kpi-meta" title="Reached only through an fbclid, which Facebook stamps on organic links too. Counted here, kept out of the cost per booking.">
                                    (<?php echo esc_html((string) $slice->paidBookings()); ?> paid)
                                </span>
                            <?php } ?>
                        </td>
                        <td><?php echo esc_html((string) $slice->qualified); ?></td>
                        <?php if ($withSpend) { ?>
                            <td><?php echo esc_html($this->money($slice->spend, $snapshot->currency)); ?></td>
                            <td><?php echo esc_html($this->money($slice->cpb(), $snapshot->currency)); ?></td>
                            <td><?php echo esc_html($this->money($slice->cpqb(), $snapshot->currency)); ?></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <?php if ($smallSample) { ?>
            <p class="rl-dash-kpi-meta" style="margin-top:8px;">
                * Fewer than <?php echo esc_html((string) config('marketing.cost_alert.small_sample', 3)); ?>
                bookings. One booking either way moves this a long way, so read it as a count, not a rate.
            </p>
        <?php } ?>

        <?php if ($viaClickId > 0) { ?>
            <p class="rl-dash-kpi-meta" style="margin-top:6px;">
                <?php echo esc_html((string) $viaClickId); ?> of these bookings were attributed by a click ID
                rather than a UTM tag.
                <?php if ($snapshot->notProvenPaidBookings() > 0) { ?>
                    <?php echo esc_html((string) $snapshot->notProvenPaidBookings()); ?> of them are kept out
                    of the cost per booking: they were reached only through an <code>fbclid</code>, which
                    Facebook stamps on organic links too, and the first-touch data does not say they were paid.
                <?php } ?>
            </p>
        <?php } ?>
        <?php
    }

    private function renderFooter(FunnelSnapshot $snapshot): void
    {
        $lastLead = $snapshot->lastLeadMinutes === null
            ? 'no lead on record'
            : 'last lead '.$this->duration($snapshot->lastLeadMinutes).' ago';

        $lastBooking = $snapshot->lastBookingMinutes === null
            ? 'no booking on record'
            : 'last booking '
                .($snapshot->lastBookingName !== null ? $snapshot->lastBookingName.', ' : '')
                .$this->duration($snapshot->lastBookingMinutes).' ago';

        ?>
        <p class="rl-dash-kpi-meta" style="margin-top:12px;border-top:1px solid #f4f4f5;padding-top:10px;">
            <?php echo esc_html(ucfirst($lastLead)); ?> &middot; <?php echo esc_html($lastBooking); ?><br>
            <?php if ($snapshot->excludedVaLeads > 0 || $snapshot->excludedVaBookings > 0) { ?>
                Excluded as likely VA applicants: <?php echo esc_html((string) $snapshot->excludedVaLeads); ?> leads,
                <?php echo esc_html((string) $snapshot->excludedVaBookings); ?> bookings. The test is a phone
                number outside the US and Canada, so it will occasionally catch a real client.<br>
            <?php } ?>
            Figures as at <?php echo esc_html($snapshot->generatedAt->format('H:i T')); ?>,
            refreshed hourly by the marketing job rather than on this page load.
        </p>
        <?php
    }

    /**
     * Why the HubSpot-qualified figure is or is not there.
     *
     * Shown on the T10 tile rather than as a tile of its own, because a tile reading "not
     * reported" is a tile people ask about once a week.
     */
    private function hubSpotNote(FunnelSnapshot $snapshot): string
    {
        if ($snapshot->qualifiedHubSpot !== null) {
            return sprintf('HubSpot-qualified: %d', $snapshot->qualifiedHubSpot);
        }

        if ($snapshot->bookings === 0) {
            return 'Self-reported $10k+ MRR';
        }

        return sprintf(
            'HubSpot stage known for %d%% — sync covers referrals only',
            (int) round($snapshot->hubSpotCoverage * 100),
        );
    }

    private function delta(int $actual, ?float $baseline): string
    {
        if ($baseline === null || $baseline <= 0.0) {
            return 'No baseline yet';
        }

        return sprintf(
            '%+d%% vs %dd avg at this hour',
            (int) round(($actual - $baseline) / $baseline * 100),
            max(1, (int) config('marketing.cost_alert.baseline_days', 7)),
        );
    }

    private function money(?float $amount, string $currency): string
    {
        return $amount === null
            ? '—'
            : ($currency === 'USD' ? '$' : $currency.' ').number_format($amount, 2);
    }

    private function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.'m';
        }

        if ($minutes < 1440) {
            return sprintf('%dh %dm', intdiv($minutes, 60), $minutes % 60);
        }

        return sprintf('%dd %dh', intdiv($minutes, 1440), intdiv($minutes % 1440, 60));
    }
}
