# Carrying Yoast SEO meta from production

Closes the first half of the **SEO parity** cutover gate in
[production-cutover.md](production-cutover.md) and §9 of the
[content migration checklist](content-migration-checklist.md).

Production runs Yoast SEO Premium 28.4; v2 now has **Yoast SEO (free) 28.5**, installed as a
Composer-managed plugin (`wp-plugin/wordpress-seo` — the repo's wpackagist mirror at
`repo.wp-packages.org`, the same namespace as `wp-plugin/google-site-kit`). Matching plugins
means production's `_yoast_wpseo_*` postmeta transfers as-is instead of being field-mapped into
a different plugin's schema.

**Yoast's settings are deliberately unconfigured.** The post archive canonical (`/guides/` vs
`/blog/`) is an open decision, and the redirect manager is Premium — a separate purchase.

The plugin is installed but **left inactive**: activating it opens Yoast's first-time
configuration wizard, which is exactly the configuration being deferred. Activate per
environment when someone is ready to answer it — `wp plugin activate wordpress-seo`. The
importer below does not need Yoast active to write the meta.

```bash
wp acorn content:import-seo --dry-run     # report, write nothing
wp acorn content:import-seo               # apply
```

## How it works

There is no database access to production, so the source is its REST API: with Yoast active,
every item carries a `yoast_head_json` object. `YoastMetaMapper` turns that rendered head back
into postmeta; `ImportYoastMetaCommand` fetches, matches and writes.

| Class | Does |
| :--- | :--- |
| `App\Domains\ContentAudit\Services\YoastMetaMapper` | Pure. Head object → `_yoast_wpseo_*` array, canonical rewriting, site-template detection |
| `App\Domains\ContentAudit\Commands\ImportYoastMetaCommand` | `content:import-seo` — fetch, match by slug, diff, write |

Keys written: `title`, `metadesc`, `canonical`, `meta-robots-noindex`, `meta-robots-nofollow`,
`meta-robots-adv`, `opengraph-title`/`-description`/`-image`, `twitter-title`/`-description`/`-image`.

**Idempotent.** The mapper owns that fixed key set, and the importer *deletes* any key the
mapping does not produce for a post, so a re-run converges instead of leaving stale overrides
behind. A second run reports everything as "already in parity".

### Options

| Option | Default | Why |
| :--- | :--- | :--- |
| `--source=` | `https://remoteleverage.com` | Production origin to read from |
| `--target=` | `home_url()` | Origin canonicals are rewritten onto |
| `--post-types=` | `page,post,case_study` | Local types to carry meta onto |
| `--dir=` / `--cache=` | — | Read / write captured `seo-<base>-<n>.json`, so a run is reproducible offline |
| `--strict-types` | off | Require the production post type to match (see case studies below) |
| `--all-social` | off | Carry OpenGraph/Twitter values even when they look like a site template |
| `--threshold=` | `0.05` | Share of the corpus at which a repeated social value reads as a template |
| `--dry-run` | off | Report, write nothing |

## The four things production's Yoast data does that the mapping has to handle

**1. Canonicals must be rewritten, or the copy de-indexes itself.** Every production canonical
is absolute (`https://remoteleverage.com/…`). Carried verbatim onto staging, every page would
tell Google the real page is on production. `YoastMetaMapper::rewriteUrl()` swaps the origin,
keeps path/query/fragment, treats `www.` and the bare host as one site, and leaves genuinely
external canonicals alone. 14 production pages — the duplicate `hire-va-*` landing variants —
canonicalise to the *bare* homepage with no path; those become `<target>/`.

**2. `yoast_head_json` reports rendered output, not postmeta.** Yoast emits a value whether it
came from a per-post override or a post-type template in Search Appearance. On production one
OpenGraph description covers **252 of 355 items** and one Twitter description covers all 103
that have one — that is configuration, not 252 editorial decisions, and freezing it into
postmeta would make a site-wide change unmakeable later.

`YoastMetaMapper::detectSiteDefaults()` flags any social value repeated across ≥5% of the corpus
(floor: 3 items) as a template and skips it, printing what it skipped so the value can be set
once in Search Appearance instead. `--all-social` overrides this. Title and meta description are
always carried: they are the parity fields the gate is about, and none of Yoast's site
configuration is being migrated alongside them.

**3. Yoast omits `canonical` on noindex URLs.** All 28 noindex items on production have no
`canonical` in their head object. That is Yoast being correct, not missing data — treat an
absent canonical on a noindex page as expected.

**4. Production has no `case_study` post type.** Its case studies are `page` entries served
under `/case-study/<slug>/` — the same path v2's CPT produces. Matching is therefore by slug
first within the same type, then across any production type; all 23 local case studies match
this way. `--strict-types` disables the fallback.

## Known trade-offs

- **SEO titles are written as rendered.** 79 production titles are the default
  `%%title%% %%sep%% %%sitename%%` template (a `- Remote Leverage` suffix). Writing the rendered
  string pins the site name into per-page meta: exact parity now, stale if the site name ever
  changes. Configuring the title template in Search Appearance and re-running would be the
  alternative — it is deliberately not done here because Yoast settings are unconfigured.
- **OpenGraph/Twitter image URLs stay on the production origin.** Bedrock serves uploads from
  `/app/uploads/`, so a rewritten media URL would 404 where the production one still resolves.
  Re-point these after the media library is migrated.
- **Only published production content is read.** Drafts and private items on production are not
  in the REST response.

## What the first dry run surfaced (2026-09-15)

187 of 355 production items match a local slug (45 pages, 119 posts, 23 case studies); 168
production pages have no local counterpart — the §2 role/industry templates and the orphaned
operational pages that are out of scope. No local item lacks a production counterpart.

Two things worth a decision before running it for real:

- **7 local pages would inherit `noindex` from production** — `signedup`, `vaonboardingform`,
  `payment`, `vastore5`, `referral-program`, `referral-program-thank-you-page-deposit`,
  `ecommerce-virtual-assistant`. The first four are form/thank-you pages where that is right.
  `referral-program` and `ecommerce-virtual-assistant` have been *rebuilt* in v2, so inheriting
  production's noindex would silently keep a rebuilt page out of the index.
- **The importer writes no site-level configuration**, by design. Yoast's Organization/WebPage
  schema settings — site name, social profiles, logo — and the post-type title/social templates
  still have to be set by hand (checklist §9), and the `/guides/` vs `/blog/` archive canonical
  has to be decided first.

## Tests

`tests/Unit/YoastMetaMapperTest.php` — 26 tests over the canonical rewriting, the meta
extraction, and the site-template detection. No network: the command's fetching is separated
from the mapping precisely so the mapping can be tested against fixture head objects.
