<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Commands\SendCostAlertCommand;
use App\Domains\Marketing\Gateways\BigQueryClient;
use App\Domains\Marketing\Services\AlertReconciler;
use App\Domains\Marketing\Services\FunnelMetricsService;
use App\Domains\Marketing\Support\AlertWindow;
use App\Domains\Marketing\Support\CronHeartbeat;
use App\Infrastructure\Slack\SlackTransport;
use App\Infrastructure\WordPress\Admin\MarketingCostAlertWidget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * The marketing cost alert: its services, its console command and its schedule.
 *
 * ## Hourly, not once
 *
 * The job runs every hour and the action decides what to do with the tick — post the day's first
 * card, edit the existing one, or do nothing because the clock is outside the reporting window.
 * Putting that decision in the action rather than in the schedule is what makes the window a
 * config value people can change (`marketing.cost_alert.window`) instead of a cron expression
 * somebody has to redeploy.
 *
 * It also makes WP-Cron's unreliability survivable. WP-Cron fires on a request, so an hour with
 * no traffic simply does not tick — and because each run recomputes the whole card from the
 * database rather than accumulating anything, a skipped hour costs nothing but freshness. The
 * next tick draws the correct picture. An alert that counted deltas between runs would instead
 * be permanently wrong after one missed hour, which is the reason it does not.
 */
class MarketingServiceProvider extends ServiceProvider
{
    /** Posts the card. Production only. */
    public const CRON_HOOK = 'rl_marketing_cost_alert';

    /**
     * Refreshes the dashboard widget's figures. Every environment.
     *
     * Separate from the send, because they are gated differently and used to be the same hook.
     * The consequence of sharing one was that staging and local — where the alert is correctly
     * never scheduled — also never warmed the cache, so the wp-admin widget read "no figures yet"
     * forever. Reading the numbers is not the thing anybody wanted to prevent outside production;
     * posting them to a channel is.
     */
    public const WARM_HOOK = 'rl_marketing_warm_snapshot';

    public function register(): void
    {
        $this->app->singleton(AlertReconciler::class, fn () => new AlertReconciler);

        /*
         * The warehouse is the only source of spend and channel attribution.
         *
         * Three ad platform clients used to live here. They were deleted when the data team's
         * BigQuery view landed: they existed to obtain a number the warehouse already had
         * reconciled, and two sources for one number is how a Slack card and a dashboard start
         * disagreeing.
         */
        $this->app->singleton(BigQueryClient::class, fn () => new BigQueryClient);

        $this->app->singleton(FunnelMetricsService::class, fn ($app) => new FunnelMetricsService(
            $app->make(AlertReconciler::class),
            $app->make(BigQueryClient::class),
        ));

        $this->app->singleton(SendCostAlertAction::class, fn ($app) => new SendCostAlertAction(
            $app->make(FunnelMetricsService::class),
            $app->make(SlackTransport::class),
            $app->make(SlackMessageRenderer::class),
        ));

        $this->app->singleton(MarketingCostAlertWidget::class, fn ($app) => new MarketingCostAlertWidget(
            $app->make(FunnelMetricsService::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SendCostAlertCommand::class]);
        }

        $this->scheduleCostAlert();
        $this->registerDashboardWidget();
    }

