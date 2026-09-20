# Environment sync — design

Bi-directional, dataset-selectable sync between local and staging, driven entirely
from wp-admin. **Production is a read-only source and never a target** — see §6.

This extends `app/Domains/Sync` rather than adding a second mechanism. See
[docs/ai-mcp-and-sync.md](ai-mcp-and-sync.md) for the existing settings/page sync
this builds on.

## Why this exists

The existing `wp rl:sync:*` commands already solve the transport problem: they call
the remote's Abilities REST API over HTTP, so the *receiving* environment never needs
WP-CLI. What they don't solve is **provisioning** — setting up the `sync-service`
user and its application password currently requires running `wp user create` and
`wp acorn rl:sync:grant` on the remote. Staging has no CLI access, so the credential
can never be created there and the whole mechanism is unreachable.

Everything else in this document follows from removing that one CLI dependency.

## Deliberate reversal of an existing principle

`config/rl-sync.php` currently says:

> Deliberately a whitelist, not a full wp_options dump — this environment's
> core/plugin option rows must never be overwritten by a sync.

This feature reverses that for the datasets you explicitly select. That is the point
of the feature, but it means the safety has to move somewhere else: into the dataset
model, the production gate, and the pre-import backup described below.

## Architecture

Three layers, each independently gated:

```
wp-admin (Settings → Environment Sync)
        │  local: push/pull controls          staging: status, history, restore
        ▼
App\Domains\Sync\SyncClient  ──HTTPS──▶  Abilities REST API on the remote
        │                                 /wp-json/wp-abilities/v1/abilities/{name}/run
        │                                 Basic auth as sync-service
        ▼
App\Domains\Sync\Transfer\*  ──▶  chunked upload, batched import, backup/restore
```

The transport, the `SyncClient`, and the `rl_manage_ai_sync` capability are reused
unchanged. What's new is provisioning, the dataset model, and the transfer engine.

## 1. Provisioning without WP-CLI

A **Generate sync credentials** action on the Settings screen, available in every
environment including production, requiring `manage_options`:

1. Creates the `sync-service` user if absent (role `subscriber`).
2. Grants `rl_manage_ai_sync` — the same grant `rl:sync:grant` performs, in PHP.
3. Mints an application password via `WP_Application_Passwords::create_new_application_password()`.
4. Displays it **once**, with the matching `.env` lines to paste locally.

This is the only piece that must exist before anything else works. It replaces the
CLI bootstrap entirely — no redeploy, no container shell, no secrets-manager round trip.

On production the screen is *only* this card: no push, no pull, no purge, no history,
and `EnvironmentSyncAdmin::handleActions()` refuses any action outside `provision` and
`revoke`. The card has to exist there because production is a pull source, and the
credential a lower environment authenticates with has to be mintable somewhere.
Provisioning is therefore the one sync surface that is not environment-gated; what the
credential can *reach* is decided by the abilities instead (§6).

> **Prerequisite: Application Passwords must be available on the target.** WordFence disables them
> by default, which makes every sync call fail as `rest_not_logged_in` — the same response as a
> wrong password, so it reads like a credential problem rather than a feature being switched off.
> `config/wordfence.php` keeps them enabled; if sync suddenly stops working, check the target's
> REST index first:
>
> ```bash
> curl -s "https://<host>/wp-json/" | jq .authentication
> # {} means disabled — no credential can authenticate
> ```
>
> See [known-issues.md](known-issues.md) entry 22.

Revoke is the same screen: delete the application password, drop the capability.

## 2. Dataset model

Each dataset is a named group with its own direction, exceptions, and pre-import
behaviour. Selection is per-run, not global config.

| Group | Tables | Default |
|---|---|---|
| `content` | `wp_posts`, `wp_postmeta`, `wp_terms`, `wp_termmeta`, `wp_term_taxonomy`, `wp_term_relationships` | selected |
| `media` | `attachment` rows + the referenced files under `uploads/` | selected |
| `leads` | `wp_rl_lead_profiles`, `wp_rl_lead_identifiers`, `wp_rl_leads`, `wp_rl_lead_activity_logs` | opt-in, **replaces the target** |
| `referrals` | `wp_rl_referrers`, `wp_rl_referrals`, `wp_rl_referral_clicks`, `wp_rl_referral_rewards`, `wp_rl_payouts` | **never transferred** |
| `scheduling` | `wp_rl_live_call_sessions` | **never transferred** |
| `settings` | the `config/rl-sync.php` option whitelist | opt-in |
| `users` | `wp_users`, `wp_usermeta` | **never transferred** |
| — | `wp_migrations`, `wp_comments`, `wp_commentmeta` | never synced, not selectable |

