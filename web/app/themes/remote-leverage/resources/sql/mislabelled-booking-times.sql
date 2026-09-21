-- Bookings taken while the picker labelled every slot in UTC.
--
-- Until 2026-09-21 the wizard formatted each slot with `Carbon::parse($iso, $timezone)`. Calendly
-- answers `event_type_available_times` in UTC Zulu, and PHP discards the timezone argument when
-- the string carries its own offset, so every button was labelled with the UTC clock time under a
-- banner naming the visitor's zone. The instant behind the button was always correct — Calendly
-- booked exactly what was clicked — so these meetings are on the calendar at the right time and
-- the wrong time was only ever the one the visitor read.
--
-- The visitor's selected zone is NOT persisted: it travels in the LeadCreated event context and
-- stops there. So this cannot say what each lead was shown minus what they expected; it lists the
-- affected bookings with the UTC label they were shown and the same instant in each zone the
-- picker offered, and you match the lead to a zone by hand.
--
-- Scope: every Calendly booking before the fix. There is no unaffected subset except visitors who
-- had explicitly selected UTC in the dropdown.
--
--   mysql -h <host> -u <user> -p <db> < mislabelled-booking-times.sql
--
-- Adjust the prefix and the cutoff for the environment you run it in.
--
-- Numeric offsets, not zone names: CONVERT_TZ('…','UTC','America/Chicago') returns NULL wherever
-- the mysql.time_zone tables were never loaded, which includes the local DBngin server, and it
-- returns it silently — every column comes back NULL rather than erroring. The offsets below are
-- the US/UK summer ones. Every booking this covers falls inside the 30-day window the picker
-- offered, so the latest of them is in October and DST does not end until 2026-11-01. Re-check
-- the offsets before reusing this query later in the year.

SET @fix_deployed_at = '2026-09-21 00:00:00';

SELECT
    l.id                                                              AS lead_id,
    l.name,
    l.email,
    log.created_at                                                    AS booked_at,
    JSON_UNQUOTE(JSON_EXTRACT(log.payload, '$.meeting_id'))           AS meeting_id,
    JSON_UNQUOTE(JSON_EXTRACT(log.payload, '$.provider'))             AS provider,
    slot.start_utc                                                    AS meeting_instant_utc,
    -- What the picker printed on the button: the UTC clock time, no conversion.
    DATE_FORMAT(slot.start_utc, '%b %e, %l:%i %p')                    AS label_shown,
    -- What that instant actually is, per offered zone. One of these is the meeting they got.
    DATE_FORMAT(CONVERT_TZ(slot.start_utc, '+00:00', '-04:00'), '%b %e, %l:%i %p') AS actual_new_york,
    DATE_FORMAT(CONVERT_TZ(slot.start_utc, '+00:00', '-05:00'), '%b %e, %l:%i %p') AS actual_chicago,
    DATE_FORMAT(CONVERT_TZ(slot.start_utc, '+00:00', '-06:00'), '%b %e, %l:%i %p') AS actual_denver,
    DATE_FORMAT(CONVERT_TZ(slot.start_utc, '+00:00', '-07:00'), '%b %e, %l:%i %p') AS actual_los_angeles,
    DATE_FORMAT(CONVERT_TZ(slot.start_utc, '+00:00', '-05:00'), '%b %e, %l:%i %p') AS actual_bogota,
    DATE_FORMAT(CONVERT_TZ(slot.start_utc, '+00:00', '+01:00'), '%b %e, %l:%i %p') AS actual_london,
    -- Still in the future when you run this, so still worth a message.
    slot.start_utc > UTC_TIMESTAMP()                                  AS upcoming
FROM wp_rl_lead_activity_logs log
JOIN wp_rl_leads l ON l.id = log.lead_id
JOIN LATERAL (
    -- Carbon writes these with toIso8601String() off a UTC instance, so the offset is always
    -- +00:00; it is converted rather than trimmed so a stored local offset would not be read
    -- as UTC.
    SELECT CONVERT_TZ(
        STR_TO_DATE(
            LEFT(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(log.payload, '$.start_time')), 'T', ' '), 19),
            '%Y-%m-%d %H:%i:%s'
        ),
        SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(log.payload, '$.start_time')), -6),
        '+00:00'
    ) AS start_utc
) slot ON TRUE
WHERE log.actor_domain = 'Scheduling'
  AND log.stage = 'consumption'
  AND log.outcome = 'succeeded'
  AND JSON_EXTRACT(log.payload, '$.start_time') IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(log.payload, '$.meeting_id')) <> 'deduplicated'
  AND log.created_at < @fix_deployed_at
ORDER BY upcoming DESC, slot.start_utc DESC;
