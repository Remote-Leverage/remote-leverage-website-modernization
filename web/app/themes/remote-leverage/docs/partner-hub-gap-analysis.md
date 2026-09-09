# Partnership Hub: Legacy Plugin vs. New Implementation — Gap Analysis

Compared `/Users/adriansalvatori/Documents/projects-rl/rl-testing/web/app/plugins/rl-partners-hub` (legacy plugin, header: `Plugin Name: Partnership Hub`) against the new `PartnerHub` domain in this repo. Verified by reading both codebases directly, 2026-09-09.

## Status: closed (2026-09-09)

Tracked as Jira epic **WR-115** with subtasks **WR-116** through **WR-120**. All 5 landed:

- New `PartnerHubAdmin` class (`app/Infrastructure/WordPress/Admin/PartnerHubAdmin.php`) restores all 8 legacy metaboxes plus the JSON export/import tool, registered via `DomainServiceProvider`.
- `single-rl_partner.blade.php` now reads all ~35 per-partner fields (both referral directions, resources, per-section PDF attachments, content overrides, the co-marketing toggle), with a new right sidebar restoring the resources/quick-actions/manager blocks.
- New `PartnerHubGlobalData` service ports the legacy's full default content library (10 services, 6 case studies, 14 FAQs, 8-row comparison matrix, 6-stage referral lifecycle, etc.) as the fallback for any blank override field.
- New `PartnerHubTabResolver` extracts the co-marketing tab visibility logic into a unit-testable class.
- Pest coverage added in `tests/Unit/PartnerHubTest.php` (24 tests) covering the admin save handler, the global data library, and tab resolution.

The rest of this document is the original gap analysis, kept for reference.

## Two different features live under "Partner Hub" in the new codebase

1. **`archive-rl_partner.blade.php`** → `<livewire:partner.partner-directory-grid>` → `QueryPartnersAction`/`SyncNotionPartnersAction`/`PartnerProfile` — a searchable, Notion-synced partner **directory/marketplace**. This is new, additive scope (not in the legacy plugin at all) and is well-built: search/filter/category/featured, cached Notion sync. **Not a gap** — no legacy equivalent existed.
2. **`single-rl_partner.blade.php`** (the `rl_partner` CPT single page, `/partners/{slug}/{tab}/`) — this is the actual port target for the legacy "Partnership Hub" plugin. **This is where the gap is.**

## Critical gap: no admin UI to manage a partner hub at all

The legacy plugin registered 8 metaboxes (`class-rl-partner-metabox.php`, ~865 lines) covering Branding, Terms, bidirectional Referral Actions & Commission, Resources, Co-Marketing, Manager Contact, Content Overrides, and Section Attachments (PDF uploads per tab, via `wp.media`) — plus a JSON export/import tool and an AI-skill markdown doc for generating new partner configs.

The new `PartnerPostType.php` registers **only the CPT and rewrite rules** — no metabox class exists anywhere in `app/`. A content editor has no admin UI to set any of the ~35 per-partner fields; they'd be stuck with WordPress's raw Custom Fields box (unlabeled, unvalidated, no media uploader). Onboarding a new partner (e.g., a new "Oyster") is not practically possible today without editing meta directly via WP-CLI or a database tool.

## Content gap: single partner page is generic/hardcoded, not per-partner configurable

The new `single-rl_partner.blade.php` keeps the legacy's exact tab structure and routing (`rl_tab` query var, same 9 tab keys) but only reads **7 of the legacy's ~35 meta fields**, and replaces the rest with hardcoded generic LatAm-staffing marketing copy that's identical for every partner.