`referrals`, `scheduling` and `users` are transfer-excluded by design: they are either
real customer data or the credentials this tool runs on. They still appear on the
screen, but only under **Maintenance** (section 5) — you can purge them, on either
side, without ever copying them between environments.

`leads` is the exception, and a deliberate one. The marketing cost alert reads spend
from the BigQuery warehouse and compares it against this site's own lead counts, so an
environment with warehouse data and no leads makes every cross-check and every
cost-per-lead figure meaningless. Leads therefore transfer in both directions, as whole
rows — PII included, unredacted — and all four of the target's lead tables are **emptied
and replaced**, never merged. Nothing is emptied on the source, ever.

Leads are the only dataset that travels as table rows rather than through the
posts/meta pipeline. The marker is `Dataset::$transferTables`, which is set for leads
alone; `$tables` is the purger's list and every dataset populates it, so it says
nothing about how a dataset moves. The order inside `$transferTables` is load order:

```
load:  rl_lead_profiles -> rl_lead_identifiers -> rl_leads -> rl_lead_activity_logs
empty: rl_lead_activity_logs -> rl_leads -> rl_lead_identifiers -> rl_lead_profiles
```

Parents before children on the way in, the reverse on the way out. Only
`rl_lead_activity_logs.lead_id -> rl_leads.id` is a declared foreign key; the two
`profile_id` columns are not, which is exactly why the order is pinned by a test. A wrong
order on an enforced constraint raises an error, but a wrong order on an unenforced one
just leaves orphan rows that look fine until something reads them.

Comments are treated as disposable and never sync in either direction. The site is not
meant to support comments at all, so the sync has no reason to carry them; disabling
commenting site-wide is worth doing separately, outside this feature.

### Exceptions within content

Per-run exclusions, so a sync can skip things you're mid-edit on:

- by post type (`post`, `page`, `case_study`, `rl_partner`, `attachment`)
- by post ID
- by taxonomy

### Clean before import

Per-group toggle (`--clean=content,media` on the CLI). When on, the target's rows for
that group are deleted before the incoming rows are written, so the target ends up an
exact mirror rather than a merge. When off, rows are upserted by primary key and
anything extra on the target survives.

Off is a merge, and on two environments that grew independently a merge is rarely what
you want: import keeps the *source's* post IDs, so an ID holding unrelated posts on each
side resolves by overwriting the target's, while a page the source has under a different
ID survives beside its replacement under the slug the source has since reused. Staging
and local hit exactly this — local `vathankyou` is ID 126, staging's ID 126 was
`sales-virtual-assistants`. Clean is what makes "push everything" mean what it says.

**Implemented 2026-09-15.** The flag was plumbed end to end from the beginning — CLI,
admin screen, manifest validation, serialization to the remote — but nothing read
`shouldClean()`, so passing it was a silent no-op and every push was a merge. Anything
that "already ran with `--clean`" before that date did not clean.

What a clean deletes is bounded three ways, and each bound is load-bearing:

- **Only what the export replaces.** `Transfer\PostSelection` is the single definition
  of a dataset's rows, used by the exporting side to decide what to send and by
  `Import\DatasetCleaner` to decide what to delete. Per-run exclusions bind the clean
  too: a post type or ID the transfer was told to skip has nothing arriving to replace
  it, so deleting it would be a one-way loss rather than a mirror.
- **Only `wp_posts` and `wp_postmeta`.** The `content` group advertises the term tables,
  but nothing in the transfer actually carries terms — `ContentExporter` sends posts and
  postmeta and nothing else. Cleaning the term tables would therefore drop every category
  and tag with nothing to restore them. Taxonomy is left exactly as the target had it,
  which also means **category and tag assignments do not travel with a push.**
- **Rows, not files.** Uploaded files stay. The media transfer already asks the target
  which files it is missing and sends only those, so deleting them would force a
  re-upload of everything for no gain — and the attachment rows pointing at them are
  rebuilt from the source regardless.

