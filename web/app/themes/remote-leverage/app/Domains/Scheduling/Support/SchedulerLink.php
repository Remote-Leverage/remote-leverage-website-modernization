<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Support;

/**
 * The public "add to calendar" link for a booked consultation: /scheduler-link/?id=<event uuid>.
 *
 * Ported from the legacy rl-elementor-blocks CalendlyIntegration. The legacy site stamped this
 * URL on every booking (Gravity Forms field 66), and the HubSpot feed carried it into
 * `schedule_link` so confirmation emails and texts could offer it. v2 never set it, and the
 * route itself was not ported, so all ~1,600 links already in the CRM 404'd from the cutover.
 *
 * The id is the Calendly *scheduled event* uuid. It is unguessable, which is the only access
 * control the legacy route had, by design: the link has to open from an email or a calendar app
 * on any device, with no session.
 */
final class SchedulerLink
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** Whether `$id` is shaped like a Calendly scheduled-event uuid. */
    public static function isEventId(string $id): bool
    {
        return (bool) preg_match(self::UUID, $id);
    }

    /**
     * The scheduled-event uuid inside a booking's meeting id.
     *
     * BookMeetingAction reports the invitee URI
     * (`…/scheduled_events/<event uuid>/invitees/<invitee uuid>`); a bare event URI or uuid is
     * accepted too.
     */
    public static function eventIdFrom(string $meetingId): ?string
    {
        $meetingId = trim($meetingId);

        if (preg_match('~/scheduled_events/([0-9a-f-]{36})(?:/|$)~i', $meetingId, $m) && self::isEventId($m[1])) {
            return strtolower($m[1]);
        }

        return self::isEventId($meetingId) ? strtolower($meetingId) : null;
    }

    /** The public URL for a scheduled event, or null when the meeting id carries none. */
    public static function forMeeting(string $meetingId): ?string
    {
        $eventId = self::eventIdFrom($meetingId);

        if ($eventId === null) {
            return null;
        }

        $base = function_exists('home_url') ? (string) home_url('/scheduler-link/') : '/scheduler-link/';

        return $base.'?id='.$eventId;
    }

    /**
     * The calendar entry for a Calendly scheduled-event resource.
     *
     * @param  array<string, mixed>  $event
     * @return array{title: string, description: string, location: string, start: string, end: string}
     */
    public static function entryFor(array $event): array
    {
        $location = (string) ($event['location']['join_url'] ?? $event['location']['location'] ?? '');
        $organizer = $event['event_memberships'][0] ?? [];
        $organizerName = trim((string) ($organizer['user_name'] ?? ''));
        $organizerEmail = trim((string) ($organizer['user_email'] ?? ''));

        $description = [];

        if ($organizerName !== '' || $organizerEmail !== '') {
            $description[] = 'Organizer: '.trim($organizerName.($organizerEmail !== '' ? " ({$organizerEmail})" : ''));
        }

        if ($location !== '') {
            $description[] = 'Location: '.$location;
        }

        return [
            'title' => (string) ($event['name'] ?? '') ?: 'Scheduled Call with Remote Leverage',
            'description' => implode("\n\n", $description),
            'location' => $location,
            'start' => (string) ($event['start_time'] ?? ''),
            'end' => (string) ($event['end_time'] ?? ''),
        ];
    }

    /**
     * An RFC 5545 calendar file for the entry.
     *
     * @param  array{title: string, description: string, location: string, start: string, end: string}  $entry
     */
    public static function ics(array $entry, string $eventId): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Remote Leverage//Add to Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            // Stable per event, so re-opening the link updates the entry instead of duplicating it.
            'UID:'.$eventId.'@remoteleverage.com',
            'DTSTAMP:'.self::utc('now'),
            'DTSTART:'.self::utc($entry['start']),
            'DTEND:'.self::utc($entry['end']),
            'SUMMARY:'.self::escape($entry['title']),
            'DESCRIPTION:'.self::escape($entry['description']),
            'LOCATION:'.self::escape($entry['location']),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ])."\r\n";
    }

    /**
     * Google Calendar's "add event" deep link, the legacy route's answer for Android.
     *
     * @param  array{title: string, description: string, location: string, start: string, end: string}  $entry
     */
    public static function googleCalendarUrl(array $entry): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $entry['title'],
            'dates' => self::utc($entry['start']).'/'.self::utc($entry['end']),
            'details' => $entry['description'],
            'location' => $entry['location'],
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function utc(string $time): string
    {
        return (new \DateTimeImmutable($time))->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $value);
    }
}
