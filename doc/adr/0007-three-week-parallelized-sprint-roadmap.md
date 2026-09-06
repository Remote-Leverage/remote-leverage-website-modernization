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

## Tradeoffs & Impact on Prior Decisions

- This is a scheduling/sequencing decision layered on top of ADR-0001
  through ADR-0006 — it does not introduce new technical scope, only an
  order and pace for delivering it.
- Testing work and the production cutover (ADR-0006) are both placed in
  Sprint 3, after all domain logic, Livewire components, and blocks from
  Sprints 1–2 are already built.

## Consequences

- All eight sprint-days across the three weeks are described in §6 as
  running largely in parallel within each sprint, rather than one
  deliverable strictly finishing before the next starts — dependent work
  (e.g. Gutenberg blocks depending on the Tailwind design tokens) still
  needs its prerequisite to land first regardless of the parallelized
  framing.
- The Gantt chart fixes a concrete start date (`2026-09-07`) for Sprint 1,
  making the 3-week window a calendar commitment rather than a relative
  estimate.
