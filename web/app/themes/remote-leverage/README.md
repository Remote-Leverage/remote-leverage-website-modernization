# remote-leverage (theme)

The Roots Sage 10 theme that *is* the Remote Leverage v2 application. Acorn runs the Laravel container inside it, so this directory holds not just templates and assets but the whole domain layer, the admin screens, the migrations and the test suite.

Repository-level context, stack table and cutover status: [repository README](../../../../README.md).
All documentation: [`docs/`](docs/README.md).

## Layout

```
app/
├── Ai/                 MCP-callable landing-page abilities
├── Application/        Livewire components · HTTP controllers · middleware
├── Blocks/             56 ACF Composer blocks (Blade views in resources/views/blocks)
├── Domains/            8 bounded contexts — the business logic
├── Fields/             ACF field groups
├── Infrastructure/     Service providers · migrations · console commands · WP admin & hooks
├── Support/            BlockDefaults · MediaLibrary · DocumentOutline · PageChrome · PageRobots ·
│                       ResponsiveImage · CaseStudySubnav · HeaderMode · SocialKit · Pattern · ReadingTime
├── View/               Blade composers · nav walkers
├── filters.php         WP filters
└── setup.php           Theme supports, asset pipeline, template_redirect hooks

config/                 services · redirects · post-types · rl-sync · ai · ai-wordpress · sentry ·
                        gated-assets
patterns/               102 Gutenberg block patterns
resources/              css/ (app, blog, editor) · js/ · views/ · fonts/ · images/
routes/                 web.php · api.php   (Acorn routes — WP pages do NOT pass through these)
tests/                  Pest: Unit/ + Feature/
```

## Commands

Run from this directory.

```bash
npm run dev            # Vite dev server + HMR
npm run build          # production assets + optimizes resources/images/pages/ -> public/images/
./vendor/bin/pest      # 774 tests, 3677 assertions
./vendor/bin/pint      # format   (--test to check only)
```

WP-CLI / Acorn commands (`wp acorn <name>`):

| Command | Purpose |
| :--- | :--- |
| `lead:purge {--days=}` | Delete leads past the retention window (30-day floor enforced) |
| `lead:process-abandoned {--hours=2}` | Mark stalled leads abandoned, dispatch `LeadAbandoned` |
| `content:audit-elementor {--post_id=}` | Report Elementor → Gutenberg conversion readiness |
| `content:convert-elementor {--dry-run} {--post_id=}` | Convert, queued for human review |
| `content:import-posts` | Import blog posts from captured production JSON |
| `rl:sync:push` / `rl:sync:pull` | Transfer datasets between environments |
| `rl:sync:settings` / `rl:sync:page` | Sync whitelisted options / a single landing page |
| `rl:sync:purge` / `rl:sync:rollback` / `rl:sync:rollback-local` | Maintenance and undo |
| `rl:sync:grant <user>` | One-time: grant `rl_manage_ai_sync` |
| `rl:deploy` | Post-deploy migrations + rewrite flush under a cross-container lock |

Details: [`docs/local-development.md`](docs/local-development.md).

## Conventions that are not optional

1. **1380px canonical container.** Every top-level section wrapper: `w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8`, and every root `wp:group` declares `"layout":{"type":"constrained","contentSize":"1380px"}`. Never `max-w-7xl`, `1140px` or `1200px`.
2. **Bold (700) is the heaviest weight.** No `font-extrabold`, no `font-black`.
3. **Reuse before you build.** 56 blocks already exist — read [`docs/block-inventory.md`](docs/block-inventory.md) before writing markup, and extend a block with an option rather than forking it. `tests/Unit/PatternBlockReuseTest.php` fails the build on hand-written card markup without a `// @bespoke:` justification. Regenerate the index with `wp acorn blocks:inventory`.
4. **Bespoke sections are ACF blocks, never raw HTML inside `core/group`.** Raw markup inside container blocks triggers Gutenberg's "unexpected or invalid content" recovery modal. Blade inside an ACF block cannot.
5. **Page content is authored as a `*-full.php` pattern in git**, then applied to the page — not edited in the database.
6. **A migration is verified by screenshot diff against production**, not by matching headings and copy. See [`docs/page-migration-and-design-system-workflow.md`](docs/page-migration-and-design-system-workflow.md).

Full rationale and the migration workflow: [`docs/page-migration-and-design-system-workflow.md`](docs/page-migration-and-design-system-workflow.md) and [`docs/design-system.md`](docs/design-system.md).