    /**
     * The same snapshot, on the admin dashboard.
     *
     * Registered even when the Slack side is switched off: the figures are still worth having on
     * a screen, and an environment with no bot token is exactly the one where the dashboard is
     * the only place they can appear.
     *
     * The widget is resolved inside the `wp_dashboard_setup` callback rather than in `boot()`.
     * Resolving it eagerly pulled the whole graph — FunnelMetricsService, the warehouse client
     * and the reconciler — into being on
     * every front-end request, to register one action that only ever fires in wp-admin. The
     * constructors are cheap, so this was waste rather than breakage; the part that was not
     * merely waste is that a throw from any of them would have come out of `boot()` as a 500 on
     * a marketing page.
     */
    private function registerDashboardWidget(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        /*
         * The "send now" button posts back to `admin.php`, so its handler binds on `admin_init` —
         * by `wp_dashboard_setup` the request it needs to intercept is already past.
         *
         * The request parameter is checked *before* the widget is resolved. Resolving it pulls
         * FunnelMetricsService, the warehouse client and every spend client into being,
         * and doing that on every admin request to serve a button almost nobody presses is the
         * eagerness this provider already had once.
         */
        \add_action('admin_init', function () {
            if (($_REQUEST['rl_action'] ?? '') !== MarketingCostAlertWidget::SEND_ACTION) {
                return;
            }

            $this->app->make(MarketingCostAlertWidget::class)->handleSendNow();
        });

        /*
         * Dismissing a finding, and undoing that. Same lazy check and the same reason: this is a
         * button almost nobody presses, and resolving the widget to find that out would pull the
         * warehouse client into every admin request.
         */
        \add_action('admin_init', function () {
            if (($_REQUEST['rl_action'] ?? '') !== MarketingCostAlertWidget::DISMISS_ACTION) {
                return;
            }

            $this->app->make(MarketingCostAlertWidget::class)->handleDismiss();
        });

        /*
         * The same send, over AJAX, plus a poll for how far it has got.
         *
         * Same lazy-resolution rule as above, and it matters more here: the progress endpoint is
         * hit every few hundred milliseconds while a send runs, and it only reads a transient. It
         * resolves the widget for one static method's worth of work either way, so both are bound
         * to closures that do the cheap thing first.
         */
        \add_action('wp_ajax_'.MarketingCostAlertWidget::SEND_AJAX, function () {
            $this->app->make(MarketingCostAlertWidget::class)->handleSendAjax();
        });

        \add_action('wp_ajax_'.MarketingCostAlertWidget::PROGRESS_AJAX, static function () {
            MarketingCostAlertWidget::handleProgressAjax();
        });

        \add_action('wp_dashboard_setup', function () {
            try {
                $this->app->make(MarketingCostAlertWidget::class)->addWidget();
            } catch (\Throwable $e) {
                Log::error('MarketingServiceProvider: could not mount the cost alert widget', [
                    'error' => $e->getMessage(),
                ]);
            }
        }, 1000);
    }

    /**
     * The next exact top of the hour, as a UTC timestamp.
     *
     * Cron timestamps are UTC and every whole hour is a multiple of 3600 there, so this is the
     * same instant as :00 in any timezone whose offset is a whole number of hours — which covers
     * every timezone this runs in.
     *
     * The tick that fires it runs once a minute (docker/wp-cron.crontab), so an event due at
     * 04:00:00 is picked up within about a minute of it. "On the hour", not "to the second".
     */
    private static function nextHour(): int
    {
        return (intdiv(time(), HOUR_IN_SECONDS) + 1) * HOUR_IN_SECONDS;
    }

