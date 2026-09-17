<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notices when a revenue tier's calendar has nothing left to sell.
 *
 * On 2026-09-17 the `t10` event type returned zero slots for several hours overnight. Nothing
 * was broken: Calendly only ever offers a rolling four-day booking window, that window held
 * two weekdays and a weekend, and `t10` takes three to five times `t0`'s booking load, so it
 * sold out. The site had no opinion about this — it rendered an empty calendar and waited.
 *
 * The outage was reconstructed after the fact from `rl_integration_calls`, which had every
 * Calendly response in it. That table is why the incident was explicable at all, but reading it
 * requires already suspecting something is wrong. This class is the part that does the
 * suspecting.
 *
 * ## Why three surfaces and not one
 *
 * They fail in different ways and cover for each other. Slack is the only one that reaches
 * someone who is not looking, and it is also the one that silently does nothing when a bot
 * token is missing from an environment. The log survives a Slack outage and is where you land
 * when reconstructing a timeline. The admin panel is the only one that answers "is it sold out
 * *right now*", which is the question you actually have when someone reports an empty calendar.
 *
 * ## Throttling
 *
 * A sell-out lasts hours and every pageview re-checks it, so the unthrottled version of this
 * posts a few hundred times. One message per tier per hour: long enough to not be noise, short
 * enough that an overnight sell-out leaves a visible trail rather than a single 2am message
 * nobody scrolls back to.
 *
 * The log line is deliberately *not* throttled. It is already cheap, and its value is density —
 * knowing a tier was empty on every single check between 03:57 and 06:48 is what distinguishes
 * a sell-out from a flapping integration.
 */
class AvailabilityHealthMonitor
{
    /** Last observation per role, for the admin panel. */
    public const OPTION_KEY = 'rl_availability_health';

    protected const ALERT_THROTTLE_PREFIX = 'rl_availability_alert_';

    protected const ALERT_THROTTLE_SECONDS = 3600;

    /** Beyond this an observation is history, not status — the panel stops claiming it is current. */
    public const STALE_AFTER_SECONDS = 1800;

    public function __construct(
        protected SlackTransport $transport = new SlackTransport,
    ) {}

    /**
     * Record what a tier's calendar looked like on this check.
     *
     * `$nextAvailable` is what decides whether this is an incident, and an empty `$dates` on
     * its own does not. These event types only publish a rolling few days, so every month
     * past the current one is legitimately empty — keying the alert on the displayed month
     * meant paging the calendar forward raised a sell-out for a tier that was fine, which is
     * a fast way to teach everyone to ignore the channel. Sold out means nothing bookable
     * anywhere, not nothing bookable in the month someone happens to be looking at.
     *
     * @param  string  $role  't10' or 't0'
     * @param  array<int, string>  $dates  Y-m-d dates in the displayed month with an open slot
     * @param  string|null  $nextAvailable  Soonest bookable date on this tier, in any month
     */
    public function record(
        string $role,
        ?string $eventTypeUri,
        array $dates,
        string $monthKey,
        ?string $nextAvailable
    ): void {
        $soldOut = empty($dates) && $nextAvailable === null;

        $this->remember($role, $eventTypeUri, $dates, $monthKey, $nextAvailable, $soldOut);

        if (! $soldOut) {
            return;
        }

        Log::warning('AvailabilityHealthMonitor: tier calendar is sold out', [
            'role' => $role,
            'month' => $monthKey,
            'event_type' => $eventTypeUri,
        ]);

        $this->alert($role, $monthKey);
    }

    /**
     * Every tier's last observation, newest first in the order the admin renders them.
     *
     * @return array<string, array{event_type: ?string, dates: int, sold_out: bool, next_available: ?string, month: string, checked_at: string}>
     */
    public function status(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];

        return is_array($stored) ? $stored : [];
    }

    /**
     * Whether an observation is recent enough to describe the present.
     */
    public static function isStale(?string $checkedAt): bool
    {
        if (empty($checkedAt)) {
            return true;
        }

        return strtotime($checkedAt) < time() - self::STALE_AFTER_SECONDS;
    }

    /**
     * @param  array<int, string>  $dates
     */
    protected function remember(
        string $role,
        ?string $eventTypeUri,
        array $dates,
        string $monthKey,
        ?string $nextAvailable,
        bool $soldOut
    ): void {
        if (! function_exists('update_option')) {
            return;
        }

        $status = $this->status();

        $status[$role] = [
            'event_type' => $eventTypeUri,
            'dates' => count($dates),
            'sold_out' => $soldOut,
            'next_available' => $nextAvailable,
            'month' => $monthKey,
            'checked_at' => gmdate('c'),
        ];

        update_option(self::OPTION_KEY, $status);
    }

    protected function alert(string $role, string $monthKey): void
    {
        $throttleKey = self::ALERT_THROTTLE_PREFIX.$role;

        if (Cache::has($throttleKey)) {
            return;
        }

        Cache::put($throttleKey, true, now()->addSeconds(self::ALERT_THROTTLE_SECONDS));

        /*
         * Rendered from config/slack-notifications.php like every other alert in the project,
         * rather than from blocks built here. The first version of this hand-rolled its own
         * layout and looked visibly unlike the rest of the channel — and a layout in PHP is one
         * a designer cannot open in Block Kit Builder.
         */
        $rendered = app(SlackMessageRenderer::class)->render('availability_sold_out', [
            'tier_label' => $role === 't10' ? 'T10 (≥$10k/mo)' : 'T0 (<$10k/mo)',
            'month' => $monthKey,
            'admin_url' => function_exists('admin_url')
                ? (string) \admin_url('admin.php?page=rl-leads-diagnostics')
                : '',

            /*
             * The account's event-type list, deliberately not `$eventTypeUri` — that is an
             * api.calendly.com address, which answers 401 in a browser. This button goes where
             * the date range is actually edited, which is what the message just asked for.
             */
            'event_type_url' => 'https://calendly.com/event_types/user/me',
        ]);

        if ($rendered['blocks'] === []) {
            Log::warning('AvailabilityHealthMonitor: sell-out template rendered empty, nothing sent.');

            return;
        }

        try {
            $this->transport->post(
                $rendered['text'],
                $rendered['blocks'],
                $rendered['color'] ?? null,
            );
        } catch (\Throwable $e) {
            // An alert that throws takes the booking calendar down with it. It is the least
            // important thing in the request.
            Log::warning('AvailabilityHealthMonitor: could not post sell-out alert: '.$e->getMessage());
        }
    }
}
