<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Support;

use Illuminate\Support\Facades\Log;

/**
 * The Calendly page a partnership prospect books their call on, straight after the form.
 *
 * ## Why an embed, and not the booking wizard's calendar
 *
 * The wizard's calendar (resources/js/booking-calendar.js with TierAvailability behind it) looks
 * reusable — it takes any event type URI — but everything around it is shaped by the sales
 * funnel, and borrowing it for partners means borrowing that too:
 *
 *  - `TierAvailability` reports every fetch to AvailabilityHealthMonitor and the utilization
 *    probe under a tier role. A `partnership` role would put a partnerships calendar into the
 *    sold-out warnings that exist to tell sales their T10/T0 tiers are full.
 *  - `BookMeetingAction` is keyed on the lead activity log. It de-duplicates against the email's
 *    recent *lead* bookings, and after booking it cancels that email's prior booking at a
 *    different slot as a "reschedule" — so a prospect who once booked a sales call would have
 *    it silently cancelled by booking a partnership call.
 *
 * The inline embed has none of that. It is what `/kickoff-workforce-planning-meeting/` already
 * does, and Calendly handles availability, confirmation emails and rescheduling itself.
 *
 * ## What the embed tells us
 *
 * An embedded Calendly page posts `calendly.event_scheduled` to its parent window with the new
 * event's and invitee's URIs, and `calendly.page_height` as its content grows. The form listens
 * for both; the first is how `booked_at` gets recorded, cheaply and without a webhook. It is the
 * browser's report, so the URIs are held to the shapes below before anything is written.
 */
final class PartnershipCallCalendar
{
    /** The iframe's origin, which every message from it must carry. */
    public const ORIGIN = 'https://calendly.com';

    private const SCHEDULED_EVENT = '#^https://api\.calendly\.com/scheduled_events/[A-Za-z0-9-]{1,64}$#';

    private const INVITEE = '#^https://api\.calendly\.com/scheduled_events/[A-Za-z0-9-]{1,64}/invitees/[A-Za-z0-9-]{1,64}$#';

    /**
     * The configured scheduling page, or null when there is none.
     *
     * Anything that is not an https calendly.com page counts as none, and says so: the value is
     * put in an iframe on a public page, so a typo must not be able to frame some other site,
     * and the message listener only trusts {@see self::ORIGIN}. The settings screen refuses such
     * a value on save; this is the same check at the point of use, for an option written some
     * other way.
     */
    public static function url(): ?string
    {
        $url = PartnershipSettings::calendlyUrl();

        if ($url === '') {
            return null;
        }

        if (! PartnershipSettings::isSchedulingPage($url)) {
            Log::warning('PartnershipCallCalendar: the Calendly page in Partnership Settings is not an https://calendly.com/ scheduling page; showing the thank-you instead.', [
                'value' => $url,
            ]);

            return null;
        }

        return $url;
    }

    /**
     * The iframe `src`: the scheduling page, marked as embedded, with the prospect prefilled.
     *
     * `embed_domain` and `embed_type` are what Calendly's own widget.js appends, and what makes
     * the page post its events to the parent — this is the same URL that script would build,
     * without loading a third-party script to build it. Any query string already on the
     * configured link (a preset custom answer, say) is kept.
     *
     * @param  array<string, string|null>  $prefill  `first_name`, `last_name`, `email`, UTMs.
     */
    public static function embedUrl(string $base, string $embedDomain, array $prefill = []): string
    {
        $parts = parse_url($base);
        parse_str((string) ($parts['query'] ?? ''), $query);

        $first = trim((string) ($prefill['first_name'] ?? ''));
        $last = trim((string) ($prefill['last_name'] ?? ''));

        $params = array_filter([
            ...$query,
            'embed_domain' => $embedDomain,
            'embed_type' => 'Inline',
            'hide_gdpr_banner' => '1',

            // Both shapes, because which one a page reads depends on whether its event type asks
            // for one name field or two — and a field the page does not have is ignored.
            'name' => trim($first.' '.$last),
            'first_name' => $first,
            'last_name' => $last,
            'email' => trim((string) ($prefill['email'] ?? '')),

            'utm_source' => $prefill['utm_source'] ?? null,
            'utm_medium' => $prefill['utm_medium'] ?? null,
            'utm_campaign' => $prefill['utm_campaign'] ?? null,
            'utm_term' => $prefill['utm_term'] ?? null,
            'utm_content' => $prefill['utm_content'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $path = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'calendly.com').($parts['path'] ?? '');

        return $path.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function isScheduledEventUri(string $uri): bool
    {
        return preg_match(self::SCHEDULED_EVENT, $uri) === 1;
    }

    public static function isInviteeUri(string $uri): bool
    {
        return preg_match(self::INVITEE, $uri) === 1;
    }

    /**
     * Whether a Calendly webhook body is a booking on the partnership call.
     *
     * CalendlyWebhookController moves the latest *lead* with the invitee's email to `booked` for
     * any event type, so a prospect who had once been a lead would have that lead marked booked
     * and LeadBookingCompleted fired — a sales follow-up for a partnership call. The controller
     * asks this first and leaves the lead alone.
     *
     * Two shapes, because the controller reads Calendly's v1 body while v2 subscriptions send
     * another, and a partnership booking must be recognised whichever arrives:
     *
     *  - v2 names the event type by URI at `payload.scheduled_event.event_type`, matched against
     *    the event type URI saved in Partnership Settings — the same form the sales tiers'
     *    `CALENDLY_T10_EVENT_TYPE` and its siblings take.
     *  - v1 carries `payload.event_type.slug` (and its `uuid`), matched against the last path
     *    segment of the configured scheduling page, so v1 needs nothing set beyond the link.
     *
     * With neither configured nothing matches, which is today's behaviour exactly.
     */
    public static function isPartnershipBooking(array $body): bool
    {
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];

        $eventType = PartnershipSettings::calendlyEventType();

        if ($eventType !== '') {
            $v2 = (string) ($payload['scheduled_event']['event_type'] ?? '');
            $v1Uuid = (string) ($payload['event_type']['uuid'] ?? '');

            if ($v2 !== '' && rtrim($v2, '/') === rtrim($eventType, '/')) {
                return true;
            }

            if ($v1Uuid !== '' && basename(rtrim($eventType, '/')) === $v1Uuid) {
                return true;
            }
        }

        $url = self::url();
        $slug = (string) ($payload['event_type']['slug'] ?? '');

        if ($url !== null && $slug !== '') {
            $configured = basename(rtrim((string) parse_url($url, PHP_URL_PATH), '/'));

            return strcasecmp($configured, $slug) === 0;
        }

        return false;
    }
}
