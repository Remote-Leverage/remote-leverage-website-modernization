# ADR-0001: Clean-Slate Greenfield Rebuild (Not an Incremental Legacy Refactor)

- Status: Accepted (documented as-is from `README.md`)
- Date: 2026-09-06 (recorded — original decision predates this record)
- Deciders: Adrián Salvatori (original author)

## Context

`README.md` §1 states the production platform — Elementor/Elementor Pro, a
legacy child theme, and 8 bespoke plugins (`rl-elementor-blocks`,
`rl-referral-program`, `rl-customer-io`, `rl-partners-hub`,
`rl-join-live-call`, `rl-posthog-feature-flags`, `rl-social-kit`,
`rl-content-auditor`) — has outgrown its architecture: multi-megabyte DOM
footprints depress Core Web Vitals, business logic is scattered across
uncoordinated plugins with separate database interactions, and the page
builder creates lock-in against predictable, version-controlled design.

## Decision

Rather than incrementally patching the existing plugins/theme, initialize a
brand-new, clean-slate greenfield project ("Strategy: Clean-Slate Greenfield
Rebuild with Parallel Staging & Zero-Downtime Cutover," §1 subtitle) built on
the Roots enterprise stack, developed in parallel to the live site, and
cut over once ready (see ADR-0006 for the cutover mechanics).

## Tradeoffs & Impact on Prior Decisions

- This is the foundational decision of the proposal — ADR-0002 through
  ADR-0007 all depend on it (stack choice, DDD boundaries, UX framework,
  block library, and cutover plan only make sense in the context of a
  parallel greenfield build rather than an in-place refactor).
- No prior recorded decision exists to conflict with; this is the starting
  point.

## Consequences

- Two codebases (legacy production, new greenfield) must be maintained
  simultaneously until cutover completes — this is the direct cost of
  "clean-slate" over "incremental," accepted implicitly by the proposal.
- All 8 legacy plugins' business logic must be re-implemented in the new
  stack (see ADR-0004) rather than gradually modernized in place.
- The new codebase and CI/CD are established in
  `Remote-Leverage/remote-leverage-website-modernization`, unify the
  Bedrock root and Sage 11 theme into a single monorepo (per `plan.md`
  Phase 1).
