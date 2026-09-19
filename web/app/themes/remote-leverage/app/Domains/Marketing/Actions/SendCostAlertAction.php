<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Actions;

use App\Domains\Lead\Services\LeadChannel;
use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Data\PlatformSlice;
use App\Domains\Marketing\Services\FunnelMetricsService;
use App\Domains\Marketing\Support\AlertWindow;
use App\Infrastructure\Slack\SlackTransport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Builds the marketing cost alert and puts it in Slack.
 *
 * ## One card a day, edited in place
 *
 * The alert this replaces posted a fresh message on every run. Nine near-identical cards a day
 * in a channel is how a channel becomes something people mute, and the history is worse than
 * useless: scrolling back a week means paging through sixty cards to find the six that mattered.
 *
 * So the first run of the day posts, and every run after it edits that same message. The channel
 * holds one live card showing where the day currently stands, and the history holds one row per
 * day. `SlackTransport::post()` already returned the `ts` needed to do this — the lead alerts
 * have threaded on it since 2026-09-16 — so the only new part is remembering it across runs.
 *
 * {@see self::STATE_OPTION} is that memory. It holds the date it belongs to, so the first run
 * after midnight sees a stale date and posts fresh rather than editing yesterday's card into
 * today's numbers, which would quietly destroy the record of yesterday.
 *
 * If the edit fails — somebody deleted the message, the channel changed underneath — it posts a
 * new card rather than dropping the run. A duplicate card is a visible, harmless annoyance; a
 * silent gap in a cost alert is neither.
 *
 * ## Why the formatting is here and the layout is not
 *
 * Every value handed to the renderer is a finished string, and the arrangement of them lives in
 * `config/slack-notifications.php` where it can be rebuilt in Block Kit Builder without touching
 * this class or its tests. The split is the same one the lead alert uses. What is *not*
 * delegated is the phrasing of the numbers — whether a figure is suppressed, what it says
 * instead, how a small sample is marked — because those are claims about data, not layout, and
 * they belong next to the reasoning that produced them.
 */
class SendCostAlertAction
{
    /** Where the day's card is remembered, so later runs edit it instead of reposting. */
    public const STATE_OPTION = 'rl_marketing_cost_alert_card';

    public function __construct(
        private readonly FunnelMetricsService $metrics,
        private readonly SlackTransport $slack,
        private readonly SlackMessageRenderer $renderer,
    ) {}

    /**
     * @param  bool  $force  Send outside the reporting window. For the console command, so a
     *                       human can check the output without waiting for 09:00.
     * @return bool Whether anything reached Slack.
     */
    public function execute(?CarbonImmutable $now = null, bool $force = false): bool
    {
        /*
         * `$force` bypasses the environment gate as well as the clock.
         *
         * Both exist to stop the *scheduler* posting when nobody asked. A person who typed the
         * command has asked, and the alternative is a console command that silently does nothing
         * on every environment but production and gives no hint why.
         */
        if (! $force && ! AlertWindow::enabledHere()) {
            return false;
        }

        /*
         * Was a moment handed to us, or are we reporting on right now?
         *
         * The difference decides whether the dashboard cache may be warmed below, and getting it
         * wrong is not subtle: `--date=2026-09-17` wrote a snapshot of that Thursday into the
         * widget's cache, and the dashboard then showed 57 leads under the heading "LEADS TODAY"
         * with a footer reading "as at 23:59". Every figure on it was real and none of it was
         * today.
         */
        $isBackfill = $now !== null;

        $timezone = (string) config('marketing.cost_alert.timezone', 'UTC');
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);

        if (! $force && ! AlertWindow::contains($now)) {
            return false;
        }

        $snapshot = $this->metrics->snapshot($now);

        /*
         * Hand the dashboard widget the snapshot we just paid for — but only when it is current.
         *
         * The widget's read path never computes, so something has to warm it, and a scheduled run
         * has already done every query and network call that warming would repeat. A run for a
         * past day has done those queries too, and its answers are worthless to a widget whose
         * every label says "today".
         */
        if (! $isBackfill) {
            $this->metrics->store($snapshot);
        }

        $rendered = $this->renderer->render('marketing_cost_alert', $this->values($snapshot));

        if ($rendered['blocks'] === []) {
            Log::warning('SendCostAlertAction: the template rendered no blocks; nothing sent.');

            return false;
        }

