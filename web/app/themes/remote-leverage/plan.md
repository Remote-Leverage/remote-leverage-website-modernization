# Remote Leverage Modernization Plan & Execution Tracker

Phased execution roadmap for the **Remote Leverage Website Modernization** project based on the [Architecture Proposal & Strategic Roadmap](https://github.com/Remote-Leverage/remote-leverage-website-modernization).

**Current Status**: Sprint 1 in progress (Foundation & Core Domain Architecture complete).

---

## Progress Overview

- **Phase 1: Foundation & Infrastructure**: `95% Completed`
- **Phase 2: Core Domain Architecture (DDD)**: `100% Completed`
- **Phase 3: Design Tokens & Tailwind CSS v4**: `0% Completed`
- **Phase 4: Livewire 4 Reactive UI Components**: `0% Completed`
- **Phase 5: Native Gutenberg Blocks Library**: `0% Completed`
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
- [ ] **Package Dependencies Installation**
  - [ ] Install `roots/acorn` dependencies & verify Acorn boot sequence.
  - [ ] Install `livewire/livewire` for reactive frontend state management.
  - [ ] Install `log1x/acf-composer` for code-driven native Gutenberg blocks.

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
  - [x] `GravityFormsSubmissionSubscriber.php`: Server-side form capture.
- [x] **Actions & WordPress Adapters**
  - [x] `RecordBehaviorEventAction.php`: Dual-dispatch to PostHog and Customer.io.
  - [x] `EvaluateVariantAction.php`: PostHog feature flag variant resolver.
  - [x] `TrackingHooks.php`: PostHog head snippet and Customer.io footer script injection.
  - [x] `GravityFormsHooks.php`: Redirect query parameter injection (`cio_id`, `cio_form`, `cio_fid`) and client tracking.
  - [x] `TrackingServiceProvider.php`: Adapter bootstrap.

### PartnerHub Domain (`app/Domains/PartnerHub`)
- [x] **Models & Services**
  - [x] `PartnerProfile.php`: Partner listing model.
  - [x] `NotionSyncService.php`: Notion API integration for partner database.
- [x] **Actions & WordPress Adapters**
  - [x] `SyncNotionPartnersAction.php`: 1-hour cached Notion database synchronization.
  - [x] `QueryPartnersAction.php`: Multi-parameter search and category filter.
  - [x] `PartnerPostType.php`: Registers `rl_partner` CPT with `^partners/([^/]+)/([^/]+)/?$` rewrite rule (`rl_tab`).

### ContentAudit Domain (`app/Domains/ContentAudit`)
- [x] **Services & Actions**
  - [x] `PrismAiAuditor.php`: Content length, headings, and keyword density analyzer.
  - [x] `AuditMarkdownContentAction.php`: Guide audit execution.
  - [x] `GenerateSignatureHtmlAction.php`: Standardized HTML email signature generator matching `rl-social-kit` (`sig-1-light`).

---

## Phase 3: Tailwind CSS v4 Design Tokens & Blade Shell (Sprint 1)

- [ ] **Tailwind CSS v4 Configuration**
  - [ ] Configure `resources/css/app.css` using modern `@theme` token syntax.
  - [ ] Define Remote Leverage brand palette:
    - Primary Navy (`#13132F`, `#0F172A`)
    - Accent Blue (`#0284C7`, `#38BDF8`)
    - Highlight Indigo/Purple (`#6366F1`, `#8A2BE2`)
    - Slate Neutrals (`#F8FAFC`, `#E2E8F0`, `#64748B`, `#0F172A`)
  - [ ] Define typography scale & import fonts (League Spartan, Inter Variable, Poppins).
  - [ ] Configure glassmorphism utility classes (`backdrop-blur-md`, subtle borders, dark glass).
- [ ] **Blade Layout Shell**
  - [ ] Modernize `resources/views/layouts/app.blade.php`.
  - [ ] Build global responsive navigation header (`resources/views/sections/header.blade.php`).
  - [ ] Build global footer with dynamic menus & compliance links (`resources/views/sections/footer.blade.php`).
  - [ ] Match block editor typography in `resources/css/editor.css`.

---

## Phase 4: Livewire 4 Reactive UI Components (Sprint 2)

- [ ] **Booking Funnel**
  - [ ] `MultistepBookingWizard.php`: 3-step qualification and slot selection wizard.
  - [ ] `multistep-booking-wizard.blade.php`: Live slot picker with timezone switching and instant validation.
  - [ ] `InstantLiveCallButton.php`: Reactive consultant presence indicator ("Online Now").
  - [ ] `instant-live-call-button.blade.php`: 1-click Google Meet routing button.
- [ ] **Partner Network & Affiliate Portal**
  - [ ] `PartnerPortalDashboard.php`: Partner dashboard for referral links, clicks, and earnings.
  - [ ] `partner-portal-dashboard.blade.php`: Reactive dashboard UI.
  - [ ] `PartnerRegistrationForm.php`: 1-step partner application form.
  - [ ] `PartnerDirectoryGrid.php`: Zero-reload search and category filter for strategic partners.
  - [ ] `partner-directory-grid.blade.php`: Responsive cards grid.
- [ ] **Interactive Utilities & Blog Filtering**
  - [ ] `EmailSignatureGenerator.php`: Live preview and clipboard copy tool.
  - [ ] `email-signature-generator.blade.php`: Interactive generator UI.
  - [ ] `GuideIndexFilter.php`: Instant taxonomy filter for VA guides.

---

## Phase 5: Native Gutenberg Blocks Library (Sprint 2)

*Consolidating 36 bespoke Elementor widgets into ~10 lightweight native Gutenberg blocks via `log1x/acf-composer`.*

- [ ] **`HeroBlock`** (`hero.blade.php`)
  - Replaces: `HeroWidget`, `HeroSectionWidget`, `HeroCarouselWidget`.
  - Features: Native lazy-loaded video background, zero CLS, LCP optimization.
- [ ] **`BookingBlock`** (`booking.blade.php`)
  - Replaces: `HeadlessCalendlyMultistepWidget`, `GoogleCalendarMultistepWidget`, `IsolatedFieldsHeadlessCalendlyMultistepWidget`.
  - Features: Embeds `MultistepBookingWizard` without iframes.
- [ ] **`LiveCallBlock`** (`live-call.blade.php`)
  - Replaces: `JoinLiveCallWidget`.
  - Features: Embeds `InstantLiveCallButton` with real-time presence polling.
- [ ] **`TestimonialsBlock`** (`testimonials.blade.php`)
  - Replaces: `TestimonialCardWidget`, `TestimonialListWidget`, `TrustSectionWidget`.
  - Features: CSS scroll-snap carousel, Alpine.js video lightbox modal.
- [ ] **`DepartmentCardsBlock`** (`department-cards.blade.php`)
  - Replaces: `DepartmentCardWidget`, `ContractorCardWidget`, `GlassCardWidget`.
  - Features: Glassmorphism Tailwind v4 cards, CSS micro-interactions.
- [ ] **`ProcessStepsBlock`** (`process-steps.blade.php`)
  - Replaces: `ProcessStepsWidget`, `HiringProcessWidget`.
  - Features: SVG stepper timeline with responsive collapse.
- [ ] **`BenefitsGuaranteeBlock`** (`benefits.blade.php`)
  - Replaces: `BenefitsSectionWidget`, `GuaranteeSectionWidget`.
  - Features: Inline SVG badges and satisfaction guarantee callouts.
- [ ] **`AccordionFaqBlock`** (`accordion.blade.php`)
  - Replaces: `ArticleFAQAccordionWidget`.
  - Features: Semantic `<details>` / `<summary>` tags with automated Schema.org FAQ structured data.
- [ ] **`DataTableBlock`** (`data-table.blade.php`)
  - Replaces: `ArticleDataTableWidget`.
  - Features: Mobile-responsive sticky table comparison.
- [ ] **`CtaBannerBlock`** (`cta-banner.blade.php`)
  - Replaces: `ContactCTAWidget`, `ArticleLeadFormWidget`.
  - Features: Embedded Gravity Forms styling with UTM pre-population.

---

## Phase 6: Dynamic Blade Templates & Layouts (Sprint 2)

- [ ] **Dynamic VA Guide Template (`single.blade.php`)**
  - Replaces: Elementor Theme Builder single post layout.
  - Automatically renders 100+ VA guides with Table of Contents, author bio, social sharing, and related reads.
- [ ] **Partner Co-Branded Hubs**
  - `single-rl_partner.blade.php`: Tabbed partner hub layout (`/partners/{slug}/{tab}`).
  - `archive-rl_partner.blade.php`: Strategic partner network index.
- [ ] **Blog & Resource Archives**
  - `index.blade.php`: Fast-filtering topic archive powered by `GuideIndexFilter`.
  - `archive.blade.php` and `404.blade.php`.

---

## Phase 7: Testing, End-to-End Tracking & Webhook QA (Sprint 3)

- [ ] **Automated Testing Suite**
  - Unit tests (`tests/Unit`) for DTOs, AttributionEngine, and Action classes.
  - Feature tests (`tests/Feature`) for webhook controllers and API endpoints.
  - Continuous Integration pipeline via GitHub Actions (`pint --test`, `pest`).
- [ ] **End-to-End Tracking & Attribution QA**
  - Verify `rl_referrer` cookie persistence across cross-domain redirects.
  - Test 24-hour click deduplication in `rl_referral_clicks`.
  - Verify Customer.io lead identification upon Gravity Forms submission.
  - Test PostHog feature flag variant evaluation.
- [ ] **Webhook QA**
  - Test Stripe Connect `account.updated` and payout transfers in Stripe Test Mode.
  - Test Calendly `invitee.created` booking payload ingestion.

---

## Phase 8: Staged Cutover & Production Go-Live (Sprint 3)

- [ ] **Staging Deployment & Verification**
  - Deploy to staging environment with Bedrock `.env` secrets.
  - Run database migrations (`wp acorn migrate`).
  - Build production assets (`npm run build`).
- [ ] **Staged Content Cutover**
  - Instant cutover for VA Guides & Blog archives (native `single.blade.php` renders existing post records immediately).
  - Review Homepage and primary marketing landing pages.
- [ ] **Performance Benchmarking**
  - Run Google PageSpeed Insights on Staging (Target: Mobile 96+, LCP < 1.2s, CLS 0.00).
- [ ] **Production DNS Cutover**
  - Flip DNS to modern platform.
  - Validate SSL, redirects, and transactional email deliverability.