Every deleted row goes into the session's undo log before it is deleted, so a clean is
reversed by the same `rl:sync:rollback` that reverses the import it precedes. This is the
opposite of `DatasetPurger`, which refuses to pretend a whole-table truncation is
undoable; the difference is that a clean is bounded by one dataset that is about to be
replaced, rather than by an open-ended table.

The clean runs lazily, on the first chunk of each dataset, rather than when the session
opens. That keeps each request bounded — emptying every selected dataset up front is the
one long request this design exists to avoid — and it means a transfer that fails before
sending anything leaves the target untouched. A session flag (`cleaned`) is persisted
*before* the first row is imported, because chunks are redelivered verbatim after a
dropped connection and a second clean would delete everything the earlier chunks wrote.

One consequence of running lazily: **a dataset the source has no rows for is never
cleaned**, because no chunk is ever sent for it. Cleaning `content` from a source with no
content leaves the target's content alone rather than emptying it.

## 3. Bi-directional

The same engine runs in both directions; only which side exports and which imports
changes.

- **Push** — local exports, staging imports.
- **Pull** — staging exports, local imports.

Pull is the safer direction (local is disposable) and is how you'd refresh local with
staging's current content. Both are initiated from the local screen, because local is
the only side that holds the remote's credentials. Staging's screen is read-only
status and restore — it never initiates a transfer.

## 4. Transfer engine

Sized for ~28MB of local data (23.7MB of it `wp_posts`) with no CLI on the far side,
which means no long-running process — every step has to fit inside a normal PHP
request and be resumable.

1. **Begin** — the importing side opens a session and records the manifest
   (groups, exceptions, flags). Returns a session id.

   *Implemented differently from the original plan:* this was specified as a
   full backup of every affected table before the first row lands. That cannot
   work on a receiving side with no CLI and no long-running process — dumping
   `wp_posts` inside one request is exactly what times out. Instead an **undo
   log** records each row's prior state immediately before it is overwritten.
   It is bounded by what actually changed rather than by table size, is written
   incrementally, and covers files as well as rows. Entries are written *before*
   the change, so a request dying between the two leaves an undo entry for a
   change that did not happen — harmless — rather than a change with no undo
   entry, which would be unrecoverable.
2. **Chunk** — the exporting side streams gzipped, base64-encoded batches. Each chunk
   is checksummed and acknowledged, so a dropped request retries just that chunk.

   **Batch size is per dataset**, because the two row shapes have nothing in common.
   A posts row drags its `post_content` and every attached meta row, so those move
   25 at a time (`Dataset::POST_BATCH_SIZE`). A lead row is a handful of narrow
   columns, and moves 100 (`Dataset::TABLE_BATCH_SIZE`).

   The figure matters more than it looks, because a batch is a whole HTTPS round
   trip and a transfer is entirely latency-bound. The first production leads pull
   ran at 25 and took **19,737 rows in 790 sequential requests, 15m39s** — about
   1.2s per request, nearly all of it waiting. 100 is not a tuned optimum, it is the
   ceiling `ExportTransferBatchAbility` clamps to; going higher means changing that
   clamp and redeploying both ends.
3. **Commit** — the importing side applies batches across repeated calls, each
   bounded well under `max_execution_time`, reporting progress. The admin screen
   polls until done.
4. **Rewrite** — a serialization-aware search-replace of `siteurl`/`home` across the
   imported rows, so a naive string replace can't corrupt serialized arrays.
5. **Finalise** — flush caches, report a row-count diff per group.

If a batch fails, the session stays open and its undo log intact — the pusher
deliberately does not tidy up, because cleaning up would discard the only thing
that can restore the target. `app/rollback-transfer` (or
`wp acorn rl:sync:rollback <session>`) reverts everything the session wrote,
files included. Logs are kept after a successful transfer too, since a sync that
worked but brought the wrong selection needs undoing just as much as one that
failed, and age out with the session's 20-run retention.

### Media

Attachment rows travel with the `media` dataset; the files travel after them.
The sender builds a manifest of every file behind those attachments, the target
answers with the subset it is missing or holds at a different checksum, and only
those are uploaded — so a re-sync after a partial run moves almost nothing.