        /*
         * Red only when the reconciliation found something.
         *
         * Overriding the template's own colour rather than adding a second template: the layout
         * is identical either way, and two copies of it would be two things to keep in step.
         */
        $color = $snapshot->warnings !== [] ? '#b91c1c' : $rendered['color'];

        return $this->deliver($rendered['text'], $rendered['blocks'], $color, $now);
    }

    /**
     * Post a snapshot somebody handed us, marked as an example.
     *
     * Used by `--demo` to show the layout with spend connected, against a database that has no
     * such day in it. Two things it deliberately does not do:
     *
     * It does not remember the card. A fabricated preview must never become the message the next
     * real run edits — that would replace an example with live figures under a notice still
     * saying every number is made up, or leave real figures wearing it.
     *
     * It does not warm the widget cache. The dashboard reads that cache, and seeding it with
     * invented numbers would put them on an admin screen with no notice attached at all.
     */
    public function preview(FunnelSnapshot $snapshot, string $notice): bool
    {
        $rendered = $this->renderer->render(
            'marketing_cost_alert',
            ['demo_notice' => $notice] + $this->values($snapshot),
        );

        if ($rendered['blocks'] === []) {
            Log::warning('SendCostAlertAction: the demo template rendered no blocks; nothing sent.');

            return false;
        }

        $channel = trim((string) config('marketing.cost_alert.channel', ''));

        return $this->slack->post(
            $rendered['text'],
            $rendered['blocks'],
            $snapshot->warnings !== [] ? '#b91c1c' : $rendered['color'],
            null,
            false,
            $channel !== '' ? $channel : null,
        ) !== null;
    }

    /**
     * Edit today's card if there is one, otherwise post a new one.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function deliver(string $text, array $blocks, ?string $color, CarbonImmutable $now): bool
    {
        $channel = trim((string) config('marketing.cost_alert.channel', ''));
        $state = $this->state();
        $today = $now->toDateString();

        if (($state['date'] ?? null) === $today && ($state['ts'] ?? '') !== '' && ($state['channel'] ?? '') !== '') {
            if ($this->slack->update((string) $state['channel'], (string) $state['ts'], $text, $blocks, $color)) {
                return true;
            }

            Log::info("SendCostAlertAction: could not edit today's card, posting a new one.", [
                'ts' => $state['ts'],
            ]);
        }

        $result = $this->slack->post($text, $blocks, $color, null, false, $channel !== '' ? $channel : null);

        if ($result === null) {
            return false;
        }

        /*
         * Only remember a card that can actually be edited later.
         *
         * The webhook fallback returns a null `ts`, and storing that would make every subsequent
         * run take the update path, fail, and post anyway — the duplicate-card behaviour, once
         * an hour, in an environment that has no bot token precisely because nobody has finished
         * setting it up.
         */
        if (($result['ts'] ?? null) !== null && ($result['channel'] ?? null) !== null) {
            $this->remember([
                'date' => $today,
                'ts' => (string) $result['ts'],
                'channel' => (string) $result['channel'],
            ]);
        }

        return true;
    }

    /**
     * Every string the template can reference.
     *
     * A key resolving to an empty string is how a section is dropped — the renderer's `_when`
     * prunes on exactly that — so "this figure is not available" is expressed by leaving the key
     * empty and filling a different one with the explanation, never by writing "N/A".
     *
     * @return array<string, string>
     */
    private function values(FunnelSnapshot $snapshot): array
    {
        return [
            // Empty on every real card, so the renderer's `_when` prunes the notice block.
            'demo_notice' => '',
            'subheading' => $this->subheading($snapshot),
            'headline_metrics' => $this->headlineMetrics($snapshot),
            'warnings' => $this->warnings($snapshot),
            'today_block' => $this->todayBlock($snapshot),
            ...$this->platformCards($snapshot),
            'platform_notes' => $this->platformNotes($snapshot),
            'activity_block' => $this->activityBlock($snapshot),
            'footnotes' => $this->footnotes($snapshot),
            ...$this->actionUrls(),
        ];
    }

    /**
     * The day's numbers, one fact per line.
     *
     * Written to be read down a column, not across one. An earlier pass packed these onto two
     * lines separated by middots, which was shorter and slower — the eye has to parse the
     * separators and hold the labels. The legacy alert put one `Label: value` on each line and
     * that is what "easy to read" meant when the person who reads this daily asked for it back.
     *
     * Volume before cost, matching the order the legacy message used: what happened, then what it
     * cost. Without spend the last four lines simply are not there.
     */
    private function todayBlock(FunnelSnapshot $snapshot): string
    {
        $lines = ['*Today*'];

        $lines[] = sprintf(
            '- Bookings: %d%s',
            $snapshot->bookings,
            $this->parenthetical($this->delta($snapshot->bookings, $snapshot->baseline['bookings'] ?? null)),
        );
        $lines[] = sprintf(
            '- Qualified: %d%s',
            $snapshot->qualifiedT10,
            $this->parenthetical($this->delta($snapshot->qualifiedT10, $snapshot->baseline['qualified'] ?? null)),
        );
        $lines[] = sprintf(
            '- Leads: %d%s',
            $snapshot->leads,
            $this->parenthetical($this->delta($snapshot->leads, $snapshot->baseline['leads'] ?? null)),
        );
        $lines[] = sprintf('- Booking rate: %d%%', (int) round($snapshot->trailingBookingRate * 100));

        if (! $snapshot->hasSpend()) {
            $lines[] = $this->spendUnavailable($snapshot);

            return implode("\n", $lines);
        }

        $lines[] = sprintf('- Spend: %s', $this->money($snapshot->spend, $snapshot->currency));
        $lines[] = sprintf(
            '- CPB: %s%s',
            $this->money($snapshot->paidCpb(), $snapshot->currency),
            $this->targetSuffix('cpb', $snapshot->paidCpb(), $snapshot),
        );
        $lines[] = sprintf(
            '- CPQB: %s%s',
            $this->money($snapshot->paidCpqb(), $snapshot->currency),
            $this->targetSuffix('cpqb', $snapshot->paidCpqb(), $snapshot),
        );

        $blended = $this->blendedLine($snapshot);

        if ($blended !== '') {
            $lines[] = $blended;
        }

        return implode("\n", $lines);
    }

    /** " (+12% vs 7d)", or nothing. Keeps the sprintf call sites from sprouting conditionals. */
    private function parenthetical(string $value): string
    {
        $value = trim($value);

        return $value === '' ? '' : ' ('.$value.')';
    }

    /** " (under $300.00)", or nothing when no target is configured. */
    private function targetSuffix(string $key, ?float $amount, FunnelSnapshot $snapshot): string
    {
        $target = config("marketing.cost_alert.targets.{$key}");

        if ($amount === null || $target === null) {
            return '';
        }

        return sprintf(
            ' (%s %s)',
            $amount > (float) $target ? 'over' : 'under',
            $this->money((float) $target, $snapshot->currency),
        );
    }

    /**
     * The pulse, one fact per line. Same shape as the legacy alert's "Lead Activity".
     */
    private function activityBlock(FunnelSnapshot $snapshot): string
    {
        $lines = ['*Activity*'];

        $lines[] = sprintf(
            '- Last lead: %s',
            $snapshot->lastLeadMinutes === null ? 'none on record' : $this->duration($snapshot->lastLeadMinutes).' ago',
        );

        $lines[] = sprintf(
            '- Last booking: %s',
            $snapshot->lastBookingMinutes === null
                ? 'none on record'
                : sprintf(
                    '%s, %s ago',
                    $snapshot->lastBookingName ?? 'someone',
                    $this->duration($snapshot->lastBookingMinutes),
                ),
        );

        $consultations = $this->consultationsLine($snapshot);

        if ($consultations !== '') {
            $lines[] = $consultations;
        }

        if ($snapshot->bookings > 0 && $snapshot->unattributedBookings() > 0) {
            $channels = [];

            foreach ($snapshot->unattributedByChannel as $channel => $count) {
                $channels[] = $count.' '.LeadChannel::label($channel);
            }

            $lines[] = sprintf(
                '- Attribution gap: %d of %d bookings%s',
                $snapshot->unattributedBookings(),
                $snapshot->bookings,
                $channels === [] ? '' : ' — '.implode(', ', $channels),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Calendar load for today and the next two business days, each named.
     *
     * "29 next business day" makes the reader work out which day that is against today's date and
     * the weekend, and on a Friday they will get it wrong. "29 Monday 21st" does not.
     *
     * A day Calendly would not answer for is printed as `unavailable` rather than skipped or
     * zeroed — an empty calendar and an unreachable one mean opposite things, and one of them is
     * a revenue stop.
     */
    private function consultationsLine(FunnelSnapshot $snapshot): string
    {
        if ($snapshot->consultationsToday === null && $snapshot->upcomingConsultations === []) {
            return '';
        }

        $parts = [sprintf(
            '%s today',
            $snapshot->consultationsToday === null ? 'unavailable' : (string) $snapshot->consultationsToday,
        )];

        foreach ($snapshot->upcomingConsultations as $label => $count) {
            $parts[] = sprintf('%s %s', $count === null ? 'unavailable' : (string) $count, $label);
        }

        return '- Consultations: '.implode(', ', $parts);
    }

    /**
     * Where the buttons go.
     *
     * Empty outside WordPress, which drops the whole actions block — a button pointing nowhere is
     * worse than no button.
     *
     * @return array<string, string>
     */
    private function actionUrls(): array
    {
        if (! function_exists('admin_url')) {
            return ['dashboard_url' => '', 'leads_url' => '', 'unattributed_url' => ''];
        }

        return [
            'dashboard_url' => admin_url('index.php'),
            'leads_url' => admin_url('admin.php?page=rl-leads&date_range=today'),

            /*
             * Straight to the bookings the card could not attribute. `direct` is the bucket they
             * land in, and it is the one figure here somebody might want to read row by row.
             */
            'unattributed_url' => admin_url('admin.php?page=rl-leads&date_range=today&platform=direct&status=booked'),
        ];
    }

    /** Why there are no cost figures: not built, not answering, or the account is stopped. */
    private function spendUnavailable(FunnelSnapshot $snapshot): string
    {
        return '_'.match (true) {
            $snapshot->spendUnreachable !== [] => $this->spendSuppressed($snapshot, $snapshot->spendUnreachable, 'did not answer'),
            $snapshot->hasAccountIssues() => $this->spendSuppressed($snapshot, $snapshot->accountIssues, 'reports a problem with the ad account'),
            default => $this->spendPending(),
        }.'_';
    }

    /**
     * Blended cost per booking, as the last line of the list.
     *
     * Last on purpose. Blended is the number the legacy alert printed alone, and putting it above
     * the paid figure invites somebody to quote whichever is nicer. Below it, with its denominator
     * named, it reads as the caveat it is.
     */
    private function blendedLine(FunnelSnapshot $snapshot): string
    {
        if (! $snapshot->hasSpend()) {
            return '';
        }

        $understatement = $snapshot->blendedUnderstatement();

        return sprintf(
            '- Blended CPB: %s (all %d bookings%s)',
            $this->money($snapshot->blendedCpb(), $snapshot->currency),
            $snapshot->bookings,
            $understatement !== null && $understatement > 0.0
                ? sprintf(', %d%% cheaper than paid', (int) round($understatement * 100))
                : '',
        );
    }

    /**
     * One stacked card per platform, spend-descending so the money is read first.
     *
     * Title and body must resolve to something — a card missing either is dropped by
     * `hasEmptyTextObject`, which is the right outcome but a silent one. The subtitle is the only
     * genuinely optional part, and it is filled on every path anyway rather than relying on the
     * renderer to prune it.
     *
     * @return array<string, string>
     */
    private function platformCards(FunnelSnapshot $snapshot): array
    {
        $slices = $snapshot->reportablePlatforms();

        usort(
            $slices,
            static fn (PlatformSlice $a, PlatformSlice $b): int => ($b->spend ?? 0.0) <=> ($a->spend ?? 0.0)
                ?: $b->bookings <=> $a->bookings,
        );

        $cards = [];

        foreach (range(1, 6) as $slot) {
            $cards["platform_{$slot}_title"] = '';
            $cards["platform_{$slot}_subtitle"] = '';
            $cards["platform_{$slot}_body"] = '';
        }

        foreach (array_slice($slices, 0, 6) as $index => $slice) {
            $slot = $index + 1;

            $cards["platform_{$slot}_title"] = $this->shortLabel($slice);
            $cards["platform_{$slot}_subtitle"] = $slice->spend !== null
                ? sprintf('%s spend', $this->money($slice->spend, $snapshot->currency))
                : 'No spend connected';
            $cards["platform_{$slot}_body"] = $this->platformCardBody($slice, $snapshot);
        }

        return $cards;
    }

    private function platformCardBody(PlatformSlice $slice, FunnelSnapshot $snapshot): string
    {
        $lines = [];

        if ($slice->spend !== null) {
            $lines[] = sprintf(
                '*CPB* %s   *CPQB* %s',
                $this->money($slice->cpb(), $snapshot->currency),
                $this->money($slice->cpqb(), $snapshot->currency),
            );
        }

        /*
         * The booked count names its paid subset only when the two differ, which on live data
         * means Meta and only when `fbclid` rescued something. Printing "9 booked, 9 paid" on
         * every other card would make the one that matters invisible.
         */
        $booked = $slice->bookingsNotProvenPaid > 0
            ? sprintf('%d booked (%d paid)', $slice->bookings, $slice->paidBookings())
            : sprintf('%d booked', $slice->bookings);

        $lines[] = sprintf('%d leads · %s · %d qual', $slice->leads, $booked, $slice->qualified);

        if ($slice->isSmallSample()) {
            $lines[] = '_n too low for a rate_';
        }

        return implode("\n", $lines);
    }

    /**
     * "Meta (Facebook / Instagram)" trimmed to a card heading, with its logo when one is set up.
     *
     * The emoji is prefixed only when `marketing.cost_alert.platform_emoji` names one. Slack
     * renders an emoji the workspace does not have as the literal text `:meta:`, and this bot
     * cannot check which exist — so the default is no icon, and turning them on is a deliberate
     * act by somebody who has just uploaded them.
     */
    private function shortLabel(PlatformSlice $slice): string
    {
        $name = match ($slice->slug) {
            'meta' => 'Meta',
            'google' => 'Google',
            'microsoft' => 'Microsoft',
            'linkedin' => 'LinkedIn',
            'customerio' => 'Customer.io',
            default => $slice->label,
        };

        $emoji = trim((string) config('marketing.cost_alert.platform_emoji.'.$slice->slug, ''));

        return $emoji === '' ? $name : $emoji.' '.$name;
    }

    /**
     * The small print under the platform grid: what the numbers in it do not say.
     */
    private function platformNotes(FunnelSnapshot $snapshot): string
    {
        $notes = [];

        $viaClickId = array_sum(array_map(
            static fn (PlatformSlice $slice): int => $slice->bookingsViaClickId,
            $snapshot->reportablePlatforms(),
        ));

        if ($viaClickId > 0) {
            $notes[] = sprintf('%d attributed by click ID', $viaClickId);
        }

        /*
         * `fbclid` is on organic Facebook and Instagram links too, so a booking rescued by one
         * may be traffic the ad account never paid for. It stays in the platform count and out of
         * the cost denominator; the card names the number so the two cannot differ silently, and
         * docs/marketing-cost-alerts.md carries the reasoning.
         */
        if ($snapshot->notProvenPaidBookings() > 0) {
            $notes[] = sprintf('%d kept out of CPB (fbclid, may be organic)', $snapshot->notProvenPaidBookings());
        }

        return implode(' · ', $notes);
    }

    private function subheading(FunnelSnapshot $snapshot): string
    {
        return sprintf(
            '%s · day %d%% elapsed · %s',
            $snapshot->generatedAt->format('D j M Y, H:i T'),
            (int) round($snapshot->dayElapsed * 100),
            $snapshot->currency,
        );
    }

    /** The notification preview: what someone reads without opening Slack. */
    private function headlineMetrics(FunnelSnapshot $snapshot): string
    {
        return sprintf(
            '%d bookings, %d qualified, %d leads',
            $snapshot->bookings,
            $snapshot->qualifiedT10,
            $snapshot->leads,
        );
    }

    private function warnings(FunnelSnapshot $snapshot): string
    {
        return implode("\n", array_map(
            static fn (string $warning): string => '• '.$warning,
            $snapshot->warnings,
        ));
    }

    /**
     * What the alert says where the cost figures will go.
     *
     * Phrased as a missing integration rather than as missing data, and it names what *is*
     * trustworthy below it. An alert with a hole in it and no explanation gets read as broken.
     */
    private function spendPending(): string
    {
        return 'Spend, CPB and CPQB are not reported: no ad platform is configured. '.
            'Everything below is live.';
    }

    /**
     * What goes where the cost figures would be when a connected platform is down.
     *
     * Deliberately not the same sentence as {@see self::spendPending()}. "Not built yet" and
     * "built, and broken right now" need different reactions, and a card that says the same thing
     * for both gets the wrong one.
     */
    /**
     * @param  array<string, string>  $platforms
     */
    private function spendSuppressed(FunnelSnapshot $snapshot, array $platforms, string $because): string
    {
        return sprintf(
            'Cost figures suppressed: %s %s. A total that is missing a platform, or one reporting zero '.
            'because it is stopped, divides by too little spend and reads cheaper than the truth.',
            implode(', ', array_map('ucfirst', array_keys($platforms))),
            $because,
        );
    }

    /** Trim "Meta (Facebook / Instagram)" to something a fixed-width column can hold. */

    /**
     * Both qualified definitions, and why one of them is missing.
     *
     * The HubSpot figure is suppressed rather than estimated when its coverage is thin, and the
     * message says what the coverage is. See LeadQualification: the lifecycle sync only polls
     * referral-attached leads, so a count over it today is a count of referrals.
     */
    /**
     * The definitions and exclusions, as one line of small print.
     *
     * This was four paragraphs — a sentence each for the qualified definition, the HubSpot
     * coverage caveat, the VA exclusion and the ad account health. Every one of them was true and
     * worth knowing once, and none of them was worth re-reading on the ninth card of the day.
     * They are now clauses, and the reasoning behind each lives in
     * docs/marketing-cost-alerts.md, which is where anyone questioning a number will go.
     *
     * What survives is the part that changes: the *numbers* excluded, and the coverage
     * percentage. A reader who spots "9 leads excluded" on a day that felt busier than that can
     * still pull the thread.
     */
    private function footnotes(FunnelSnapshot $snapshot): string
    {
        $parts = ['Qualified = self-reported $10k+ MRR'];

        if ($snapshot->qualifiedHubSpot !== null) {
            $parts[] = sprintf('HubSpot-qualified %d', $snapshot->qualifiedHubSpot);
        } elseif ($snapshot->bookings > 0) {
            $parts[] = sprintf('HubSpot stage known for %d%%', (int) round($snapshot->hubSpotCoverage * 100));
        }

        if ($snapshot->excludedVaLeads > 0 || $snapshot->excludedVaBookings > 0) {
            $parts[] = sprintf(
                '%s, %s excluded as likely VA',
                $this->plural($snapshot->excludedVaLeads, 'lead'),
                $this->plural($snapshot->excludedVaBookings, 'booking'),
            );
        }

        $parts[] = $this->healthClause($snapshot);

        return implode(' · ', array_filter($parts));
    }

    /**
     * Ad account health, in as few words as the situation allows.
     *
     * The legacy alert spent three lines a day saying "No active issues", once per platform. A
     * clause that says nothing on the overwhelming majority of days is a clause people learn to
     * skip, and then miss on the day it changes. Any real problem is already a red finding at the
     * top of the card, so this only has to say whether to look.
     */
    private function healthClause(FunnelSnapshot $snapshot): string
    {
        if (! $snapshot->hasSpendIntegration()) {
            return 'ad health not monitored';
        }

        if ($snapshot->hasAccountIssues()) {
            return sprintf('%s flagged, see above', implode(', ', array_map('ucfirst', array_keys($snapshot->accountIssues))));
        }

        if ($snapshot->spendUnreachable !== []) {
            return sprintf('%s did not answer', implode(', ', array_map('ucfirst', array_keys($snapshot->spendUnreachable))));
        }

        return 'ad accounts clear';
    }

    /**
     * "+12% vs 7d", or nothing when there is no usable baseline.
     *
     * The comparison is against the *same hour* on previous days — bookings lag the spend that
     * produced them, so a mid-afternoon figure measured against a full-day average is
     * structurally pessimistic. The label does not say "at this hour" because the card is read
     * nine times a day and four words of methodology on four tiles is forty words of noise. That
     * detail lives in docs/marketing-cost-alerts.md, which is where somebody checking a number
     * goes anyway.
     */
    private function delta(int $actual, ?float $baseline): string
    {
        if ($baseline === null || $baseline <= 0.0) {
            return '';
        }

        $change = ($actual - $baseline) / $baseline;

        return sprintf(' %+d%% vs %dd', (int) round($change * 100), $this->baselineDays());
    }

    private function baselineDays(): int
    {
        return max(1, (int) config('marketing.cost_alert.baseline_days', 7));
    }

    private function plural(int $count, string $noun): string
    {
        return $count.' '.$noun.($count === 1 ? '' : 's');
    }

    private function money(?float $amount, string $currency): string
    {
        if ($amount === null) {
            return '—';
        }

        return ($currency === 'USD' ? '$' : $currency.' ').number_format($amount, 2);
    }

    /** Minutes as something a person reads at a glance: `6m`, `4h 29m`, `2d 3h`. */
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

    /** @return array<string, mixed> */
    private function state(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        $state = get_option(self::STATE_OPTION, []);

        return is_array($state) ? $state : [];
    }

    /** @param array<string, string> $state */
    private function remember(array $state): void
    {
        if (function_exists('update_option')) {
            update_option(self::STATE_OPTION, $state);
        }
    }
}
