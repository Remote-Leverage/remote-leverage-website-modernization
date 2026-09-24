# Data API: leads and bookings by time window

Two read-only endpoints for the data team. Each returns every lead, or every booking, in a window
the caller chooses.

```
GET /wp-json/rl-data/v1/leads?from=…&to=…
GET /wp-json/rl-data/v1/bookings?from=…&to=…
```

Code: `app/Domains/Lead/Api/`. Tests: `tests/Unit/LeadDataApiTest.php`.

## Getting a credential

Settings → **Data API** in wp-admin (admins only). Issue one credential per consumer, named after
it (`bigquery-nightly`, `jane-notebook`), so one can be revoked without breaking the others. The
password is shown once. The credential belongs to a dedicated `data-api` user whose only extra
power is `rl_read_business_data`, the same capability that gates the MCP lead abilities.

Authentication is a WordPress Application Password over HTTP Basic:

```bash
curl -u 'data-api:xxxx xxxx xxxx xxxx xxxx xxxx' \
  'https://remoteleverage.com/wp-json/rl-data/v1/leads?from=2026-09-01&to=2026-09-08'
```

No WP-CLI is needed, which matters because production and staging have none.

## Parameters

| Param | Required | Meaning |
| --- | --- | --- |
| `from` | yes | Window start, **included**. ISO 8601 date or datetime. |
| `to` | yes | Window end, **excluded**. At most 366 days after `from`. |
| `groups` | no | Comma-separated field groups. Default: all of them. |
| `limit` | no | Rows per page, 1–1000. Default 500. |
| `cursor` | no | The previous page's `next_cursor`. |

- **Half-open window.** Pass one window's `to` as the next one's `from`. A row on the boundary
  second then lands in exactly one pull.
- **Timezone.** An explicit offset (`Z`, `-04:00`) is honoured. A value without one is read as
  `America/New_York`, the same zone the cost alert and the BigQuery views use. So `from=2026-09-01`
  means 04:00 UTC during EDT. Every response echoes the window back in UTC. Check it on your first
  request.
- **Encode `+`.** `+02:00` must be sent as `%2B02:00`. An unencoded `+` arrives as a space. The API
  repairs that one case, but other clients' query builders may not.
- **Relative dates are refused** (`yesterday`, `last monday`), so a scheduled job always states
  its window explicitly.

## Response

```json
{
  "window": { "from": "2026-09-01T04:00:00Z", "to": "2026-09-08T04:00:00Z" },
  "groups": ["identity", "contact", "..."],
  "total": 1243,
  "count": 500,
  "next_cursor": 88412,
  "data": [ { "id": "88001", "created_date_utc": "2026-09-01 04:12:09", "email": "…", "...": "…" } ]
}
```

Keep requesting with `cursor=<next_cursor>` until `next_cursor` is `null`. Pages are ordered by
lead id. A lead that arrives mid-pull lands on a later page and never shifts one already read.
`total` covers the whole window, not just the page.

An empty field is `null`. Timestamps without a suffix (`created_date_utc`, `booked_at_utc`) are
UTC, `Y-m-d H:i:s`. Field values are strings.

### Fields

Rows use the same catalogue as the wp-admin CSV export (`LeadExportColumns`), keyed by the CSV
header in snake_case. The groups are `identity` (always included), `contact`, `qualification`,
`audience`, `meeting`, `attribution`, `click_ids`, `referral`, `tracking`, `raw_attribution` and
`activity`. **Rows include customer contact details** (names, emails, phone numbers).

Field names are a contract. The full list is pinned in `LeadDataApiTest`, so a rename fails the
build instead of a nightly load. If one has to change, tell the data team first.

`possible_va` (`yes`/`no`, in the `audience` group) flags leads that look like VA applicants rather
than clients. They are included, not filtered out. Filter on the flag if you want the cost
alert's client-only view.

## What counts as a booking

`/bookings` returns leads whose **first** `LeadBookingCompleted` activity-log entry falls in the
window, with `booked_at_utc` set to that moment. This is the definition the Slack cost alert
counts (`LeadBookings`), so the two reconcile.

- A lead captured last week that books today is today's booking.
- A rebooking after a cancellation does not count again. The row's `status` shows the current
  state, `canceled` included.
- The meeting time itself is `scheduled_time` in the `meeting` group. It is `null` for a meeting
  booked directly on Calendly rather than through the site's wizard, because the Calendly webhook
  logs the meeting id but not its time.
- **Leads imported from Gravity Forms have no booking time.** About 2,568 leads carry
  `status = booked` but predate the activity log. A `/bookings` window before the cutover reads
  almost empty, and that is correct. For historical booked counts, pull `/leads` and filter on
  `status`.

Soft-deleted leads are excluded from both endpoints and from `total`.

## Errors

`400` with a code: `rl_data_missing_window`, `rl_data_invalid_datetime`, `rl_data_empty_window`,
`rl_data_window_too_long`, `rl_data_unknown_group`. An unknown group is refused rather than
ignored, so a typo cannot silently drop fields. `401` means no credential or a revoked one, `403`
means the user lacks the capability.

## Audit

Every request logs `Data API: <resource> served` with the user, window, cursor and row count.
WordPress also records each application password's last-used time and IP, shown on the Data API
screen.
