# Carrying Yoast SEO meta from production

Closes the first half of the **SEO parity** cutover gate in
[production-cutover.md](production-cutover.md) and §9 of the
[content migration checklist](content-migration-checklist.md).

Production runs Yoast SEO Premium 28.4; v2 now has **Yoast SEO (free) 28.5**, installed as a
Composer-managed plugin (`wp-plugin/wordpress-seo` — the repo's wpackagist mirror at
`repo.wp-packages.org`, the same namespace as `wp-plugin/google-site-kit`). Matching plugins
means production's `_yoast_wpseo_*` postmeta transfers as-is instead of being field-mapped into
a different plugin's schema.

**Status: done locally on 2026-09-15.** The plugin is **active**, configured deliberately
rather than through the first-time wizard, and the import has been run for real — 190 local
items carry production's meta. Everything below records what was decided and how it was
verified, so the same thing can be reproduced on staging and production.

```bash
wp acorn content:import-seo --dry-run     # report, write nothing
wp acorn content:import-seo               # apply — safe to re-run, converges to 0 changes
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
| `--force-index=` | `referral-program,ecommerce-virtual-assistant` | Slugs that keep their own indexability whatever production says (see below) |
| `--threshold=` | `0.05` | Share of the corpus at which a repeated social value reads as a template |
| `--dry-run` | off | Report, write nothing |

## The five things production's Yoast data does that the mapping has to handle

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

**5. Two production pages' `noindex` is stale, and that had to survive a re-run.**
`referral-program` and `ecommerce-virtual-assistant` were rebuilt in v2, so production's
`noindex,nofollow` on the pages that used to hold those slugs describes the old pages, not the
new ones. Carrying it across would have kept two rebuilt pages out of the index.

Deleting the meta by hand afterwards would not have held: the importer is meant to be
re-runnable, and the next run would have written the `noindex` straight back. So the exclusion
is an option on the command — `--force-index=`, defaulted to exactly those two slugs, so a
plain `wp acorn content:import-seo` does the right thing with no arguments. The mapper drops
the whole suppression for a forced slug (`meta-robots-noindex`, `-nofollow` and `-adv`
together): production hid those pages in one gesture, and it is that gesture that went stale.
Everything else production says about them — title, meta description, social — is still
carried. A forced slug ends up with no stored canonical either, because Yoast withholds the
canonical on a noindex URL (point 3 above); Yoast's own self-canonical is the right value once
the page is indexable again, and it resolves to the local host. The command prints the list it
is honouring, and warns when a `--force-index` slug matches no local post, since a typo would
otherwise fail silently.

`tests/Unit/YoastMetaMapperTest.php` covers it in 9 tests, including that the other five
noindex pages stay suppressed and that an empty list carries every directive.

## Known trade-offs

- **SEO titles are written as rendered.** 79 production titles are the default
  `%%title%% %%sep%% %%sitename%%` template (a `- Remote Leverage` suffix). Writing the rendered
  string pins the site name into per-page meta: exact parity now, stale if the site name ever
  changes. The alternative — leave those 79 titles unset and let Yoast's `title-page` /
  `title-post` template render them — was rejected because `%%sitename%%` resolves to whatever
  `blogname` happens to be in the environment, which is `Remote Leverage v2` locally. Exact
  parity beat a template that renders differently per environment.
- **OpenGraph/Twitter image URLs stay on the production origin.** Bedrock serves uploads from
  `/app/uploads/`, so a rewritten media URL would 404 where the production one still resolves.
  Re-point these after the media library is migrated.
- **Only published production content is read.** Drafts and private items on production are not
  in the REST response.

## Yoast's configuration (applied 2026-09-15)

Set with `wp option patch update` rather than by clicking through the first-time configuration
wizard, so each value is a recorded decision and the same commands reproduce it per
environment. `configuration_finished_steps` is then marked complete so nobody later runs the
wizard and overwrites these by accident.

| Option | Value | Why |
| :--- | :--- | :--- |
| `wpseo_titles.company_or_person` / `company_name` / `company_alternate_name` | `company` / `Remote Leverage` / `RemoteLeverage` | Mirrors production's `Organization` schema node, read off its rendered `yoast-schema-graph` |
| `wpseo_titles.website_name` | `Remote Leverage` | Production's `WebSite` node name |
| `wpseo_titles.org-email` / `org-description` | production's values | Same node |
| `wpseo_titles.company_logo` | production's `Logo-Square-1024-x-1024-px.png` URL | **No local attachment exists** — the media library is deliberately not ported. Re-point at a local upload before cutover, the same follow-up the OpenGraph images need |
| `wpseo_social.*` | Facebook, X (`Remote_Leverage`), LinkedIn, Instagram, YouTube | Production's Organization `sameAs` list |
| `wpseo.enable_xml_sitemap` | `true` | See the sitemap decision below |
| `wpseo.enable_index_now` | `false` | IndexNow would ping Bing from a pre-cutover copy and advertise URLs on the wrong host |
| `wpseo.configuration_finished_steps` | all three steps | Closes the wizard deliberately |
| `wpseo.tracking` | `false` (unchanged), `toggled_tracking` `true` | Answers the prompt rather than leaving it pending |

**Not touched, on purpose:** `ignore_search_engines_discouraged_notice`. Locally `blog_public`
is `0` because `DISALLOW_INDEXING` is set for every non-production environment, and Yoast's
"search engines discouraged" notice is a true and useful statement of that. Dismissing it would
hide a real signal on some future environment.

**Redirects stay in `config/redirects.php`.** Yoast Premium is not being bought, so the
redirect manager is not configured and nothing depends on it.

### `/blog/` is the canonical post archive

Decided 2026-09-15. This is what production already does, so it needs no Yoast override — it
needs confirming that nothing contradicts it:

- Permalinks are `/blog/%postname%/` and `page_for_posts` is the `blog` page (ID 799).
- `config/redirects.php` 301s `guides` and `vaguides` onto `blog`; production 301s `/guides/`
  onto `/blog/` too. There is no local `guides` page for the import to land meta on — the
  production `guides` page is one of the 165 with no local counterpart.
- After the import, page 799 carries `_yoast_wpseo_canonical =
  https://remoteleverage-v2.test/blog/`, carried from production's own `/blog/` canonical.
