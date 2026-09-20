-- The marketing day, as the data team defines it.
--
-- OWNED BY THE DATA TEAM. This file is a verbatim copy of the query they maintain against
-- `vw_mkt_home_daily`; it is duplicated here only because the alert has to run it. Do not edit it
-- to change a definition — changing what "qualified" or "paid" means here makes the Slack card
-- disagree with every other report in the business, which is worse than being wrong in the same
-- way as everything else.
--
-- When they change theirs, replace this whole file. `MarketingDayTest` asserts the column names
-- the mapper depends on, so a rename fails loudly instead of rendering a card of nulls.
--
-- Returns exactly one row.
WITH now_et AS (
  SELECT
    CURRENT_DATE('America/New_York')                                   AS today,
    EXTRACT(HOUR FROM DATETIME(CURRENT_TIMESTAMP(), 'America/New_York')) AS hour_et
),

params AS (
  SELECT
    IF(hour_et < 8, DATE_SUB(today, INTERVAL 1 DAY), today) AS d,
    IF(hour_et < 8, 'CLOSING', 'DAY-TO-DATE')                AS report_kind,
    -- dia fechado anterior ao reportado, para comparação
    IF(hour_et < 8, DATE_SUB(today, INTERVAL 2 DAY), DATE_SUB(today, INTERVAL 1 DAY)) AS prev_d,
    today, hour_et
  FROM now_et
),

dia AS (
  SELECT
    h.channel, h.is_paid_channel, h.is_meta_stale,
    h.spend, h.leads, h.bookings, h.qualified_bookings AS qb
  FROM `rl-data-platform-dev.sandbox_victorluz.vw_mkt_home_daily` h
  CROSS JOIN params p
  WHERE h.date = p.d
    AND h.is_channel_row          -- exclui a linha 'All Channels' (só revenue)
),

agg AS (
  SELECT
    SUM(spend) AS total_spend, SUM(leads) AS total_leads,
    SUM(bookings) AS total_added, SUM(qb) AS qualified,
    SUM(IF(channel='Facebook', spend,    0)) AS facebook_spend,
    SUM(IF(channel='Google',   spend,    0)) AS google_spend,
    SUM(IF(channel='Bing',     spend,    0)) AS bing_spend,
    SUM(IF(channel='Facebook', bookings, 0)) AS facebook_added,
    SUM(IF(channel='Google',   bookings, 0)) AS google_added,
    SUM(IF(channel='Bing',     bookings, 0)) AS bing_added,
    SUM(IF(channel='Facebook', qb,       0)) AS facebook_qual,
    SUM(IF(channel='Google',   qb,       0)) AS google_qual,
    SUM(IF(channel='Bing',     qb,       0)) AS bing_qual,
    SUM(IF(is_paid_channel, spend,    0)) AS paid_spend,
    SUM(IF(is_paid_channel, bookings, 0)) AS paid_added,
    SUM(IF(is_paid_channel, qb,       0)) AS paid_qual,
    SUM(IF(NOT is_paid_channel, bookings, 0)) AS unclassified_appointments,
    SUM(IF(NOT is_paid_channel, qb,       0)) AS unclassified_qualified,
    SUM(IF(NOT is_paid_channel, leads,    0)) AS unclassified_leads,
    LOGICAL_OR(is_meta_stale) AS meta_stale
  FROM dia
),

-- dia fechado anterior, mesma fonte, mesmas regras
prev AS (
  SELECT
    SUM(h.spend)              AS prev_spend,
    SUM(h.bookings)           AS prev_added,
    SUM(h.qualified_bookings) AS prev_qual
  FROM `rl-data-platform-dev.sandbox_victorluz.vw_mkt_home_daily` h
  CROSS JOIN params p
  WHERE h.date = p.prev_d
    AND h.is_channel_row
)

SELECT
  p.d                                                   AS Date,
  p.report_kind,
  FORMAT_TIMESTAMP('%H:%M', DATETIME(CURRENT_TIMESTAMP(),'America/New_York')) AS as_of_et,
  -- TRUE só no fechamento (ontem) com o feed de Meta cobrindo o dia
  p.report_kind = 'CLOSING' AND a.meta_stale IS NOT TRUE AS spend_is_complete,

  CAST(a.total_added AS INT64)                          AS total_new_appts,
  CAST(a.qualified   AS INT64)                          AS total_new_qualified,
  CAST(a.total_added AS INT64)                          AS total_appointments,
  CAST(a.qualified   AS INT64)                          AS total_qualified,
  CAST(a.total_leads AS INT64)                          AS total_leads,

  ROUND(a.total_spend,   2)                             AS total_spend,
  ROUND(a.google_spend,  2)                             AS google_spend,
  ROUND(a.facebook_spend,2)                             AS facebook_spend,
  ROUND(a.bing_spend,    2)                             AS bing_spend,

  ROUND(SAFE_DIVIDE(a.total_spend, NULLIF(a.total_leads,0)), 2) AS cpl,
  ROUND(SAFE_DIVIDE(a.total_spend, NULLIF(a.total_added,0)), 2) AS cpb,
  ROUND(SAFE_DIVIDE(a.total_spend, NULLIF(a.qualified,  0)), 2) AS cpqb,
  ROUND(SAFE_DIVIDE(a.total_spend, NULLIF(a.total_added,0)), 2) AS cpb_all,
  ROUND(SAFE_DIVIDE(a.total_spend, NULLIF(a.qualified,  0)), 2) AS cpqb_all,
  ROUND(SAFE_DIVIDE(a.paid_spend,  NULLIF(a.paid_added, 0)), 2) AS cpb_paid,
  ROUND(SAFE_DIVIDE(a.paid_spend,  NULLIF(a.paid_qual,  0)), 2) AS cpqb_paid,

  ROUND(SAFE_DIVIDE(a.google_spend,   NULLIF(a.google_added,  0)), 2) AS google_cpb,
  ROUND(SAFE_DIVIDE(a.facebook_spend, NULLIF(a.facebook_added,0)), 2) AS facebook_cpb,
  ROUND(SAFE_DIVIDE(a.bing_spend,     NULLIF(a.bing_added,    0)), 2) AS bing_cpb,
  ROUND(SAFE_DIVIDE(a.google_spend,   NULLIF(a.google_qual,   0)), 2) AS google_cpqb,
  ROUND(SAFE_DIVIDE(a.facebook_spend, NULLIF(a.facebook_qual, 0)), 2) AS facebook_cpqb,
  ROUND(SAFE_DIVIDE(a.bing_spend,     NULLIF(a.bing_qual,     0)), 2) AS bing_cpqb,

  CAST(a.unclassified_leads        AS INT64) AS unclassified_leads,
  CAST(a.unclassified_appointments AS INT64) AS unclassified_appointments,
  CAST(a.unclassified_qualified    AS INT64) AS unclassified_qualified,
  ROUND(SAFE_DIVIDE(a.unclassified_appointments, NULLIF(a.total_added,0)), 4) AS unclassified_appointments_share,

  -- referência: dia fechado anterior
  p.prev_d                                                        AS prev_date,
  ROUND(pr.prev_spend, 2)                                         AS prev_spend,
  CAST(pr.prev_added AS INT64)                                    AS prev_appointments,
  ROUND(SAFE_DIVIDE(pr.prev_spend, NULLIF(pr.prev_added,0)), 2)   AS prev_cpb,
  ROUND(SAFE_DIVIDE(pr.prev_spend, NULLIF(pr.prev_qual, 0)), 2)   AS prev_cpqb
FROM agg a
CROSS JOIN params p
CROSS JOIN prev pr
