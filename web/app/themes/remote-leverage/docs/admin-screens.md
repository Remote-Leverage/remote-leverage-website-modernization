# WP Admin surfaces

The theme adds four top-level menus, one Settings page, list-table enhancements on three post types, dashboard widgets, and a global admin restyle. All are registered from `DomainServiceProvider::boot()` except Environment Sync, which registers itself from `SyncServiceProvider` and only when sync is enabled.

Every screen requires `manage_options`.

---

## Leads

`LeadsAdminDashboard` — `admin.php?page=rl-leads`, menu position 30, `dashicons-groups`.

| Submenu | Slug | Shows |
| :--- | :--- | :--- |
| All Leads | `rl-leads` | The lead table with KPI badges, search, status filters, CSV export, retention purge |
| Activity Logs | `rl-leads-activity` | Global dual-write audit stream across all leads, filterable by stage and outcome |
| Diagnostics | `rl-leads-diagnostics` | API & integration health |
| Settings | `rl-leads-settings` | Notification recipients, optional-field toggles, retention days, HubSpot/Slack/webhook overrides |

**KPI badges**: total leads, booked consultations, high-tier (≥ $10k MRR) counts, partial form drops, total audit log volume.

**Filters**: search across name, email, phone, company and UTM source; status pills for `captured`, `booked`, `partial`, `abandoned`, `qualified`, `canceled`.

**Lead dossier** (`?view_lead=ID`): contact details, revenue tier, the full UTM/attribution set including `gclid` and `fbclid`, the booked slot and Google Meet URL, and the execution timeline comparing Stage 1 dispatch against Stage 2 consumption with expandable JSON payloads. This is the screen that answers "did HubSpot actually receive this lead?" — see [domains/lead.md](domains/lead.md#the-dual-write-audit-contract).

**Actions**: CSV export of the filtered set; one-click 30-day retention purge (the floor is enforced in `PurgeOldLeadsAction`, not in the UI); manual status updates. Handled in `handleAdminActions()` on `admin_init`, gated on `manage_options` and a nonce.

## Referrers

`ReferralAdminDashboard` — `admin.php?page=rl-referrers`, position 31.

| Submenu | Slug |
| :--- | :--- |
| Analytics | `rl-referrers` |
| All Referrers | `rl-referrers-list` |
| Referrals | `rl-referrers-referrals` |
| Rewards & Payouts | `rl-referrers-rewards` |
| Settings | `rl-referrers-settings` |

Settings writes `rl_referral_settings` via `ReferralSettingsService` — cookie lifetime, reward type and currency, landing pages.

## Calendly

`CalendlyAdminDashboard` — `admin.php?page=rl-calendly`, position 31, `dashicons-calendar-alt`.

| Submenu | Slug | Shows |
| :--- | :--- | :--- |
| Token Pool | `rl-calendly` | Every token, its rate-limit cooldown, per-context failure count and circuit state; add/remove; clear failures |
| Event Types | `rl-calendly-event-types` | Discovered event types, and the role assignment (`default` / `t10` / `t0` / `live_call`) that drives revenue-tier routing |

The role mapping lives in `wp_options`, so routing can be re-pointed without a deploy. See [domains/scheduling.md](domains/scheduling.md#revenue-tier-routing).

## Environment Sync

`EnvironmentSyncAdmin` + `EnvironmentSyncScreen` — **Settings → Environment Sync** (`options-general.php?page=rl-environment-sync`).

Registered only when `SyncEnvironment::syncEnabled()` — it does not exist at all when `WP_ENV === 'production'`, which is gate 1 of four. Progress is polled through `wp_ajax_rl_sync_push_step` and `wp_ajax_rl_sync_pull_step`; every POST goes through `check_admin_referer`.

Full behaviour: [domains/sync.md](domains/sync.md).

## List-table enhancements

| Class | Post types | Adds |
| :--- | :--- | :--- |
| `ContentAuditAdmin` | `post`, `page` | Conversion Status column with badges, status filter dropdown, **Approve & Mark Clean** row action (sets `_rl_conversion_status=approved`, archives `_elementor_data` → `_elementor_data_archived`) |
| `PartnerHubAdmin` | `rl_partner` | Referral Code and Submission Form columns. Field editing itself is ACF's tabbed field group (`App\Fields\PartnerHubFields`), not a custom metabox |

`ContentAuditAdmin` is the editorial review queue that makes ADR-0005's human sign-off gate real — see [domains/content-audit.md](domains/content-audit.md#editorial-review-queue).

## Dashboard

`MarketingDashboard` hooks `wp_dashboard_setup` at priority 999: it removes the default WordPress blog widgets and mounts domain widgets reading from `Lead`, `LeadActivityLog`, `Referrer`, `Referral`, `Payout` and `ElementorAuditService`, with results cached.

## Global chrome

`WordPressAdminTheme` restyles admin, login and the front-end admin bar: Remote Leverage logo and favicon, login screen styling, a recoloured Redis Object Cache chart, and a notifications centre rendered into the admin bar (`admin_footer` and `wp_footer`).

`AdminDesignSystem` holds the shared token set the custom screens are built from — the greys, radii and button shapes originally inlined in `LeadsAdminDashboard`. Everything is scoped under `.rl-admin-wrap`, because wp-admin's own styles are loaded on the same page and aggressive; without the scope this would be a global restyle of the dashboard. Screens enqueue it with `AdminDesignSystem::enqueue()` on their own hook only, so other admin pages carry none of its weight.

## Front-end admin surfaces

Not wp-admin, but admin-adjacent — Acorn routes serving application pages:

| Route | View |
| :--- | :--- |
| `/book-consultation` | `pages/book-consultation` |
| `/referrer-portal`, `/referrer-register` | `pages/referrer-portal`, `pages/referrer-register` |
| `/referral-dashboard` | Legacy tabbed URL — routes to portal or registration by query string |
| `/tools/signature-generator` | `pages/signature-generator` |
| `/live-call/connect` | Redirects to a Meet room or to `/book-consultation?offline=1` |
| `/api/health` | JSON health check |
