-- Consultations sitting on the calendar for each of a few named days.
--
-- OURS, not the data team's. marketing-home-daily.sql is the definition of a marketing day and
-- must not be edited; it counts bookings *created* on a day. This counts meetings *scheduled for*
-- a day, which is the other half of the card and a different question: one is the day's
-- production, the other is the calendar's load.
--
-- ## Why this replaced Calendly
--
-- This figure used to come from CalendlyClient::countBookedEvents(), once per day per tier, over
-- the two event-type URIs held in the `t0` and `t10` roles. The account has ten active event
-- types whose name is a VA Hiring Consultation — the two automated `(A)` tiers the booking wizard
-- writes into, a shared manual `(M)` type, and one per sales rep — so the card counted two of ten
-- and under-reported the calendar by about a quarter. On 2026-09-21 it said 80 against a true 112,
-- and sales were within minutes of switching ads off over the gap.
--
-- `gold.sales_consultation_meetings` already solves this, and solves more of it than a fix to the
-- role list would have. It matches on the event *name* across every type, so a new rep's event
-- type is counted the day it is created rather than the day somebody remembers to add its URI; it
-- takes `status = 'active'`, so cancelled and rescheduled-away meetings drop out; and it unions in
-- consultations that only ever existed in Google Calendar — manual and rebooked meetings Calendly
-- cannot see, ten to twenty a day once a day is underway — de-duplicated against Calendly by
-- external event id and by (email, date, hour).
--
-- ## COUNT(DISTINCT meeting_key), not COUNT(*)
--
-- The view's last join is `ON m.client_email = d.email`, so a client holding two RecruitCRM deals
-- fans one meeting out into two rows. On 2026-09-21 that is one meeting and the difference between
-- 113 and 112; it inflated three of the next four days. The CRM screenshot that started this said
-- 113 for exactly this reason. Counting keys rather than rows is what makes this agree with the
-- calendar instead of with the deal table.
--
-- ## One row per requested day, always
--
-- The LEFT JOIN out of `wanted` is load-bearing. A day with no meetings produces no row in the
-- view, and this card's standing rule is that "we could not read it" is never rendered as zero —
-- so the two cases have to be distinguishable downstream. With the join, a day that is genuinely
-- empty comes back as 0 and only a failed query comes back as nothing at all. Without it, a quiet
-- Sunday and an unreachable warehouse are the same silence.
--
-- @@DATES@@ is replaced with a comma-separated list of DATE literals, each validated as
-- YYYY-MM-DD before substitution — queryRows() posts raw SQL with no parameter support.
--
-- `meeting_date` is already DATE(start_at, 'America/New_York'), the same timezone the alert
-- computes its day boundaries in, so there is no conversion to get wrong here.
WITH wanted AS (
  SELECT d FROM UNNEST([@@DATES@@]) AS d
)
SELECT
  FORMAT_DATE('%Y-%m-%d', w.d)    AS date,
  COUNT(DISTINCT m.meeting_key)   AS consultations
FROM wanted w
LEFT JOIN `rl-data-platform-dev.gold.sales_consultation_meetings` m
  ON m.meeting_date = w.d
GROUP BY w.d
ORDER BY w.d
