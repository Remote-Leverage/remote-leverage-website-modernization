# ADR-0007: Three-Week Parallelized Sprint Roadmap

- Status: Accepted (documented as-is from `README.md`)
- Date: 2026-09-06 (recorded — original decision predates this record)
- Deciders: Adrián Salvatori (original author)

## Context

Having decided what to build (ADR-0001–0006), the proposal also fixes how
the work is sequenced and paced: `README.md` §6, "Fast-Track Delivery
Roadmap," states the intent "To eliminate unnecessary delays."

## Decision

Structure delivery into three parallelized 1-week sprints (`README.md` §6,
Gantt chart, dates 2026-09-07 through 2026-09-26):

- **Sprint 1 — Foundation & Core Domains** (Days 1–5): Bedrock/Sage 11
  scaffold, Tailwind v4 design tokens & Blade shell, tracking/attribution
  pipeline, Referral & Scheduling domains (DDD) — largely run in parallel
  rather than sequentially within the sprint.
- **Sprint 2 — Reactive UI & Blocks** (Days 6–10): Livewire 4 funnels
  (booking, live call), Partner Portal & Hub directory, Gutenberg ACF
  blocks library, dynamic `single.blade.php` & archives.
- **Sprint 3 — Staged Cutover & Launch** (Days 11–15): VA Guides/Blog
  cutover, Homepage & primary landing pages, end-to-end tracking/webhook
  QA, production DNS cutover & go-live.

`plan.md` mirrors this structure as a phase-by-phase execution tracker
(Phases 1–8, each tagged to a sprint) with checkbox-level granularity per
deliverable.

## Tradeoffs & Impact on Prior Decisions

- This is a scheduling/sequencing decision layered on top of ADR-0001
  through ADR-0006 — it does not introduce new technical scope, only an
  order and pace for delivering it.
- Testing work (§6/`plan.md` Phase 7) and the production cutover (ADR-0006,
  `plan.md` Phase 8) are both placed in Sprint 3, after all domain logic,
  Livewire components, and blocks from Sprints 1–2 are already built.

## Consequences

- Per `plan.md` at time of writing: Phase 1 (Foundation & Infrastructure)
  and Phase 2 (Core Domain Architecture) are marked 100% complete, Phase 3
  (Design Tokens & Blade Shell) is marked 0% complete in the phase-summary
  table even though its sub-checklist items are all checked, and Phases 4–8
  are 0% complete — recorded here as a factual observation of the current
  tracker state, not evaluated further per this review's agreed scope.
- The Gantt chart's start date (`2026-09-07`) is in the future relative to
  `plan.md` already showing Phases 1–3 work as complete; this
  status/timeline relationship is noted for whoever owns `plan.md` but is
  out of scope for this architecture-focused ADR set.
