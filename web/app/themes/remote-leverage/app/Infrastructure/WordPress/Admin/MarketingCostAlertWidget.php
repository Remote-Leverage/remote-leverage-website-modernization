<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Data\ChannelDay;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Gateways\BigQueryClient;
use App\Domains\Marketing\Services\FunnelMetricsService;
use Carbon\CarbonImmutable;

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

    /** The `rl_action` value that fires an immediate send. */
    public const SEND_ACTION = 'rl_send_cost_alert';

    /**
     * Post today's card to Slack immediately.
     *
     * Bound from `MarketingServiceProvider`, which checks the request parameter before resolving
     * this class — see there for why.
     *
     * `force: true`, which bypasses both the reporting window and the environment gate. That is
     * the point of the button: somebody on staging at 9pm wants to see the real thing, and the
     * scheduled job will not oblige. The gates exist to stop the *scheduler* posting when nobody
     * asked; a person clicking a button has asked.
     *
     * It deliberately sends to whatever channel is configured rather than anywhere safer. A test
     * that posts somewhere else is not a test of the thing that will happen.
     */
    public function handleSendNow(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to send the marketing cost alert.');
        }

        check_admin_referer(self::SEND_ACTION);

        try {
            $sent = app(SendCostAlertAction::class)->execute(force: true);
            $result = $sent ? 'sent' : 'failed';
        } catch (\Throwable $e) {
            Log::error('MarketingCostAlertWidget: manual send failed', ['error' => $e->getMessage()]);
            $result = 'failed';
        }

        /*
         * Back to the dashboard rather than staying on a blank admin-post response. The result
         * travels in the query string because the alternative is a transient keyed by user, and
         * the only thing being communicated is one word.
         */
        wp_safe_redirect(add_query_arg('rl_cost_alert', $result, admin_url('index.php')));
        exit;
    }

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
             * Not an error. The hourly job warms this cache; until its first run of the
             * environment's life there is genuinely nothing to show.
             *
             * The actions still render. They used to sit behind an early return, which put the
             * one button that would populate this card behind the card being populated — on a
             * freshly deployed environment, the state where somebody most wants to press it.
             */
            echo '<p class="rl-dash-kpi-meta">No figures yet &mdash; the hourly marketing job has not run '
                .'since this environment last started. Send one now to fill this in, or wait for the next run.</p>';

            $this->renderWarehouseStatus();
            $this->renderActions();

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
        $day = $snapshot->marketingDay;

        /*
         * The trailing rate, not today's bookings over today's leads. See funnelLines() in
         * SendCostAlertAction: the same-day ratio looks like a conversion rate, is not one, and
         * disagreed with the real one by a factor of seventeen on the example card.
         */
        $bookingRate = $snapshot->trailingSampleSize > 0
            ? round($snapshot->trailingBookingRate * 100).'% of leads book, trailing '.$snapshot->trailingSampleSize
            : 'No booking rate yet';

        [$closing, $period] = $this->period($snapshot);

        ?>
        <div class="rl-dash-kpi-grid">
            <div class="rl-dash-kpi-card">
                <div class="rl-dash-kpi-header"><span class="rl-dash-kpi-label">Leads <?php echo $period; ?></span></div>
                <div class="rl-dash-kpi-number"><?php echo esc_html((string) $snapshot->leads); ?></div>
                <div class="rl-dash-kpi-meta"><?php echo esc_html($this->delta($snapshot->leads, $snapshot->baseline['leads'] ?? null, $closing)); ?></div>
            </div>

            <div class="rl-dash-kpi-card">
                <div class="rl-dash-kpi-header"><span class="rl-dash-kpi-label">Bookings <?php echo $period; ?></span></div>
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
                    <?php echo esc_html($day?->cpbPaid === null ? '&mdash;' : $this->money($day->cpbPaid, $snapshot->currency)); ?>
                </div>
                <div class="rl-dash-kpi-meta">
                    <?php
                    /*
                     * Rendered with a dash and a reason rather than hidden when there is nothing
                     * to show: a missing tile reads as a metric nobody tracks, where a dash with
                     * an explanation reads as one that is temporarily unavailable.
                     */
                    echo esc_html(match (true) {
                        $day === null => 'Warehouse did not answer',
                        $day->cpbPaid === null => 'No paid bookings yet',
                        default => 'Paid channels, from the warehouse',
                    });
        ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * What did not come from paid, then the channel table.
     *
     * Both from the warehouse. This site's own attribution still exists — the reconciler uses it
     * — but reporting one system's spend against another's bookings is not a measurement of
     * anything, so the table is the warehouse's throughout.
     */
    private function renderPlatforms(FunnelSnapshot $snapshot): void
    {
        $day = $snapshot->marketingDay;

        if ($day === null) {
            /*
             * Name the specific reason when there is one. "Did not answer" covers a missing
             * credential, a missing project id and a query that failed, and only the last of
             * those is something to go and read a log about.
             */
            $problem = app(BigQueryClient::class)->misconfiguration();

            printf(
                '<p class="rl-dash-kpi-meta">%s The figures above come from this site.</p>',
                $problem === null
                    ? esc_html('The marketing warehouse did not answer, so spend and the channel breakdown are unavailable.')
                    : esc_html('The marketing warehouse is not set up: '.$problem.'.'),
            );

            return;
        }

        if ($day->unclassifiedAppointments > 0) {
            ?>
            <p class="rl-dash-kpi-meta" style="margin:0 0 10px;">
                <strong><?php echo esc_html((string) $day->unclassifiedAppointments); ?></strong>
                of <?php echo esc_html((string) $day->appointments); ?> bookings
                <?php if ($day->unclassifiedShare !== null) { ?>
                    (<?php echo esc_html(number_format($day->unclassifiedShare * 100, 1)); ?>%)
                <?php } ?>
                did not come from a paid channel. Organic and direct are in that figure, so it is
                broader than bookings this site failed to attribute.
            </p>
            <?php
        }

        $channels = array_filter(
            $day->channels,
            static fn (ChannelDay $channel): bool => $channel->isActive(),
        );

        if ($channels === []) {
            return;
        }

        usort(
            $channels,
            static fn (ChannelDay $a, ChannelDay $b): int => ($b->spend ?? 0.0) <=> ($a->spend ?? 0.0),
        );
        ?>
        <table class="rl-dash-table">
            <thead>
                <tr><th>Channel</th><th>Spend</th><th>CPB</th><th>CPQB</th></tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $channel) { ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst($channel->slug)); ?></td>
                        <td><?php echo esc_html($this->money($channel->spend, $snapshot->currency)); ?></td>
                        <td><?php echo esc_html($this->money($channel->cpb, $snapshot->currency)); ?></td>
                        <td><?php echo esc_html($this->money($channel->cpqb, $snapshot->currency)); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
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

        $this->renderActions();
        ?>
        <p class="rl-dash-kpi-meta" style="margin-top:12px;border-top:1px solid #f4f4f5;padding-top:10px;">
            <?php echo esc_html(ucfirst($lastLead)); ?> &middot; <?php echo esc_html($lastBooking); ?><br>
            <?php if ($snapshot->excludedVaLeads > 0 || $snapshot->excludedVaBookings > 0) { ?>
                Excluded as likely VA applicants: <?php echo esc_html((string) $snapshot->excludedVaLeads); ?> leads,
                <?php echo esc_html((string) $snapshot->excludedVaBookings); ?> bookings. The test is a phone
                number outside the US and Canada, so it will occasionally catch a real client.<br>
            <?php } ?>
            <?php [$closing, $period] = $this->period($snapshot); ?>
            <?php if ($closing) { ?>
                A <strong>closing report</strong> for <?php echo $period; ?>: before 08:00 Eastern the
                warehouse reports the previous day complete, and both halves of this card follow it.
                Read at <?php echo esc_html($snapshot->generatedAt->format('H:i T')); ?>.<br>
            <?php } else { ?>
                Figures as at <?php echo esc_html($snapshot->generatedAt->format('H:i T')); ?>,
            <?php } ?>
            refreshed hourly by the marketing job rather than on this page load.
        </p>
        <?php
    }

    /**
     * The send button, and the outcome of the last press.
     *
     * Rendered on both paths — with figures and without. It is the only way to make the card fill
     * in without waiting an hour, so hiding it until the card has filled in is exactly backwards.
     */
    private function renderActions(): void
    {
        $result = sanitize_text_field((string) ($_GET['rl_cost_alert'] ?? ''));
        ?>
        <?php if ($result !== '') { ?>
            <p class="rl-dash-kpi-meta" style="margin-top:12px;color:<?php echo $result === 'sent' ? '#15803d' : '#b91c1c'; ?> !important;">
                <?php echo $result === 'sent'
                    ? 'Posted to Slack. It replaces today&rsquo;s card rather than adding another.'
                    : 'Slack rejected the message, or the alert is switched off. See the error log.'; ?>
            </p>
        <?php } ?>

        <form method="post" action="<?php echo esc_url(admin_url('index.php')); ?>" style="margin-top:12px;">
            <?php wp_nonce_field(self::SEND_ACTION); ?>
            <input type="hidden" name="rl_action" value="<?php echo esc_attr(self::SEND_ACTION); ?>" />
            <button type="submit" class="button button-secondary">Send to Slack now</button>
            <span class="rl-dash-kpi-meta" style="margin-left:8px;">
                Posts immediately, ignoring the reporting window and the environment gate.
            </span>
        </form>
        <?php
    }

    /**
     * Whether the warehouse is wired up, on the empty card.
     *
     * Shown here rather than only alongside figures, because the empty card is where somebody is
     * standing when they have just finished connecting and are wondering whether it took.
     */
    private function renderWarehouseStatus(): void
    {
        $problem = app(BigQueryClient::class)->misconfiguration();

        printf(
            '<p class="rl-dash-kpi-meta">%s</p>',
            $problem === null
                ? esc_html('Marketing warehouse: configured.')
                : esc_html('Marketing warehouse is not set up: '.$problem.'.'),
        );
    }

    /**
     * Why the HubSpot-qualified figure is or is not there.
     *
     * Shown on the T10 tile rather than as a tile of its own, because a tile reading "not
     * reported" is a tile people ask about once a week.
     */
    /**
     * Which day this card is about, as a flag and as a label.
     *
     * "Today" is a lie on a closing report. Before 08:00 Eastern the warehouse reports the
     * previous day complete and FunnelMetricsService follows it, so every figure here is that
     * day's — saying "today" over them is how a correct card gets read as a collapse. The Slack
     * renderer already names the day; this one did not, and both the tiles and the footer need
     * the answer.
     *
     * @return array{0: bool, 1: string} Whether this is a closing report, and the label for it.
     */
    private function period(FunnelSnapshot $snapshot): array
    {
        $day = $snapshot->marketingDay;
        $closing = $day !== null && $day->isClosing() && $day->date !== '';

        return [$closing, $closing ? esc_html($this->prettyDate($day->date)) : 'today'];
    }

    /**
     * "Fri 19 Sep", or the raw string if it will not parse.
     *
     * Mirrors SendCostAlertAction::prettyDate so the widget and the Slack card name a day the
     * same way — someone comparing the two should not have to work out whether two formats mean
     * the same date.
     */
    private function prettyDate(string $date): string
    {
        try {
            return CarbonImmutable::parse($date)->format('D j M');
        } catch (\Throwable) {
            return $date;
        }
    }

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

    /**
     * "+12% vs 7d avg at this hour", or "vs 7d avg" on a closing report.
     *
     * The qualifier has to match what FunnelMetricsService::baseline() actually measured. It
     * compares like for like — same hour against same hour on a day-to-date card, whole day
     * against whole days on a closing one — so saying "at this hour" over the second is a caption
     * describing a comparison that was not made.
     */
    private function delta(int $actual, ?float $baseline, bool $closing = false): string
    {
        if ($baseline === null || $baseline <= 0.0) {
            return 'No baseline yet';
        }

        return sprintf(
            '%+d%% vs %dd avg%s',
            (int) round(($actual - $baseline) / $baseline * 100),
            max(1, (int) config('marketing.cost_alert.baseline_days', 7)),
            $closing ? '' : ' at this hour',
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
