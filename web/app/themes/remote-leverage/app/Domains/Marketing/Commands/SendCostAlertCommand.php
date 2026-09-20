<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Commands;

use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Services\FunnelMetricsService;
use App\Domains\Marketing\Support\DemoSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendCostAlertCommand extends Command
{
    protected $signature = 'marketing:cost-alert
                            {--force : Send even outside the configured reporting window}
                            {--dry : Print the figures to the terminal and post nothing}
                            {--date= : Report on this day (YYYY-MM-DD) instead of today}
                            {--channel= : Post to this channel instead of the configured one}
                            {--demo : Post a fabricated example card, clearly labelled as one}';

    protected $description = "Post or refresh today's marketing cost alert in Slack";

    /**
     * `--dry` exists because the alternative for checking a change is posting to a channel that
     * people read. It goes through the same {@see FunnelMetricsService} the real run does, so
     * what it prints is what the message will be computed from — it just stops short of Slack.
     *
     * `--date` reports on a past day. Useful against a database whose copy is a few days old,
     * where "today" is legitimately empty and says nothing about whether the alert works.
     */
    public function handle(SendCostAlertAction $action, FunnelMetricsService $metrics): int
    {
        try {
            $at = $this->resolveMoment();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry')) {
            return $this->dryRun($metrics, $at);
        }

        $channelOption = trim((string) $this->option('channel'));

        if ($channelOption !== '') {
            config(['marketing.cost_alert.channel' => $channelOption]);
        }

        if ($this->option('demo')) {
            return $this->demo($action, $at);
        }

        $this->line('Posting to '.config('marketing.cost_alert.channel').'.');

        $sent = $action->execute($at, force: (bool) $this->option('force'));

        if (! $sent) {
            $this->warn(
                'Nothing sent. Either the alert is disabled, the clock is outside the reporting '.
                'window (use --force), or Slack rejected the message — check the log.'
            );

            return self::FAILURE;
        }

        $this->info("Today's cost alert is posted or refreshed.");

        return self::SUCCESS;
    }

    /**
     * Post the fabricated example card.
     *
     * Exists because no local database has a day with ad spend on it, so there is no way to see
     * what a healthy card looks like short of inventing one. Inventing it here rather than
     * writing fake leads into somebody's database — which then has to be remembered and undone —
     * and the card announces itself as fabricated before the first number. See DemoSnapshot.
     */
    private function demo(SendCostAlertAction $action, ?CarbonImmutable $at): int
    {
        $channel = (string) config('marketing.cost_alert.channel');

        $this->line("Posting a labelled EXAMPLE card to {$channel}. No database figures are involved.");

        if (! $action->preview(DemoSnapshot::build($at), DemoSnapshot::NOTICE)) {
            $this->error('Slack rejected the example card. Check the log.');

            return self::FAILURE;
        }

        $this->info('Example card posted. It is not remembered as a daily card and will not be edited by a real run.');

        return self::SUCCESS;
    }

    /**
     * The moment to report on, from `--date`, or null for now.
     *
     * A bare date is read as the *end* of that day rather than midnight. Midnight of a past day
     * reports a day that had barely started — zero of everything — which looks exactly like a
     * broken integration rather than like the wrong end of the day being asked about.
     */
    private function resolveMoment(): ?CarbonImmutable
    {
        $date = trim((string) $this->option('date'));

        if ($date === '') {
            return null;
        }

        $timezone = (string) config('marketing.cost_alert.timezone', 'UTC');
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone);

        if ($parsed === false) {
            throw new \InvalidArgumentException("Could not read \"{$date}\" as a date. Use YYYY-MM-DD.");
        }

        return $parsed->endOfDay();
    }

    private function dryRun(FunnelMetricsService $metrics, ?CarbonImmutable $at = null): int
    {
        $snapshot = $metrics->snapshot($at);

        $this->line($snapshot->generatedAt->format('D j M Y, H:i T'));
        $this->newLine();

        $day = $snapshot->marketingDay;

        $this->table(['Metric', 'Value'], [
            ['Report', $day === null ? 'warehouse unavailable' : $day->reportKind.' for '.$day->date],
            ['Bookings (warehouse)', $day?->appointments ?? '-'],
            ['Qualified (warehouse)', $day?->qualified ?? '-'],
            ['Leads (warehouse)', $day?->leads ?? '-'],
            ['Spend', $day?->spend ?? 'unavailable'],
            ['CPB paid / all', ($day?->cpbPaid ?? '-').' / '.($day?->cpbAll ?? '-')],
            ['CPQB paid / all', ($day?->cpqbPaid ?? '-').' / '.($day?->cpqbAll ?? '-')],
            ['Not from paid', $day === null ? '-' : $day->unclassifiedAppointments.' bookings'],
            ['Bookings (this site)', $snapshot->bookings],
            ['Excluded as VA', $snapshot->excludedVaLeads.' leads, '.$snapshot->excludedVaBookings.' bookings'],
            ['Booking rate', round($snapshot->trailingBookingRate * 100).'%'],
        ]);

        foreach (($day->channels ?? []) as $channel) {
            if (! $channel->isActive()) {
                continue;
            }

            $this->line(sprintf(
                '  %-12s spend %-10s CPB %-10s CPQB %s',
                $channel->slug,
                $channel->spend,
                $channel->cpb ?? '-',
                $channel->cpqb ?? '-',
            ));
        }

        if ($snapshot->warnings !== []) {
            $this->newLine();
            $this->error('Reconciliation findings:');

            foreach ($snapshot->warnings as $warning) {
                $this->line('  - '.$warning);
            }
        }

        return self::SUCCESS;
    }
}
