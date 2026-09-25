<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Support\SchedulerLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * GET /scheduler-link/?id=<calendly scheduled event uuid> — "add this call to my calendar".
 *
 * Android gets Google Calendar's add-event page; everything else, iOS included, gets an .ics
 * download. That split is the legacy route's, kept so a link sent before the cutover behaves
 * exactly as it did. See {@see SchedulerLink}.
 *
 * Every lookup is a Calendly API call on the same token pool the booking wizard books through,
 * so this route must not become a way to exhaust it: ids are checked for shape before anything
 * is fetched, answers are cached (misses too), and the whole route shares one global budget.
 * Real traffic is a click or two per booking, far below it.
 *
 * nginx lists this path in `$skip_cache_path`: the response is per-meeting, and CloudFront keys
 * pages without the query string, so a cached copy would hand one person's meeting to the next.
 */
class SchedulerLinkController
{
    /** Calendly lookups the route may make per minute, across all visitors. */
    private const LOOKUPS_PER_MINUTE = 60;

    private const HIT_TTL_SECONDS = 600;

    private const MISS_TTL_SECONDS = 300;

    public function __construct(protected CalendlyClient $calendly) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $id = strtolower(trim((string) $request->query('id', '')));

        if (! SchedulerLink::isEventId($id)) {
            return $this->notFound();
        }

        $event = $this->lookup($id);

        if ($event === null) {
            return $this->notFound();
        }

        if ($event === 'throttled') {
            return new Response('Too many requests. Please try again in a minute.', 429, $this->noStore(['Retry-After' => '60']));
        }

        $entry = SchedulerLink::entryFor($event);

        if (preg_match('/Android/i', (string) $request->userAgent())) {
            return new RedirectResponse(SchedulerLink::googleCalendarUrl($entry), 302, $this->noStore());
        }

        return new Response(SchedulerLink::ics($entry, $id), 200, $this->noStore([
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="scheduled-call.ics"',
        ]));
    }

    /**
     * The scheduled event, null when Calendly has none (or it is canceled), or 'throttled'.
     *
     * @return array<string, mixed>|string|null
     */
    private function lookup(string $id): array|string|null
    {
        $key = 'rl_scheduler_link_'.$id;
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached === 'missing') {
            return null;
        }

        /*
         * A plain per-minute counter rather than RateLimiter: a global budget only needs to be
         * roughly right, and it reads and writes through the same Cache the lookups use. Not
         * atomic, so a burst can overshoot by a request or two, which is harmless.
         */
        $window = 'rl_scheduler_link_budget_'.intdiv(time(), 60);
        $spent = (int) Cache::get($window, 0);

        if ($spent >= self::LOOKUPS_PER_MINUTE) {
            return 'throttled';
        }

        Cache::put($window, $spent + 1, 120);

        $event = $this->calendly->getScheduledEvent($id);

        if (! is_array($event) || empty($event['start_time']) || empty($event['end_time']) || ($event['status'] ?? 'active') === 'canceled') {
            Cache::put($key, 'missing', self::MISS_TTL_SECONDS);

            return null;
        }

        Cache::put($key, $event, self::HIT_TTL_SECONDS);

        return $event;
    }

    private function notFound(): Response
    {
        return new Response('This calendar link is no longer valid.', 404, $this->noStore());
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function noStore(array $headers = []): array
    {
        return $headers + ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex'];
    }
}
