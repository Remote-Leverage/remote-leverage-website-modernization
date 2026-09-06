# ADR-0003: Livewire 4 for Reactive UX (Instead of a Headless SPA)

- Status: Accepted (documented as-is from `README.md`)
- Date: 2026-09-06 (recorded — original decision predates this record)
- Deciders: Adrián Salvatori (original author)

## Context

The legacy platform's "Interactive UX" relies on "Bulky iframe embeds, heavy
jQuery sliders, and fragile shortcodes" (`README.md` §2) for complex funnels
such as the booking wizard and partner dashboards. `README.md` §4 explicitly
frames the alternative considered and rejected: "building a brittle,
decoupled Headless React/Next.js frontend that forfeits native WordPress SEO
and increases maintenance overhead."

## Decision

Use **Livewire 4** for all reactive frontend state (`README.md` §1, §4):
"Next-gen reactive frontend components delivering Single-Page-Application
(SPA) fluidity for complex funnels ... with zero detached headless
infrastructure." Five high-impact components are named in §4:
`MultistepBookingWizard`, `InstantLiveCallButton`, `PartnerPortalDashboard`,
`PartnerDirectoryGrid`, and `EmailSignatureGenerator`. §4 includes a sequence
diagram showing the booking flow: Livewire component → `Scheduling` bounded
context → Calendly/Google Meet API → Customer.io/Gravity Forms.

## Tradeoffs & Impact on Prior Decisions

- Depends on ADR-0002 (Acorn 5 stack) — Livewire 4 is installed and wired
  through Acorn's service container per `plan.md` Phase 1.
- Interacts directly with ADR-0004 (DDD bounded contexts): Livewire
  components call into Domain `Actions` (e.g. `BookMeetingAction`) rather
  than containing business logic themselves — the reactive UI layer is kept
  thin by design.
- The explicit rejection of a headless React/Next.js frontend is a
  documented tradeoff already made in the proposal: it trades a
  fully-decoupled frontend (more common tooling, larger talent pool) for
  native WordPress SEO and a single deployable unit with no separate
  frontend build/deploy pipeline.

## Consequences

- All complex interactive funnels are server-rendered-first with Livewire's
  wire-protocol reactivity, not a client-side SPA framework — frontend
  developers work in Blade + PHP for these components, not a JS framework.
- Booking, partner-dashboard, and directory-search UX is coupled to
  Livewire's request/response model (AJAX-driven partial re-renders) rather
  than a fully client-side state store.
