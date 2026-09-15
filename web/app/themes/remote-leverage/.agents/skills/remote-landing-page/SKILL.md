---
name: remote-landing-page
description: >-
  Create or edit a landing page on a live WordPress environment (staging or production)
  over MCP, without a local WordPress install and without a theme deploy. Use when asked to
  build a page "like this other one but with this copy", to change the copy on an existing
  page, or to check how a landing page is performing.
---

# Remote landing pages over MCP

This skill is for working against a **running** environment through the `rl-staging`
or `rl-production` MCP server. Nothing here needs a local WordPress, a database, or
`npm run build` — the abilities operate on content that already exists on the site.

It is the counterpart to [`page-migration`](../page-migration/SKILL.md), which is
about building *new blocks and patterns* in the theme and does require local work
and a deploy. Pick this one when the design already exists and only the words change.

---

## Decide which job this is

| The ask | Use |
| --- | --- |
| "Like the Athena page but for Belay" | `clone-page` |
| "Change the headline on the pricing page" | `update-page-sections` |
| "A page with a hero, testimonials and a booking footer" | `create-landing-page` |
| "Is the Athena page converting?" | `lead-stats` |
| A new *section design* that no block produces | **Stop.** That is a theme change — use `page-migration`. |

The last row matters. These abilities assemble and re-word what the design system
already has; they cannot author a new block. If the request needs a section that no
existing block renders, say so rather than approximating it with the wrong block.

---

## The workflow

### 1. Resolve the page — never guess an ID

```
app/list-pages   { "search": "Athena" }
```

Post IDs differ between environments. An ID that was right on staging is a
*different page* on production. Always resolve on the environment you are about to
write to.

### 2. Read it before you change it

```
app/describe-page   { "post_id": 382 }
```

This returns the page's sections and their current field values. It is the only
source of valid `section` and `field` names — do not invent them, and do not reuse
names from a different page.

Field types tell you what is safe to touch:

- `text` — copy. Rewrite freely.
- `image_id` — an attachment ID. Changing it needs an ID that exists **on that
  environment**; do not copy one from elsewhere.
- `repeater_count` — how many rows a repeater has. Leave it alone unless you are
  deliberately adding or removing rows, and then set every row's fields too.

### 3. Write

```
app/clone-page {
  "source_post_id": 382,
  "title": "Compare Belay",
  "slug": "compare-belay",
  "overrides": [
    { "section": "hero",  "fields": { "headline": "…", "cta_text": "…" } },
    { "section": "#2.0",  "fields": { "text": "…" } }
  ]
}
```

### 4. Check `skipped` — always

Both write abilities return `applied` and `skipped`. An override naming a section
or field that does not exist lands in `skipped` and **was not written**.

Never report a page as done without reading `skipped`. A page whose overrides were
all skipped is a copy of the original with a new title, and it looks completely
successful if you only read `post_id`.

Also check `audit.clean`. If it is `false`, raw HTML leaked outside a block
delimiter and the page will show Gutenberg's "unexpected or invalid content"
recovery modal.

---

## Rules

**Draft by default.** `clone-page` creates a draft unless told otherwise. Do not
pass `status: "publish"` unless the person explicitly asked for the page to go
live. On production, leaving a draft for a human to review is the right default
even when you *can* publish.

**Say which environment you wrote to.** Every report should name it. "Created
draft 412 on production" and "Created draft 412 on staging" are very different
sentences.

**Section addresses come in two forms.** A name like `hero` is declared in the
pattern and is stable. A positional address like `#2.0` is derived from the
page's current structure and moves if the page is restructured — re-run
`describe-page` rather than reusing one from earlier in a conversation.

**A cloned page is not in git.** Most landing pages here are a single
`wp:pattern` reference whose content lives in `patterns/*.php`. A clone is
expanded markup in the database instead — that is what makes it editable without
a deploy, and it also means a database refresh will overwrite it. Fine for a
campaign page; mention it when the page is meant to last.

---

## Performance questions

```
app/lead-stats { "group_by": "landing_url", "since": "2026-09-01" }
```

Groups can also be keyed by `utm_source`, `utm_medium`, `utm_campaign`,
`referral_code`, `source_type` or `status`, and every filter accepts
`landing_url_contains` for tying results to one page.

Prefer `lead-stats` to `query-leads`. `query-leads` returns real customer names,
emails and phone numbers; reach for it only when the individual records are
genuinely what was asked for, and do not paste contact details into a summary
that did not ask for them. Both require the `rl_read_business_data` capability,
which is granted separately from page editing — a permission error here means the
agent user was never granted it, not that the query was wrong.
