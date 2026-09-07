# Remote Leverage Modernization Plan & Execution Tracker

Phased execution roadmap for the **Remote Leverage Website Modernization** project based on the [Architecture Proposal & Strategic Roadmap](https://github.com/Remote-Leverage/remote-leverage-website-modernization).

**Current Status**: Sprint 1 in progress (Foundation & Core Domain Architecture complete).

---

## Progress Overview

- **Phase 1: Foundation & Infrastructure**: `100% Completed`
- **Phase 2: Core Domain Architecture (DDD)**: `100% Completed`
- **Phase 3: Design Tokens & Tailwind CSS v4**: `0% Completed`
- **Phase 4: Livewire 4 Reactive UI Components**: `100% Completed`
- **Phase 5: Native Gutenberg Blocks Library**: `100% Completed (9 core blocks active; HeroBlock excluded per user instruction)`
- **Phase 6: Dynamic Blade Templates & Layouts**: `0% Completed`
- **Phase 7: Testing, QA & End-to-End Tracking**: `0% Completed`
- **Phase 8: Staged Cutover & Production Launch**: `0% Completed`

---

## Phase 1: Greenfield Foundation & Infrastructure (Sprint 1)

- [x] **Repository Architecture Setup**
  - [x] Unify Bedrock root and Sage 11 theme into a single atomic monorepo.
  - [x] Connect git remote to `Remote-Leverage/remote-leverage-website-modernization`.
  - [x] Preserve master Architecture Proposal & Roadmap as repository root `README.md`.
  - [x] Configure root and theme `.gitignore` rules (ignore `vendor/`, `node_modules/`, `web/wp/`, `.env`, `.DS_Store`).
  - [x] Push initial scaffolding to `origin/main`.
- [x] **Theme Customization & Metadata**
  - [x] Update `style.css` with Remote Leverage enterprise theme metadata, URI, author, and version `2.1.0`.
  - [x] Standardize `screenshot.png` to WordPress-compliant 4:3 aspect ratio (`1200x900`), perfectly centered.
- [x] **Configuration Layer**
  - [x] Create `config/services.php` with Stripe, Calendly, Google Calendar, Customer.io, PostHog, and Notion schemas.
  - [x] Create `config/post-types.php` for `rl_partner` custom post type settings.
  - [x] Register master `DomainServiceProvider` inside `app/Providers/ThemeServiceProvider.php`.
- [x] **Package Dependencies Installation**
  - [x] Install `roots/acorn` dependencies & verify Acorn boot sequence.
  - [x] Install `livewire/livewire` (`v4.4.3`) for reactive frontend state management.
  - [x] Install `log1x/acf-composer` (`v3.4.7`) for code-driven native Gutenberg blocks.

---

## Phase 2: Core Domain Architecture (DDD) (Sprint 1)

### Referral Domain (`app/Domains/Referral`)
- [x] **Data Transfer Objects (DTOs)**
  - [x] `PartnerData.php`: Immutable typed partner representation.
  - [x] `ReferralData.php`: Click and lead attribution data payload.
  - [x] `PayoutData.php`: Payout amount, currency, and transfer records.
- [x] **Database Migrations**
  - [x] `2026_09_01_000001_create_referral_clicks_table.php` (`rl_referral_clicks`).
  - [x] `2026_09_01_000002_create_referrals_table.php` (`rl_referrals`).
  - [x] `2026_09_01_000003_create_referral_rewards_table.php` (`rl_referral_rewards`).
- [x] **Eloquent Models & Repositories**
  - [x] `ReferralClick.php`: Click analytics tracking entity.
  - [x] `Referral.php`: Form submission lead tracking entity.
  - [x] `ReferralReward.php`: Partner reward and commission records.
  - [x] `PartnerRepositoryInterface.php` & `EloquentPartnerRepository.php`.
- [x] **Services & Gateways**
  - [x] `AttributionEngine.php`: Handles `rl_referrer` cookie, `via`, `ref`, `r` parameters, and 24h IP deduplication.
  - [x] `StripeConnectGateway.php`: Express onboarding link generation and transfer payouts.
- [x] **Actions, Events & Listeners**
  - [x] `TrackReferralClickAction.php`: 24-hour deduplicated click tracker.
  - [x] `RegisterPartnerAction.php`: Partner onboarding and code generator.
  - [x] `ProcessPayoutAction.php`: Payout creation and Stripe transfer dispatch.
  - [x] `ReferralRecorded`, `PartnerRegistered`, `PayoutCompleted` events.
  - [x] `SendPartnerWelcomeEmail`, `DispatchReferralWebhook` listeners.
- [x] **HTTP Middleware & Providers**
  - [x] `ReferralAttributionMiddleware.php`: Request interceptor for query params and cookies.
  - [x] `ReferralServiceProvider.php`: Container bindings and event listeners.

### Scheduling Domain (`app/Domains/Scheduling`)
- [x] **Data Transfer Objects (DTOs)**
  - [x] `TimeSlotData.php`: Timezone-aware slot representation.
  - [x] `BookingRequestData.php`: Lead appointment request details.
- [x] **Database Migrations & Models**
  - [x] `2026_09_01_000004_create_live_call_sessions_table.php` (`rl_live_call_sessions`).
  - [x] `LiveCallSession.php`: Eloquent model for live consultation sessions.
- [x] **Services & Gateways**
  - [x] `CalendlyClient.php`: Invitee creation via `api.calendly.com/invitees`, availability slots, and event details.
  - [x] `GoogleCalendarClient.php`: OAuth2 token refresh and Google Meet conference generation.
  - [x] `LiveCallAvailabilityRouter.php`: Real-time consultant availability state manager.
- [x] **Actions & Providers**
  - [x] `FetchAvailableSlotsAction.php`: Aggregates real-time availability slots.
  - [x] `BookMeetingAction.php`: Multi-provider booking (Calendly + Google Calendar).
  - [x] `RouteInstantCallAction.php`: Instant Google Meet session router and logger.
  - [x] `SchedulingServiceProvider.php`: Singleton container bindings.

### Tracking Domain (`app/Domains/Tracking`)
- [x] **Data Transfer Objects (DTOs)**
  - [x] `UserProfileData.php`: Customer profile attributes.
  - [x] `AnalyticsEventData.php`: Structured behavioral events.
- [x] **Gateways & Subscribers**
  - [x] `CustomerIOClient.php`: Identify and Track API gateway.
  - [x] `PostHogClient.php`: Capture API and feature flag evaluation gateway.
  - [x] `HandleLeadCreatedForTracking.php`: Subscribes to `LeadCreated` lifecycle event, identifying profile and logging dual-write to `LeadActivityLog` (Gravity Forms retired per ADR-0008).
- [x] **Actions & WordPress Adapters**
  - [x] `RecordBehaviorEventAction.php`: Dual-dispatch to PostHog and Customer.io.
  - [x] `EvaluateVariantAction.php`: PostHog feature flag variant resolver.
  - [x] `TrackingHooks.php`: PostHog head snippet and Customer.io footer script injection.
  - [x] `TrackingServiceProvider.php`: Adapter bootstrap.

### Lead Domain (`app/Domains/Lead`) *(Added per ADR-0008)*
- [x] **Data Transfer Objects (DTOs) & Models**
  - [x] `LeadCaptureData.php`: Immutable lead submission input payload.
  - [x] `Lead.php`: Canonical prospective contact entity with typed source attribution.
  - [x] `LeadActivityLog.php`: Append-only audit trail logging dispatches and consumption.
- [x] **Database Migrations**
  - [x] `2026_09_07_000001_create_leads_table.php` (`rl_leads`).
  - [x] `2026_09_07_000002_create_lead_activity_logs_table.php` (`rl_lead_activity_logs`).
- [x] **Lifecycle Events & Services**
  - [x] `LeadFormSubmitted.php`: Pre-persistence extensibility hook.
  - [x] `LeadCreated.php`, `LeadAbandoned.php`, `LeadBookingCompleted.php`, `LeadBookingCanceled.php`.
  - [x] `PhoneValidationService.php`: International phone validation and E.164 parsing via `giggsey/libphonenumber-for-php`.
  - [x] `HubSpotGateway.php`: Direct CRM contact synchronization replacing legacy `GF_HubSpot` add-on.
  - [x] `LeadActivityLogger.php`: Dual-logging helper enforcing dispatch and consumption audits.
- [x] **Actions, Commands & Provider**
  - [x] `CaptureLeadAction.php`: Validates, stamps attribution via `AttributionEngine`, persists `Lead`, and dispatches lifecycle events.
  - [x] `PurgeOldLeadsAction.php`: Enforces 30-day minimum retention floor.
  - [x] `PurgeLeadsCommand.php`: Artisan/WP-CLI command `wp acorn lead:purge`.
  - [x] `LeadServiceProvider.php`: Container bindings, command registration, and event listeners.

### PartnerHub Domain (`app/Domains/PartnerHub`)
- [x] **Models & Services**
  - [x] `PartnerProfile.php`: Partner listing model.
  - [x] `NotionSyncService.php`: Notion API integration for partner database.
- [x] **Actions & Listeners**
  - [x] `SyncNotionPartnersAction.php`: 1-hour cached Notion database synchronization.
  - [x] `QueryPartnersAction.php`: Multi-parameter search and category filter.
  - [x] `PartnerPostType.php`: Registers `rl_partner` CPT with `^partners/([^/]+)/([^/]+)/?$` rewrite rule (`rl_tab`).
  - [x] `HandleLeadBookingCompletedForPartner.php`: Subscribes to `LeadBookingCompleted` and attributes booking to partner.

### ContentAudit Domain (`app/Domains/ContentAudit`)
- [x] **Services & Actions**
  - [x] `PrismAiAuditor.php`: Content length, headings, and keyword density analyzer.
  - [x] `AuditMarkdownContentAction.php`: Guide audit execution.
  - [x] `GenerateSignatureHtmlAction.php`: Standardized HTML email signature generator matching `rl-social-kit` (`sig-1-light`).
  - [x] `ElementorAuditService.php`: Inspects `_elementor_data` AST and maps widgets against Gutenberg matrix (ADR-0005 Amendment).
  - [x] `ConvertElementorPostAction.php`: Converts Elementor widget trees to native ACF block markup and queues for human editorial review.
  - [x] `AuditElementorCommand.php`: CLI command `wp acorn content:audit-elementor` for audit and conversion reporting.
  - [x] `ContentAuditServiceProvider.php`: Provider registration.

---

## Phase 3: Tailwind CSS v4 Design Tokens & Blade Shell (Sprint 1)

- [x] **Tailwind CSS v4 Configuration**
  - [x] Configure `resources/css/app.css` using modern `@theme` token syntax.
  - [x] Define Remote Leverage brand palette extracted from live pages:
    - Primary Brand Violet/Purple (`#8A2BE2`, `#6200A4`)
    - Deep Midnight & Hero (`#13132F`, `#342567`, `#18112C`, `#250D4A`)
    - High-Conversion Magenta & Accent Orange (`#F90066`, `#FB7501`, `#F97316`)
    - Neutrals & Backgrounds (`#F4F6FC`, `#FFFFFF`, `#070707`, `#E2E8F0`, `#878EA0`)
    - Semantic Status Colors (`#00D67D`, `#F5C700`, `#D94900`)
  - [x] Define typography scale & import fonts (Inter, League Spartan, Poppins).
  - [x] Configure utility classes (glassmorphism cards, glowing status live dot, pill buttons, marquee animations).
- [x] **Blade Layout Shell**
  - [x] Modernize `resources/views/layouts/app.blade.php` with Tailwind classes, smooth scroll, and Livewire tags.
  - [x] Build global responsive navigation header (`resources/views/sections/header.blade.php`) with logo, roles dropdown, live status indicator, and mobile menu.
  - [x] Build global footer (`resources/views/sections/footer.blade.php`) with specialties, resources, trust badges, and compliance links.
  - [x] Match block editor typography in `resources/css/editor.css`.

---

## Phase 4: Livewire 4 Reactive UI Components (Sprint 2)

- [x] **Booking Funnel**
  - [x] `MultistepBookingWizard.php`: 3-step qualification and slot selection wizard.
  - [x] `multistep-booking-wizard.blade.php`: Live slot picker with timezone switching and instant validation.
  - [x] `InstantLiveCallButton.php`: Reactive consultant presence indicator ("Online Now").
  - [x] `instant-live-call-button.blade.php`: 1-click Google Meet routing button.
- [x] **Partner Network & Affiliate Portal**
  - [x] `PartnerPortalDashboard.php`: Partner dashboard for referral links, clicks, and earnings.
  - [x] `partner-portal-dashboard.blade.php`: Reactive dashboard UI.
  - [x] `PartnerRegistrationForm.php`: 1-step partner application form.
  - [x] `PartnerDirectoryGrid.php`: Zero-reload search and category filter for strategic partners.
  - [x] `partner-directory-grid.blade.php`: Responsive cards grid.
- [x] **Interactive Utilities & Blog Filtering**
  - [x] `EmailSignatureGenerator.php`: Live preview and clipboard copy tool.
  - [x] `email-signature-generator.blade.php`: Interactive generator UI.
  - [x] `GuideIndexFilter.php`: Instant taxonomy filter for VA guides.

---

## Phase 5: Native Gutenberg Blocks Library (Sprint 2)

*Consolidating 36 bespoke Elementor widgets into ~10 lightweight native Gutenberg blocks via `log1x/acf-composer`.*

- [x] **`BookingBlock`** (`booking.blade.php`)
  - Replaces: `HeadlessCalendlyMultistepWidget`, `GoogleCalendarMultistepWidget`, `IsolatedFieldsHeadlessCalendlyMultistepWidget`.
  - Features: Embeds `MultistepBookingWizard` without iframes.
- [x] **`LiveCallBlock`** (`live-call.blade.php`)
  - Replaces: `JoinLiveCallWidget`.
  - Features: Embeds `InstantLiveCallButton` with real-time presence polling.
- [x] **`TestimonialsBlock`** (`testimonials.blade.php`)
  - Replaces: `TestimonialCardWidget`, `TestimonialListWidget`, `TrustSectionWidget`.
  - Features: CSS scroll-snap carousel, Alpine.js video lightbox modal.
- [x] **`DepartmentCardsBlock`** (`department-cards.blade.php`)
  - Replaces: `DepartmentCardWidget`, `ContractorCardWidget`, `GlassCardWidget`.
  - Features: Glassmorphism Tailwind v4 cards, CSS micro-interactions.
- [x] **`ProcessStepsBlock`** (`process-steps.blade.php`)
  - Replaces: `ProcessStepsWidget`, `HiringProcessWidget`.
  - Features: SVG stepper timeline with responsive collapse.
- [x] **`BenefitsGuaranteeBlock`** (`benefits-guarantee.blade.php`)
  - Replaces: `BenefitsSectionWidget`, `GuaranteeSectionWidget`.
  - Features: Inline SVG badges and satisfaction guarantee callouts.
- [x] **`AccordionFaqBlock`** (`accordion-faq.blade.php`)
  - Replaces: `ArticleFAQAccordionWidget`.
  - Features: Semantic `<details>` / `<summary>` tags with automated Schema.org FAQ structured data.
- [x] **`DataTableBlock`** (`data-table.blade.php`)
  - Replaces: `ArticleDataTableWidget`.
  - Features: Mobile-responsive sticky table comparison.
- [x] **`CtaBannerBlock`** (`cta-banner.blade.php`)
  - Replaces: `ContactCTAWidget`, `ArticleLeadFormWidget`.
  - Features: Native lead capture link to booking wizard, ambient glowing brand gradients, and zero legacy plugin dependency.

---

## Phase 6: Dynamic Blade Templates & Layouts (Sprint 2)

- [x] **Dynamic VA Guide Template (`single.blade.php`)**
  - *Deferred per user directive: "Ignore the 100+va guides. No need for that just yet."*
- [x] **Partner Co-Branded Hubs**
  - `single-rl_partner.blade.php`: Tabbed partner hub layout (`/partners/{slug}/{tab}`) with all 9 tabs, co-branded header, and mobile dropdown.
  - `archive-rl_partner.blade.php`: Strategic partner network index embedding `<livewire:partner.partner-directory-grid />` and partner application CTA.
- [x] **Blog & Resource Archives**
  - `index.blade.php`: Knowledge hub & tactical guide index powered by `<livewire:blog.guide-index-filter />`.
  - `archive.blade.php`: Taxonomy and category archive with card grid, badges, and posts navigation.
  - `404.blade.php`: Branded high-converting 404 error page with live search and quick links.

---

## Phase 7: Testing, End-to-End Tracking & Webhook QA (Sprint 3)

- [x] **Automated Testing Suite**
  - Unit tests (`tests/Unit`) for DTOs, AttributionEngine, and Action classes (18 tests, 70 assertions).
  - Feature tests (`tests/Feature`) for webhook controllers (Stripe, Calendly) and tracking subscribers (9 tests, 27 assertions).
  - Continuous Integration pipeline via GitHub Actions (`.github/workflows/ci.yml`).
- [x] **End-to-End Tracking & Attribution QA**
  - Verify `rl_referrer` cookie persistence across cross-domain redirects (tested & documented in QA runbook).
  - Test 24-hour click deduplication in `rl_referral_clicks` (unit tested in `AttributionEngineTest`).
  - Verify Customer.io lead identification upon Gravity Forms submission (feature tested in `TrackingSubscribersTest`).
  - Test PostHog feature flag variant evaluation (unit tested in `ActionsTest`).
- [x] **Webhook QA**
  - Test Stripe Connect `account.updated` and payout transfers (`StripeWebhookTest`).
  - Test Calendly `invitee.created` booking payload ingestion (`CalendlyWebhookTest`).
  - QA Runbook compiled in [`docs/qa-attribution-webhooks.md`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/docs/qa-attribution-webhooks.md).

---

## Phase 8: Staged Cutover & Production Go-Live (Sprint 3)

- [ ] **Staging Deployment & Verification**
  - Deploy to staging environment with Bedrock `.env` secrets.
  - Run database migrations (`wp acorn migrate`).
  - Build production assets (`npm run build`).
- [ ] **Staged Content Cutover (ADR-0005 Amendment Gate)**
  - Execute AI-assisted Elementor audit (`wp acorn content:audit-elementor`) across all posts.
  - Run automated Gutenberg conversion pass (`ConvertElementorPostAction`) for mapped Elementor widgets.
  - Complete 100% human editorial review sign-off for all converted posts before production publishing.
  - Review Homepage and primary marketing landing pages.
- [ ] **Performance Benchmarking**
  - Run Google PageSpeed Insights on Staging (Target: Mobile 96+, LCP < 1.2s, CLS 0.00).
- [ ] **Production DNS Cutover**
  - Flip DNS to modern platform.
  - Validate SSL, redirects, and transactional email deliverability.
