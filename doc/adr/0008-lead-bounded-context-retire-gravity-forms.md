# ADR-0008: Introduce a `Lead` Bounded Context and Retire Gravity Forms

- Status: Proposed
- Date: 2026-09-06
- Deciders: Hyller Bandeira (proposer) — pending review by Adrián Salvatori

## Context

The baseline proposal keeps Gravity Forms, not replaces it:

- `README.md` §3 lists Gravity Forms as one of the "Minimal 3rd party
  plugins" retained in `web/app/plugins/`.
- `README.md` §7 (documented in [ADR-0006](0006-parallel-staging-zero-downtime-cutover.md))
  states the mitigation for "Lead Generation Disruption" is explicitly:
  "Gravity Forms + GP Advanced Phone Field are preserved."
- [ADR-0004](0004-ddd-bounded-contexts.md) documents the `Tracking` domain's
  existing Gravity Forms integration points:
  `GravityFormsSubmissionSubscriber.php` (server-side form capture) and
  `GravityFormsHooks.php` (redirect query parameter injection — `cio_id`,
  `cio_form`, `cio_fid` — for Customer.io attribution).

Separately, the legacy codebase (`rl-elementor-blocks/src/Integrations/CalendlyIntegration.php:738-742`)
shows Gravity Forms already runs a `gravityformshubspot` add-on (class
`GF_HubSpot`) for CRM sync, alongside `gravityformsslack` and
`gravityformswebhooks` add-ons — confirming HubSpot is a real, currently-used
integration, distinct from the Customer.io pipeline already documented under
`Tracking`.

Today, "the lead" is not a first-class entity anywhere in the architecture:
lead data lives inside Gravity Forms' own entries table, and each downstream
integration (Customer.io, HubSpot, PostHog) independently captures its own
partial copy with no single source of truth, no cross-integration audit
trail, and no defined retention policy.

## Decision

Retire Gravity Forms and its HubSpot/Slack/Webhooks add-ons entirely, and
introduce a new **`Lead` bounded context** (`app/Domains/Lead`) as the sole
owner of lead capture, storage, and downstream distribution, following the
same internal shape as the other domains in ADR-0004
(Actions/Data/Events/Listeners/Models/Repositories/Services):

- **Entity**: `Lead` — a core record representing a prospective contact,
  carrying two hidden fields, `sourceType` (`ad`, `organic`, `referral_hub`,
  or `partnership`) and `sourceID` (the specific identifier within that
  type — a partner code, ad campaign ID, etc., or `null` for organic) — see
  "`Lead.source` as an evolution of `AttributionEngine`" below for how these
  are computed.
