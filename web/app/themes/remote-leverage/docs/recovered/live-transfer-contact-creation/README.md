# Recovered: `/live-transfer-contact-creation/`

Pulled from the live production site over SSH on **2026-09-17**, read-only.

| | |
| :--- | :--- |
| Post ID | `51147` |
| Type / status | `page` / `publish` |
| Slug | `live-transfer-contact-creation` |
| Created | 2026-09-15 14:33:46 |
| Last modified | 2026-09-16 07:55:24 |
| Page template | `default` |
| Built with | Elementor 4.2.4 — one `container` holding one `html` widget |
| Yoast title / description | none set |

## What it is

An internal sales tool, not a marketing page. Heading: **"Log a booking"**, subtitle *"For leads
who didn't book online but agreed to a meeting on the call."* A BDR fills it in after a live call
so the lead gets a booking without having gone through the online funnel.

Form version string in the JS is `3.0.0`.

### Fields

`first_name` · `last_name` · `email` · `phone` · `company_or_website` · `booking_datetime` ·
`bdr_name` · `sales_rep_email`

### Where it posts

```
POST https://n8n.srv1338052.hstgr.cloud/webhook/live-call-transfer
```

A **different** n8n webhook from the `gravityforms-leads` one the Gravity Forms feeds use
(see [`../../domains/tracking-event-matrix.md`](../../domains/tracking-event-matrix.md)). Nothing
in the theme or in either GTM container touches it.

Two details worth preserving on any port:

- **Bookings are always entered in Eastern time** — `TIMEZONE = "America/New_York"` is hardcoded,
  deliberately, "whatever the rep's own clock says".
- **Idempotency**: one `submissionId` (`crypto.randomUUID()`, with a timestamp+random fallback for
  non-secure origins) is minted per filled-in form and survives retries, so n8n can drop
  duplicates. Re-sending after a failed submit must reuse the same id or n8n will double-create.

## Files

| File | What it is |
| :--- | :--- |
| `form.html` | **The real content.** The `html` widget's markup, styles and script, 13,696 chars — this is what renders |
| `elementor_data.json` | The full `_elementor_data` blob, for rebuilding the Elementor page as-is |
| `post_content.stale.html` | The `post_content` column, 6,382 chars. **An older, shorter copy** that does not match the widget. Kept for reference only — do not port this one |

The divergence between the last two is worth knowing about: WordPress holds two versions of this
page and only the Elementor widget is live. Anything reading `post_content` — a REST consumer, an
export, a migration script — gets the stale one.

## Two things to fix rather than port as-is

**1. It is public and indexed.** There is no `noindex`, no auth gate, and it appears in
`page-sitemap.xml`. Fetched anonymously it returns the full working form. So an unauthenticated
stranger can POST arbitrary contacts into the n8n flow, and the page is discoverable in search.

Given `wp_add9221751_snippets` #32170 ("SSO Tools Access Control") already gates `/tools` behind
login on production, this page looks like it was meant to sit behind that and never was.

**2. The n8n URL is inline in the markup.** On a v2 port it belongs in config alongside the other
webhook URLs, not hardcoded in a page body where changing it needs a content edit.
