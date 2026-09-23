-- Everything about the reported day that the data team's query does not select.
--
-- OURS, not theirs. marketing-home-daily.sql is the definition of a marketing day and must not be
-- edited; it returns the figures the business reports on. This reads the same view, for the same
-- date, and returns three things it leaves out:
--
--   1. `extracted_at` — when the warehouse last pulled this data. The card's "as of" is when *we
--      asked*, which keeps ticking forward whether or not the ETL is still running. Without this
--      a stalled pipeline shows as a card full of frozen numbers wearing a current timestamp.
--   2. The staleness flags for Google, Bing and spend generally. Their query derives
--      `spend_is_complete` from `is_meta_stale` alone, so a Google Ads outage arrives as reduced
--      spend with no caveat and a cost per booking that silently improves.
--   3. Impressions, clicks and landing page views — the funnel above the lead. The card's earliest
--      warning today is "no new lead for Xm", which is lagging by construction.
--   4. Leads, bookings and qualified bookings per channel. Their query publishes each channel's
--      spend and the two costs but not the counts behind them, and a cost per booking without its
--      denominator cannot be told apart from a small sample.
--
-- No arithmetic beyond SUM and MAX of the view's own columns. Rates stay theirs.
--
-- @@DATE@@ is replaced with the date their query reported, so the two can never describe different
-- days. BigQueryClient validates it as YYYY-MM-DD before substitution.
--
-- Returns exactly one row, or none for a date with no channel rows.
SELECT
  FORMAT_DATE('%Y-%m-%d', h.date)                     AS date,
  CAST(MAX(h.extracted_at) AS STRING)                 AS extracted_at,
  MAX(CAST(h.is_meta_stale AS INT64))                 AS meta_stale,
  MAX(CAST(h.is_google_stale AS INT64))               AS google_stale,
  MAX(CAST(h.is_bing_stale AS INT64))                 AS bing_stale,
  MAX(CAST(h.is_spend_pending AS INT64))              AS spend_pending,
  SUM(h.impressions)                                  AS impressions,
  SUM(h.clicks)                                       AS clicks,
  SUM(h.landing_page_views)                           AS landing_page_views,

  SUM(IF(h.channel = 'Facebook', h.leads, 0))              AS facebook_leads,
  SUM(IF(h.channel = 'Facebook', h.bookings, 0))           AS facebook_bookings,
  SUM(IF(h.channel = 'Facebook', h.qualified_bookings, 0)) AS facebook_qualified,
  SUM(IF(h.channel = 'Google', h.leads, 0))                AS google_leads,
  SUM(IF(h.channel = 'Google', h.bookings, 0))             AS google_bookings,
  SUM(IF(h.channel = 'Google', h.qualified_bookings, 0))   AS google_qualified,
  SUM(IF(h.channel = 'Bing', h.leads, 0))                  AS bing_leads,
  SUM(IF(h.channel = 'Bing', h.bookings, 0))               AS bing_bookings,
  SUM(IF(h.channel = 'Bing', h.qualified_bookings, 0))     AS bing_qualified
FROM `rl-data-platform-dev.sandbox_victorluz.vw_mkt_home_daily` h
WHERE h.date = DATE '@@DATE@@'
  AND h.is_channel_row
GROUP BY date