- **Form configuration** (replaces Gravity Forms' form builder): admin-
  configurable field definitions, validation rules, and notification
  routing, exposed via WP admin.
- **HubSpot integration** (replaces the `gravityformshubspot` add-on): a
  `HubSpotGateway` in `Lead/Services/`, following the same gateway pattern
  as `CustomerIOClient`/`PostHogClient` in `Tracking`.
- **Cross-context event triggers** (replaces `GravityFormsSubmissionSubscriber`/
  `GravityFormsHooks`): domain events (e.g. `LeadCompleted`) dispatched onto
  a queue rather than direct synchronous hook calls.
- **International phone number support** (replaces the "GP Advanced Phone
  Field" Gravity Perks add-on): a dedicated phone-input/validation library
  (e.g. `giggsey/libphonenumber-for-php` server-side, paired with an
  international-aware phone input on the frontend).
- **Action log/trace** (replaces Gravity Forms' entry notes/history): an
  append-only `LeadActivityLog` recording every state transition against a
  lead (captured, enriched, routed, matched-to-partner, converted, purged).
- **Retention**: leads are retained for a default and minimum floor of 30
  days; admins can configure a *longer* window via WP admin, but not shorter
  than 30 days, per "needs to be stored for at least 30 days." A scheduled
  purge job (Acorn/WP-Cron command) enforces the configured window and never
  purges a lead younger than it. *(This 30-day-floor reading is this ADR's
  interpretation of the requirement — flag if a hard floor isn't intended.)*
- **Cross-context communication**: `Lead` talks to other bounded contexts
  asynchronously over a queue, not direct calls. Worked example from the
  requirement: `Lead` dispatches a `LeadCompleted` event onto the queue;
  `PartnerHub` registers a subscriber that consumes it, checks the lead's
  `sourceType`/`sourceID` (see below) against a tracked partner, and
  executes the relevant attribution/commission business rules in its own
  domain.
- **Scheduling runs only after lead creation**: the Calendly/Google
  Calendar booking flow (`Scheduling`'s `FetchAvailableSlotsAction`,
  `BookMeetingAction`) is not invoked directly by the booking wizard UI —
  it is triggered by a `LeadCreated` event dispatched once the `Lead`
  domain finishes capturing and persisting the lead. `Scheduling` registers
  a queue subscriber on `LeadCreated` and only then checks availability and
  creates the appointment, so every booked meeting is guaranteed to
  reference an already-persisted `Lead`.
- **`Lead.source` as an evolution of `AttributionEngine`**: `sourceType`/
  `sourceID` are not manually set — they are computed at lead-creation time
  by internal lead rules evolved from `Referral`'s existing
  `AttributionEngine`, reading the same signals it already reads today
  (`rl_referrer` cookie, `via`/`ref`/`r` query parameters) and reclassifying
  them into `sourceType` + `sourceID` on the `Lead` record. This requires
  adaptation in two existing domains:
  - **`Referral`**: `AttributionEngine`'s rule evaluation is invoked as part
    of `Lead`'s capture pipeline rather than only running independently at
    click-time; downstream `Referral` logic that currently derives
    attribution from the cookie directly (e.g. at payout time) should
    instead key off the `sourceType`/`sourceID` already stamped on the
    `Lead`, so there is one attribution decision per lead, not two.
  - **`PartnerHub`**: its `LeadCompleted` subscriber (above) validates and
    routes using `sourceType`/`sourceID` directly — e.g. when
    `sourceType == 'referral_hub'` or `'partnership'`, `sourceID` is looked
    up against `PartnerHub`'s own partner records — rather than
    independently re-deriving a partner match from raw request data.

## Tradeoffs & Impact on Prior Decisions

- **Reverses a specific mitigation in [ADR-0006](0006-parallel-staging-zero-downtime-cutover.md)**:
  the baseline risk table's stated mitigation for "Lead Generation
  Disruption" is "Gravity Forms ... preserved." This ADR replaces a known,
  battle-tested third-party plugin with new in-house code that must
  independently prove form validation correctness, spam/bot protection
  (built into Gravity Forms; absent by default in bespoke code),
  accessibility, and phone-number correctness across locales — a materially
  *higher*-risk approach to precisely the funnel ADR-0006 was trying to
  de-risk. This should go back to whoever owns that risk table for explicit
  re-approval, not be treated as a drop-in swap.
- **Extends [ADR-0004](0004-ddd-bounded-contexts.md)**: adds a 6th bounded
  context to the five already defined. It also *narrows* `Tracking`'s
  existing scope — `GravityFormsSubmissionSubscriber.php` and
  `GravityFormsHooks.php`, currently documented as `Tracking`'s
  responsibility, are superseded by `Lead`'s own capture/event pipeline;
  `Tracking` should be understood going forward as owning only the
  `CustomerIOClient`/`PostHogClient` gateways plus whatever `Lead` events it
  subscribes to.
- **Interacts with [ADR-0003](0003-livewire-4-reactive-ux.md)**: lead
  capture forms are a natural fit for a Livewire component, consistent with
  the reactive-funnel pattern already used for `MultistepBookingWizard`,
  rather than introducing a second rendering approach for forms.
- **Weakens a specific claim in [ADR-0003](0003-livewire-4-reactive-ux.md)**:
  sequencing Calendly/Google Calendar booking behind a queue-consumed
  `LeadCreated` event means booking confirmation can no longer complete
  synchronously within the same Livewire request/response cycle. This
  directly cuts against ADR-0003's "instant client-side validation (zero
  latency)" framing and the same-request booking flow shown in its
  sequence diagram — there is now an unavoidable gap, bounded by
  queue-worker latency, between "lead submitted" and "booking confirmed."
  The `MultistepBookingWizard` needs an explicit waiting/polling (or
  broadcast-driven) state for this gap; this is new UX scope the baseline
  sequence diagram doesn't account for.
- **Requires infrastructure not covered by [ADR-0002](0002-roots-bedrock-sage-acorn-stack.md)**:
  asynchronous cross-context events need a configured Laravel queue driver
  (database, Redis, or SQS) and at least one persistent queue worker process
  running in every environment. The baseline stack decision does not
  specify a queue driver or worker process anywhere — this is new
  operational surface area (a long-running process to provision and
  monitor), not just PHP-FPM handling web requests.
- **Impacts [ADR-0007](0007-three-week-parallelized-sprint-roadmap.md)**:
  a configurable form system, a HubSpot gateway, international phone
  validation, an audit-trail log, a configurable retention+purge job, and
  queue-based inter-domain events amount to a domain roughly comparable in
  scope to the other five combined. It does not fit inside the existing
  3-week roadmap without either extending the timeline or displacing other
  Sprint 2/3 scope — it needs to be sized and slotted explicitly.
- **Creates a new dependency between `Lead` and `Referral`/`PartnerHub`**
  (ADR-0004), now partially resolved: making `sourceType`/`sourceID` an
  evolution of `AttributionEngine` (rather than an independently-computed
  field) removes the double-attribution risk originally flagged here — one
  canonical decision is stamped per lead instead of two disagreeing ones.
  What remains an open question is *which domain owns the rule-evaluation
  logic itself*: does `AttributionEngine` relocate out of `Referral` into
  `Lead` (making `Referral` a downstream consumer of `Lead`'s stamped
  output), or does `Lead`'s capture pipeline call out to `Referral`'s
  existing `AttributionEngine` as a dependency (making `Lead` depend on
  `Referral`)? Either direction is workable, but leaving it undecided risks
  a circular dependency between the two domains — this should be picked
  explicitly before implementation starts.

## Consequences

- A single source of truth for "what is a lead and what happened to it"
  replaces today's fragmentation across Gravity Forms entries and each
  integration's independent copy.
- Removing Gravity Forms' HubSpot/Slack/Webhooks add-ons means losing their
  built-in spam protection, conditional-logic UI, and vendor-maintained API
  compatibility — the `Lead` domain must explicitly reimplement or consciously
  forgo each of these, not inherit them for free.
- Retention/purge becomes a testable, enforced policy (30-day floor, admin-
  configurable ceiling-less extension) instead of Gravity Forms' implicit
  indefinite persistence today.
- Adds a new always-on dependency (a queue worker process, e.g.
  `wp acorn queue:work`) whose failure mode — a stalled worker silently
  dropping cross-context events like `LeadCreated`/`LeadCompleted` — needs
  monitoring; this pairs with domain-tagged error observability but is not
  yet covered by any baseline ADR for the `Lead` domain specifically. A
  stalled worker now also means a submitted lead never gets its Calendly/
  Google Calendar meeting booked, not just a missed analytics event — this
  raises the severity of that failure mode above what a dropped tracking
  event alone would be.
- Every booking is traceable back to a specific `Lead` and its stamped
  `sourceType`/`sourceID` via `LeadActivityLog`, giving `Referral`/
  `PartnerHub` a single auditable attribution record per lead instead of
  reconstructing attribution from cookies at payout time.
