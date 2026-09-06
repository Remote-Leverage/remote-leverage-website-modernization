# ADR-0005: Native Gutenberg Blocks via ACF Composer Replace Elementor Widgets

- Status: Accepted (documented as-is from `README.md`); amended 2026-09-06
  — see "Amendment" section below
- Date: 2026-09-06 (recorded — original decision predates this record)
- Deciders: Adrián Salvatori (original author)

## Context

`README.md` §1 and §2 describe the legacy content-authoring model as a
"Heavy Elementor canvas with multi-level div nesting" across 36 bespoke
Elementor widgets, creating "Severe Page Builder Lock-in" where "minor
layout tweaks require brittle builder interfaces rather than predictable,
version-controlled design systems."

## Decision

Consolidate the 36 Elementor widgets into ~10–16 native Gutenberg blocks
(the proposal states both "~10" in §1/§4-adjacent text and "~16" in §2/§5;
recorded here as written, not reconciled) built with `log1x/acf-composer`
and rendered via Laravel Blade templates (`README.md` §5, migration matrix):

- `HeroBlock`, `BookingBlock`, `LiveCallBlock`, `TestimonialsBlock`,
  `DepartmentCardsBlock`, `ProcessStepsBlock`, `BenefitsGuaranteeBlock`,
  `AccordionFaqBlock`, `DataTableBlock`, `CtaBannerBlock` — each replacing a
  named set of legacy Elementor widgets, listed per-block in §5's migration
  matrix.
- Two additional legacy widget groups (`ArticleHeaderWidget` /
  `ArticleTableOfContentWidget` / `ArticleAuthorBioWidget` /
  `ArticleAlsoReadWidget` and `BlogIndexHeaderWidget` /
  `BlogIndexFilterWidget` / `BlogIndexGridWidget`) are replaced not by
  blocks but by native dynamic templates, `single.blade.php` and
  `index.blade.php` respectively — per §5, `single.blade.php` "Instantly
  upgrades 100+ VA Guides without touching post records."

## Tradeoffs & Impact on Prior Decisions

- Depends on ADR-0002 (Acorn 5 stack, which brings in `log1x/acf-composer`)
  and shares design tokens with the Tailwind CSS v4 configuration described
  in `README.md` §3.
- Shares the block/Livewire boundary noted in ADR-0003: several blocks
  (`BookingBlock`, `LiveCallBlock`) are documented as thin wrappers around
  Livewire components (`MultistepBookingWizard`, `InstantLiveCallButton`)
  rather than containing their own interactive logic.
- The "instant" claim for `single.blade.php` and `index.blade.php` assumes
  existing post records render correctly through the new template without
  modification — this ADR records that assumption as written in the
  proposal; it is not verified here.

## Consequences

- Content editors move from Elementor's visual canvas to native Gutenberg
  block insertion for all new content.
- Widget-to-block consolidation reduces the number of distinct authoring
  components a content editor must choose from (36 → ~10–16).

---

## Amendment (2026-09-06): AI-Assisted Elementor-Dependency Audit & Mandatory Gutenberg Migration

- Status: Proposed
- Proposed by: Hyller Bandeira

### Context

The baseline Decision above records, as written in `README.md` §5, that
`single.blade.php` "Instantly upgrades 100+ VA Guides without touching post
records." This assumes `the_content()` / `post_content` is directly
renderable through the new Blade templates. It is not, for any post
currently authored in Elementor: Elementor stores its layout as serialized
JSON in the `_elementor_data` post meta key and intercepts the `the_content`
filter itself to render that JSON — a filter `single.blade.php` /
`index.blade.php` do not register. Left unaddressed, either the page renders
blank/broken, or Elementor/Elementor Pro must stay active at runtime to
render it, which directly contradicts ADR-0001's clean-slate goal of
retiring the legacy page-builder dependency entirely.

### Decision

Add a mandatory workstream, gating the "Staged content cutover" step in
ADR-0006, before any post is switched onto `single.blade.php` /
`index.blade.php`:

1. **AI-assisted audit**: scan every existing post/page's `_elementor_data`
   and classify it as either no Elementor dependency (safe to cut over as
   documented in the baseline Decision) or Elementor dependency detected
   (widget types and counts recorded).
2. **AI-assisted patch (auto-conversion)**: for every flagged post, an
   AI-driven pass walks the Elementor widget tree and generates the
   equivalent native Gutenberg block markup, using the Elementor→Gutenberg
   mapping already defined in `README.md` §5 / this ADR's Decision section
   (e.g. `HeroWidget` → `HeroBlock`). Widgets without a defined mapping are
   converted to the closest available native block and explicitly flagged
   as unmapped.
3. **Mandatory human editorial review**: every AI-converted post — mapped
   and unmapped alike, with no confidence-based sampling — is queued for
   human sign-off before it is allowed to go live. No AI-converted post
   publishes unreviewed.
4. **Completion gate**: Elementor and Elementor Pro will never exists under 
   this project so there should be none posts carrying `_elementor_data`. The
   workstream is not done at "most posts migrated" — it is done only when 
   zero posts depend on Elementor at runtime.

### Tradeoffs & Impact on Prior Decisions

- **Amends this ADR's own baseline Decision**: the "instant cutover" framing
  for `single.blade.php` / `index.blade.php` no longer holds unconditionally
  — it holds only for posts the audit (step 1) already classifies as
  Elementor-free; every other post is contingent on steps 2–3 completing.
- **Reinforces ADR-0001** (clean-slate rebuild): the "no Elementor left"
  completion gate (step 4) makes full Elementor retirement a hard
  requirement of the clean-slate strategy, not an optional cleanup — a
  surviving Elementor dependency would make this a partial rebuild.
- **Impacts ADR-0006** (parallel staging cutover): the "Staged content
  cutover" step can no longer be treated as instant for any post still
  carrying Elementor data — it is now sequenced strictly after this
  audit/patch/review pipeline completes for that post.
- **Impacts ADR-0007** (roadmap): mandatory 100%-coverage human review (not
  a spot-check) of every AI-converted post is a materially larger effort
  than the single-day "VA Guides & Blog Cutover (Instant)" Sprint 3 task
  implies. With up to 100+ VA Guides potentially carrying Elementor data,
  this is its own review workstream and needs to be budgeted and scheduled
  explicitly, likely starting in Sprint 2 alongside the Gutenberg block
  library build, not squeezed into Sprint 3.
- **Extends the `ContentAudit` domain's scope** (ADR-0004): the existing
  `PrismAiAuditor` / `AuditMarkdownContentAction` were documented for
  content length/heading/keyword-density analysis. Elementor-dependency
  detection and block conversion is new responsibility for that domain and
  should be added to it deliberately rather than bolted on as an unrelated
  script.

### Consequences

- No post is considered cut over until a human has reviewed its
  AI-generated Gutenberg conversion — human review throughput (posts
  reviewable per day), not engineering throughput, becomes the limiting
  factor on how fast this workstream completes.
- Because the completion gate requires zero remaining Elementor dependency,
  a single unconverted or unreviewed post blocks deactivating
  Elementor/Elementor Pro entirely — the plugin-count reduction outcome
  (`README.md` §8, 27 → ~10 plugins) is all-or-nothing on this dependency,
  not something that can be partially realized.
- Unmapped widgets (step 2) surface a concrete, enumerable punch list of
  content gaps in the block library (ADR-0005's baseline block set) rather
  than being discovered ad hoc during manual review.