An attachment is more than one file. WordPress generates a resized copy per
registered image size and records them in `_wp_attachment_metadata`, and it does
not rebuild them on demand. On this install that is the difference between 512
files and **1,599** (≈140MB): sending only the originals would leave every
srcset variant 404ing even though the attachment row and its main file arrived.

Files stream in 1MB chunks, written to a temporary name and moved into place
only once the whole file has arrived and its SHA-256 matches. An interrupted
transfer therefore leaves a stray `.rl-sync-part` file rather than a truncated
image that looks present and renders broken.

Paths are the dangerous input here — they arrive from another environment and
are joined onto the uploads directory to be written. `UploadPath` validates
rather than sanitises: absolute paths, traversal segments, stream wrappers, null
bytes, unsafe characters and any extension outside a small allowlist are
refused outright. A path that does not pass is never written and never even
requested.

## 5. Maintenance

Separate from transfer, and the only thing you can do to `referrals` and `scheduling`
(`leads` can also be transferred — see section 2):

- **Purge on this environment** — truncate the group's tables locally.
- **Purge on the remote** — same, over the sync channel.

Both require typing the environment name to confirm, and both are refused when either
side is production.

## 6. Production safety

Production is a **source**, never a target. The gates are shaped around that asymmetry
rather than around a blanket refusal:

1. The admin screen registers on production but renders only the credentials card, and
   `handleActions()` allows exactly `provision` and `revoke`. The push/pull AJAX
   endpoints are not registered there at all, so they answer `-1` rather than reaching
   a handler.
2. Every ability that **writes** — import, purge, rollback, receive-chunk,
   receive-media, begin, finish — extends `TransferAbility`, which refuses when
   `WP_ENV === 'production'` regardless of caller or capability.
3. The three abilities a pull calls on the far side — `export-transfer-batch`,
   `export-media-manifest`, `read-media-file` — extend `ReadOnlyTransferAbility`
   instead. They check the capability and not the environment. None of them touches
   the database or the filesystem.
4. `TransferPusher` refuses a target that is production by name, and refuses any
   target sharing a host with `PRODUCTION_SYNC_URL`. The admin dropdowns offer
   production as a source only.
5. `sync-service` on production holds `rl_manage_ai_sync` only if an admin pressed
   Generate. Nothing in the deploy grants it, and Revoke drops the capability along
   with the password.

Gate 2 is still the important one: it means production cannot be written to even by
someone holding a valid credential, and copying the staging code, the credentials and
the config wholesale still buys no write.

### Why this was widened

It used to be four gates and a flat "never in production". That also made production
unusable as a source, which is a different thing from protecting it as a target. The
marketing cost alert reads spend from the BigQuery warehouse and divides it by this
site's own lead counts, so an environment with real warehouse data and no leads
produces a cost-per-lead figure that is confidently wrong. Testing it needs production's
lead rows in local and staging, and there is no shell on either remote to get them out
another way.

The cost is real and worth stating plainly: a credential minted on production reads
production data, lead PII included and unredacted. It is created by hand, revoked from
the same screen, and should not be left live between pulls.

## 7. What this does not do

- **Schema changes.** `wp_migrations` is never synced. If local is ahead on schema,
  run migrations on the target first — a transfer into a stale schema is refused
  rather than half-applied.
- **Plugin/theme files.** Code ships through the deploy pipeline, not this.
- **Writes to production, in any form.** Production can be pulled *from*; nothing
  can be pushed, imported, purged or rolled back there.

## Decisions

1. **Attachment ID collisions — remap on import.** Source IDs are not preserved.
   The importer allocates new IDs on the target and rewrites every reference to the
   old ID: `post_content` (including block attribute JSON and serialized ACF values),
   `_thumbnail_id`, and any postmeta holding a bare attachment ID. Slower and more
   code than clobbering, but it can't destroy an attachment the target already had.
   The rewrite runs inside the same session as the import, so a failure rolls back to
   the step-1 backup rather than leaving half-remapped references.
2. **Backup retention — 20 runs.** Pruned oldest-first on the target's disk (EFS on
   staging) once the 21st run completes.
3. **Comments — never synced.** Moved out of `content` entirely, as above.

## Build order

1. Provisioning (section 1) — unblocks everything, useful on its own.
2. Dataset model + transfer engine, push only, `content` group.
3. Pull.
4. Media.
5. Maintenance/purge.
