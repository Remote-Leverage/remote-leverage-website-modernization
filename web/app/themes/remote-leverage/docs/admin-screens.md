# WP Admin surfaces

The theme adds four top-level menus, one Settings page, list-table enhancements on three post types, dashboard widgets, a global admin restyle, and rebrands of Yoast SEO and WordFence into in-house "SEO" and "Security" sections. All are registered from `DomainServiceProvider::boot()` except Environment Sync, which registers itself from `SyncServiceProvider` and only when sync is enabled.

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

Since 2026-09-16 that timeline interleaves a second source: the **integration call log**, the full
request and response of every outbound call made on this lead's behalf. The activity log says what
each domain decided to do; the call log says what actually crossed the wire. Reading them merged
is the point — a HubSpot 400 sits directly under the entry that claimed the sync succeeded. Each
row carries a 16px source icon, and a call row expands to request headers and body, response
headers and body, duration, and which account it authenticated as. Credentials appear only as
fingerprints. See [observability.md](observability.md).

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

## Social Media Kit

`SocialKitAdmin` — **Settings → RL Social Kit** (`options-general.php?page=rl-social-kit`).

Ported from the retired `rl-social-kit` plugin on 2026-09-15. Writes the same option names the
plugin used, so an existing `wp_options` row keeps working; `SocialKit::OPTION_DEFAULTS` is the
single source of truth for the defaults and the screen never restates them.

Two traps if this is ever edited. The Require Login checkbox needs an explicit
`sanitize_callback` — an unchecked box is simply absent from the POST body, so without one the
value can never save as `'0'`. And `rl_social_kit_company_logo` has to be registered separately
because it is resolved at read time by `SocialKit::companyLogo()` rather than living in
`OPTION_DEFAULTS`; miss it and the field renders but silently never saves.

Full behaviour: [social-media-kit.md](social-media-kit.md).

## SEO (Yoast)

`App\Infrastructure\WordPress\Admin\Seo\*` — presents Yoast SEO as our own section. Everything is
gated on `SeoAdmin::pluginActive()` (`WPSEO_VERSION`), so an uninstalled plugin leaves no trace
rather than a widget full of zeroes.

| Class | Does |
| :--- | :--- |
| `SeoMenu` | Renames the menu to **SEO** with `dashicons-search`, unregisters the premium/marketing pages, and switches off the HelpScout beacon |
| `SeoEditor` | Renames the metabox to **SEO**, swaps the icon-only list-table headers for Lucide ones, and loads the shim that renames the Gutenberg sidebar panel |
| `SeoAdminSkin` | Dequeues the promotional stylesheets, enqueues the skin on the right screens |
| `SeoSkinStyles` | The CSS override layer — tokens, the Yoast screens, the editor, the widget |
| `SeoStats` | The figures, read from Yoast's postmeta and indexable table, cached 180s |
| `SeoDashboardWidget` | Replaces Yoast's "Posts Overview" widget |
| `SeoOverviewPage` | Replaces Yoast's React General page at `admin.php?page=wpseo_dashboard` |

### The section header

WordFence's own screens get a `Security` title, subtitle and the same tab strip as the
overview, injected on `in_admin_header` — the hook fires before the page callback, which is the
only place to put a header on a screen somebody else renders.

That hook fires inside `#wpcontent` but **before** `#wpbody`, which makes the header the first
child of a container with no top padding. Space it with a `margin-top` and the margin collapses
straight out through `#wpcontent` and `#wpwrap`, pushing the whole admin layout — sidebar
included — down by that amount, which reads as an unexplained band under the admin bar. It is
padding for that reason.
Without it those pages open with no indication of what section you are in and no way back
except the sidebar; they read as a different product the menu happens to link to.

