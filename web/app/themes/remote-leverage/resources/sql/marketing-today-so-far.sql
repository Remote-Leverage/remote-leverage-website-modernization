-- Today's running totals, for the overnight card only.
--
-- OURS, not the data team's. Their file (marketing-home-daily.sql) is the definition of a
-- marketing day and must not be edited; before 08:00 Eastern it deliberately reports the previous
-- day complete and says nothing about the hours since midnight. That is the right headline, but
-- the alert it replaced showed today-so-far around the clock, and people read the overnight cards
-- to see whether the morning had started. So this reads the same view for today and nothing else.
--
-- Deliberately four sums and no arithmetic. Every rate on the card -- CPL, CPB, CPQB -- still
-- comes from their query, because a cost divided our way is a number that disagrees with every
-- other report in the business. Sums of their own columns cannot disagree with anything.
--
-- Same view, same `is_channel_row` filter that excludes the 'All Channels' revenue row, same
-- timezone. If they move or rename the view, this breaks alongside their file rather than
-- silently reading something else -- MarketingTodaySoFarTest pins the column names.
--
-- Returns exactly one row, or none before the first channel row of the day exists.
SELECT
  FORMAT_DATE('%Y-%m-%d', h.date)                                        AS date,
  FORMAT_TIMESTAMP('%H:%M', DATETIME(CURRENT_TIMESTAMP(), 'America/New_York')) AS as_of_et,
  SUM(h.spend)              AS total_spend,
  SUM(h.leads)              AS total_leads,
  SUM(h.bookings)           AS total_appointments,
  SUM(h.qualified_bookings) AS total_qualified,

  -- The three headline costs, same SAFE_DIVIDE of the view's own columns their query uses for a
  -- closed day. Null until the denominator exists, which overnight is most of the time -- a cost
  -- per booking on zero bookings is not zero, it is absent, and the card prints a dash.
  SAFE_DIVIDE(SUM(h.spend), SUM(h.leads))              AS cpl,
  SAFE_DIVIDE(SUM(h.spend), SUM(h.bookings))           AS cpb,
  SAFE_DIVIDE(SUM(h.spend), SUM(h.qualified_bookings)) AS cpqb,


  -- Per channel, in the shape their query publishes for a closed day, so the card can lay today
  -- out exactly as it lays out yesterday. The two costs are SAFE_DIVIDE of the view's own columns
  -- and nothing else: verified against their published figures for 19 Sep, where Facebook's
  -- 19106.75 / 62 gave 308.1733 against their 308.17, and / 47 gave 406.5266 against their 406.53.
  -- Same definition, applied to a day they do not publish.
  SUM(IF(h.channel = 'Facebook', h.spend, 0))                                     AS facebook_spend,
  SAFE_DIVIDE(SUM(IF(h.channel = 'Facebook', h.spend, 0)),
              SUM(IF(h.channel = 'Facebook', h.bookings, 0)))                     AS facebook_cpb,
  SAFE_DIVIDE(SUM(IF(h.channel = 'Facebook', h.spend, 0)),
              SUM(IF(h.channel = 'Facebook', h.qualified_bookings, 0)))           AS facebook_cpqb,

  SUM(IF(h.channel = 'Google', h.spend, 0))                                       AS google_spend,
  SAFE_DIVIDE(SUM(IF(h.channel = 'Google', h.spend, 0)),
              SUM(IF(h.channel = 'Google', h.bookings, 0)))                       AS google_cpb,
  SAFE_DIVIDE(SUM(IF(h.channel = 'Google', h.spend, 0)),
              SUM(IF(h.channel = 'Google', h.qualified_bookings, 0)))             AS google_cpqb,

  SUM(IF(h.channel = 'Bing', h.spend, 0))                                         AS bing_spend,
  SAFE_DIVIDE(SUM(IF(h.channel = 'Bing', h.spend, 0)),
              SUM(IF(h.channel = 'Bing', h.bookings, 0)))                         AS bing_cpb,
  SAFE_DIVIDE(SUM(IF(h.channel = 'Bing', h.spend, 0)),
              SUM(IF(h.channel = 'Bing', h.qualified_bookings, 0)))               AS bing_cpqb
FROM `rl-data-platform-dev.sandbox_victorluz.vw_mkt_home_daily` h
WHERE h.date = CURRENT_DATE('America/New_York')
  AND h.is_channel_row
GROUP BY date
