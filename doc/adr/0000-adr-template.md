# ADR-0000: ADR Template & Process

- Status: Accepted
- Date: 2026-09-06
- Deciders: Hyller Bandeira

## Context

The Architecture Proposal & Strategic Migration Plan (`README.md`) bundles
many significant, hard-to-reverse decisions (stack choice, DDD boundaries,
UX framework, content-authoring model, cutover strategy) into a single
narrative document. Before any new suggestion is proposed, the decisions
already made need to be recorded individually, as-is, so a reviewer can
reference and reason about one decision at a time.

## Decision

Adopt lightweight Architecture Decision Records (ADRs) under `/doc/adr`, one
file per significant decision, numbered sequentially (`NNNN-kebab-title.md`).
Each ADR uses this shape:

```
# ADR-NNNN: Title

- Status: Proposed | Accepted | Superseded by ADR-XXXX
- Date: YYYY-MM-DD
- Deciders: ...

## Context
## Decision
## Tradeoffs & Impact on Prior Decisions
## Consequences
```

ADR-0001 through ADR-0007 document the decisions already present in
`README.md` **as they currently stand** — they do not change, reinterpret,
or critique those decisions. Their "Tradeoffs & Impact on Prior Decisions"
sections note relationships/dependencies between the baseline decisions
themselves, since nothing is being changed yet. Any ADR proposing an
amendment to one of these baseline decisions is numbered after them and
must state which baseline ADR(s) it affects and what changes as a result.

## Tradeoffs & Impact on Prior Decisions

None — this ADR only establishes process and does not alter any technical
decision in the existing proposal.

## Consequences

- Future architectural changes to this project should be proposed as a new
  ADR that references the baseline ADR(s) it amends, rather than a silent
  edit to `README.md`.
- `README.md` should eventually link to the ADRs once amendments are agreed.
