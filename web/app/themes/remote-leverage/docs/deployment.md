# Deployment

Two AWS environments exist: **staging** (`staging.remoteleverage.com`) and a production **preview** host (`production.remoteleverage.com`). Apex `remoteleverage.com` stays on the legacy site until DNS is under our control and we cut over.

Runtime secrets are **edited in GitHub Environment secrets** and copied into Secrets Manager. ECS still reads Secrets Manager at runtime. `ACF_PRO_KEY` is also a repository secret so pull-request CI can install ACF (PRs have no AWS OIDC).

```mermaid
flowchart LR
    subgraph github [GitHub]
      EnvSecrets[Environment secrets]
      ACF[ACF_PRO_KEY repo secret]
      Sync[sync-app-secrets / deploy]
      CI[PR CI and image build]
    end
    subgraph aws [AWS]
      SM["Secrets Manager wordpress-*/app"]
      ECS[ECS task]
      RDS[Aurora MySQL]
      EFS[EFS uploads]
    end
    EnvSecrets --> Sync
    Sync --> SM
    ACF --> CI
    SM --> ECS
    ECS --> RDS
    ECS --> EFS
```

---

## Pipeline

```mermaid
flowchart LR
    PR["Pull request → main"] --> CI["ci.yml<br/>Composer · Pint · Pest · Vite build"]
    PUSH["Push to main"] --> CI2["ci.yml (reused via workflow_call)"]
    CI2 --> BLD["docker build --platform linux/amd64"]
    BLD --> ECR["push :staging and :SHA to ECR"]
    ECR --> ECS["aws ecs update-service --force-new-deployment"]
    ECS --> WAIT["aws ecs wait services-stable"]
    ECS --> ENTRY["entrypoint.sh on each task"]
    ENTRY --> DEPLOY["wp acorn rl:deploy<br/>(MySQL named lock)"]
```

Nothing deploys without CI passing — both deploy workflows declare `needs: ci`.

| Workflow | Trigger | GitHub Environment | Image tag |
| :--- | :--- | :--- | :--- |
| `deploy-staging.yml` | Push to `main` or `workflow_dispatch` | `staging` | `staging` |
| `deploy-production.yml` | Git tag `v-YYYYMMDD-vN` | `production` (required reviewers) | `production` plus the release tag |

Production is not auto-deployed from `main`. Push a tag such as `v-20260916-v1` (then `v2`, `v3`, `v12` for later deploys that day). CI runs first; the **Build and deploy** job waits for a GitHub Environment approval before it builds or touches ECS. The image is pushed as `:production` (what ECS runs), `:<release tag>`, and `:<git sha>`.

## CI (`.github/workflows/ci.yml`)

Runs on every PR to `main`, and is reused by the deploy workflows via `workflow_call`.

**Job `php`** — PHP 8.4, Composer cache keyed on both lockfiles:

1. Configure ACF Pro Composer auth from the `ACF_PRO_KEY` secret — **fails fast if the secret is missing**, because a silent ACF install failure produces a broken image.
2. `composer install` at the Bedrock root, then in the theme.
3. `vendor/bin/pint --test`
4. `vendor/bin/pest`

**Job `assets`** — Node 22, npm cache, `npm ci`, `npm run build`.

Concurrency is `ci-${{ github.ref }}` with `cancel-in-progress: true`, so superseded PR runs stop.

## Deploy staging (`.github/workflows/deploy-staging.yml`)

Triggers on push to `main` or manually (`workflow_dispatch`). Concurrency group `deploy-staging` with `cancel-in-progress: false` — deploys queue rather than cancelling each other.

AWS auth is OIDC (`id-token: write`), assuming `AWS_ROLE_ARN`; there are no long-lived AWS keys in the repo. GitHub Actions does **not** have `GetSecretValue` on the app secret.

| Variable | Default |
| :--- | :--- |
| `AWS_REGION` | `us-east-1` |
| `AWS_ROLE_ARN` | `arn:aws:iam::742621604050:role/wordpress-staging-github-actions` |
| `ECR_REPOSITORY` / `ECS_CLUSTER` / `ECS_SERVICE` | `wordpress-staging` |
| `IMAGE_TAG` | `staging` |

Each image is pushed twice — `:staging` and `:<git sha>` — so a rollback is a tag change, not a rebuild.

