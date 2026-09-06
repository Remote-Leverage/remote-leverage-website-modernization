# ADR-0005: Native Gutenberg Blocks via ACF Composer Replace Elementor Widgets

- Status: Accepted (documented as-is from `README.md`)
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