- The generated sitemap lists `/blog/` and 119 `/blog/<slug>/` posts, and no `/guides/` URL.

### The sitemap: Yoast's `/sitemap_index.xml` wins

`web/robots.txt` now points at `https://remoteleverage.com/sitemap_index.xml`.

Activating Yoast settles this on its own: with `enable_xml_sitemap` on, Yoast serves
`/sitemap_index.xml` and **301s core's `/wp-sitemap.xml` onto it**. Verified locally — the same
pair of responses production has been serving for years. Three reasons to let it win rather
than disabling Yoast's sitemap to keep core's:

1. **It is the URL Search Console already knows.** Production has always served
   `/sitemap_index.xml`; core's `/wp-sitemap.xml` 301s onto it there too. Choosing core's would
   change the submitted sitemap URL at exactly the moment the site changes underneath it.
2. **Core's sitemap does not know about the meta this import just wrote.** `wp-sitemap.xml`
   lists every published post; it has no concept of `_yoast_wpseo_meta-robots-noindex`. It
   would advertise all eight noindexed pages for crawling while their markup says not to index
   them. Yoast's sitemap reads that postmeta and leaves them out — confirmed below.
3. **Nothing is lost.** The 301 means an old `/wp-sitemap.xml` reference still resolves.

Note this is not affected by `DISALLOW_INDEXING`: core suppresses `/wp-sitemap.xml` entirely
when `blog_public` is `0` (it 404s locally without Yoast), while Yoast serves its sitemap
regardless. That is convenient for verification and changes nothing on production.

## The real run (2026-09-15)

| | Before | After |
| :--- | ---: | ---: |
| Posts carrying any `_yoast_wpseo_*` meta | 0 | 190 |
| `_yoast_wpseo_*` postmeta rows | 0 | 863 |

The first run matched 187 of 355 production items and changed all 187. Three more local pages
(`services`, `store`, `contractoragreement`) were created by other work while the verification
was running and were picked up by the following runs, taking the match count to 190.

| Key | Rows |
| :--- | ---: |
| `title`, `metadesc` | 190 each |
| `canonical` | 180 |
| `opengraph-image` | 171 |
| `twitter-title` | 68 |
| `opengraph-title` | 47 |
| `meta-robots-noindex` | 8 |
| `opengraph-description` | 5 |
| `meta-robots-nofollow` | 4 |

180 canonicals rather than 190 is exactly right: the 8 noindex pages have none (Yoast withholds
it) and so do the 2 force-indexed pages, which were noindex on production.

### Canonicals point at the local host

The single most damaging thing this import could do is carry production canonicals verbatim, so
it was checked as a whole rather than sampled:

- `_yoast_wpseo_canonical` rows whose value contains `remoteleverage.com`: **0**.
- Distinct canonical hosts across all 180 rows: **`remoteleverage-v2.test`**, and nothing else.
- The only `_yoast_wpseo_*` values still on the production origin are the 171
  `opengraph-image` URLs — the documented trade-off, not a rewrite failure.

### Spot checks