    private function scheduleCostAlert(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        \add_action('init', function () {
            /*
             * The warm runs everywhere. It reads the database and the warehouse and writes a
             * cache entry; it posts nothing, so there is nothing to gate.
             */
            if (! \wp_next_scheduled(self::WARM_HOOK)) {
                \wp_schedule_event(time(), 'hourly', self::WARM_HOOK);
            }

            $enabled = AlertWindow::enabledHere();
            $scheduled = \wp_next_scheduled(self::CRON_HOOK);

            if ($enabled && ! $scheduled) {
                \wp_schedule_event(self::nextHour(), 'hourly', self::CRON_HOOK);

                return;
            }

            /*
             * Drag an existing event back onto the hour.
             *
             * WP-Cron's 'hourly' is "every 3600 seconds from the first run", not "at :00". So the
             * card posts at whatever second this hook was first registered on that install and
             * keeps that offset forever — :51:42 on the machine this was written on. Changing the
             * schedule call alone fixes nothing on an environment that already has the event,
             * because wp_next_scheduled() is truthy and the branch above never runs.
             *
             * People read an hourly report as being about the hour. One that lands at 09:51
             * carrying figures "as of 09:51" invites the reasonable assumption that 10:00 is
             * missing.
             */
            if ($enabled && $scheduled && $scheduled % HOUR_IN_SECONDS !== 0) {
                \wp_unschedule_event($scheduled, self::CRON_HOOK);
                \wp_schedule_event(self::nextHour(), 'hourly', self::CRON_HOOK);

                return;
            }

            /*
             * Unschedule when switched off, rather than letting the job keep firing into an
             * action that returns early.
             *
             * The early return would work. It would also leave a cron entry that looks live on
             * every diagnostics screen, which is how someone spends an afternoon working out why
             * a disabled alert is "still running".
             */
            if (! $enabled && $scheduled) {
                /*
                 * Removing the event is a bigger act than skipping a run, and it used to happen in
                 * silence. One request in which `enabledHere()` reads false takes the hourly job
                 * off the schedule, and nothing anywhere records that it happened or why — the
                 * next person sees a cost alert that simply stopped, with a cron list that does
                 * not mention it, and no way to tell "switched off" from "broken".
                 *
                 * Logged as a warning rather than info because this is almost never intentional
                 * on production: the deliberate path is MARKETING_COST_ALERT_ENABLED=false, and
                 * the accidental one is that variable mapped to an empty string, which reads as
                 * false and looks identical from the outside.
                 */
                \wp_unschedule_event($scheduled, self::CRON_HOOK);

                Log::warning('MarketingServiceProvider: unscheduled the hourly cost alert — it is switched off here.', [
                    'environment_type' => function_exists('wp_get_environment_type') ? \wp_get_environment_type() : null,
                    'allowed_environments' => config('marketing.cost_alert.environments'),
                    'enabled_config' => config('marketing.cost_alert.enabled'),
                    'was_scheduled_for' => gmdate('c', (int) $scheduled),
                ]);
            }
        });

        \add_action(self::WARM_HOOK, function () {
            try {
                $this->app->make(FunnelMetricsService::class)->warmCache();
            } catch (\Throwable $e) {
                Log::error('MarketingServiceProvider: could not warm the cost alert snapshot', [
                    'error' => $e->getMessage(),
                ]);
            }
        });

        \add_action(self::CRON_HOOK, function () {
            /*
             * Tell Sentry the tick happened, so Sentry can tell us when it stops.
             *
             * This wraps the whole tick rather than the send: the question is whether the
             * scheduler is alive, and the alert legitimately posts nothing outside its window.
             * See CronHeartbeat for why the alerting has to come from a missing check-in rather
             * than from anything of ours noticing.
             */
            $startedAt = microtime(true);
            $ok = true;

            try {
                $this->app->make(SendCostAlertAction::class)->execute();
            } catch (\Throwable $e) {
                $ok = false;
                /*
                 * Swallowed deliberately. This runs on a visitor's request through WP-Cron, and
                 * an uncaught throw from a reporting job would surface as a 500 on a page someone
                 * was trying to read.
                 */
                Log::error('MarketingServiceProvider: the cost alert run failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            /*
             * Warm on the same tick as well as on the warm hook's own schedule.
             *
             * `execute()` returns early outside the reporting window, so the send cannot be
             * relied on to leave fresh figures behind — but when it has just run, this is nearly
             * free: `warmCache()` no-ops when the stored copy is recent, so it costs a cache read
             * rather than a second round of warehouse calls.
             */
            try {
                $this->app->make(FunnelMetricsService::class)->warmCache();
            } catch (\Throwable $e) {
                Log::error('MarketingServiceProvider: could not warm the cost alert snapshot', [
                    'error' => $e->getMessage(),
                ]);
            }

            /*
             * Last, so the duration covers the whole tick. This line used to sit at the end of
             * the warm hook instead, where neither `$ok` nor `$startedAt` exists: the warm threw
             * a TypeError every hour after its work was done, and this tick — the one the monitor
             * is for — never checked in at all.
             */
            $this->app->make(CronHeartbeat::class)->ran($ok, microtime(true) - $startedAt);
        });
    }
}