| Legacy field(s) | Read in new template? | Consequence |
|---|---|---|
| `_rl_partner_code`, `_rl_partner_name`, `_rl_partner_logo_url` | Yes | Branding works |
| `_rl_partnership_type`, `_rl_territory` | Yes | Shown in overview snapshot |
| `_rl_partner_to_rl_fee` | Yes | Shown in referral-program tab |
| `_rl_manager_name`, `_rl_manager_email` | Yes | Shown in sidebar + contact tab |
| `_rl_partner_website`, `_rl_partner_cover_url` | Fetched but **never rendered** | Website link and hero cover image are dead code |
| `_rl_reporting_period`, `_rl_initial_term`, `_rl_renewal_terms` | **No** | Agreement specifics no longer shown anywhere |
| `_rl_referral_form_url` (Google Form intake) | **No** | The actual lead-intake mechanism for Direction A is gone — replaced with a generic `?via=CODE` link + mailto |
| `_rl_referral_drive_url` (tracking sheet) | **No** | No tracking-sheet link anywhere on the page |
| `_rl_intro_email` | **No** | Hardcoded flow instead |
| `_rl_partner_referral_label/_email/_url`, `_rl_rl_to_partner_fee` | **No** | **Entire Direction B (RL → Partner) is gone.** For a bidirectional partner like Oyster (RL refers clients *to* Oyster for EOR/payroll), there is no mechanism left at all — the new page only handles one direction |
| `_rl_rl_resource_*`, `_rl_partner_resource_*`, `_rl_one_pager_pdf_url`, `_rl_agreement_pdf_url` | **No** | The entire right-sidebar "Partnership Resources" block (Drive/Notion links, one-pager, signed agreement PDF) is gone — no right sidebar exists at all in the new template |
| `_rl_enable_comarketing` toggle | **No** | Co-marketing tab is now unconditionally shown for every partner (legacy made it optional per-partner) |
| `_rl_comarketing_text`, `_rl_comarketing_approval_note` | **No** | Replaced with generic hardcoded copy; the "mutual approval required" legal notice is gone |
| `_rl_override_welcome_text`, `_rl_override_company_desc`, `_rl_override_referral_rules`, `_rl_override_commission_terms` | **No** | The entire override mechanism (per-partner customization with global fallback) is gone |
| `_rl_section_attachments` (per-tab PDF attachments) | **No** | Entire feature gone — no way to attach a partner-specific PDF to any tab |

## Structured content gap: `RL_Global_Data` has no equivalent

The legacy `class-rl-global-data.php` provided the *default* content that populates each tab when a partner doesn't override it: 5 core values, 7 "why choose RL" points, an 8-row comparison matrix (RL vs. in-house vs. agency), 16 target industries, 5 geographic markets, **10 detailed services**, a **6-stage referral lifecycle stepper**, 6 default referral rules, co-marketing opportunities, 3 email templates (unused in template, but available), **6 detailed case studies** (challenge/solution/outcome), and **14 partner FAQs**.

None of this was ported to a corresponding class. The new template instead hardcodes different, LatAm-nearshore-specific copy (2 case studies, 3 FAQs, 4 services) directly in the blade file — same tab *shape*, different and much thinner *content*, and with no per-partner override path since the override fields aren't read.

## What is genuinely equivalent or improved

- CPT registration, rewrite rules (`/partners/{slug}/{tab}/`), and query var — ported faithfully (`PartnerPostType.php` mirrors `RL_Partner_CPT` almost line-for-line).
- Tab routing logic and the 9 tab keys/labels — preserved.
- The Notion-synced directory grid (archive page) is new, additive, and functional — not present in the legacy plugin at all.
- `HandleLeadBookingCompletedForPartner` (ADR-0008's `PartnerHub` subscriber to `LeadBookingCompleted`, matching by `sourceType`/`sourceID`) is real, new architecture the legacy plugin never had — this is genuine forward progress on attribution, separate from the co-branded hub content itself.

## Bottom line

The **directory/marketplace** half of PartnerHub is solid and net-new. The **co-branded single-partner hub** half — which is what the legacy "Partnership Hub" plugin actually was — has kept the page's shape (URL structure, 9 tabs, sidebar nav) but lost:
1. Every admin authoring tool (metaboxes, JSON import/export, media uploader, admin list columns) — there is currently no way for a non-developer to configure a new partner.
2. The bidirectional referral model (Direction B: RL → Partner) entirely.
3. All dedicated resource/PDF download functionality (both the sidebar resource links and per-tab attachments).
4. Per-partner content overrides and the co-marketing toggle.
5. The rich default global content library (`RL_Global_Data`) that made each tab useful even before customization.

If Oyster (or any other real bidirectional partner) needs to go live on this new hub as-is, it would show generic nearshore-staffing marketing copy instead of Oyster's actual EOR/payroll referral terms, with no admin way to fix that without a developer.