Eleven items across all three post types were compared against a **freshly fetched**
`yoast_head_json` — a separate request, not the payload the importer used — on title, meta
description, canonical (production's value with the origin swapped), noindex, nofollow and
OpenGraph image. All matched.

`page/about-us`, `page/vapricing`, `page/blog`, `post/how-to-delegate-any-task`,
`post/project-manager-cost`, `post/outsourcing-customer-service`, `case_study/chick-fil-a`,
`case_study/double-close`, `page/signedup`, `page/referral-program`,
`page/ecommerce-virtual-assistant`.

Case studies are the interesting ones: production has no `case_study` post type, so those meta
came from production *pages* under `/case-study/<slug>/` and their canonicals rewrote onto v2's
CPT permalinks, e.g. `https://remoteleverage-v2.test/case-study/chick-fil-a/`.

### Indexability, verified per page and not by grepping the HTML

The local site emits a site-wide `noindex` because `DISALLOW_INDEXING` is set for every
non-production environment (known-issues #4), so the rendered markup cannot tell a page-level
directive from the environment-level one. Two checks that can:

**Yoast's own computation.** `YoastSEO()->meta->for_post()` in a throwaway CLI script with two
environment obstacles lifted *in that process only* — `yoast_seo_development_mode` (Yoast
refuses to build indexables outside production) and Yoast's site-wide `wpseo_robots_array`
filter, which it registers at load time when `blog_public` is `0`. Nothing in the theme is
involved, and no indexability control was added anywhere: the indexable rows the check created
were deleted afterwards, leaving the table empty as Yoast expects outside production.

| Page | `is_robots_noindex` | Yoast robots | Yoast canonical |
| :--- | :--- | :--- | :--- |
| `about-us` (control) | NULL | `index,follow,…` | `…-v2.test/about-us/` |
| `blog` (control) | NULL | `index,follow,…` | `…-v2.test/blog/` |
| **`referral-program`** | NULL | `index,follow,…` | `…-v2.test/referral-program/` |
| **`ecommerce-virtual-assistant`** | NULL | `index,follow,…` | `…-v2.test/ecommerce-virtual-assistant/` |
| `signedup` | true | `noindex,follow` | — |
| `vaonboardingform` | true | `noindex,follow` | — |
| `payment` | true | `noindex,follow` | — |
| `vastore5` | true | `noindex,nofollow` | — |
| `referral-program-thank-you-page-deposit` | true | `noindex,nofollow` | — |

NULL is Yoast's "inherit the post-type default", which is index — the correct shape for an
indexable page, and the same as the two controls. It also shows the self-canonical the two
excluded pages get without a stored one, on the local host.

**The sitemap, independently.** `/page-sitemap.xml` lists `/referral-program/` and
`/ecommerce-virtual-assistant/` and omits all eight noindexed pages. This path reads the
postmeta directly, so it agrees with the table above without depending on indexables at all.

### Idempotency

The first re-run was **not** clean, and the reason was worth finding. Yoast registers a
sanitise callback on its own meta keys, and the canonical goes through `esc_url_raw()`, which
percent-encodes non-ASCII. One production canonical contains a Hawaiian ʻokina
(`/blog/how-kuʻulei-found-…/`), so the value the mapper produced could never equal the value
stored a moment later: every run reported that row as changed, rewrote it, and set the next run
up to do the same. It only appeared once Yoast was **active** — the dry runs, taken with the
plugin inactive, could not have caught it.

`ImportYoastMetaCommand::asStored()` now puts the mapped values through `sanitize_meta()` before
comparing, so the comparison is against what `update_post_meta()` will actually store. With
Yoast inactive nothing is registered for those keys and the values pass through untouched,
which is equally correct — nothing transforms them on write either.

A subsequent run reports **0 changed, 190 already in parity**, with no accumulated overrides:
the postmeta snapshot before and after is identical.

## Still open

- **Three pages built in v2 today inherited production's `noindex` and nobody has ruled on
  them.** `services`, `store` and `contractoragreement` did not exist when the exclusion
  decision was made — they were created while this import was being verified. All three are
  noindex on production, and all three are named in
  [production-cutover.md](production-cutover.md) as pages "being built as real v2 pages", which
  is the same reasoning that excluded `referral-program` and `ecommerce-virtual-assistant`. The
  default was deliberately **not** widened without a decision. If they should be indexable:

  ```bash
  wp acorn content:import-seo --force-index=referral-program,ecommerce-virtual-assistant,services,store,contractoragreement
  ```

  and move that list into the option's default in `ImportYoastMetaCommand` so plain re-runs keep
  honouring it.
- **`company_logo` and the 171 OpenGraph image URLs still point at production.** Both wait on
  the media library, and both are one option/meta update once it lands.
- **SEO titles are stored as rendered**, including the `- Remote Leverage` suffix on the 79
  pages that use production's default title template. See the trade-off above.
- **`blogname` is `Remote Leverage v2` locally.** It feeds `%%sitename%%` in the title templates
  that archives, search and 404 use. Harmless locally; it must read `Remote Leverage` on
  production.
- Re-run the import and re-apply this configuration **per environment** — the postmeta is
  content, so a database refresh from production would carry it, but a fresh v2 database will
  not.

## Tests

`tests/Unit/YoastMetaMapperTest.php` — 35 tests over the canonical rewriting, the meta
extraction, the site-template detection and the force-indexed slugs. No network: the command's fetching is separated
from the mapping precisely so the mapping can be tested against fixture head objects.
