# Remote Leverage WordPress Theme (v2.1.0)

A high-performance, enterprise-grade WordPress theme engineered for **Remote Leverage**. Built on **Roots Sage 10**, **Acorn 6**, **Tailwind CSS v4**, **Livewire 4**, and **Log1x ACF Composer**, following strict **Domain-Driven Design (DDD)** principles and headless marketing funnel architecture.

---

## 📑 Table of Contents

1. [Architectural Overview & Tech Stack](#-architectural-overview--tech-stack)
2. [Design System & Styling Directives](#-design-system--styling-directives)
3. [Domain-Driven Architecture (`app/Domains`)](#-domain-driven-architecture-appdomains)
   - [Lead Domain (ADR-0008)](#1-lead-domain-appdomainslead)
   - [Scheduling Domain](#2-scheduling-domain-appdomainsscheduling)
   - [Tracking & Analytics Domain](#3-tracking--analytics-domain-appdomainstracking)
   - [Referral & Affiliate Domain](#4-referral-domain-appdomainsreferral)
   - [Partner Hub Domain](#5-partner-hub-domain-appdomainspartnerhub)
   - [Content Audit & Modernization Domain](#6-content-audit-domain-appdomainscontentaudit)
4. [Reactive Livewire 4 Components](#-reactive-livewire-4-components)
   - [Unified 3-Step Booking Wizard](#multistepbookingwizard-unified-3-step-funnel)
   - [Instant Live Call Presence Button](#instantlivecallbutton)
   - [Partner Portal & Directory](#partner-portal--affiliate-system)
   - [Interactive Utilities](#interactive-utilities)
5. [Native ACF Gutenberg Blocks Library](#-native-acf-gutenberg-blocks-library)
6. [Pre-Hydrated Block Patterns (`patterns/`)](#-pre-hydrated-block-patterns-patterns)
7. [Templates & Layout Structure](#-templates--layout-structure)
8. [Developer Workflow & CLI Commands](#-developer-workflow-cli-commands)
9. [Thorough Testing Guide for Every Domain](#-thorough-testing-guide-for-every-domain)
   - [Lead Domain](#1-testing-the-lead-domain-appdomainslead)
   - [Scheduling Domain](#2-testing-the-scheduling-domain-appdomainsscheduling)
   - [Tracking & Analytics Domain](#3-testing-the-tracking--analytics-domain-appdomainstracking)
   - [Referral & Affiliate Domain](#4-testing-the-referral--affiliate-domain-appdomainsreferral)
   - [Partner Hub Domain](#5-testing-the-partner-hub-domain-appdomainspartnerhub)
   - [Content Audit Domain](#6-testing-the-content-audit-domain-appdomainscontentaudit)
10. [Third-Party Integrations & Complete Environment Reference](#-third-party-integrations--configuration)

---

## 🚀 Architectural Overview & Tech Stack

| Layer | Technology | Version | Purpose / Architectural Standard |
| :--- | :--- | :--- | :--- |
| **Theme Core** | [Roots Sage](https://roots.io/sage/) | `10.x` | Modern WordPress theme framework with Laravel Acorn container integration. |
| **App Container** | [Roots Acorn](https://roots.io/acorn/) | `^6.0` | Laravel runtime within WordPress (Service Providers, DI, Eloquent, Events). |
| **Reactive UI** | [Livewire](https://livewire.laravel.com/) | `^4.4` | Zero-bundle reactive components replacing heavy client SPAs and Gravity Forms. |
| **Styling** | [Tailwind CSS](https://tailwindcss.com/) | `^4.0` | `@theme` CSS tokens, zero runtime CSS bloat, instant compilation via Vite. |
| **Build Tooling** | [Vite](https://vitejs.dev/) | `^8.0` | Sub-second HMR and optimized production bundles. |
| **Block Engine** | [Log1x ACF Composer](https://github.com/Log1x/acf-composer) | `^3.4` | Code-first native Gutenberg ACF blocks with Blade templates. |
| **Testing** | [Pest PHP](https://pestphp.com/) | `^5.1` | Comprehensive unit and feature test suite. |
| **Monitoring** | [Sentry](https://sentry.io/) | `^4.27` | Real-time frontend and backend exception tracing. |
| **Phone Validation** | [libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php) | `^9.0` | Strict E.164 international phone number validation and formatting. |

---

## 🎨 Design System & Styling Directives

The theme follows strict design and layout constraints enforced across all views, blocks, and templates:

### 1. Canonical 1380px Container Width
- **Rule**: Every main section container across all landing pages, templates, and patterns MUST be constrained to **`1380px`**.
- **Blade/Tailwind Implementation**: Content wrappers use `w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8`.
- **Gutenberg Group Layout**: Every root `wp:group` declares `"layout":{"type":"constrained","contentSize":"1380px"}`.
- **Prohibited**: Never use generic `max-w-4xl`, `max-w-5xl`, `max-w-6xl`, `max-w-7xl`, `1140px`, or `1200px` for top-level content sections.

### 2. Typography Constraints
- **Bold (700) is the Maximum Weight**: Never use extra bold (`font-extrabold`, `font-black`, `800`, `900`).
- **Font Scale**: Display headings use modern font stacks (`font-display` / Inter & Spartan) tuned with tight negative letter-spacing (`tracking-tight`).

### 3. Curated Brand Color Palette
Defined directly in `resources/css/app.css` using modern Tailwind v4 `@theme` directives:
- **Brand Purple**: `#8A2BE2` / `--color-brand-purple`
- **Deep Midnight / Hero**: `#13132F`, `#342567`, `#18112C`, `#250D4A`
- **High-Conversion Magenta**: `#F8248A` / `#E91E63` (used for primary booking CTAs and focus rings)
- **Accent Orange**: `#F97316` / `#EA580C`
- **Semantic Status**: Success `#00D67D`, Alert/Warning `#F5C700`, Error `#D94900`
- **Light Surfaces**: `#F8F9FA` (inputs, cards, subtle hover backgrounds)

---

## 🏛️ Domain-Driven Architecture (`app/Domains`)

Business logic is organized into isolated, maintainable bounded contexts under `app/Domains/`. Each domain contains its own Actions, DTOs, Events, Listeners, Models, and Services.

```
app/Domains/
├── ContentAudit/       # Legacy Elementor AST inspection, conversion & email signature generation
├── Lead/               # Native lead ingestion, phone validation, HubSpot & dual-write activity logging
├── PartnerHub/         # Strategic partner network CPT (rl_partner) & Notion database sync
├── Referral/           # Affiliate click tracking, 24h IP deduplication, Stripe Connect payouts
├── Scheduling/         # Calendly API, Google Meet generation & instant live calling
└── Tracking/           # Customer.io & PostHog dual-dispatch behavioral analytics
```

---

### 1. Lead Domain (`app/Domains/Lead`)

Under **ADR-0008**, legacy Gravity Forms plugins (`GF_HubSpot`, `gform_after_submission` hooks, `wp_gf_*` tables) have been completely retired. Forms are powered natively by Livewire components routing through the Lead domain.

- **`CaptureLeadAction`**: The central lead ingestion orchestrator. Validates inputs, stamps multi-touch attribution via `AttributionEngine`, persists records to `rl_leads`, dispatches lifecycle events, and updates existing leads without creating duplicate records.
- **`AttributionEngine`**: Resolves `sourceType` (`ad`, `organic`, `referral_hub`, `partnership`) and `sourceID`. Inspects query params (`utm_source`, `utm_medium`, `utm_campaign`, `gclid`, `fbclid`, `via`, `ref`, `r`) and persistent attribution cookies (`rl_referrer`).
- **`LeadActivityLogger`**: Enforces a strict dual-logging contract:
  - **Stage 1: Dispatch** (`dispatch_initiated`)
  - **Stage 2: Consumption** (`consumed_by_tracking`, `consumed_by_crm`, `consumed_by_webhook`)
- **`PhoneValidationService`**: Uses `libphonenumber-for-php` to parse and validate international phone numbers against country codes, outputting standardized E.164 strings.
- **`HubSpotGateway`**: Direct API integration syncing lead contacts and lifecycle stages directly to HubSpot.
- **`PurgeOldLeadsAction`**: GDPR and compliance maintenance enforcing a 30-day minimum retention floor via `wp acorn lead:purge`.

---

### 2. Scheduling Domain (`app/Domains/Scheduling`)

Provides multi-provider scheduling (Calendly + Google Calendar) and instant live consultation routing.

- **`FetchAvailableSlotsAction`**: Fetches real-time available consultation intervals across timezones.
- **`BookMeetingAction`**: Creates meeting invitees via Calendly or Google Calendar API with Google Meet video links.
- **Dynamic Revenue-Based Routing**:
  - Leads with Monthly Recurring Revenue (MRR) $\ge \$10\text{k}$ route to the High-Tier event (`T10`).
  - Leads with MRR $< \$10\text{k}$ route to the Standard event (`T0`).
  - Job seekers and applicant flows cleanly skip the sales calendar.
- **`RouteInstantCallAction` & `LiveCallAvailabilityRouter`**: Manages real-time consultant presence and instant 1-click Google Meet video rooms.

---

### 3. Tracking & Analytics Domain (`app/Domains/Tracking`)

- **`RecordBehaviorEventAction`**: Dispatches structured analytics events concurrently to **PostHog** and **Customer.io**.
- **`EvaluateVariantAction`**: Server-side PostHog feature flag and A/B test variant evaluation with cookie fallbacks.
- **`HandleLeadCreatedForTracking`**: Event subscriber automatically identifying user profiles upon lead creation.
- **`TrackingHooks`**: Injects PostHog analytics snippet in `<head>` and Customer.io script in `<footer>`.

---

### 4. Referral & Affiliate Domain (`app/Domains/Referral`)

- **`TrackReferralClickAction`**: Ingests referral clicks with a strict **24-hour IP deduplication window** stored in `rl_referral_clicks`.
- **`RegisterPartnerAction`**: Generates unique partner referral slugs and initializes commission profiles.
- **`ProcessPayoutAction`**: Calculates earned rewards (`rl_referral_rewards`) and executes automated Stripe Connect payout transfers.
- **`ReferralAttributionMiddleware`**: Global HTTP middleware capturing `?via=`, `?ref=`, `?r=` and setting the `rl_referrer` cookie.

---

### 5. Partner Hub Domain (`app/Domains/PartnerHub`)

- **Custom Post Type (`rl_partner`)**: Manages strategic co-branded partner hubs under `/partners/{slug}/{tab}` with custom rewrite rules.
- **`NotionSyncService`**: Automatically synchronizes partner directories and metadata from an external Notion database.
- **`QueryPartnersAction`**: Provides zero-reload category filtering and full-text search for the partner directory.

---

### 6. Content Audit Domain (`app/Domains/ContentAudit`)

- **`ElementorAuditService`**: Traverses legacy `_elementor_data` JSON abstract syntax trees (AST) and maps legacy widgets against the modern Gutenberg block matrix.
- **`ConvertElementorPostAction`**: Programmatically converts legacy Elementor posts into native Gutenberg ACF block comments.
- **`GenerateSignatureHtmlAction`**: Generates standardized, cross-client compatible HTML email signatures matching Remote Leverage brand guidelines (`sig-1-light`).

---

## ⚡ Reactive Livewire 4 Components

Located in `app/Application/Livewire/` and rendered via `<livewire:... />`:

### `MultistepBookingWizard` (Unified 3-Step Funnel)
Powers all consultation scheduling across the hero, standalone pages, and footer.

```
┌──────────────────────────────────────────────────────────────┐
│                  UNIFIED 3-STEP BOOKING FUNNEL               │
├──────────────────────────────┬───────────────────────────────┤
│ STEP 1: Details & Revenue    │ Name, Email, Phone, Revenue   │
│                              │ (Supports progressive email)  │
├──────────────────────────────┼───────────────────────────────┤
│ STEP 2: Date Picker          │ Interactive month calendar    │
├──────────────────────────────┼───────────────────────────────┤
│ STEP 3: Time Slot & Confirm  │ Click time → reveals inline   │
│                              │ [ Time ] + [ Confirm ] button │
├──────────────────────────────┼───────────────────────────────┤
│ SUCCESS: Scheduled Screen    │ Confirmed meeting summary,    │
│                              │ Google Meet URL, Add-to-Cal   │
└──────────────────────────────┴───────────────────────────────┘
```

#### Available Skins & Config Attributes:
- **`skin="default"`**: Standalone card with white background, progress bar, and host profile header.
- **`skin="naked"`**: Borderless, background-free variant designed to embed cleanly into hero cards.
- **`skin="glass"`**: Sleek dark-mode glassmorphic design used in the global website footer.
- **`:enable-isolated-fields="true"`**: Enables progressive substep disclosure (e.g. Email field isolated first; after submission, the remaining fields expand smoothly).
- **`:isolated-steps="[['step_label' => 'Email', 'step_fields' => ['email']], ...]"`**: Custom field segmentation.
- **`:hide-profile-header="true"`** & **`:hide-progress-bar="true"`**: Configurable UI toggles for compact hero placements.
- **Full UTM & Session Capture**: Automatically maps hidden acquisition inputs (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `gclid`, `fbclid`, `referral_code`, `landing_url`, `referrer_url`, `session_id`).

### `InstantLiveCallButton`
Displays live consultant presence status ("Online Now" with glowing green live dot). Clicking connects qualified leads to an instant Google Meet room without waiting for a scheduled calendar slot.

### Partner Portal & Affiliate System
- **`PartnerPortalDashboard`**: Real-time affiliate dashboard displaying referral links, total clicks, conversions, pending commissions, and Stripe Connect status.
- **`PartnerRegistrationForm`**: Streamlined partner application form.
- **`PartnerDirectoryGrid`**: Reactive partner directory search and category filtering.

### Interactive Utilities
- **`EmailSignatureGenerator`**: Client-side interactive generator allowing staff and partners to preview and copy formatted HTML email signatures.
- **`GuideIndexFilter`**: Zero-reload search and category filter for the Knowledge Hub and VA guide repository.

---

## 🧱 Native ACF Gutenberg Blocks Library

All blocks are declared via `Log1x\AcfComposer` (`app/Blocks/`), use dedicated Blade views (`resources/views/blocks/`), and pull default demo content from `App\Support\BlockDefaults` so they are fully populated upon insertion in Gutenberg.

| Block Name | Block Slug | View File | Key Features & Replacements |
| :--- | :--- | :--- | :--- |
| **Hire VA Hero** | `acf/hire-va-hero` | `hire-va-hero.blade.php` | Full-height hero (`min-h-dvh`), headline, 6-item checklist, candidate backdrop grid, client logo marquee, embedded 3-step booking wizard. |
| **Booking Block** | `acf/booking` | `booking.blade.php` | Embeds `MultistepBookingWizard` with customizable skin and revenue routing. |
| **Booking Footer** | `acf/booking-footer` | `booking-footer.blade.php` | Dedicated dark glassmorphic footer booking section (`skin="glass"`). |
| **Talent Marquee** | `acf/talent-marquee` | `talent-marquee.blade.php` | Smooth infinite scrolling candidate cards with avatar, role, country, and rates. |
| **Client Logos Marquee** | `acf/client-logos-marquee`| `client-logos-marquee.blade.php` | Monochrome client logo ticker with infinite CSS scroll animation. |
| **Trust & Stats** | `acf/trust-stats` | `trust-stats.blade.php` | Social proof stats, 5-star review ratings, and trust metric counters. |
| **Department Cards** | `acf/department-cards` | `department-cards.blade.php` | Glassmorphism role category cards with hover micro-animations. |
| **Roles Grid** | `acf/roles-grid` | `roles-grid.blade.php` | Categorized directory of available virtual assistant roles. |
| **Why Hire** | `acf/why-hire` | `why-hire.blade.php` | Value proposition cards highlighting speed, cost savings, and quality. |
| **Comparison Matrix** | `acf/comparison-matrix` | `comparison-matrix.blade.php` | Detailed side-by-side feature comparison table (Remote Leverage vs. In-House vs. Agency). |
| **Process Steps** | `acf/process-steps` | `process-steps.blade.php` | 3-step hiring roadmap with visual step badges. |
| **Guarantee Card** | `acf/guarantee-card` | `guarantee-card.blade.php` | 100% risk-free replacement guarantee callout with trust badges. |
| **Benefits & Guarantee** | `acf/benefits-guarantee` | `benefits-guarantee.blade.php` | Full-width benefits breakdown with guarantee seal. |
| **Testimonials** | `acf/testimonials` | `testimonials.blade.php` | CSS scroll-snap testimonial carousel with Alpine.js video lightbox modal. |
| **Accordion FAQ** | `acf/accordion-faq` | `accordion-faq.blade.php` | Semantic `<details>` accordion with automatic Schema.org JSON-LD FAQ structured data. |
| **Data Table** | `acf/data-table` | `data-table.blade.php` | Mobile-responsive tabular comparison data. |
| **CTA Banner** | `acf/cta-banner` | `cta-banner.blade.php` | High-conversion ambient gradient banner linking to the booking funnel. |
| **Feature Cards** | `acf/feature-cards` | `feature-cards.blade.php` | Multi-column feature grids with icons and descriptive copy. |
| **Live Call** | `acf/live-call` | `live-call.blade.php` | Dedicated CTA embedding the `InstantLiveCallButton`. |

---

## 🧩 Pre-Hydrated Block Patterns (`patterns/`)

The theme includes 21+ ready-to-use Gutenberg Block Patterns registered under the `Remote Leverage` block pattern category. Every pattern contains fully hydrated ACF block attributes so that default demo content renders immediately without empty placeholders:

- `hire-va-4-full.php`: Complete end-to-end landing page pattern matching `hire-va-4`.
- `hire-va-4-hero.php`: Modern full-height hero section with candidate grid backdrop and booking wizard.
- `hire-va-4-comparison.php`: 3-way agency vs. direct hire comparison matrix.
- `hire-va-4-process-steps.php`: 3-step candidate vetting and hiring process.
- `hire-va-4-testimonials.php`: Client review cards and video proof.
- `hire-va-4-guarantee.php`: Replacement guarantee callout.
- `hire-va-4-faq.php`: Common questions accordion with structured data.
- `hire-va-4-booking-footer.php`: Glassmorphic footer booking pattern.
- `full-homepage.php`: Complete homepage section arrangement.
- `client-logos.php`, `trust-and-impact.php`, `worlds-best-talent.php`, etc.

---

## 📐 Templates & Layout Structure

```
resources/views/
├── 404.blade.php                    # High-converting branded 404 page with search
├── archive-rl_partner.blade.php     # Strategic partner network directory index
├── archive.blade.php                # Category and taxonomy archive template
├── blocks/                          # Blade views for all native ACF blocks
├── components/                      # Reusable Blade UI components (buttons, badges)
├── front-page.blade.php             # Full homepage template
├── index.blade.php                  # Knowledge Hub & article index
├── layouts/
│   └── app.blade.php                # Master HTML layout shell with Tailwind & Livewire tags
├── livewire/                        # Livewire component Blade templates
│   ├── booking/                     # multistep-booking-wizard.blade.php
│   ├── partner/                     # partner-portal, partner-directory
│   └── scheduling/                  # instant-live-call-button.blade.php
├── sections/
│   ├── header.blade.php             # Global navigation with roles dropdown & live status
│   └── footer.blade.php             # Global footer with compliance & navigation links
└── single-rl_partner.blade.php      # Co-branded 9-tab partner hub (/partners/{slug}/{tab})
```

---

## 🛠️ Developer Workflow, CLI Commands & Testing

### Installation & Asset Compilation

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Start local development server with Hot Module Replacement (HMR)
npm run dev

# Build optimized production assets
npm run build
```

### WP-CLI & Acorn Artisan Commands

```bash
# Ingest and audit legacy Elementor posts for Gutenberg conversion
wp acorn content:audit-elementor

# Purge leads exceeding 30-day retention floor (compliance run)
wp acorn lead:purge

# Run Acorn database migrations
wp acorn migrate
```

---

## 🧪 Thorough Testing Guide for Every Domain

The theme includes a comprehensive Pest PHP test suite (53 tests, 251 assertions) and supports interactive CLI, database, and browser verification across all 6 bounded contexts.

```bash
# Run the entire test suite across all domains
./vendor/bin/pest
```

---

### 1. Testing the Lead Domain (`app/Domains/Lead`)

The Lead Domain handles form submission ingestion, international phone validation, attribution resolution, dual-write activity logging, CRM dispatching, and retention pruning.

#### A. Automated Unit Tests
```bash
./vendor/bin/pest tests/Unit/LeadDomainTest.php
```
- Verifies `PhoneValidationService` parses and normalizes US and international phone numbers into E.164.
- Verifies `CaptureLeadAction` validates data, stamps multi-touch attribution, persists records to `rl_leads`, and logs dispatch events in `rl_lead_activity_logs`.
- Verifies `CaptureLeadAction` updates existing partial leads on final submission without creating duplicate rows.
- Verifies `PurgeOldLeadsAction` respects the 30-day retention floor.
- Verifies Slack and outbound webhook listener consumption logging.

#### B. Manual Browser Verification
1. Navigate to any page containing a booking funnel (e.g. `https://remoteleverage-v2.test/hire-va-4-preview/` or `/book-consultation`).
2. Fill out Step 1 (Email, Full Name, Phone, and Monthly Revenue tier).
3. Click **Next: Pick a Date**.
4. Check the database to confirm the partial lead was persisted:
   ```bash
   wp db query "SELECT id, email, first_name, last_name, phone, monthly_revenue, status FROM rl_leads ORDER BY id DESC LIMIT 1;"
   ```
5. Check the activity audit log to verify the dual-logging contract:
   ```bash
   wp db query "SELECT id, lead_id, stage, action, status, created_at FROM rl_lead_activity_logs WHERE lead_id = (SELECT MAX(id) FROM rl_leads) ORDER BY id ASC;"
   ```
   - Expect: `stage = 'dispatch'` / `action = 'dispatch_initiated'`
   - Expect: `stage = 'consumption'` / `action = 'consumed_by_tracking'`

#### C. CLI Retention Purge Test
```bash
wp acorn lead:purge
```
- Deletes leads older than 30 days that are not marked as active customers.

---

### 2. Testing the Scheduling Domain (`app/Domains/Scheduling`)

The Scheduling Domain manages real-time appointment availability, revenue-tiered Calendly routing, Google Meet creation, and webhook reconciliation.

#### A. Automated Unit & Feature Tests
```bash
# Test Calendly dynamic revenue routing and wizard progression
./vendor/bin/pest tests/Unit/BookingWizardRoutingTest.php

# Test Calendly webhook ingestion (invitee.created, invitee.canceled)
./vendor/bin/pest tests/Feature/CalendlyWebhookTest.php
```
- Verifies leads with $MRR \ge \$10\text{k}$ are assigned the `T10` high-value event type.
- Verifies leads with $MRR < \$10\text{k}$ are routed to `T0`.
- Verifies job applicant submissions bypass the consultation calendar.
- Verifies month forward/backward navigation retains date availability.
- Verifies webhook ingestion flips lead status to `booked` or `canceled` and records the Google Meet room URL.

#### B. Manual Browser End-to-End Booking Flow
1. Navigate to `https://remoteleverage-v2.test/hire-va-4-preview/`.
2. Fill out Step 1:
   - Email: `founder@acme.com`
   - Name: `Jane Doe`
   - Phone: `(305) 555-0199`
   - Revenue: `$10k to $50k` (routes to Tier 10)
3. Step 2 (Calendar): Select any green available date.
4. Step 3 (Times): Click any time slot.
   - Verify it expands into the **`[ Time ]`** pill and **`[ Confirm ]`** button inline (3-step flow).
5. Click **Confirm**:
   - Verify immediate transition to the **You're All Set!** confirmation screen.
   - Verify Google Meet video link is displayed with Add-to-Calendar buttons (Google, Outlook, Apple).
6. Verify DB record:
   ```bash
   wp db query "SELECT id, email, status, preferred_slot, timezone, meeting_id, meeting_url FROM rl_leads ORDER BY id DESC LIMIT 1;"
   ```

#### C. Testing Webhook Reconciliation via cURL
Simulate Calendly `invitee.created` webhook:
```bash
curl -X POST https://remoteleverage-v2.test/api/webhooks/calendly \
  -H "Content-Type: application/json" \
  -d '{
    "event": "invitee.created",
    "payload": {
      "email": "founder@acme.com",
      "scheduled_event": {
        "uri": "https://api.calendly.com/scheduled_events/test-event-123",
        "start_time": "2026-09-18T15:00:00Z"
      }
    }
  }'
```

---

### 3. Testing the Tracking & Analytics Domain (`app/Domains/Tracking`)

Handles dual-dispatch analytics (Customer.io + PostHog), A/B testing feature flag evaluation, and script tag injection.

#### A. Automated Unit & Feature Tests
```bash
./vendor/bin/pest tests/Unit/ActionsTest.php
./vendor/bin/pest tests/Feature/TrackingSubscribersTest.php
```
- Verifies `EvaluateVariantAction` evaluates PostHog feature flags with cookie fallback.
- Verifies `HandleLeadCreatedForTracking` auto-identifies newly captured leads on Customer.io and PostHog.

#### B. Manual Browser Script Verification
1. Open Chrome DevTools (`F12` or `Cmd+Option+I`) on `https://remoteleverage-v2.test/`.
2. Open the **Console** tab:
   - Run `window.posthog` $\rightarrow$ should return the initialized PostHog client instance.
   - Run `window._cio` $\rightarrow$ should return the Customer.io tracking snippet array.
3. Open the **Network** tab, filter by `posthog` or `customer.io`:
   - Verify tracking calls (`/e/` or `/api/v1/customers/`) fire with anonymized distinct IDs.

---

### 4. Testing the Referral & Affiliate Domain (`app/Domains/Referral`)

Handles referral link tracking, 24-hour IP deduplication, commission calculations, and Stripe Connect webhook payouts.

#### A. Automated Unit & Feature Tests
```bash
# Test attribution cookie, URL param overrides, and IP deduplication
./vendor/bin/pest tests/Unit/AttributionEngineTest.php

# Test Stripe Connect account updates and payout transfers
./vendor/bin/pest tests/Feature/StripeWebhookTest.php
```
- Verifies `AttributionEngine` extracts referral slugs from `?via=`, `?ref=`, `?r=`, or `HTTP_REFERER`.
- Verifies malicious referral slugs are sanitized and stripped of XSS vectors.
- Verifies cookie lifetimes are set accurately.
- Verifies Stripe Connect webhooks update partner status (`account.updated`) and mark payouts as paid (`transfer.paid`).

#### B. Manual Referral Tracking Verification
1. Visit the website with an affiliate query parameter:
   `https://remoteleverage-v2.test/?via=testpartner`
2. Inspect Application $\rightarrow$ Cookies:
   - Confirm cookie `rl_referrer` is set with value `testpartner`.
3. Fill out any consultation or contact form and submit.
4. Verify lead is stamped with partner attribution:
   ```bash
   wp db query "SELECT id, email, source_type, source_id, referral_code FROM rl_leads ORDER BY id DESC LIMIT 1;"
   ```
   - Expect: `referral_code = 'testpartner'`, `source_type = 'referral_hub'`

#### C. Testing Stripe Connect Webhooks via cURL
Simulate a successful Stripe transfer:
```bash
curl -X POST https://remoteleverage-v2.test/api/webhooks/stripe \
  -H "Content-Type: application/json" \
  -d '{
    "type": "transfer.paid",
    "data": {
      "object": {
        "id": "tr_test_12345",
        "amount": 1400,
        "currency": "usd",
        "destination": "acct_test_partner"
      }
    }
  }'
```

---

### 5. Testing the Partner Hub Domain (`app/Domains/PartnerHub`)

Manages `rl_partner` custom post types, 9-tab co-branded landing pages, and Notion synchronization.

#### A. Routing & Tab Navigation
1. Visit the Strategic Partner Directory:
   `https://remoteleverage-v2.test/partners`
   - Test Livewire search filter input and category dropdown.
2. Visit a specific partner profile:
   `https://remoteleverage-v2.test/partners/oyster` (or any seeded partner)
   - Verify tab navigation works without page reloads across all 9 tabs:
     `/partners/oyster/overview`, `/partners/oyster/pricing`, `/partners/oyster/integration`, `/partners/oyster/faq`.

#### B. Testing Notion Sync via Acorn Tinker
```bash
wp acorn tinker --execute="\App\Domains\PartnerHub\Actions\SyncNotionPartnersAction::run();"
```
- Fetches and synchronizes partner listings from the Notion Partners database.

---

### 6. Testing the Content Audit Domain (`app/Domains/ContentAudit`)

Handles legacy Elementor AST inspection, conversion to native Gutenberg blocks, and HTML email signature generation.

#### A. Automated Unit Tests
```bash
./vendor/bin/pest tests/Unit/ElementorAuditTest.php
```
- Verifies `ElementorAuditService` traverses widget trees and flags unmapped elements.
- Verifies `ConvertElementorPostAction` converts Elementor AST into valid Gutenberg block comments.

#### B. CLI Elementor Audit Command
```bash
wp acorn content:audit-elementor
```
- Scans all published posts for Elementor data and outputs a conversion readiness report.

#### C. HTML Email Signature Generator
Execute via Acorn Tinker:
```bash
wp acorn tinker --execute="echo \App\Domains\ContentAudit\Actions\GenerateSignatureHtmlAction::run(new \App\Domains\ContentAudit\Data\SignatureData('Jane Doe', 'Director of Operations', 'jane@remoteleverage.com', '+1 (305) 555-0199'));"
```
- Outputs clean, inline-styled HTML email signature conforming to `rl-social-kit` standards.

---

## 🔌 Third-Party Integrations & Configuration

All third-party credentials and webhook secrets are configured via Bedrock `.env` and registered in `config/services.php`.

### Environment Variable Reference

```env
# ==============================================================================
# Database & Core WordPress (Bedrock Standard)
# ==============================================================================
DB_NAME='your_db_name'
DB_USER='your_db_user'
DB_PASSWORD='your_db_password'
DB_HOST='127.0.0.1'
WP_ENV='development'
WP_HOME='https://your-domain.test'
WP_SITEURL="${WP_HOME}/wp"
APP_KEY=base64:your_app_key_here

# ==============================================================================
# UI & Frontend Animations
# ==============================================================================
BARBA_ENABLED=true
LOCOMOTIVE_ENABLED=true

# ==============================================================================
# Mail Delivery
# ==============================================================================
MAIL_HOST=''
MAIL_PORT=''
MAIL_USERNAME=''
MAIL_PASSWORD=''
MAIL_FROM_ADDRESS=''
MAIL_FROM_NAME=''

# ==============================================================================
# Calendly Scheduling & Revenue Routing (ADR-0008)
# ==============================================================================
CALENDLY_API_KEY=''
CALENDLY_USER_URI=''
CALENDLY_DEFAULT_EVENT_TYPE=''
CALENDLY_T10_EVENT_TYPE=''
CALENDLY_T0_EVENT_TYPE=''
CALENDLY_WEBHOOK_SIGNING_KEY=''

# ==============================================================================
# Google Calendar & Meet Integration
# ==============================================================================
GOOGLE_CALENDAR_CLIENT_ID=''
GOOGLE_CALENDAR_CLIENT_SECRET=''
GOOGLE_OAUTH_CLIENT_ID=''
GOOGLE_OAUTH_CLIENT_SECRET=''
GOOGLE_CALENDAR_REFRESH_TOKEN=''
GOOGLE_CALENDAR_ID='primary'

# ==============================================================================
# Customer.io Analytics & Tracking
# ==============================================================================
CUSTOMERIO_SITE_ID=''
CUSTOMERIO_API_KEY=''
CUSTOMERIO_APP_API_KEY=''

# ==============================================================================
# Stripe Connect & Payouts
# ==============================================================================
STRIPE_KEY=''
STRIPE_SECRET=''
STRIPE_TEST_KEY=''
STRIPE_TEST_SECRET=''
STRIPE_WEBHOOK_SECRET=''
STRIPE_CONNECT_CLIENT_ID=''

# ==============================================================================
# PostHog Analytics & Feature Flags
# ==============================================================================
POSTHOG_API_KEY=''
POSTHOG_HOST='https://us.i.posthog.com'

# ==============================================================================
# Notion Strategic Partners Sync
# ==============================================================================
NOTION_API_KEY=''
NOTION_PARTNERS_DATABASE_ID=''

# ==============================================================================
# Webhooks, Real-Time Notifications & Email Validation
# ==============================================================================
REFERRAL_WEBHOOK_SECRET=''
ZEROBOUNCE_API_KEY=''
SLACK_WEBHOOK_URL=''
LEAD_WEBHOOK_URL=''

# ==============================================================================
# AI & Content Auditor
# ==============================================================================
PRISM_SERVER_ENABLED=true
GEMINI_API_KEY=''

# ==============================================================================
# Error Monitoring (Sentry)
# ==============================================================================
SENTRY_LARAVEL_DSN=''
```

---

## 📄 License & Maintenance

Maintained by the **Remote Leverage Engineering Team**. Built upon Roots Sage (MIT License). All domain logic and custom assets are proprietary to Remote Leverage.

