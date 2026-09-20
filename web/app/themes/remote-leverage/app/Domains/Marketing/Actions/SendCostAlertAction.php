<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Actions;

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Marketing\Data\ChannelDay;
use App\Domains\Marketing\Data\FunnelSnapshot;
use App\Domains\Marketing\Data\MarketingDay;
use App\Domains\Marketing\Services\FunnelMetricsService;
use App\Domains\Marketing\Support\AlertWindow;
use App\Infrastructure\Slack\SlackTransport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Builds the marketing cost alert and puts it in Slack.
 *
 * ## One card per run, and the channel is the history
 *
 * Every run posts a new message. Twenty-four cards a day is the point rather than the cost: the
 * channel becomes a scrollable record of how the day developed, and a stakeholder who opens it at
 * lunchtime can see what the morning looked like without asking anyone.
 *
 * This was built the other way first — post once, then `chat.update` the same message hourly — on
 * the reasoning that near-identical cards are how a channel gets muted. That trades away the
 * thing the channel is for. An edited card answers "where do we stand now", which the dashboard
 * widget already answers on demand; only the channel can answer "what did this look like at
 * 06:00", and editing destroys that answer every hour.
 *
 * The consequence to keep in mind: a figure in the channel is a reading from the moment it was
 * posted and is never corrected afterwards. That is what a log is. Every card carries the hour it
 * was read at, so an old one is identifiable as old rather than merely wrong.
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
    /**
     * The last card posted: its date, timestamp and channel.
     *
     * Nothing reads it to decide what to send — every run posts. It is kept because the Slack
     * timestamp is otherwise unrecoverable: the bot has `chat:write` and no `channels:history`,
     * so a card it posted cannot be found again, and deleting one means knowing its `ts`. One
     * entry is a thin handle on that, and it costs a single option row.
     */
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
    public function execute(?CarbonImmutable $now = null, bool $force = false, ?callable $onProgress = null): bool
    {
        /*
         * Optional, and null everywhere except the dashboard's send button. The scheduled run has
         * nobody watching it; a person who pressed a button and is now looking at ten seconds of
         * nothing does. See FunnelMetricsService::snapshot() for why the steps are named.
         */
        $step = static function (string $label, int $percent) use ($onProgress): void {
            if ($onProgress !== null) {
                $onProgress($label, $percent);
            }
        };

        $step('Checking the environment and reporting window', 3);

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

        $snapshot = $this->metrics->snapshot($now, $onProgress);

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

        $step('Laying out the card', 92);

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

        $step('Posting to Slack', 96);

        return $this->deliver($rendered['text'], $rendered['blocks'], $color, $now, $snapshot->marketingDay);
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
        /*
         * Both notices, when both apply. A fabricated card sent from staging is two different
         * things a reader needs to know, and dropping one because the other is present is how
         * somebody takes a demo card for a staging card or the reverse.
         */
        $rendered = $this->renderer->render(
            'marketing_cost_alert',
            ['notice' => trim($notice."\n".$this->environmentNotice())] + $this->values($snapshot),
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
    /**
     * Post the card. Always a new message, never an edit.
     *
     * See the class docblock: the channel is the history, so each hourly run leaves its own
     * record. The posted message is remembered only so its timestamp exists somewhere.
     */
    private function deliver(
        string $text,
        array $blocks,
        ?string $color,
        CarbonImmutable $now,
        ?MarketingDay $day = null,
    ): bool {
        $channel = trim((string) config('marketing.cost_alert.channel', ''));

        $result = $this->slack->post($text, $blocks, $color, null, false, $channel !== '' ? $channel : null);

        if ($result === null) {
            return false;
        }

        /*
         * The webhook fallback returns a null `ts`. Nothing depends on having one any more, so a
         * missing timestamp is no longer a problem to defend against — it just means this card
         * cannot be found again later.
         */
        if (($result['ts'] ?? null) !== null && ($result['channel'] ?? null) !== null) {
            $this->remember([
                'card' => $this->cardKey($now, $day),
                'date' => $now->toDateString(),
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
            'notice' => $this->environmentNotice(),
            'subheading' => $this->subheading($snapshot),
            'headline_metrics' => $this->headlineMetrics($snapshot),
            'warnings' => $this->warnings($snapshot),
            'today_block' => $this->todayBlock($snapshot),
            ...$this->platformCards($snapshot),
            'activity_block' => $this->activityBlock($snapshot),
            'footnotes' => $this->footnotes($snapshot),
            ...$this->actionUrls(),
        ];
    }

    /**
     * The day's numbers, one fact per line, from the data team's warehouse.
     *
     * Everything here is the view's own arithmetic rather than this application's. That is the
     * point: the card has to agree with the dashboard the rest of the business reads, and the
     * fastest way to guarantee that is to do no arithmetic of our own.
     */
    private function todayBlock(FunnelSnapshot $snapshot): string
    {
        $day = $snapshot->marketingDay;

        if ($day === null) {
            return "*Today*\n".$this->spendUnavailable();
        }

        $lines = [sprintf('*%s*', $day->isClosing() ? 'Closing — '.$this->prettyDate($day->date) : 'Today, so far')];

        /*
         * Funnel order, each step carrying its share of the one above.
         *
         * The two percentages are same-window ratios, computed from the warehouse's own three
         * numbers so they cannot disagree with them. They are *not* conversion rates and must not
         * be read as such: a booking on this day can come from a lead captured last week, so the
         * denominator is not the cohort the numerator came from. The trailing booking rate in the
         * Activity block is the lead-level measure, and the two can differ sharply — that is the
         * factor-of-seventeen gap noted there, and it is why both appear rather than one.
         *
         * Sound enough on a closing report, which is a complete day. On a day-to-date card read at
         * 09:00 the ratio is dominated by which of the two lags more, so treat it as a shape check
         * rather than a rate.
         */
        $lines[] = sprintf('- Leads: %d', $day->leads);
        $lines[] = sprintf('- Bookings: %d%s', $day->appointments, $this->notes(
            $this->shareOf($day->appointments, $day->leads, 'leads'),
            $this->versusPrevious($day->appointments, $day->previousAppointments, $day),
        ));
        $lines[] = sprintf('- Qualified: %d%s', $day->qualified, $this->notes(
            $this->shareOf($day->qualified, $day->appointments, 'bookings'),
        ));

        if ($day->spend === null) {
            $lines[] = '- Spend: not reported for this day';

            return implode("\n", $lines);
        }

        $lines[] = sprintf('- Spend: %s%s', $this->money($day->spend, $snapshot->currency), $this->spendCaveat($day));
        $lines[] = sprintf('- CPL: %s', $this->money($day->cpl, $snapshot->currency));
        $lines[] = sprintf(
            '- CPB: %s%s%s',
            $this->money($day->cpbPaid, $snapshot->currency),
            $this->targetSuffix('cpb', $day->cpbPaid, $snapshot),
            $this->versusPreviousMoney($day->cpbPaid, $day->previousCpb, $day, $snapshot),
        );
        $lines[] = sprintf(
            '- CPQB: %s%s',
            $this->money($day->cpqbPaid, $snapshot->currency),
            $this->targetSuffix('cpqb', $day->cpqbPaid, $snapshot),
        );

        /*
         * Blended last, with its denominator named. It is the number the legacy alert printed
         * alone; above the paid figure it invites somebody to quote whichever is nicer.
         */
        if ($day->cpbAll !== null && $day->cpbAll !== $day->cpbPaid) {
            $lines[] = sprintf(
                '- Blended CPB: %s (all %d bookings, paid and not)',
                $this->money($day->cpbAll, $snapshot->currency),
                $day->appointments,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Whether the spend figure is final.
     *
     * `spend_is_complete` is only true on a closing report whose Meta feed covered the day. On a
     * day still running it is always false, which is honest rather than useful — so the caveat is
     * only printed when it says something the reader does not already know from the heading.
     */
    private function spendCaveat(MarketingDay $day): string
    {
        if ($day->spendIsComplete) {
            return '';
        }

        return $day->isClosing() ? ' _(Meta feed incomplete)_' : '';
    }

    /**
     * " (was 14)", but only when the comparison is fair.
     *
     * The view supplies the previous closed day on every report. Printing it against a
     * day-to-date figure compares two hours of today with a full yesterday, which makes every
     * morning look like a collapse and teaches people to ignore the comparison entirely. See
     * MarketingDay::hasFairComparison().
     */
    private function versusPrevious(int $current, ?int $previous, MarketingDay $day): string
    {
        if (! $day->hasFairComparison() || $previous === null || $previous === 0) {
            return '';
        }

        return sprintf('was %d on %s', $previous, $this->prettyDate((string) $day->previousDate));
    }

    private function versusPreviousMoney(?float $current, ?float $previous, MarketingDay $day, FunnelSnapshot $snapshot): string
    {
        if (! $day->hasFairComparison() || $current === null || $previous === null || $previous <= 0.0) {
            return '';
        }

        return sprintf(' (was %s)', $this->money($previous, $snapshot->currency));
    }

    /**
     * The parenthetical after a figure: " (65% of leads, was 35 on Fri 18 Sep)".
     *
     * One bracket however many notes there are. Two adjacent groups —
     * "63 (65% of leads) (was 35 on Fri 18 Sep)" — is the kind of density the card was
     * restructured to get rid of.
     */
    private function notes(string ...$parts): string
    {
        $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));

        return $parts === [] ? '' : ' ('.implode(', ', $parts).')';
    }

    /**
     * "50% of leads", or nothing when the denominator cannot carry it.
     *
     * Silent on a zero denominator rather than printing "0%" or a dash: with nothing to divide by
     * there is no share, and inventing one is how a quiet hour reads as a collapse. Silent above
     * 100% too — a step cannot exceed the one above it, so a figure that does is the two sides
     * measuring different things, and the honest response is to print the counts and say nothing
     * about the ratio.
     */
    private function shareOf(int $part, ?int $whole, string $noun): string
    {
        if ($whole === null || $whole <= 0 || $part > $whole) {
            return '';
        }

        return sprintf('%d%% of %s', (int) round($part / $whole * 100), $noun);
    }

    /** "Thu 18 Sep" from a warehouse date string, or the string itself if it will not parse. */
    private function prettyDate(string $date): string
    {
        try {
            return CarbonImmutable::parse($date)->format('D j M');
        } catch (\Throwable) {
            return $date;
        }
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

    /** Why there are no cost figures. The warehouse is the only source, so there is one reason. */
    private function spendUnavailable(): string
    {
        return '_'.$this->spendPending().'_';
    }

    /**
     * The pulse, one fact per line. Same shape as the legacy alert's "Lead Activity".
     *
     * Everything here comes from this site rather than the warehouse: the warehouse knows what
     * was spent and booked, it does not know when the last lead arrived or how full the calendar
     * is next Tuesday.
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

        $lines[] = sprintf('- Booking rate: %d%%', (int) round($snapshot->trailingBookingRate * 100));

        $consultations = $this->consultationsLine($snapshot);

        if ($consultations !== '') {
            $lines[] = $consultations;
        }

        /*
         * The unclassified bucket, as the warehouse defines it: everything that is not a paid
         * channel, so organic and direct are in it too. That is why this reads higher than the
         * share of bookings the site simply failed to attribute.
         */
        $day = $snapshot->marketingDay;

        if ($day !== null && $day->unclassifiedAppointments > 0) {
            $lines[] = sprintf(
                '- Not from paid: %d of %d bookings%s',
                $day->unclassifiedAppointments,
                $day->appointments,
                $day->unclassifiedShare === null ? '' : sprintf(' (%.1f%%)', $day->unclassifiedShare * 100),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Calendar load for today and the next two business days, each named.
     *
     * "29 next business day" makes the reader work out which day that is against today's date and
     * the weekend, and on a Friday they will get it wrong. "29 Monday 21st" does not.
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
            'unattributed_url' => admin_url('admin.php?page=rl-leads&date_range=today&platform=direct&status=booked'),
        ];
    }

    /**
     * One stacked card per channel, from the warehouse, spend-descending.
     *
     * The counts on these cards are the warehouse's, not this application's. Mixing the two would
     * produce a card whose spend came from one system and whose bookings came from another, and a
     * cost per booking built from that is not a measurement of anything.
     *
     * A channel that spent nothing gets no card. Three channels exist in the view and on a quiet
     * day one of them is zero all day; a row of dashes teaches people to skip the section.
     *
     * @return array<string, string>
     */
    private function platformCards(FunnelSnapshot $snapshot): array
    {
        $cards = [];

        foreach (range(1, 6) as $slot) {
            $cards["platform_{$slot}_title"] = '';
            $cards["platform_{$slot}_subtitle"] = '';
            $cards["platform_{$slot}_body"] = '';
        }

        $day = $snapshot->marketingDay;

        if ($day === null) {
            return $cards;
        }

        $channels = array_filter(
            $day->channels,
            static fn (ChannelDay $channel): bool => $channel->isActive(),
        );

        usort(
            $channels,
            static fn (ChannelDay $a, ChannelDay $b): int => ($b->spend ?? 0.0) <=> ($a->spend ?? 0.0),
        );

        foreach (array_slice($channels, 0, 6) as $index => $channel) {
            $slot = $index + 1;

            $cards["platform_{$slot}_title"] = $this->channelLabel($channel->slug);
            $cards["platform_{$slot}_subtitle"] = sprintf('%s spend', $this->money($channel->spend, $snapshot->currency));
            $cards["platform_{$slot}_body"] = sprintf(
                '*CPB* %s   *CPQB* %s',
                $this->money($channel->cpb, $snapshot->currency),
                $this->money($channel->cpqb, $snapshot->currency),
            );
        }

        return $cards;
    }

    /**
     * A channel's display name, with its logo when the workspace has one.
     *
     * Slack renders an emoji it does not have as the literal text `:meta:`, and this bot cannot
     * check which exist, so an unconfigured slug renders the plain name.
     */
    private function channelLabel(string $slug): string
    {
        $name = match ($slug) {
            'meta' => 'Meta',
            'google' => 'Google',
            'microsoft' => 'Microsoft',
            default => ucfirst($slug),
        };

        $emoji = trim((string) config('marketing.cost_alert.platform_emoji.'.$slug, ''));

        return $emoji === '' ? $name : $emoji.' '.$name;
    }

    /**
     * A banner on anything sent from an environment that is not production.
     *
     * The alert is not scheduled outside production, but it can still be fired by hand — the
     * dashboard button and `--force` both exist so somebody can test against real data. Those
     * land in the same channel production posts to, so without this a staging card is
     * indistinguishable from the real thing and somebody acts on figures from a test database.
     *
     * Empty on production, which prunes the block entirely.
     */
    private function environmentNotice(): string
    {
        $environment = function_exists('wp_get_environment_type')
            ? (string) \wp_get_environment_type()
            : (string) (env('WP_ENV') ?: 'production');

        if ($environment === 'production') {
            return '';
        }

        return sprintf(
            '*TEST MESSAGE — sent by hand from %s, not production.* The figures come from that '.
            "environment's database and may be stale, partial or invented. Nothing here is a real "
            .'trading number.',
            strtoupper($environment),
        );
    }

    /**
     * The line under the header: which day, as of when, in what currency.
     *
     * The warehouse's own `as_of_et` rather than this application's clock. They will normally
     * agree; when they do not, the figures are the warehouse's and so should the timestamp be.
     */
    private function subheading(FunnelSnapshot $snapshot): string
    {
        $day = $snapshot->marketingDay;

        if ($day === null) {
            return sprintf(
                '%s · %s',
                $snapshot->generatedAt->format('D j M Y, H:i T'),
                $snapshot->currency,
            );
        }

        return sprintf(
            '%s · as of %s ET · %s',
            $day->isClosing()
                ? 'Closing report for '.$this->prettyDate($day->date)
                : 'Day to date, '.$this->prettyDate($day->date),
            $day->asOfEt,
            $snapshot->currency,
        );
    }

    /** The notification preview: what somebody reads without opening Slack. */
    private function headlineMetrics(FunnelSnapshot $snapshot): string
    {
        $day = $snapshot->marketingDay;

        if ($day === null) {
            return 'Warehouse unavailable; funnel figures only';
        }

        return sprintf(
            '%d bookings, %d qualified, %s spend',
            $day->appointments,
            $day->qualified,
            $day->spend === null ? 'no' : $this->money($day->spend, $snapshot->currency),
        );
    }

    private function warnings(FunnelSnapshot $snapshot): string
    {
        return implode("\n", array_map(
            static fn (string $warning): string => '• '.$warning,
            $snapshot->warnings,
        ));
    }

    /** What the card says where the cost figures would be. */
    private function spendPending(): string
    {
        return 'Spend, CPB and CPQB come from the marketing warehouse, which did not answer this run.';
    }

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
        /*
         * Where the figures come from, first, because it is the thing somebody comparing this
         * card against the leads screen needs to know before anything else: the counts above are
         * the warehouse's, and its definition of a qualified booking does not have to match this
         * site's revenue band.
         */
        $parts = ['Spend and bookings from the marketing warehouse'];

        /*
         * This site's own qualified definitions, which the card no longer reports as figures but
         * still names — the HubSpot coverage below is meaningless without saying what it is
         * coverage of.
         */
        $parts[] = 'this site reads qualified as self-reported $10k+ MRR';

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
     * Whether the warehouse answered, in as few words as the situation allows.
     *
     * The legacy alert spent three lines a day saying "No active issues", once per platform. A
     * clause that says nothing on the overwhelming majority of days is a clause people learn to
     * skip, and then miss on the day it changes. Account health now lives in the warehouse's own
     * `spend_is_complete`, so all this has to report is whether the figures arrived.
     */
    private function healthClause(FunnelSnapshot $snapshot): string
    {
        return $snapshot->marketingDay === null ? 'warehouse unavailable' : 'warehouse current';
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
    /**
     * A label for what the last stored card was about: reported day and report kind.
     *
     * Recorded rather than acted on. Between midnight and 08:00 Eastern the warehouse reports the
     * previous day closed and from 08:00 the current day so far, so the calendar date alone does
     * not say which of the two a stored timestamp belongs to.
     */
    private function cardKey(CarbonImmutable $now, ?MarketingDay $day): string
    {
        if ($day === null || $day->date === '') {
            return $now->toDateString();
        }

        return $day->date.'/'.strtoupper($day->reportKind);
    }

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
