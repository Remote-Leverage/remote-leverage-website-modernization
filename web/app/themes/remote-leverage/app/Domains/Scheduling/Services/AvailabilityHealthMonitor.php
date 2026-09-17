<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notices when a revenue tier's calendar is running out of things to sell, and when it has run out.
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
 * ## Two questions, two entry points
 *
 * `record()` answers "is there anything left at all", from the data the booking wizard already
 * has in hand on a pageview. It is free, it is immediate, and by the time it fires the tier is
 * already unsellable.
 *
 * `recordUtilization()` answers "how much is left", and is the leading indicator — it is what
 * turns a 3am sell-out into a 9pm warning that someone can act on. It costs a Calendly round
 * trip that `record()` does not, because the availability API only returns what is still open
 * and never says how much there was, so the booked side has to be counted separately. That is
 * why it runs from a probe on a schedule rather than inside the booking request.
 *
 * ## The band ladder
 *
 * ok -> warning (90%) -> critical (95%) -> sold out. A tier alerts when it crosses *into* a
 * worse band and stays quiet while it sits in one, so a calendar hovering at 91% all afternoon
 * is one message rather than a stream of them. Coming back down is silent, and re-entering a
 * band from below alerts again.
 *
 * Sold out is the exception and keeps its own hourly repeat, deliberately: unlike the warnings
 * it is not a heads-up but an active revenue stop, and the repeat is what leaves a trail across
 * an overnight incident instead of a single 2am message nobody scrolls back to. Its throttle is
 * independent of the ladder, so a tier that goes from 90% to sold out inside an hour still gets
 * both messages.
 *
 * ## Why three surfaces and not one
 *
 * They fail in different ways and cover for each other. Slack is the only one that reaches
 * someone who is not looking, and it is also the one that silently does nothing when a bot
 * token is missing from an environment. The log survives a Slack outage and is where you land
 * when reconstructing a timeline. The admin panel is the only one that answers "is it sold out
 * *right now*", which is the question you actually have when someone reports an empty calendar.
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

    /*
     * Ordered, and compared with `<`/`>` — the ladder is the whole escalation rule, so these
     * are ints rather than strings.
     */
    public const BAND_OK = 0;

    public const BAND_WARNING = 1;

    public const BAND_CRITICAL = 2;

    public const BAND_SOLD_OUT = 3;

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

        $this->remember($role, [
            'event_type' => $eventTypeUri,
            'dates' => count($dates),
            'sold_out' => $soldOut,
            'next_available' => $nextAvailable,
            'month' => $monthKey,
            'checked_at' => gmdate('c'),
        ]);

        if (! $soldOut) {
            return;
        }

        /*
         * Raised, never lowered. This path has no idea what the fill percentage is, so a tier
         * it does not consider sold out may still be sitting at 96% — resetting the ladder here
         * would re-fire the critical warning on the next pageview, every pageview.
         */
        $this->raiseBand($role, self::BAND_SOLD_OUT);

        Log::warning('AvailabilityHealthMonitor: tier calendar is sold out', [
            'role' => $role,
            'month' => $monthKey,
            'event_type' => $eventTypeUri,
        ]);

        $this->alertSoldOut($role, $monthKey);
    }

    /**
     * Record how full a tier's rolling booking window is, and warn before it closes.
     *
     * Both counts are over the same forward window. `$booked` must be null, not zero, when the
     * count could not be made — zero booked is the emptiest a calendar gets, and mistaking a
     * failed lookup for it would report a tier about to sell out as wide open.
     *
     * @param  string  $role  't10' or 't0'
     * @param  int  $open  Bookable slots left in the window
     * @param  int|null  $booked  Meetings already on the books in the same window, or null if unknown
     * @return float|null The fill fraction acted on, or null when the sample was too small to judge
     */
    public function recordUtilization(
        string $role,
        ?string $eventTypeUri,
        int $open,
        ?int $booked,
        int $windowDays
    ): ?float {
        if ($booked === null) {
            return null;
        }

        $capacity = $open + $booked;

        /*
         * A tier with three slots published and two taken is 67% full, and saying so is noise:
         * the number moves a third of its range on one booking. Worse is the shape that actually
         * pages you — zero open and one booked reads as 100% on a calendar whose real problem is
         * that nobody published any hours. Below the floor there is no percentage worth having.
         */
        $minSample = max(1, (int) config('booking.availability.min_sample', 8));

        if ($capacity < $minSample) {
            $this->remember($role, [
                'event_type' => $eventTypeUri,
                'open_slots' => $open,
                'booked_slots' => $booked,
                'fill' => null,
                'window_days' => $windowDays,
                'measured_at' => gmdate('c'),
            ]);

            return null;
        }

        $fill = $booked / $capacity;
        $band = $this->bandForFill($fill);
        $previous = $this->storedBand($role);

        /*
         * The probe only ever looks at the rolling window, so it cannot see the later date that
         * separates a sell-out from a horizon. When it finds nothing open it declines to overrule
         * record(), which can.
         */
        if ($open === 0 && $previous === self::BAND_SOLD_OUT) {
            $band = self::BAND_SOLD_OUT;
        }

        $this->remember($role, [
            'event_type' => $eventTypeUri,
            'open_slots' => $open,
            'booked_slots' => $booked,
            'fill' => round($fill, 4),
            'band' => $band,
            'window_days' => $windowDays,
            'measured_at' => gmdate('c'),
        ]);

        Log::info('AvailabilityHealthMonitor: tier utilization', [
            'role' => $role,
            'open' => $open,
            'booked' => $booked,
            'fill' => round($fill * 100, 1),
            'band' => $band,
            'window_days' => $windowDays,
        ]);

        // Escalation only. Sitting in a band is quiet, and so is coming back down. The band
        // itself was already stored above, by the same write that recorded the counts.
        if ($band > $previous && in_array($band, [self::BAND_WARNING, self::BAND_CRITICAL], true)) {
            $this->alertFillingUp($role, $band, $fill, $open, $booked, $windowDays);
        }

        return $fill;
    }

    /**
     * Every tier's last observation, newest first in the order the admin renders them.
     *
     * @return array<string, array<string, mixed>>
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
     * Which band a fill fraction falls in. Never returns BAND_SOLD_OUT — a full window is not
     * the same claim as nothing bookable anywhere, and only record() has the evidence for that.
     */
    public function bandForFill(float $fill): int
    {
        $critical = (float) config('booking.availability.critical_threshold', 0.95);
        $warning = (float) config('booking.availability.warning_threshold', 0.90);

        return match (true) {
            $fill >= $critical => self::BAND_CRITICAL,
            $fill >= $warning => self::BAND_WARNING,
            default => self::BAND_OK,
        };
    }

    /**
     * Merge one reading into a role's stored observation.
     *
     * Merged rather than replaced because the two entry points write disjoint halves of it, on
     * different cadences. A pageview sell-out check must not wipe the last utilization reading
     * out of the admin panel, and the panel timestamps each half separately for that reason.
     *
     * @param  array<string, mixed>  $reading
     */
    protected function remember(string $role, array $reading): void
    {
        if (! function_exists('update_option')) {
            return;
        }

        $status = $this->status();
        $existing = is_array($status[$role] ?? null) ? $status[$role] : [];

        $status[$role] = array_merge($existing, $reading);

        update_option(self::OPTION_KEY, $status);
    }

    /**
     * The worst band this tier has been alerted about in the current episode.
     */
    protected function storedBand(string $role): int
    {
        $status = $this->status();

        return (int) ($status[$role]['band'] ?? self::BAND_OK);
    }

    protected function setBand(string $role, int $band): void
    {
        $this->remember($role, ['band' => $band]);
    }

    protected function raiseBand(string $role, int $band): void
    {
        if ($band > $this->storedBand($role)) {
            $this->setBand($role, $band);
        }
    }

    protected function alertSoldOut(string $role, string $monthKey): void
    {
        /*
         * Its own throttle key, not the ladder. A sell-out is a live revenue stop rather than a
         * heads-up, and the hourly repeat is what leaves a trail across an overnight incident.
         */
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
        $this->post('availability_sold_out', [
            'tier_label' => $this->tierLabel($role),
            'month' => $monthKey,
            'admin_url' => $this->adminUrl(),

            /*
             * The account's event-type list, deliberately not `$eventTypeUri` — that is an
             * api.calendly.com address, which answers 401 in a browser. This button goes where
             * the date range is actually edited, which is what the message just asked for.
             */
            'event_type_url' => 'https://calendly.com/event_types/user/me',
        ]);
    }

    protected function alertFillingUp(string $role, int $band, float $fill, int $open, int $booked, int $windowDays): void
    {
        $this->post('availability_filling_up', [
            'tier_label' => $this->tierLabel($role),
            'headline' => $band === self::BAND_CRITICAL
                ? 'Almost nothing left to book'
                : 'Booking window is filling up',
            'severity' => $band === self::BAND_CRITICAL ? 'Critical' : 'Warning',
            'fill_label' => number_format($fill * 100, 1).'% full',
            'remaining' => $open === 1 ? '1 slot left' : $open.' slots left',
            'window_label' => 'next '.$windowDays.($windowDays === 1 ? ' day' : ' days'),
            'counts' => $booked.' of '.($open + $booked).' slots taken',
            'admin_url' => $this->adminUrl(),
            'event_type_url' => 'https://calendly.com/event_types/user/me',
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function post(string $template, array $values): void
    {
        $rendered = app(SlackMessageRenderer::class)->render($template, $values);

        if ($rendered['blocks'] === []) {
            Log::warning("AvailabilityHealthMonitor: {$template} rendered empty, nothing sent.");

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
            Log::warning("AvailabilityHealthMonitor: could not post {$template} alert: ".$e->getMessage());
        }
    }

    protected function tierLabel(string $role): string
    {
        return $role === 't10' ? 'T10 (≥$10k/mo)' : 'T0 (<$10k/mo)';
    }

    protected function adminUrl(): string
    {
        return function_exists('admin_url')
            ? (string) \admin_url('admin.php?page=rl-leads-diagnostics')
            : '';
    }
}
