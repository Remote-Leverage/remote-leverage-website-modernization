<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Domains\Scheduling\Services\AvailabilityHealthMonitor;
use Illuminate\Support\Facades\Cache;

/**
 * Regression cover for the 2026-09-17 t10 sell-out.
 *
 * The incident itself was not a bug — Calendly offers a rolling four-day booking window, the
 * tier sold out inside it, and the API correctly said so. What made it a five-hour outage
 * rather than a five-minute one was the site's reaction: a ten-minute hold on the empty answer,
 * no alert, and an empty state that read as breakage. These are the three.
 */
describe('Availability sell-out handling', function () {
    beforeEach(function () {
        Cache::flush();

        config([
            'services.calendly.t10_event_type' => 'https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb',
            'services.calendly.t0_event_type' => 'https://api.calendly.com/event_types/ff20712e-6387-4965-9026-dee4c7e5ef62',

            // Without this the template assertions below pass vacuously against an empty config.
            'slack-notifications' => require __DIR__.'/../../config/slack-notifications.php',
        ]);
    });

    test('an empty answer is cached for far less time than a populated one', function () {
        // The load-bearing number. Twelve bookings landed on t10 during the window it was
        // reporting empty, so the slot a visitor wanted was frequently free again well inside
        // the old ten-minute hold.
        expect(MultistepBookingWizard::EMPTY_AVAILABILITY_TTL_SECONDS)
            ->toBeLessThan(MultistepBookingWizard::AVAILABILITY_TTL_SECONDS)
            ->and(MultistepBookingWizard::EMPTY_AVAILABILITY_TTL_SECONDS)->toBe(60)
            ->and(MultistepBookingWizard::AVAILABILITY_TTL_SECONDS)->toBe(600);
    });

    test('tier role follows the revenue band', function () {
        $wizard = new MultistepBookingWizard;

        $wizard->monthlyRevenue = '$100k+ Per Month';
        expect($wizard->activeTierRole())->toBe('t10');

        $wizard->monthlyRevenue = '$5k to $10k Per Month';
        expect($wizard->activeTierRole())->toBe('t0');
    });

    test('a sold-out tier is recorded as sold out, a populated one is not', function () {
        $monitor = new AvailabilityHealthMonitor;

        $monitor->record('t10', 'https://api.calendly.com/event_types/abc', [], '2026-09', null);
        $status = $monitor->status();

        expect($status['t10']['sold_out'])->toBeTrue()
            ->and($status['t10']['dates'])->toBe(0)
            ->and($status['t10']['month'])->toBe('2026-09');

        $monitor->record('t0', 'https://api.calendly.com/event_types/def', ['2026-09-17', '2026-09-18'], '2026-09', '2026-09-17');
        $status = $monitor->status();

        expect($status['t0']['sold_out'])->toBeFalse()
            ->and($status['t0']['dates'])->toBe(2);

        // Both tiers are tracked independently: t10 selling out while t0 is healthy is
        // exactly the shape of the incident, and a monitor that collapsed them would have
        // reported the site as fine.
        expect($status['t10']['sold_out'])->toBeTrue();
    });

    test('an empty month that is merely past the booking horizon is not a sell-out', function () {
        $monitor = new AvailabilityHealthMonitor;

        /*
         * The regression this file exists to prevent a second time. These event types publish a
         * rolling four-day window, so every month after the current one comes back empty for a
         * completely healthy tier. Keying the alert on the displayed month meant a visitor
         * paging the calendar forward raised a sell-out — hundreds of false alarms, and a
         * channel nobody reads by the time a real one lands.
         */
        $monitor->record('t10', 'https://api.calendly.com/event_types/abc', [], '2026-10', '2026-09-17');

        $status = $monitor->status();

        expect($status['t10']['sold_out'])->toBeFalse()
            ->and($status['t10']['next_available'])->toBe('2026-09-17')
            ->and(Cache::has('rl_availability_alert_t10'))->toBeFalse();
    });

    test('the sell-out alert is throttled per tier', function () {
        $monitor = new AvailabilityHealthMonitor;

        $monitor->record('t10', 'https://api.calendly.com/event_types/abc', [], '2026-09', null);

        // A sell-out lasts hours and every pageview re-checks it. The throttle key is what
        // stands between one incident and several hundred Slack messages.
        expect(Cache::has('rl_availability_alert_t10'))->toBeTrue()
            ->and(Cache::has('rl_availability_alert_t0'))->toBeFalse();
    });

    test('the sell-out alert renders from a config template like every other message', function () {
        $rendered = (new SlackMessageRenderer)->render('availability_sold_out', [
            'tier_label' => 'T10 (≥$10k/mo)',
            'month' => '2026-09',
            'admin_url' => 'https://example.test/wp/wp-admin/admin.php?page=rl-leads-diagnostics',
            'event_type_url' => 'https://calendly.com/event_types/user/me',
        ]);

        $encoded = json_encode($rendered['blocks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect($rendered['blocks'])->not->toBeEmpty()
            ->and(array_column($rendered['blocks'], 'type'))->toBe(['section', 'card', 'context', 'actions'])
            ->and($encoded)->toContain('T10 (≥$10k/mo)')
            ->and($encoded)->toContain('2026-09')
            // The line that stops a sell-out being escalated as an outage.
            ->and($encoded)->toContain('not an outage');

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}]/u', $encoded))
            ->toBe(0);
    });

    test('no message button in any template carries a style', function () {
        /*
         * The house rule, asserted across every template at once rather than per family. Slack
         * renders `primary`/`danger` filled and everything else outlined, so one styled button
         * makes an otherwise uniform action row look misaligned. The only legitimate `style` is
         * inside a `confirm` dialog, which colours the modal rather than anything in the channel.
         */
        $styled = [];

        foreach ((array) config('slack-notifications') as $name => $template) {
            foreach ((array) ($template['blocks'] ?? []) as $block) {
                if (($block['type'] ?? '') !== 'actions') {
                    continue;
                }

                foreach ((array) ($block['elements'] ?? []) as $element) {
                    if (isset($element['style'])) {
                        $styled[] = $name.': '.($element['text']['text'] ?? $element['action_id'] ?? '?');
                    }
                }
            }
        }

        expect($styled)->toBe([]);
    });

    test('an observation goes stale rather than claiming to be current', function () {
        expect(AvailabilityHealthMonitor::isStale(null))->toBeTrue()
            ->and(AvailabilityHealthMonitor::isStale(gmdate('c')))->toBeFalse()
            ->and(AvailabilityHealthMonitor::isStale(gmdate('c', time() - 7200)))->toBeTrue();
    });
});
