# The Slack app

Everything this site says in Slack goes through one app and one transport,
[`SlackTransport`](../app/Infrastructure/Slack/SlackTransport.php). The app began life as a
lead-alert pipe ported from the Gravity Forms Slack feed; it now carries leads, live calls, the
referral programme and the action buttons, which is why it was renamed from **Remote Leverage
Leads Application** to **Remote Leverage Website**.

## Identity

| | |
| :--- | :--- |
| App id | `A0C2296E7U3` |
| Settings | <https://api.slack.com/apps/A0C2296E7U3> |
| Workspace | Remote Leverage (`T05L10N77M4`) |
| Bot user | `U0C26HYC538`, bot id `B0C2AEB62U9` |
| Bot scopes | `chat:write`, `chat:write.public` |
| Default channel | `C086BBKUXL5` — `#new-appts`, the channel the legacy feed posted to |

Not `C09HXD9S76Z`: that is `#sales-meetings`, which the legacy `rl_jlc_slack_channel` option
points at for live-call alerts — a different notification entirely.

### Renaming it

The name lives only in Slack, nowhere in this repo, and changing it invalidates nothing — not
the bot token, not the channel binding. Two places, or the channel keeps showing the old handle
as the poster:

1. **Basic Information → Display Information → App name**
2. **App Home → Your App's Presence → Display Name (Bot Name)**

## What it posts

