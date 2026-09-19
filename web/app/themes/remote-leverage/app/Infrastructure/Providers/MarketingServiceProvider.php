<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Commands\SendCostAlertCommand;
use App\Domains\Marketing\Gateways\MetaInsightsClient;
use App\Domains\Marketing\Services\AdSpendCollector;
use App\Domains\Marketing\Services\AlertReconciler;
use App\Domains\Marketing\Services\FunnelMetricsService;
use App\Domains\Marketing\Support\AlertWindow;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
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
    public const CRON_HOOK = 'rl_marketing_cost_alert';

    public function register(): void
    {
        $this->app->singleton(AlertReconciler::class, fn () => new AlertReconciler);

        /*
         * The ad spend sources, in the order the message lists them.
         *
         * Google and Microsoft are not here yet. An unconfigured source is skipped rather than
         * failed, so adding one before its credentials exist would be harmless — but a class that
         * does not exist cannot be listed, and phase 2 is Meta alone.
         */
        $this->app->singleton(AdSpendCollector::class, fn () => new AdSpendCollector([
            new MetaInsightsClient,
        ]));

        $this->app->singleton(FunnelMetricsService::class, fn ($app) => new FunnelMetricsService(
            /*
             * Calendly is optional here on purpose. The consultation counts are the one figure in
             * the alert that needs a network call, and an environment with no Calendly token
             * should still get every other number rather than no message at all — so the service
             * takes a null client and renders that line as unavailable.
             */
            $app->bound(CalendlyClient::class) ? $app->make(CalendlyClient::class) : null,
            $app->bound(CalendlyEventTypeRoleResolver::class) ? $app->make(CalendlyEventTypeRoleResolver::class) : null,
            $app->make(AlertReconciler::class),
            $app->make(AdSpendCollector::class),
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
     * Resolving it eagerly pulled the whole graph — FunnelMetricsService, CalendlyClient, the
     * token pool, the role resolver, the spend collector and the Meta client — into being on
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

    private function scheduleCostAlert(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        \add_action('init', function () {
            $enabled = AlertWindow::enabledHere();
            $scheduled = \wp_next_scheduled(self::CRON_HOOK);

            if ($enabled && ! $scheduled) {
                \wp_schedule_event(time(), 'hourly', self::CRON_HOOK);

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
                \wp_unschedule_event($scheduled, self::CRON_HOOK);
            }
        });

        \add_action(self::CRON_HOOK, function () {
            try {
                $this->app->make(SendCostAlertAction::class)->execute();
            } catch (\Throwable $e) {
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
             * Warm the dashboard widget's snapshot on the same tick.
             *
             * Separate from the block above, and deliberately not inside it: `execute()` returns
             * early outside the reporting window and when the Slack side is switched off, but the
             * widget is registered either way and still needs figures. Overnight, and in any
             * environment with no bot token, this is the only thing that fills it.
             *
             * `warmCache()` no-ops when the stored copy is recent, so on the ticks where the
             * alert has just run this costs a cache read rather than a second round of Calendly
             * and Meta calls.
             */
            try {
                $this->app->make(FunnelMetricsService::class)->warmCache();
            } catch (\Throwable $e) {
                Log::error('MarketingServiceProvider: could not warm the cost alert snapshot', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