## Deploy production (`.github/workflows/deploy-production.yml`)

Triggered only by a git tag matching **`v-YYYYMMDD-vN`** — for example `v-20260916-v1`. Same-day follow-ups are `v2`, `v3`, `v12`, any positive integer. Tags that do not match are ignored; the validate job also rejects anything that is not eight date digits and `-v` plus one or more digits.

```bash
git tag v-20260916-v1
git push origin v-20260916-v1
```

The **Build and deploy** job uses GitHub Environment `production`, so it does not start until a required reviewer approves it in the Actions UI. Reviewers must have write access on the repo. Environment vars: `AWS_ROLE_ARN`, `ECR_REPOSITORY`, `ECS_CLUSTER`, `ECS_SERVICE`. Both builds keep using `secrets.ACF_PRO_KEY`.

OIDC for this stack is limited to `environment:production` (not `ref:refs/heads/main`). Reuse the account provider `arn:aws:iam::742621604050:oidc-provider/token.actions.githubusercontent.com`.

## The image (`Dockerfile`)

Three stages:

1. **`assets`** — `node:22-bookworm`, `npm ci` then `npm run build`. Assets are built once and copied in; the runtime image has no Node.
2. **`php-base` / `build`** — `php:8.4-fpm-bookworm` with `bcmath`, `exif`, `gd` (freetype/jpeg/webp), `intl`, `mysqli`, `opcache`, `pdo_mysql`, `soap`, `zip`, plus the `redis` PECL extension. Composer installs `--no-dev` at both roots, with ACF Pro credentials supplied as a **BuildKit secret** (`--secret id=composer_auth`) so the licence key never lands in a layer. `redis-cache` 2.8.0 is downloaded here and its `object-cache.php` drop-in copied into `web/app/`.
3. **`runtime`** — `php-base` plus nginx, WP-CLI, `default-mysql-client`, and `awscli` (the last two are for ECS Exec content import). `WP_ENV=staging` by default; ECS overrides it. The built application is copied in owned by `www-data`.

nginx and PHP-FPM run in the same container (`docker/nginx.conf`, `docker/www.conf`, `docker/php.ini`).

## Container start (`docker/entrypoint.sh`)

```sh
mkdir cache dirs; chown
if wp core is-installed; then
  wp plugin activate redis-cache
  wp acorn rl:deploy        # fatal on failure — refuses to start
  wp acorn optimize
  [ -n "$WP_REDIS_HOST" ] && wp redis enable
fi
cron && export DISABLE_WP_CRON=true   # real cron; falls back to request spawning if it fails
[ "$QUEUE_CONNECTION" != sync ] && wp acorn queue:work ... &   # only when a queue is configured
php-fpm -D
exec nginx -g "daemon off;"
```

`rl:deploy` failing is **fatal on purpose**. A container serving against a schema the code does not expect fails quietly and can corrupt data; refusing to start fails the ECS deployment loudly and leaves the previous tasks serving.

`SKIP_CHOWN=1` skips the uploads chown — set in `docker-compose.yml`, because chowning a bind-mounted host directory is slow and unnecessary locally. Production ECS also sets it because uploads live on EFS.

On the production preview host, `DISALLOW_INDEXING=true` is injected by Terraform. `config/environments/production.php` turns that env var into Bedrock's constant so Google does not index a second copy of the site. After apex cutover, set `disallow_indexing = false` and redeploy.

## Post-deploy tasks (`wp acorn rl:deploy`)

`App\Infrastructure\Console\Commands\RunDeployTasksCommand` — migrations plus a rewrite-rule flush, serialised across containers by a **MySQL named lock** (`rl_deploy_tasks`).

Why a named lock and not `migrate --isolated`: ECS rolling deploys start several tasks at once, and Acorn's cache store is `file` — `--isolated` locks per-container and gives no cross-task protection at all. A MySQL named lock is held on the connection, visible to every task pointing at the same database, and released automatically if the container dies mid-run.

A task that cannot acquire the lock within `--timeout` (default 120s) logs and exits successfully: another container is running the same code, so the work will be done — this task just must not race it.

## CloudFront invalidation after a cache-header change

