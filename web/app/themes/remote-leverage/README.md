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
8. [Developer Workflow, CLI Commands & Testing](#-developer-workflow-cli-commands--testing)
9. [Third-Party Integrations & Configuration](#-third-party-integrations--configuration)

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

### Automated Testing Suite (Pest PHP)

The theme maintains comprehensive test coverage across all domain actions, tracking attribution, routing, and webhook ingestion:

```bash
# Run the entire test suite
./vendor/bin/pest

# Run specific domain or routing tests
./vendor/bin/pest tests/Unit/BookingWizardRoutingTest.php
./vendor/bin/pest tests/Unit/AttributionEngineTest.php
./vendor/bin/pest tests/Feature/CalendlyWebhookTest.php
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

## 🔌 Third-Party Integrations & Configuration

All third-party credentials and webhook secrets are configured via Bedrock `.env` and registered in `config/services.php`:

| Service | Environment Variables | Primary Usage |
| :--- | :--- | :--- |
| **Calendly** | `CALENDLY_API_KEY`, `CALENDLY_DEFAULT_EVENT_TYPE`, `CALENDLY_T10_EVENT_TYPE`, `CALENDLY_T0_EVENT_TYPE` | Consultation appointment booking and dynamic revenue routing. |
| **Google Calendar** | `GOOGLE_CALENDAR_CLIENT_ID`, `GOOGLE_CALENDAR_CLIENT_SECRET`, `GOOGLE_CALENDAR_REFRESH_TOKEN` | Google Meet conference generation for instant live calls. |
| **Customer.io** | `CUSTOMERIO_SITE_ID`, `CUSTOMERIO_API_KEY` | Lead profile identification and behavioral tracking. |
| **PostHog** | `POSTHOG_API_KEY`, `POSTHOG_HOST` | Feature flags, A/B testing variant evaluation, and analytics. |
| **Stripe Connect** | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Automated partner onboarding links and commission payouts. |
| **Notion** | `NOTION_API_KEY`, `NOTION_PARTNERS_DATABASE_ID` | Synchronizing strategic partner profiles into `rl_partner` CPT. |
| **Slack / Webhooks**| `SLACK_WEBHOOK_URL`, `LEAD_WEBHOOK_URL` | Real-time lead notifications and Zapier/Make webhook dispatches. |
| **Sentry** | `SENTRY_LARAVEL_DSN` | Exception reporting and performance telemetry. |

---

## 📄 License & Maintenance

Maintained by the **Remote Leverage Engineering Team**. Built upon Roots Sage (MIT License). All domain logic and custom assets are proprietary to Remote Leverage.
