# Jira Backlog: ADR Compliance & Production Launch Tasks

This document contains production-ready Jira tickets for all remaining engineering tasks required to achieve 100% compliance with Architecture Decision Records (**ADR-0001 through ADR-0008**) for the Remote Leverage website modernization.

---

## Epic Overview
* **Epic Name**: Remote Leverage Platform Modernization & ADR Compliance
* **Jira Project**: [Wordpress RL (WR)](https://remoteleveragetech.atlassian.net/jira/software/projects/WR/boards/3)
* **Target Release**: Sprint 3 Production Cutover
* **Total Estimated Points**: 26 Story Points

---

## Ticket [WR-98](https://remoteleveragetech.atlassian.net/browse/WR-98): [Lead Domain] Scheduled `LeadAbandoned` Lifecycle Event Processor & Activity Logging

* **Issue Type**: Story
* **Priority**: High
* **ADR Reference**: [ADR-0008 (§ Domain Events & Traceability)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0008-lead-bounded-context-retire-gravity-forms.md)
* **Component**: `app/Domains/Lead`
* **Estimate**: 3 Story Points

### Description
ADR-0008 specifies four core lifecycle events for the `Lead` bounded context: `LeadCreated`, `LeadAbandoned`, `LeadBookingCompleted`, and `LeadBookingCanceled`. While `LeadCreated` fires on form submission and `LeadBookingCompleted` fires upon Calendly confirmation, `LeadAbandoned` currently has no automated trigger mechanism.

We need a scheduled Artisan command and WP-Cron task that queries prospective leads that were captured/partially submitted (Step 1) but never completed Step 3 booking within an expiration window (default 2 hours, configurable up to 24 hours), transitions their status to `abandoned`, dispatches the `LeadAbandoned` event, and writes dual dispatch/consumption logs to `LeadActivityLog`.

### Acceptance Criteria
```gherkin
Scenario: Lead captured but booking not completed within timeout window
  Given a lead exists with status "captured" or "partial"
  And the lead's created_at timestamp is older than 2 hours ago
  And no LeadBookingCompleted event exists in LeadActivityLog for this lead
  When the "lead:process-abandoned" command executes
  Then the lead status is updated to "abandoned"
  And the LeadAbandoned event is dispatched
  And a dispatch record is written to LeadActivityLog with eventType "LeadAbandoned"
  And any registered listener logs consumption outcome in LeadActivityLog
```

### Technical Implementation Details
* **New Command**: [ProcessAbandonedLeadsCommand.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Domains/Lead/Commands/ProcessAbandonedLeadsCommand.php)
  * Signature: `lead:process-abandoned {--hours=2 : Abandonment timeout threshold in hours}`
* **Schedule**: Register in `app/Console/Kernel.php` or `Acorn` scheduler to run hourly: `$schedule->command('lead:process-abandoned')->hourly();`.
* **Action**: Create or extend [CaptureLeadAction.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Domains/Lead/Actions/CaptureLeadAction.php) or create `MarkLeadAbandonedAction`.
* **Logging**: Ensure `LeadActivityLogger` records both dispatch and listener consumption.

### Verification Plan
* Add Pest unit test in `tests/Unit/LeadDomainTest.php` simulating an expired lead and asserting event dispatch and log insertion.

---

## Ticket [WR-99](https://remoteleveragetech.atlassian.net/browse/WR-99): [Tracking Domain] Centralize GTM, LinkedIn Insight, and Meta Pixel Scripts in `TrackingHooks`

* **Issue Type**: Task
* **Priority**: High
* **ADR Reference**: [ADR-0004 (§ Tracking)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0004-ddd-bounded-contexts.md) & [ADR-0006 (§ Risk Mitigation)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0006-parallel-staging-zero-downtime-cutover.md)
* **Component**: `app/Infrastructure/WordPress/Hooks` & `app/Domains/Tracking`
* **Estimate**: 2 Story Points

### Description
Per `README.md` §7 and ADR-0006, all tracking and telemetry pixels must be centrally managed in `TrackingServiceProvider` and `TrackingHooks`. Currently, `TrackingHooks.php` only injects PostHog and Customer.io snippets. 

We must implement injection for:
1. **Google Tag Manager (GTM)**: `<head>` container script + `wp_body_open` `<iframe>` fallback.
2. **LinkedIn Insight Tag**: Partner ID `6411876` with tracking image noscript.
3. **Meta Pixel**: Primary pixel ID script with `PageView` automatic tracking.
All scripts must read their IDs from `.env` via `config/services.php` and remain completely dormant if the respective configuration key is empty (development/test environments).

### Acceptance Criteria
```gherkin
Scenario: Production environment with tracking IDs configured
  Given GTM_CONTAINER_ID, LINKEDIN_PARTNER_ID, and META_PIXEL_ID are set in config
  When a public frontend page is rendered
  Then the GTM script is injected into <head> at priority 1
  And the GTM noscript iframe is injected into wp_body_open
  And the LinkedIn Insight script with Partner ID "6411876" is injected
  And the Meta Pixel script is injected with PageView initialization
  And all scripts are excluded on wp-login.php and wp-admin screens

Scenario: Development environment without tracking IDs
  Given tracking IDs are empty or unset in .env
  When a public frontend page is rendered
  Then no raw or broken tracking script blocks are output
```

### Technical Implementation Details
* **Modify**: [TrackingHooks.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Infrastructure/WordPress/Hooks/TrackingHooks.php)
* **Modify**: `config/services.php` to register `gtm`, `linkedin`, and `meta_pixel` keys.
* **Environment Variables**: `GTM_CONTAINER_ID`, `LINKEDIN_PARTNER_ID=6411876`, `META_PIXEL_ID`.

### Verification Plan
* Add unit test in `tests/Unit/TrackingDomainTest.php` asserting rendered HTML output when config is populated vs. empty.

---

## Ticket [WR-100](https://remoteleveragetech.atlassian.net/browse/WR-100): [Content Migration] Production Elementor Content Ingestion & Batch Conversion Pipeline

* **Issue Type**: Story / Migration
* **Priority**: Blocker
* **ADR Reference**: [ADR-0005 Amendment (AI-Assisted Elementor Audit & Migration)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0005-native-gutenberg-blocks-acf-composer.md)
* **Component**: `app/Domains/ContentAudit`
* **Estimate**: 5 Story Points

### Description
The ADR-0005 Amendment establishes a mandatory completion gate: Elementor and Elementor Pro must be completely retired with **zero posts carrying `_elementor_data`**. 

While `ElementorAuditService`, `ConvertElementorPostAction`, and `AuditElementorCommand` have been developed and pass unit tests, the local database currently only has clean sample posts. We must:
1. Ingest the production MySQL database dump (`wp_posts`, `wp_postmeta`, `wp_terms`).
2. Execute `wp acorn content:audit-elementor` to catalog all legacy widgets across the 100+ VA Guides and landing pages.
3. Run batch conversion using `ConvertElementorPostAction` to transform `_elementor_data` JSON trees into clean Gutenberg block markup.
4. Flag unmapped widgets and verify the completion gate.

### Acceptance Criteria
```gherkin
Scenario: Ingestion and conversion of legacy Elementor posts
  Given production posts and postmeta containing _elementor_data are loaded into staging
  When "wp acorn content:audit-elementor" is executed
  Then a structured table lists total posts, active Elementor dependencies, and unmapped widgets
  When the batch converter runs
  Then converted posts have their post_content replaced with valid native Gutenberg blocks
  And all converted posts are stamped with meta "_rl_conversion_status = needs_review"
  And zero posts rely on Elementor runtime filters
```

### Technical Implementation Details
* **New Command**: `wp acorn content:convert-elementor {--dry-run} {--post_id=}`
* **Service Integration**: Call [ConvertElementorPostAction.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Domains/ContentAudit/Actions/ConvertElementorPostAction.php) across all records in `wp_postmeta WHERE meta_key = '_elementor_data'`.

### Verification Plan
* Execute dry run on staging database, inspect generated Gutenberg block comments, and verify with `wp post list`.

---

## Ticket [WR-101](https://remoteleveragetech.atlassian.net/browse/WR-101): [WP Admin] Editorial Review Queue for AI-Converted Gutenberg Posts

* **Issue Type**: Story
* **Priority**: High
* **ADR Reference**: [ADR-0005 Amendment (§ Mandatory Human Editorial Review)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0005-native-gutenberg-blocks-acf-composer.md)
* **Component**: `app/Infrastructure/WordPress/Admin`
* **Estimate**: 3 Story Points

### Description
ADR-0005 Amendment explicitly mandates: *"Every AI-converted post — mapped and unmapped alike, with no confidence-based sampling — is queued for human sign-off before it is allowed to go live. No AI-converted post publishes unreviewed."*

We need a dedicated Editorial Review Queue in WP Admin allowing content managers to:
1. Filter posts by `Needs Review`, `Unmapped Widgets Detected`, and `Approved`.
2. Inspect side-by-side or block preview of the converted post.
3. Provide a 1-click **"Approve & Mark Clean"** action that updates `_rl_conversion_status` to `approved` and archives the old `_elementor_data` meta key.

### Acceptance Criteria
```gherkin
Scenario: Content editor reviews an AI-converted post
  Given a post was converted from Elementor and has "_rl_conversion_status = needs_review"
  When the editor visits the WordPress Posts list screen
  Then a quick-filter tab "Needs Editorial Review" is available
  And an indicator badge displays whether all widgets were mapped or unmapped
  When the editor approves the post in Gutenberg editor
  Then "_rl_conversion_status" is set to "approved"
  And the post is eligible for production publishing
```

### Technical Implementation Details
* **Add Meta Filter**: Hook into `manage_posts_columns` and `restrict_manage_posts` in `WordPressAdminTheme.php` or a dedicated `ContentAuditAdmin` class.
* **Review Meta Box**: Add an ACF or Gutenberg sidebar meta box for editorial sign-off.

---

## Ticket [WR-102](https://remoteleveragetech.atlassian.net/browse/WR-102): [Lead Domain] WP Admin Configurable Form & Routing Settings Screen

* **Issue Type**: Story
* **Priority**: Medium
* **ADR Reference**: [ADR-0008 (§ Form Configuration)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0008-lead-bounded-context-retire-gravity-forms.md)
* **Component**: `app/Infrastructure/WordPress/Admin` & `app/Domains/Lead`
* **Estimate**: 3 Story Points

### Description
ADR-0008 requires an admin-configurable form system to replace Gravity Forms' settings. While the high-conversion Livewire wizard provides core fields, administrators need a settings interface in WP Admin to:
1. Configure notification recipient emails for new lead captures.
2. Toggle optional form fields (e.g., Company, Notes, Phone Country Dropdown).
3. Set the lead retention purge threshold (enforcing a hard floor of at least 30 days).
4. Configure HubSpot and Slack webhook overrides.

### Acceptance Criteria
```gherkin
Scenario: Admin configures lead retention and notification settings
  Given an administrator with "manage_options" capability
  When navigating to "Leads -> Settings" in WP Admin
  Then a settings form allows updating lead retention days, notification emails, and field visibility
  And attempting to set retention to less than 30 days is rejected with a validation error
  And saving settings persists values to WordPress options with sanitization
```

### Technical Implementation Details
* **New Submenu**: Register `rl-leads-settings` in [LeadsAdminDashboard.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Infrastructure/WordPress/Admin/LeadsAdminDashboard.php).
* **Option Storage**: Persist settings under option key `rl_lead_settings`.
* **Validation**: Reject values `< 30` for `retention_days` per ADR-0008 compliance.

---

## Ticket [WR-103](https://remoteleveragetech.atlassian.net/browse/WR-103): [SEO & Infrastructure] Legacy URL 301 Redirect Mapping & Visual Regression Suite

* **Issue Type**: Task
* **Priority**: High
* **ADR Reference**: [ADR-0006 (§ SEO & Risk Mitigation)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0006-parallel-staging-zero-downtime-cutover.md)
* **Component**: `app/Application/Http` & DevOps
* **Estimate**: 3 Story Points

### Description
To prevent organic traffic loss, broken backlinks, or 404 errors during DNS cutover, ADR-0006 requires:
1. An explicit 301 redirect map for legacy URLs, query string parameters, and retired testing landing pages.
2. Automated visual regression testing comparing key pages on staging (`remoteleverage-v2.test`) against production (`remoteleverage.com`).

### Acceptance Criteria
```gherkin
Scenario: Visitor accesses a retired landing page URL
  When a visitor requests a legacy URL defined in the redirect map (e.g. "/hire-va-old/")
  Then a 301 Permanent Redirect is returned pointing to the modern canonical URL ("/hire-va-4/")
  And referral cookies and UTM parameters are preserved through the redirect

Scenario: Visual regression test suite execution
  When visual regression tests run across Homepage, VA Guides, and Booking funnels
  Then visual diff score is below 0.5% threshold against design tokens
```

### Technical Implementation Details
* **Middleware**: Create `LegacyRedirectMiddleware.php` in `app/Application/Http/Middleware/` or configure Nginx rewrite rules.
* **Test Tooling**: Add Playwright visual regression test script comparing staging against production baseline screenshots.

---

## Ticket [WR-104](https://remoteleveragetech.atlassian.net/browse/WR-104): [Livewire] Real-Time Consultant Availability Polling for `InstantLiveCallButton`

* **Issue Type**: Improvement
* **Priority**: Medium
* **ADR Reference**: [ADR-0003 (§ InstantLiveCallButton)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0003-livewire-4-reactive-ux.md) & [ADR-0004 (§ Scheduling)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0004-ddd-bounded-contexts.md)
* **Component**: `app/Application/Livewire/Scheduling` & `app/Domains/Scheduling`
* **Estimate**: 2 Story Points

### Description
The `InstantLiveCallButton` component currently renders a static or simulated availability status. Per ADR-0003 and `README.md` §4, the component should poll real-time consultant availability (e.g., querying Google Calendar / Meet availability via `LiveCallAvailabilityRouter`) and update the CTA button dynamically (e.g., "3 Consultants Online Now" vs. "Leave a Video Message" if after business hours).

### Acceptance Criteria
```gherkin
Scenario: Consultants are available during business hours
  Given current time is within business hours and consultant calendar has free slots
  When the visitor views the InstantLiveCallButton component
  Then the button displays green status with count of available consultants
  And clicking routes immediately to the live call room

Scenario: No consultants available / outside business hours
  Given no consultants are online
  When the component polls availability
  Then the status indicator transitions to offline/calendar fallback
  And clicking smoothly scrolls to or opens the MultistepBookingWizard
```

### Technical Implementation Details
* **Modify**: [InstantLiveCallButton.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Application/Livewire/Scheduling/InstantLiveCallButton.php)
* **Service**: Connect to [LiveCallAvailabilityRouter.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Domains/Scheduling/Services/LiveCallAvailabilityRouter.php) with a 60-second transient cache.

---

## Ticket [WR-105](https://remoteleveragetech.atlassian.net/browse/WR-105): [CI/CD & DevOps] GitHub Actions Automated CI Pipeline for Pint, Pest & Asset Builds

* **Issue Type**: Task
* **Priority**: Medium
* **ADR Reference**: [ADR-0002 (§ Laravel Pint & PHPUnit/Pest)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0002-roots-bedrock-sage-acorn-stack.md) & [ADR-0007](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0007-three-week-parallelized-sprint-roadmap.md)
* **Component**: DevOps / Root
* **Estimate**: 2 Story Points

### Description
ADR-0002 mandates automated linting and unit testing to replace legacy unversioned deployments. We need a GitHub Actions CI workflow that runs on every pull request and push to `main` to ensure code quality and build integrity.

### Acceptance Criteria
```gherkin
Scenario: Pull request opened or updated
  When a developer opens a pull request
  Then GitHub Actions triggers the "CI / Test & Build" workflow
  And Laravel Pint checks code formatting with 0 syntax or style violations
  And Pest test suite executes all 72+ unit and feature tests with 100% pass rate
  And Vite compiles production frontend assets ("npm run build") with zero errors
```

### Technical Implementation Details
* **New File**: `.github/workflows/ci.yml`
* **Steps**:
  1. Setup PHP 8.3 with sqlite, mbstring, dom, curl extensions.
  2. Setup Node.js 20.
  3. `composer install --no-interaction --prefer-dist`
  4. `./vendor/bin/pint --test`
  5. `./vendor/bin/pest`
  6. `npm ci && npm run build`

---

## Ticket [WR-106](https://remoteleveragetech.atlassian.net/browse/WR-106): [DevOps] Production Queue Worker Provisioning & Failure Monitoring

* **Issue Type**: Task / Infra
* **Priority**: High
* **ADR Reference**: [ADR-0008 (§ Infrastructure & Queue Transports)](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/doc/adr/0008-lead-bounded-context-retire-gravity-forms.md)
* **Component**: Infrastructure / DevOps
* **Estimate**: 3 Story Points

### Description
ADR-0008 highlights a critical architectural constraint: if any cross-domain listener uses deferred queue transports (`ShouldQueue`), a persistent worker process (`wp acorn queue:work`) must run continuously in staging and production. 

We must:
1. Provide Supervisor / systemd service configuration for `wp acorn queue:work`.
2. Configure automated retry, timeout, and max-memory limits.
3. Wire queue failures into Sentry / error monitoring so stalled workers or dropped lifecycle events alert the engineering team immediately.

### Acceptance Criteria
```gherkin
Scenario: Server restart or process crash
  Given the server restarts or the queue worker process encounters an unexpected exit
  Then Supervisor / systemd automatically restarts "wp acorn queue:work" within 5 seconds
  And failed jobs are written to the database failed_jobs table
  And any unhandled job exception is dispatched to Sentry error monitoring
```

### Technical Implementation Details
* **Supervisor Configuration**: `deployment/supervisor/acorn-worker.conf`
* **Command**: `php /var/www/remoteleverage/current/web/wp-content/themes/remote-leverage/artisan queue:work --sleep=3 --tries=3 --max-time=3600`

---

## Sprint Planning & Execution Summary

| Key | Title | Priority | Est | Assignee | Direct Link |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **WR-98** | Scheduled `LeadAbandoned` Event Processor | High | 3 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-98) |
| **WR-99** | Centralize GTM, LinkedIn Insight & Meta Pixel in `TrackingHooks` | High | 2 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-99) |
| **WR-100** | Production Elementor Content Ingestion & Batch Converter | Highest | 5 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-100) |
| **WR-101** | Editorial Review Queue for AI-Converted Posts | High | 3 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-101) |
| **WR-102** | WP Admin Configurable Form & Routing Settings | Medium | 3 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-102) |
| **WR-103** | Legacy URL 301 Redirect Mapping & Visual Regression Suite | High | 3 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-103) |
| **WR-104** | Real-Time Availability Polling for `InstantLiveCallButton` | Medium | 2 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-104) |
| **WR-105** | GitHub Actions Automated CI Pipeline | Medium | 2 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-105) |
| **WR-106** | Production Queue Worker Provisioning & Failure Monitoring | High | 3 pts | Adrián Salvatori | [View on Jira](https://remoteleveragetech.atlassian.net/browse/WR-106) |
| **Total** | | | **26 pts** | | |

---

## Parent Task [WR-93](https://remoteleveragetech.atlassian.net/browse/WR-93): Gutenberg Block/Pattern Library
* **Status**: En curso (In Progress)
* **Sprint**: WORDPRES Sprint 6
* **Assignee**: Adrián Salvatori

### Subtasks for Remaining Gutenberg Scope:
*(Note: Hero block subtask WR-108 removed per design decision; the primary Hero section is fully completed via native Gutenberg pattern `remote-leverage/hire-va-4-hero`).*

| Key | Summary | Type | Priority | Assignee | Direct Link |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **WR-109** | **[Block Editor WYSIWYG] Gutenberg Canvas Styling Parity & editor.css Tailwind Alignment** | Subtarea | High | Adrián Salvatori | [View WR-109](https://remoteleveragetech.atlassian.net/browse/WR-109) |
| **WR-110** | **[Block Inserter Previews] Add Rich Example Data to ACF Composer Blocks** | Subtarea | Medium | Adrián Salvatori | [View WR-110](https://remoteleveragetech.atlassian.net/browse/WR-110) |
| **WR-111** | **[Pattern Library] Register Curated Gutenberg Pattern Categories & VA Guide Layouts** | Subtarea | High | Adrián Salvatori | [View WR-111](https://remoteleveragetech.atlassian.net/browse/WR-111) |
| **WR-112** | **[Landing Page Patterns] Modernize Secondary Landing Page Templates into Reusable Block Patterns** | Subtarea | Medium | Adrián Salvatori | [View WR-112](https://remoteleveragetech.atlassian.net/browse/WR-112) |
| **WR-113** | **[Block QA] Cross-Browser & Mobile Viewport Audit of Native Blocks** | Subtarea | High | Adrián Salvatori | [View WR-113](https://remoteleveragetech.atlassian.net/browse/WR-113) |