Layouts are Block Kit templates in [`config/slack-notifications.php`](../config/slack-notifications.php),
so the design is a config edit rather than a change to a listener with its own tests. Build one
in [Block Kit Builder](https://app.slack.com/block-kit-builder), paste the JSON in, swap literal
values for `{{ placeholders }}`.

| Template | Fired by | Notes |
| :--- | :--- | :--- |
| `new_lead` | `LeadCreated`, partial only | Opens the thread. Suppressed for blocked profiles and for `submission_type: Final`. |
| `booked` | `LeadBookingCompleted` | On by default since 2026-09-17; `SLACK_NOTIFY_ON_BOOKING=false` silences it. Replies in-thread, broadcast. |
| `lead_claimed`, `lead_contacted`, `lead_blocked` | A button press | Replies in-thread. Only the block broadcasts. |
| `live_call_routed` | `LiveCallRequested`, routed | Always on — a live call starts within 15 minutes. |
| `live_call_declined` | `LiveCallRequested`, declined | The reason this event exists; see below. |
| `referrer_registered`, `referral_recorded`, `payout_completed` | The referral domain | Top level, not threaded — no lead thread to hang them on. |

Two rules the renderer enforces, both there because the alternative shows up in a channel people
watch all day: an unresolved placeholder renders **empty**, never as the literal `{{ key }}`; and
`_when` drops a block or field whose named values are all empty, so a lead with no campaign data
does not render a grid of dashes.

**House rule: no emoji, anywhere.** Hierarchy comes from headers, dividers and field grouping.
Each family of templates has a test asserting it, because the Block Kit Builder picker is one
click away from the JSON you are about to paste.

## One thread per lead

The partial alert stores its `ts` and channel on the lead (`slack_message_ts`,
`slack_channel_id`); everything afterwards replies under it. Before this, one person produced a
scatter of unrelated cards and reconstructing the order meant reading timestamps.

Three things worth knowing:

- **The incoming-webhook fallback cannot thread.** A webhook answers `ok` and nothing else, so
  there is no `ts` to store and replies post flat. That is a degradation, not a failure.
- **A `ts` is only valid in the channel that produced it.** If `SLACK_CHANNEL` changes, the
  stored channel no longer matches and the reply posts flat on purpose — Slack would otherwise
  accept the stale timestamp and silently post flat anyway, which looks like a bug with no cause.
- **Replies broadcast when they must not be missed.** A booking and a block go to the channel as
  well as the thread; a claim and a contact stay in the thread.

## Turning the buttons on

The lead alert's link buttons have always worked. The **action** buttons — Claim, Mark
contacted, Block — need an interactivity request URL, and until one exists Slack prints "this app
is not configured to handle interactive responses" beside every button it cannot deliver. So they
are not rendered at all until the app is wired:

1. **Basic Information → App Credentials → Signing Secret** → set it, either as
   `SLACK_SIGNING_SECRET` or in **Leads → Settings → Slack signing secret** (see below).
2. **[Interactivity & Shortcuts](https://api.slack.com/apps/A0C2296E7U3/interactive-messages)** →
   toggle **Interactivity** on → **Request URL** →
   `https://<host>/api/webhooks/slack/interactions` → **Save Changes**.

Both, or neither: the signing secret doubles as the feature flag, so setting it without the
request URL renders buttons that go nowhere.

Slack verifies the URL by POSTing to it when you save, so it has to be publicly reachable over
HTTPS at that moment. `https://remoteleverage-v2.test` is not — for local work, run a tunnel
(`cloudflared tunnel --url https://remoteleverage-v2.test`) and paste the tunnel's hostname
instead, remembering that it changes every restart.

### Environment variable or admin setting

Environment wins where it is set; the setting is the fallback, and `SlackCredentials` is the one
place that decides. The setting is not a convenience — ECS maps Secrets Manager keys to
environment variables one at a time in the task definition, so a *newly added* credential cannot
reach staging any other way until that changes. The Lead settings blob is already in the
environment-sync whitelist (`config/rl-sync.php`), so the intended route to staging is:

1. Set it locally in **Leads → Settings**.
2. Push it with environment sync — it rides along inside `rl_lead_settings`, no new sync config.

The same is true of the bot token and channel, which have never been on the settings form at
all. They now survive a save of that screen: `save()` replaces the whole blob, and until
2026-09-16 any key the form did not post was written back as an empty string — so saving the
Leads settings screen for an unrelated reason silently unwired Slack, after which alerts fell
back to the incoming webhook with nothing anywhere to say why. A credential is now only cleared
by submitting it empty, never by omitting it.

### One app, one request URL

This is the trap. The URL is a property of the **app**, not of an environment, and every
environment shares this one app and one bot token — so whichever host is in that box receives
*every* button press, no matter which environment posted the alert.

Point it at staging and a button on a production lead alert arrives at staging, where that lead
id belongs to somebody else or to nobody. The handler answers "that lead no longer exists" and
the press is lost, which looks like a broken button rather than a misdirected one.

So: while v2 is pre-cutover, point it at **staging** and treat the buttons as a staging feature.
At cutover, move it to production in the same edit that moves the traffic. If both environments
ever need working buttons at once, they need separate Slack apps — and separate channels, or the
alerts become impossible to tell apart.

[`SlackInteractionController`](../app/Application/Http/Controllers/SlackInteractionController.php)
fails closed like the Stripe and Calendly endpoints — 503 with no secret, 403 on a bad or
replayed signature. It can block a person and change a lead's status, so an unsigned payload is
enough to do real damage.

**Authorisation is channel membership.** Anyone who can see the message can press the button.
That is deliberate: the channel is already the list of people trusted with every lead's name,
phone number and session replay, and a second permission system would be a second place to
forget someone. The button's `value` carries a lead id and nothing else — every other fact is
read from the database, so a replayed interaction can repeat an action but cannot assert
anything.

## Live calls, and the refusals nobody could see

`RouteInstantCallAction` has seven ways to turn a visitor away, and all seven used to be silent:
the person got a polite line in the browser and the most motivated visitor on the site went away
unrecorded. Every path now dispatches `LiveCallRequested`, and the declined ones are split into
two kinds because they mean completely different things:

- **Availability** — `phone_not_supported`, `team_offline`, `consultant_busy`,
  `no_immediate_slot`. A staffing fact.
- **Ours to fix** — `not_configured`, `booking_failed`, `no_meeting_link`. These turn away
  *every* visitor until someone acts, so they get their own headline and a note saying so.
  Rendering them the same as a quiet afternoon is how one survives a week.

## An empty value used to cost the whole message

Slack refuses an **entire** `chat.postMessage` with `invalid_blocks` if any text object carries
an empty string. Not the block that used it — the message. So one unbound placeholder is the
difference between a full alert and total silence, and the only trace is a `WARNING` in the
Acorn log.

That is not hypothetical: `referrer_registered` bound its card subtitle to `{{ company }}`, which
the public registration form never collects, so **every referrer registration went unannounced**
for as long as the feature existed. Found 2026-09-17 by reading the log after a registration
produced nothing.

Three layers now stand between a blank value and a lost notification:

1. **`_when` guards**, which drop a whole block, the `accessory`, or an item inside
   `fields`/`elements` when the placeholders it names are empty. This is the right tool when a
   block is genuinely optional, and it is the only one that predates the incident.
2. **Optional-key pruning** — `SlackMessageRenderer::OPTIONAL_TEXT_KEYS` removes an empty
   `subtitle` or `description`. `_when` cannot reach these: a card's own properties are not a
   block, an accessory, a field or an element.
3. **`hasEmptyTextObject()`**, the net: any block *still* holding an empty text object after
   substitution is dropped rather than sent. Losing one card is visibly worse than a complete
   one and enormously better than silence.

**The net is not the fix.** A dropped card fails invisibly — the message posts looking like a
rendering glitch and nothing logs it. The actual fix is a fallback where the value is built, so
the card renders with something true in it: `'Unnamed visitor'`, `'Confidential Contact'`,
`'No contact details captured'`, `'No referrals listed'`, `'Unknown referrer'`. Prefer that, and
let the net catch what you did not think of.

`tests/Unit/SlackEmptyBlockTest.php` renders **every** template with every value blank and fails
if any block would still carry an empty text object, or if a template would send neither blocks
nor fallback text. A new template is covered by it automatically.

Two things that guard does **not** cover, so check them by hand when adding a template:

- **Button `url`.** It inspects `type`+`text` pairs only. An empty or relative `url` is also
  rejected — and that failure takes the whole message with it, exactly like an empty text
  object. Every button in the config today is `_when`-guarded on its URL, which covers *empty*
  but not *relative*: `Lead::posthogReplayUrl()` builds on `services.posthog.app_host`, so
  `POSTHOG_APP_HOST=us.posthog.com` (no scheme) would have produced a relative one. It now
  returns null instead. Any new button built from a configurable host needs the same check.
- **Length caps.** 3000 characters for section text, 150 for a header, 50 blocks per message.
  Nothing in the config approaches these — there are no `header` blocks at all — but an
  interpolated value with no length bound could.

## Adding an alert

1. A template in `config/slack-notifications.php`.
2. A listener method that builds the value map. Three listeners share the transport —
   `HandleLeadEventsForSlack`, `HandleReferralEventsForSlack`, `HandleLiveCallEventsForSlack` —
   and each keeps a `protected send()` seam that its tests override rather than reaching Slack.
3. Wire the event in the domain's service provider with `dispatch(...)->afterResponse()`. The
   closure **must** be `static` and must not touch `$this`: serializable-closure otherwise tries
   to serialize the provider and the container behind it, which fails silently during request
   termination and drops the notification entirely. See the long note in `LeadServiceProvider`.

## Limits worth knowing before promising anything

- The app is **post-only**. `chat:write` and `chat:write.public`, nothing else. Reading a
  channel, listing users, DMing someone, or resolving a Slack user to a WordPress account all
  need scopes it does not have and a reinstall to grant.
- There are **no slash commands**. That needs the `commands` scope and a second request URL.
- `LeadAbandoned` and `LeadBookingCanceled` still have no Slack path. Both events already fire.
