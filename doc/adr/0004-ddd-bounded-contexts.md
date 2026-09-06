# ADR-0004: Domain-Driven Design Bounded Contexts Replace 8 Legacy Plugins

- Status: Accepted (documented as-is from `README.md`)
- Date: 2026-09-06 (recorded — original decision predates this record)
- Deciders: Adrián Salvatori (original author)

## Context

`README.md` §1 and §2 describe business logic — referral tracking, Calendly
integration, Stripe webhooks, Customer.io telemetry — as "scattered across
uncoordinated plugins with separate database interactions and autoloaders,"
with direct global `$wpdb` calls and no isolation between concerns.

## Decision

Re-architect the 8 legacy plugins into 5 cohesive, testable, isolated
**Bounded Contexts** under `app/Domains/` (`README.md` §1, §3):

- **Referral** (from `rl-referral-program`): DTOs (`PartnerData`,
  `ReferralData`, `PayoutData`), migrations for `rl_referral_clicks`,
  `rl_referrals`, `rl_referral_rewards`, `AttributionEngine`,
  `StripeConnectGateway`, `TrackReferralClickAction`,
  `RegisterPartnerAction`, `ProcessPayoutAction`.
- **Scheduling** (from `rl-elementor-blocks` & `rl-join-live-call`):
  `TimeSlotData`, `BookingRequestData`, `rl_live_call_sessions` table,
  `CalendlyClient`, `GoogleCalendarClient`, `LiveCallAvailabilityRouter`.
- **Tracking** (from `rl-customer-io` & `rl-posthog-feature-flags`):
  `CustomerIOClient`, `PostHogClient`, `GravityFormsSubmissionSubscriber`,
  `TrackingHooks`.
- **PartnerHub** (from `rl-partners-hub`): `PartnerProfile`,
  `NotionSyncService`, `PartnerPostType` (CPT `rl_partner`).
- **ContentAudit** (from `rl-content-auditor` & `rl-social-kit`):
  `PrismAiAuditor`, `AuditMarkdownContentAction`,
  `GenerateSignatureHtmlAction`.

Each domain follows a consistent internal shape (Actions, Data/DTOs, Events,
Listeners, Models, Repositories, Services/Gateways) and is wired into
WordPress via a dedicated `ServiceProvider` per domain plus HTTP middleware
where needed (`ReferralAttributionMiddleware`).

## Tradeoffs & Impact on Prior Decisions

- Depends on ADR-0002 (Acorn 5) for the DI container, Eloquent ORM, and
  service-provider registration that make bounded contexts practical inside
  WordPress.
- Is the business-logic layer that ADR-0003 (Livewire) components and
  ADR-0005 (Gutenberg blocks) call into — both later decisions are framed
  around invoking Domain Actions rather than owning logic themselves.
- Reduces the original 8-plugin boundary to 5 domain boundaries — this is a
  reorganization of ownership boundaries, not a 1:1 port; some legacy
  plugins' logic is split or merged (e.g. `rl-elementor-blocks` contributes
  to `Scheduling`, `rl-social-kit` contributes to `ContentAudit`).

## Consequences

- Business logic becomes unit-testable in isolation from WordPress hooks
  (Actions/DTOs are plain PHP), as opposed to the legacy procedural
  approach.
- Introduces a new internal API surface (Actions, DTOs, Events) that
  Livewire components, Gutenberg blocks, and WordPress hooks must all be
  written against consistently going forward.
