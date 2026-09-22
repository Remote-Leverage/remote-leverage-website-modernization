<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Domains\Marketing\Support\AlertWindow;
use App\Domains\Sync\SyncCapability;
use App\Infrastructure\Providers\MarketingServiceProvider;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Why the hourly cost alert will or will not post, on the environment you ask.
 *
 * ## Why a whole ability for four booleans
 *
 * Because the alert can stop in a way that produces no evidence of any kind, and on 2026-09-21 it
 * did, for most of a day.
 *
 * `AlertWindow::enabledHere()` reading false does not merely skip a run.
 * {@see MarketingServiceProvider} calls `wp_unschedule_event()` and takes the hourly job off
 * WP-Cron, and only re-adds it when the same check reads true. So a single boot in that state
 * removes the event permanently: `execute()` is never called again, every log line inside it is
 * therefore never written, the unschedule branch cannot fire a second time because there is
 * nothing left to unschedule, and WP-Cron's list simply has no entry — which is indistinguishable
 * from an alert that was never configured.
 *
 * Adding logging to the gates did not solve it, and could not have: the gates are inside the thing
 * that stopped being called. The only way to tell "switched off" from "broken" from "never set up"
 * is to ask the environment what it currently believes, which is what this does.
 *
 * ## Read-only, and it names the raw value
 *
 * `enabled_env_raw` is the point of the whole thing. `MARKETING_COST_ALERT_ENABLED` mapped to an
 * empty string reads as an explicit `false` that outranks the environment list, and an ECS task
 * definition maps a blank Secrets Manager key exactly that way. `'off because the variable is set
 * to ""'` and `'off because this environment is not production'` need opposite fixes and look
 * identical from outside.
 */
class CostAlertStatusAbility extends Ability
{
    public function label(): string
    {
        return 'Cost Alert Status';
    }

    public function description(): string
    {
        return 'Reports whether the hourly marketing cost alert is enabled on this environment, '.
            'what decided that, and whether its WP-Cron event is actually scheduled. Read-only diagnostics.';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input): mixed
    {
        $raw = env('MARKETING_COST_ALERT_ENABLED');
        $next = function_exists('wp_next_scheduled')
            ? \wp_next_scheduled(MarketingServiceProvider::CRON_HOOK)
            : false;
        $warm = function_exists('wp_next_scheduled')
            ? \wp_next_scheduled(MarketingServiceProvider::WARM_HOOK)
            : false;

        return [
            'will_post' => AlertWindow::enabledHere(),

            'environment_type' => function_exists('wp_get_environment_type') ? \wp_get_environment_type() : null,
            'allowed_environments' => config('marketing.cost_alert.environments'),

            /*
             * Both the resolved config and the untouched variable behind it. The config alone
             * cannot distinguish "nobody set this" from "somebody set it to an empty string",
             * and those are the two candidates.
             */
            'enabled_config' => config('marketing.cost_alert.enabled'),
            'enabled_env_raw' => $raw === null ? 'unset' : var_export($raw, true),

            'window' => AlertWindow::from().'–'.AlertWindow::to(),
            'channel' => config('marketing.cost_alert.channel'),

            /*
             * The half that logging can never reach. A false here with `will_post` true means the
             * event was dropped while the alert was switched off and has not been re-added yet;
             * false with `will_post` false is the state that persists on its own forever.
             */
            'cron_scheduled' => $next !== false,
            'cron_next_run' => $next === false ? null : gmdate('c', (int) $next),
            'warm_scheduled' => $warm !== false,
        ];
    }

    public function permission(): bool|WP_Error
    {
        if (! SyncCapability::currentUserCan()) {
            return new WP_Error('forbidden', 'The '.SyncCapability::NAME.' capability is required.');
        }

        return true;
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function category(): ?string
    {
        return 'site';
    }

    /** See ErrorLogAbility::meta() — REST-reachable, kept out of MCP auto-discovery. */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