**A deploy does not clear CloudFront.** ECS rolls, the origin starts sending the new headers, and
every object already in the distribution keeps serving its stored copy — and its stored *response
headers* — until it ages out. For an ordinary code change that is harmless. For a change to what the
origin says about cacheability it is not, because the bad entries are exactly the ones that outlive
the fix.

This is not hypothetical. The `Set-Cookie` fix in `docker/nginx.conf`
([known-issues.md](known-issues.md) #23) stopped the origin advertising a session-bearing response as
`public, s-maxage=60` — but the entries CloudFront had already cached still hold a
`laravel-session` cookie, and go on handing it to every visitor until their TTL expires. **Deploy
that change to an environment and then invalidate that environment's distribution**, on staging and
production alike, before treating it as fixed.

```bash
# Find the distribution for a hostname, then invalidate everything.
aws cloudfront list-distributions \
  --query "DistributionList.Items[?contains(Aliases.Items, 'staging.remoteleverage.com')].Id" \
  --output text

aws cloudfront create-invalidation --distribution-id <ID> --paths '/*'
```

`--paths '/*'` is the right blunt instrument here: the poisoned entries are keyed by URL and there is
no list of which ones were served while the bug was live. Verify with a pair of independent requests
to `/book-consultation` and confirm that either no `Set-Cookie` is present or the value differs
between them — and read `x-cache` back, since `Hit from cloudfront` is evidence about whenever the
object was stored, not about now ([known-issues.md](known-issues.md) #19).

## Secrets

Runtime still comes from AWS Secrets Manager (`wordpress-staging/app` or `wordpress-production/app`), injected into the ECS task. **GitHub Environment secrets are the place you edit values.** A workflow copies them into Secrets Manager; ECS does not read GitHub at runtime.

GitHub does not fire an event when a secret value changes. After you edit secrets on the `staging` or `production` GitHub Environment:

1. Run **Sync app secrets** (`sync-app-secrets.yml`) for that environment — production waits for the same environment approval as a deploy — **or**
2. Deploy (staging push / production release tag). Both deploy workflows sync secrets before they roll ECS.

Empty GitHub secrets are skipped so they do not blank keys already in Secrets Manager. `DB_*`, `WP_HOME`, and `WP_SITEURL` are not GitHub secrets; they stay on the task definition.

`ACF_PRO_KEY` remains a **repository** secret as well, because pull-request CI has no environment and no AWS OIDC.

`scripts/seed-staging-secrets.sh` still exists for a one-time seed from the local `env` file. Day-to-day updates should go through GitHub secrets.

**Stripe, per environment (settled 2026-09-15).** Staging GitHub Environment secrets should hold Stripe **test** keys (`STRIPE_KEY` / `STRIPE_SECRET` / test-mode `STRIPE_WEBHOOK_SECRET`); production Environment secrets hold the live pair. Stripe issues a separate signing secret per webhook endpoint, so copying one between environments produces a 403. `/api/webhooks/stripe` and `/api/webhooks/calendly` fail closed (503 with no secret, 403 on a bad signature). See [cutover-decisions.md](cutover-decisions.md) §5b and [known-issues.md](known-issues.md) #7.

> **Note:** the secret is injected into the ECS **task definition**, so writing it into Secrets
> Manager is not enough on its own — the service needs a new deployment (or a forced update)
> before a running task sees it. The sync workflow does that when **Restart ECS** is checked.

**Do not add the `*_SYNC_*` keys.** They point in the opposite direction: they are read *locally* so the sync commands can call out to a remote environment's REST API, and are never baked into a container. See [domains/sync.md](domains/sync.md#configuration).

## Infrastructure

| Piece | Staging | Production preview |
| :--- | :--- | :--- |
| Compute | ECS `wordpress-staging` (1 task) | ECS `wordpress-production` (2 tasks, ECS Exec on) |
| Registry | ECR `wordpress-staging` | ECR `wordpress-production` |
| VPC / VPN | `10.91.0.0/16` / `10.92.0.0/22` | `10.93.0.0/16` / `10.94.0.0/22` |
| Uploads | EFS | EFS |
| Cache | Redis `WP_REDIS_*` | Redis `WP_REDIS_*` |
| Secrets | `/wordpress-staging/app` | `/wordpress-production/app` |
| `WP_ENV` | `staging` | `production` |
| Indexing | noindex | noindex (`DISALLOW_INDEXING`) until apex cutover |
| URL | <https://staging.remoteleverage.com> | <https://production.remoteleverage.com> |

Terraform: `terraform/envs/wordpress-staging` and `terraform/envs/wordpress-production`. State keys `envs/wordpress-staging/terraform.tfstate` and `envs/wordpress-production/terraform.tfstate`.

## DNS (preview hostname)

ACM validation and the `production` CNAME go at **whoever currently hosts `remoteleverage.com` DNS**. That does not have to be GoDaddy yet. Do **not** change apex or `www`.

After `terraform apply` in `terraform/envs/wordpress-production`, `terraform output acm_validation_cnames` and `terraform output godaddy_records` print the records. Current values (add these at the current DNS host; do **not** change apex/`www`):

| Type | Name | Value | TTL |
| :--- | :--- | :--- | :--- |
| CNAME | `_03b9414e55c61d994eb25c115f1ed56c.production` | `_12bc8663db45c0d8504ac4893bd7ec4c.wzccmgtwzk.acm-validations.aws.` | 600 |
| CNAME | `production` | CloudFront domain from `terraform output cloudfront_domain_name` (after the cert is issued) | 600 |

Until the ACM record exists, CloudFront and the ALB HTTPS listener cannot be created — the certificate is `PENDING_VALIDATION`. The rest of the stack (VPC, Aurora, EFS, Redis, ECR, ECS) is already up. Add the ACM CNAME, wait for ISSUED, then `terraform apply` again.

### Later cutover to `remoteleverage.com` (not this pass)

When you control DNS:

- Add CloudFront aliases `remoteleverage.com` and `www.remoteleverage.com` (ACM SAN).
- `wp search-replace 'https://production.remoteleverage.com' 'https://remoteleverage.com' --all-tables --precise`
- Point apex/`www` at CloudFront.
- Set `disallow_indexing = false` in Terraform and redeploy.
- Point the Stripe live webhook at the apex URL.

## First production bring-up

Empty ECR will not stay healthy. Order:

1. Apply Terraform + add ACM/CNAME for `production.remoteleverage.com`.
2. Seed Secrets Manager. **There is no `STRIPE_MODE` key** — an earlier version of this list said to
   seed one and nothing reads it. The code reads **`STRIPE_TEST_MODE`** (`config/services.php`,
   `stripe.test_mode`), and *live* is that being absent or false, with `STRIPE_KEY` / `STRIPE_SECRET`
   holding the live pair — live mode reuses those rather than a parallel `STRIPE_LIVE_*` pair. The
   webhook secret can wait — Stripe webhooks 503 until it is set.
3. Push a release tag (`v-YYYYMMDD-v1`) and approve the production environment job.
4. Import SQL + extract uploads (below).
5. Smoke-test the booking form, media, and admin on the preview host.

## Content import (one-time)

Local artifacts at the website repo root (gitignored):

- SQL: `remoteleveragev2-2026-09-15-534bed7.sql`
- Media: `uploads.zip` — extract into EFS `web/app/uploads`. The zip has a top-level `uploads/` directory; the import script strips that so `2026/09/...` and `home/` land on the mount, not `uploads/uploads/...`.

Aurora and EFS allow **tasks only**, not the VPN. After the first production image is running:

```bash
AWS_REGION=us-east-1 ./scripts/import-production-content.sh
```

That uploads the dump and zip to a short-lived prefix on `remote-leverage-wordpress-backups`, runs a one-off Fargate task (same VPC/EFS/Aurora as the service), imports with `wp db import`, rewrites hosts, unzips onto EFS, then deletes the S3 objects. ECS Exec also works if the Session Manager plugin is installed; the script uses `RunTask` so it does not depend on that plugin.

### URL rewrite after import

The dump is a local Herd snapshot with baked `remoteleverage-v2.test` URLs plus `home`/`siteurl`. A raw import 404s images and mixed-content CSS backgrounds. Attachment *files* in the zip use relative paths and are fine; the broken part is absolute URLs in `wp_posts` / `wp_postmeta` / `wp_options`.

WP-CLI `search-replace` rewrites PHP serialized Gutenberg/ACF data (a naive SQL replace would corrupt it):

```text
wp search-replace 'http://remoteleverage-v2.test' 'https://production.remoteleverage.com' --all-tables --precise
wp search-replace 'https://remoteleverage-v2.test' 'https://production.remoteleverage.com' --all-tables --precise
```

Also set `home` / `siteurl` to `https://production.remoteleverage.com` and `https://production.remoteleverage.com/wp`.

Do **not** rewrite `https://remoteleverage.com` in post body during the preview period — those are live-site links and still resolve.

`wp acorn rl:deploy` already runs on container start and applies migrations after the import. Leftover `.test` / localhost hosts in `the_content` still get rewritten by `BlockDefaults::rewriteLocalAbsoluteUrls`.

### Videos on EFS (per environment) — **outstanding on staging and production**

One video is deliberately **not** in the image and **not** in the database dump, so nothing in the
pipeline above puts it on a host. It has to be uploaded by hand, once per environment.

| File | Destination | Used by |
| :--- | :--- | :--- |
| `5-minute-VSL_Horizontal_V01.mp4` (61MB) | `uploads/videos/` on the EFS mount | `/about-us/` |

Everything smaller travels on its own: `resources/videos/**` is tracked in git and the
`themeVideos()` Vite plugin copies it into `public/videos/**` during the `assets` build stage, so the
1.6MB walkthrough on `/vathankyou/` is already in every image. The VSL is excluded because a 61MB
blob in git is paid for on every clone and every CI checkout, forever — see
[known-issues.md](known-issues.md) #24 for why both videos 404'd before this split existed.

`BlockDefaults::video()` looks in `uploads/videos/` first, then the theme's built `public/videos/`,
and when neither has the file it returns the uploads URL anyway — so **the page renders a broken
player rather than an error**, and the fix is an upload rather than a deploy. That is the current
state of `/about-us/` on staging and on the production preview host.

EFS is reachable from tasks only, not from the VPN, so the upload takes the same route as the
uploads zip: put the file on the backups bucket and have a task copy it onto the mount.
`scripts/import-production-content.sh` does **not** do this — it only handles `db.sql` and
`uploads.zip` — so run it as a one-off, either through ECS Exec on a running task or a `RunTask`
shaped like the import script's:

```sh
aws s3 cp 5-minute-VSL_Horizontal_V01.mp4 s3://remote-leverage-wordpress-backups/tmp/
# then, on a task with the EFS mount:
mkdir -p web/app/uploads/videos
aws s3 cp s3://remote-leverage-wordpress-backups/tmp/5-minute-VSL_Horizontal_V01.mp4 web/app/uploads/videos/
chown www-data:www-data web/app/uploads/videos/5-minute-VSL_Horizontal_V01.mp4
```

Delete the S3 copy afterwards. Verify by loading `/about-us/` rather than by listing the directory:
a zero-byte file passes `ls` and fails `BlockDefaults::video()`'s usability check, which is the
failure mode [known-issues.md](known-issues.md) #5 describes for images.

### Pages holding expanded markup instead of a pattern reference — `/about-us/` needs a database edit

An upload alone will not fix `/about-us/`. That page (ID 209 locally) held **expanded block markup**
in `post_content` rather than the one-line pattern reference, so the old
`/app/themes/remote-leverage/public/videos/…` URL is baked into the database row and the pattern
edit cannot reach it. Fixed locally on 2026-09-17 by repointing the page at its pattern:

```bash
# Resolve the id per environment — do not assume 209 travels.
wp post list --post_type=page --name=about-us --field=ID

wp post update <ID> --post_content='<!-- wp:pattern {"slug":"remote-leverage/about-full"} /-->'
```

Both renders were diffed before the swap. The only substantive difference was `font-black` versus
`font-bold` on the decorative quote glyph — a change commit `2990554` made to the pattern that the
database copy never received, which is the drift this arrangement exists to prevent, caught in the
act.

**Staging and production still hold the stale expanded markup**, so the same update has to be run
against each of them — through ECS Exec or a one-off task, alongside the video upload above — or the
video fix will not land on that page however correct the file on EFS is.

Not just this page: a survey of published pages on 2026-09-17 found **63 correctly holding a pattern
reference and 4 still holding expanded markup**. Those four are not a work item anyone has taken on,
but the drift is real and now measured. Pages created through the MCP `app/clone-page` ability are a
deliberate exception and are expected to be database-resident — see
[ai-mcp-and-sync.md](ai-mcp-and-sync.md).