Tabs are only rendered for pages WordFence actually registered, so Blocking and Live Traffic
(both conditional, and Live Traffic is off in this project's config) do not appear as dead
links. Audit Log and Live Traffic redirect to `page=WordfenceTools&subpage=…`, so
`resolveTabSlug()` maps the subpage back — otherwise clicking Audit Log highlights Tools and
the Audit Log tab never highlights at all.

WordFence's own second-level tabs (`ul.wf-page-fixed-tabs` on Tools and Firewall) are restyled
as an underlined secondary row rather than a second set of pills, so they read as subordinate
to the Security tabs above them.

### Why the inner views are skinned, not rebuilt

Yoast's Settings, General, Academy and Integrations screens are React apps that mount into bare
divs (`#yoast-seo-settings`, `#yoast-seo-general`). Their markup carries the `yst-` Tailwind
utility classes and **almost no semantic class hooks** — 278 distinct utilities and 6 semantic
names across the settings bundle. All layout lives in `tailwind-*.css`, so dequeuing it does not
leave restyleable HTML behind; it leaves unstyled div soup. The structural sheets therefore stay
and `SeoSkinStyles` repaints on top of them.

Two things make that tractable:

1. Yoast ships a **semantic BEM component layer** beside the utilities — `yst-button--primary`,
   `yst-card__header`, `yst-paper__header`, `yst-table--default`, `yst-toggle--checked`,
   `yst-alert--*`, `yst-title--1..5`. That is what most of the skin targets.
2. The brand colour reaches the UI through a closed set of **23 `primary-*` utilities**. Repaint
   those and Yoast's magenta (`rgb(166 30 105)`) is gone everywhere at once, including screens
   nobody has opened. `SeoAdminTest` asserts the skin covers every one of them by reading the
   plugin's own CSS, so a Yoast upgrade that adds a new one fails the build rather than leaving
   a patch of magenta.

Yoast emits every utility with `!important`, so overrides need higher specificity **and** a later
cascade position. `SeoSkinStyles` prefixes selectors with `body` for the first; `SeoAdminSkin`
registers a src-less style handle that declares the Yoast sheets as dependencies for the second.
Unregistered dependencies are filtered out first — naming one makes WordPress skip the whole
stylesheet silently, which reads as "the skin randomly doesn't apply on some screens".

### The admin bar is removed, not restyled

The toolbar's SEO menu is switched off at Yoast's own gate: `SeoMenu::disableAdminBarMenu()`
filters `option_wpseo` (and `default_option_wpseo`) to force `enable_admin_bar_menu` false.
`WPSEO_Admin_Bar_Menu::register_hooks()` returns early on that check, so the node is never built
**and** `yoast-seo-adminbar` is never enqueued on either the front end or in wp-admin — removing
the node on `admin_bar_menu` instead would still pay for both. Yoast reads the option on
`wp_loaded`; Acorn boots on `after_setup_theme`, so the filter is always in place first.

`removeAdminBarMenu()` on `admin_bar_menu` at 999 is belt and braces for the multisite path,
where network options can re-enable the menu behind the site-level one. Removing the root node
takes its children with it.

Note this also turns off the "SEO in the admin bar" toggle on Yoast's own Settings screen: it
will read as off and switching it on will not bring the menu back. That is intended — the
decision lives in code, not in the database.

### The one thing that could not stay in PHP

Yoast's Gutenberg sidebar panel title is a hardcoded string inside its React bundle. `gettext`
never sees a JS string, and `load_script_translations` only fires when a translation file exists
— which it does not on an English install. `resources/js/seo-admin.js` renames the rendered label
and disconnects its observer as soon as it succeeds. It is loaded on `enqueue_block_editor_assets`
only.

**Import the Vite facade as `Illuminate\Support\Facades\Vite`.** Getting that wrong fatals on
every editor load, and because `enqueue_block_editor_assets` fires *before* `SeoAdminSkin`'s
priority-100 hook, the symptom is not an error page — it is the whole skin silently not loading
in the editor.

### Removed pages

`SeoMenu::removeCommercialPages()` filters `wpseo_submenu_pages` at `PHP_INT_MAX`, so these are
never registered — gone from the menu *and* unreachable by URL: Academy, Support, Workouts
(Premium), Redirects (Premium), Plans/Licenses, the Upgrade nag, and AI Brand Insights. A second
sweep over `$submenu` catches anything registered with a direct `add_submenu_page()` call.

### Verified, not assumed

This was built against a headless capture of the running admin (`chrome`, 1440px, session
generated with `wp eval`), because two rounds of reasoning from Yoast's stylesheets alone shipped
CSS that did not apply. Three things only that capture revealed:

1. **The metabox needs the `yst-` layer too.** The settings screens are wholly `yst-`; the editor
   metabox mixes `yst-` components (toggles, buttons, cards — 242 distinct classes in
   `editor-modules.js`) into the older `wpseo-`/`yoast-` markup. Shipping the old-class rules
   alone left our restyled tabs above a Yoast-magenta toggle. `appCss()` and `editorCss()` now
   share `killMagenta()` and `components()`, and the component rules are *not* scoped to
   `.yst-root` — it is not a reliable ancestor in the metabox.
2. **"Yoast Redirects" is not under the SEO menu.** It is an `add_management_page()` stub under
   **Tools** that only redirects to the Premium screen, so a sweep of `$submenu['wpseo_dashboard']`
   never saw it. `SeoMenu::rebrand()` now sweeps every parent.
3. **The Settings upsell rail is pure utilities.** A fixed 16rem column carrying the Premium pitch
   and the academy promo, with no semantic hook — addressed by `yst-fixed` + `yst-end-8`, and the
   main column's reserved 17.5rem of inline-end padding zeroed with it.

The check lives in the session scratchpad rather than the repo; re-create it by driving Playwright
with a `wp eval`-generated `SECURE_AUTH_COOKIE` (this admin runs under `force_ssl_admin`, so the
logged-in cookie alone lands on the login screen) and asserting `magentaCount === 0` for
`rgb(166 30 105)` across the SEO screens, the editor and the dashboard.

### The widget

`rl_seo_overview` replaces `wpseo-dashboard-overview` (whose second half was an RSS feed of
yoast.com posts) and the Wincher upsell. Server-rendered, no JavaScript, three sections: the
score distribution as a stacked bar linking into `edit.php?seo_filter=`, the content gaps
(missing meta description, missing SEO title), and indexing health.

`discouraged` is the figure that matters most: a database restored from staging leaves
`blog_public` at `0` and every other number stays green while the whole site is noindexed, so it
renders as a critical row above everything else.

## Security (WordFence)

`SecurityAdmin`, `SecuritySkin`, `SecuritySnapshot` — presents WordFence as our own section.
Everything checks for the plugin first, so deactivating it leaves no menu rather than a fatal on
wp-admin's home page.

| Class | Does |
| :--- | :--- |
| `SecurityAdmin` | Renames the menu to **Security** with a lucide shield, prunes the marketing submenus, owns the overview page and the dashboard widget |
| `SecuritySkin` | The `.rl-sec-*` primitives, plus the CSS override layer for WordFence's own screens |
| `SecuritySnapshot` | The figures, read from `wfFirewall`, `wfScanner`, `wfIssues` and `wfActivityReport`, cached 300s |

### The menu slug stays `Wordfence`

Only the titles change. WordPress derives a submenu page's hook name from its **parent's
sanitised title**, so re-parenting the entries under a slug of our own would have WordPress
looking for `security_page_WordfenceWAF` while WordFence registered `wordfence_page_WordfenceWAF`
— every Firewall, Scan and Login Security page would 404. The slug is never shown, so renaming
titles gets the whole visible result at none of that risk. `SecurityAdminTest` guards it.

The landing page is swapped by `remove_action()`/`add_action()` on `toplevel_page_Wordfence`.
`?subpage=` is handed back to WordFence, because Global Options hangs off the same page and is
linked from inside the Firewall and Scan screens.

### Removed submenus

Help, Wordfence Central and the "Upgrade to…" callout (which WordFence renders in orange bold)
are outbound marketing. **All Options** is removed for a different reason: `config/wordfence.php`
is the source of truth and `rl:deploy` reverts anything set by hand, so a screen inviting edits
that will be silently reverted is worse than no screen — the drift table replaces it. Removing a
menu entry does not revoke access; the pages stay reachable by URL.

### Why the inner views are skinned, not rebuilt

Firewall, Scan, Blocking, Tools and Login Security are a Vue app over a Bootstrap-derived `wf-*`
grid, and they are stateful flows — scan triage, rule toggling, block-rule editing. Owning those
means re-testing them on every WordFence release. The overview is a read-only summary, so that
one is ours and the rest are repainted in place.

Two specificity traps, both of which produce silent, plausible-looking wrong results:

1. WordFence's licence-tier sheet (`css/license/free-global.css`) **doubles the variant class** —
   `a.wf-btn.wf-btn-primary.wf-btn-primary` — and loads after our inline styles, so a
   class-only rule ties on `!important` and loses on source order. The same sheet repaints the
   menu item itself in `#1b719e` on hover and when current, via single-id selectors; prefixing
   with `#adminmenu` gives two and wins outright.
2. The console's own link reset is `#wpbody-content a { color: #09090b !important }`, and one id
   outranks any number of classes, so the buttons came out dark-on-dark. Skin selectors name
   `#wpbody-content` plus the element and both classes to clear both bars at once.

The gauges are SVG paths whose colour WordFence sets as a `stroke` attribute. The skin remaps
them **by value** (`[stroke="#16bc9b"]` → our emerald) rather than flattening every ring to one
colour, so a gauge that is green because it is healthy stays green. A hex not on the list keeps
WordFence's own colour, which is how this should age.

**Login Security is a second design system.** The module ships under its own `wfls-` prefix
with its own copy of the whole component set — block, btn, table, option, modal,
section-title. None of the `wf-` rules touch it, so the screen stayed entirely in stock
WordFence blue inside an otherwise themed section until a parallel `wfls-` layer was added.

**Two scoping rules, both learned the hard way.** `.rl-security-skin` lands on `<body>`, so it
has to be the *first* compound in every selector: written as `#wpbody-content .rl-security-skin
…` it asks for a descendant of `#wpbody-content` carrying the body class, which never matches —
the rule is dead and the screen silently keeps WordFence's styling. And a bare
`.wf-nav-pills { display: inline-flex !important }` ties WordFence's
`.wf-visible-xs { display: none !important }` and wins on source order, which put a stray mobile
"Go to" dropdown on every desktop Tools screen. `SecurityAdminTest` guards both: one test
asserts every selector head carries the scope, another that no display rule on the nav
components omits `:not(.wf-visible-xs)`.

`ul.wf-tour-template` is hidden: it is markup WordFence prints for its guided tour and hides with
`css/wf-onboarding.css`, which on this install is never enqueued — leaving ~2,400px of tour copy
and a full-width gear illustration at the foot of the Firewall page. Verified pre-existing; it
renders identically with every one of our stylesheets disabled.

### The widget

`rl_dashboard_security` replaces `wordfence_activity_report_widget`, which reported five-row
top-N tables that read "No IPs blocked yet" on a site behind a CDN. The replacement leads with
firewall mode, scan outcome, 24h block volume and **configuration drift**.

Drift is the reason the widget is worth replacing rather than restyling: `config/wordfence.php`
is the source of truth and `rl:deploy` re-asserts it, so a setting that differs is either a
hand-edit about to be reverted or a deploy that never ran. Neither is visible from WordFence's
own settings pages, which show the live value with nothing to say it is contested.
`WordfenceConfigurator::audit()` reports it without writing; the **Re-apply from git** button runs
the same `apply()` that `rl:deploy` runs.

The widget is also switched off at source (`email_summary_dashboard_widget_enabled => false`) so
it never registers after the next deploy; the `remove_meta_box()` call covers databases restored
from a dump before then.

### The WAF storage engine

`config/application.php` sets `WFWAF_STORAGE_ENGINE = mysqli` so firewall state survives the
immutable container. That alone was not enough: the WAF bootstraps before WordPress and finds the
database by **text-parsing `wp-config.php`** for `define('DB_USER', …)`, and Bedrock's has no such
literals. The parse found nothing, the connection failed, and WordFence fell back to flat files
in `wflogs/` — the exact failure the setting exists to prevent — while showing "the WAF storage
engine is currently set to mysqli, but Wordfence is unable to use the database". The
`WFWAF_DB_*` constants beside it are WordFence's own supported override, read before the parse.
Fixed 2026-09-16.

`web/app/wflogs/` is gitignored: runtime WAF state, including an 8.6MB GeoIP database, rewritten
on every request and recreated on boot.

## List-table enhancements

| Class | Post types | Adds |
| :--- | :--- | :--- |
| `ContentAuditAdmin` | `post`, `page` | Conversion Status column with badges, status filter dropdown, **Approve & Mark Clean** row action (sets `_rl_conversion_status=approved`, archives `_elementor_data` → `_elementor_data_archived`) |
| `PartnerHubAdmin` | `rl_partner` | Referral Code and Submission Form columns. Field editing itself is ACF's tabbed field group (`App\Fields\PartnerHubFields`), not a custom metabox |

`ContentAuditAdmin` is the editorial review queue that makes ADR-0005's human sign-off gate real — see [domains/content-audit.md](domains/content-audit.md#editorial-review-queue).

## Dashboard

`MarketingDashboard` hooks `wp_dashboard_setup` at priority 999: it removes the default WordPress blog widgets and mounts domain widgets reading from `Lead`, `LeadActivityLog`, `Referrer`, `Referral`, `Payout` and `ElementorAuditService`, with results cached.

`SeoDashboardWidget` hooks the same action at 1000 — one higher, because Yoast also adds its widget from `wp_dashboard_setup` and `remove_meta_box()` only works once the box is there.

`SecurityAdmin::setupDashboard()` hooks it at 9999 for the same reason, after both.

## Global chrome

`WordPressAdminTheme` restyles admin, login and the front-end admin bar: Remote Leverage logo and favicon, login screen styling, a recoloured Redis Object Cache chart, and a notifications centre rendered into the admin bar (`admin_footer` and `wp_footer`).

Core adds a `php-error` body class whenever `error_get_last()` is non-null and
`display_errors` is on, and styles it with `.php-error #adminmenuback { margin-top: 2em }` to
leave room for a printed error. `error_get_last()` also reports errors suppressed with `@`,
which WordPress and its plugins do routinely, so on a healthy site that is a 26px empty band
under the admin bar. `WordPressAdminTheme` zeroes it. Local-only in practice:
`config/environments/development.php` enables `WP_DEBUG_DISPLAY`, production does not.

`AdminDesignSystem` holds the shared token set the custom screens are built from — the greys, radii and button shapes originally inlined in `LeadsAdminDashboard`. Everything is scoped under `.rl-admin-wrap`, because wp-admin's own styles are loaded on the same page and aggressive; without the scope this would be a global restyle of the dashboard. Screens enqueue it with `AdminDesignSystem::enqueue()` on their own hook only, so other admin pages carry none of its weight.

One trap when adding a control to a custom screen: `WordPressAdminTheme`'s "Global Link & Text Neutralization" block paints `#wpbody-content a` with `color` and `text-decoration` at `!important`. That selector is specificity (1,0,1), so it outranks every single-class rule in `AdminDesignSystem` — an `<a class="rl-btn rl-btn-primary">` rendered as dark underlined text on a dark button until this was fixed on 2026-09-16. Anchors used as controls need an ID in the selector to clear that bar; `AdminDesignSystem` now carries those rules and `AdminDesignSystemTest` guards them.

## Front-end admin surfaces

Not wp-admin, but admin-adjacent — Acorn routes serving application pages:

| Route | View |
| :--- | :--- |
| `/book-consultation` | `pages/book-consultation` |
| `/referrer-portal`, `/referrer-register` | `pages/referrer-portal`, `pages/referrer-register` |
| `/referral-dashboard` | Legacy tabbed URL — routes to portal or registration by query string |
| `/social-media-kit` | `pages/social-media-kit` — replaced `/tools/signature-generator`, removed 2026-09-15 |
| `/live-call/connect` | Redirects to a Meet room or to `/book-consultation?offline=1` |
| `/api/health` | JSON health check |
