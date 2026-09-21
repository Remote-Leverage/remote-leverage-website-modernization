# Tools domain

`app/Domains/Tools` — the OpenAI proxy behind the Elementor-era browser tools.

One job: serve `/wp-json/jobwidget/v1/{chat,chat4,whisper}` so eight legacy tool pages keep working, without handing the internet an unmetered OpenAI account.

Configuration: [`config/job-widget.php`](../../config/job-widget.php).

---

## Why it exists

The legacy site's tool pages are self-contained HTML widgets: the browser builds the prompt, POSTs it to a WordPress REST route, and renders what comes back. That route was **not a plugin** — it lived in `hello-theme-child/functions.php`, three handlers and a hardcoded API key. Cutover replaced that theme with this one and the routes went with it.

Nothing announced the breakage. `rest_no_route` answers 404 with a **JSON** body, so `await resp.json()` succeeded, the widgets' `catch` blocks never ran, and every tool rendered its "no output" placeholder instead of an error. From the v2 launch until 2026-09-21 that is what the vastore5 generator had been showing.

## What calls it

Eight pages, per `grep -rl "jobwidget/v1" legacy-snapshots/pages`:

| Page | Routes |
| :--- | :--- |
| `vastore5`, `vastore5-b` | `chat` |
| `resume` | `chat` |
| `text-optimizer` | `chat` |
| `job-description-generator`, `job-description-generator-2` | `chat` |
| `job-posting-template-generator` | `chat` |
| `tools` | `chat`, `chat4` |

`whisper` is ported for the Audio Scorer but no snapshot page currently calls it.

**Not everything on `/tools/` comes through here.** One widget on that page posts Claude prompts straight to a Cloudflare Worker (`icy-math-19df.floral-wood-168a.workers.dev`) with no WordPress involvement at all. That is a separate third-party dependency, outside this domain and not revived by it.

## Shape

| Class | Responsibility |
| :--- | :--- |
| `Http\JobWidgetRestRoutes` | Registers the three routes on `rest_api_init`. Wiring only. |
| `Services\OpenAiProxyGuard` | Every rule: enablement, origin, nonce, rate limit, payload allowlist. |
| `Services\OpenAiProxy` | The outbound call. Adds the credential and the timeouts, nothing else. |

The split is what makes the rules testable — `tests/Unit/JobWidgetProxyTest.php` exercises all of them without a network or a REST stack.

## What changed from the legacy handler

The old route was `permission_callback => '__return_true'`, forwarded the browser's body verbatim, and had no limit of any kind. Anyone who read the page source could bill our OpenAI account for anything, at any volume.

| | Legacy | Here |
| :--- | :--- | :--- |
| Credential | Hardcoded in the theme file | `OPENAI_API_KEY` from the environment |
| Auth | None | `wp_rest` nonce + same-origin check |
| Volume | Unbounded | 30 requests / IP / 10 min |
| Model | Whatever was sent | Allowlist (`gpt-3.5-turbo`, `gpt-4o-mini`, `gpt-4o`) |
| Body | Forwarded verbatim | Allowlisted keys; `max_tokens` and `temperature` capped |
| Upstream errors | Status and message passed through | Logged; the browser gets 502 and a generic message |

Two of those are less obvious than they look and are the subject of their own tests:

**The rate limit reads the right-most `X-Forwarded-For` entry, not the left-most.** CloudFront *appends* the viewer IP to whatever the caller sent, so the left-most entry is caller-controlled. Reading it — which is what `AttributionCollector` correctly does, because for attribution the original client is the point — would let one caller present a fresh IP per request and never meet the limit.

**Upstream's status never reaches the browser.** A 401 from OpenAI means *our* key is wrong. Passing it through lets an anonymous caller probe the state of our credential, and tells the visitor nothing they can act on.

## Turning it off

Unset `OPENAI_API_KEY`, and no route is registered — the endpoints 404 exactly as they did before this existed. That is the default for any environment that was never wired up, and it is why the routes gate themselves at `rest_api_init` rather than answering 503 on a route that cannot work.

`JOB_WIDGET_PROXY_ENABLED=false` does the same thing with the key still present.

## The key itself

The key recovered from the backup is **compromised by construction** — it sat in a theme file served as part of the site, behind an endpoint with no auth. It works, which is the problem. Rotate it, set the new value as `OPENAI_API_KEY`, and never restore the old constant.
