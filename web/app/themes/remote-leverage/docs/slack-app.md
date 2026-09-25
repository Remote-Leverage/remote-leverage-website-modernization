# The Slack app

Everything this site says in Slack goes through one app and one transport,
[`SlackTransport`](../app/Infrastructure/Slack/SlackTransport.php). The app began life as a
lead-alert pipe ported from the Gravity Forms Slack feed; it now carries leads, live calls and
the referral programme, which is why it was renamed from **Remote Leverage Leads Application**
to **Remote Leverage Website**.

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
| `lead_amended` | `LeadCreated`, partial, for a lead already announced | The visitor changed an answer and submitted step 1 again. Replies in-thread and edits the card above it. Silent when nothing changed. |
| `booked` | `LeadBookingCompleted` | On by default since 2026-09-17; `SLACK_NOTIFY_ON_BOOKING=false` silences it. Replies in-thread only. |
| `live_call_routed` | `LiveCallRequested`, routed | Always on — a live call starts within 15 minutes. |
| `live_call_declined` | `LiveCallRequested`, declined | The reason this event exists; see below. |
| `referrer_registered`, `referral_recorded`, `payout_completed` | The referral domain | Top level, not threaded — no lead thread to hang them on. |
| `partnership_prospect` | `PartnershipProspectSubmitted` (the `/become-a-partner/` form) | Its own channel (Partners Hub → Partnership Settings) or **not at all** — never the default sales channel, and skipped without a bot token because a webhook cannot honour the override. A prospect added by hand on Partners Hub → Prospects is never announced (`RecordPartnershipProspectAction` does not dispatch the event). |
| `marketing_cost_alert` | The hourly `rl_marketing_cost_alert` cron, through `SendCostAlertAction` | Its own channel. A new card every run, never an edit; its **Send new alert** button posts one on demand. See below. |

Two rules the renderer enforces, both there because the alternative shows up in a channel people
watch all day: an unresolved placeholder renders **empty**, never as the literal `{{ key }}`; and
`_when` drops a block or field whose named values are all empty, so a lead with no campaign data
does not render a grid of dashes.

**House rule: no emoji, anywhere.** Hierarchy comes from headers, dividers and field grouping.
Each family of templates has a test asserting it, because the Block Kit Builder picker is one
click away from the JSON you are about to paste.

### The cost alert posts a card every run

`marketing_cost_alert` is a running total rather than an event. It was first built to post once a
day and `chat.update` that card hourly; it now posts a new card on every run, because the channel
is the record of how the day developed and an edit destroys that record every hour. The reasoning
is in `SendCostAlertAction`'s class docblock. `STATE_OPTION` still holds the last card's `ts` and
channel, but only so a card can be found again — nothing decides what to send from it.

It is also the first thing to post outside `#new-appts`. `SlackTransport::post()` grew an optional
`$channel` argument for it, filled from `marketing.cost_alert.channel`; omitted, everything else
resolves the default channel exactly as before. The webhook path cannot honour it, because an
incoming webhook URL is bound to the channel it was created for — so the override is logged loudly
and the message is sent anyway, to the webhook's own channel. A cost digest landing in the wrong
channel is obvious and somebody fixes it within the hour; sending nothing is the failure that goes
unnoticed for a month.

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
- **Only a block broadcasts.** A broadcast reply arrives twice — once in the thread and once as
  an independent message in the channel — so everything else, the booking included, stays in the
  thread. A block is the exception because it is a decision about a person the channel has
  already been alerted about.

## A second step 1 is an amendment, not a second lead

Someone picks "$0 to $5k", reads the revenue-band warning, goes back, picks "$5k to $10k" and
continues. Step 1 runs twice.

That used to produce two of everything — two lead rows, two cards in `#new-appts` with the more
prominent one stating a band the visitor had already corrected, two Meta `Lead` conversions for
one person, two admin emails — and left the first row in the dashboard forever as a partial
drop-off that nobody had dropped off from. The cause was one missing key: `submitBooking()`
passed `lead_id` into the capture and `capturePartialLead()` never did, so the second pass
inserted rather than updated.

It now resolves to one row per visit (`MultistepBookingWizard::resumableLeadId()`, keyed on the
address plus PostHog's session id inside a 30-minute window — `$sessionId` is minted per
component and a reload produces a new one, so it cannot carry this), and the listener decides
what the channel hears:

- **Nothing changed** — silence, logged as a skipped consumption on the lead's timeline. A
  second identical card tells sales nothing they cannot already see.
- **Something changed** — `chat.update` rewrites the card to the current answers, and a context
  reply records the change: `revenue: $0 to $5k Per Month → $5k to $10k Per Month`. Both halves,
  because Slack shows no edit marker on a bot message: without the reply the card would change
  under the reader with nothing saying it had.
- **Nothing to amend** — the webhook transport, or the channel moved since. A whole card is
  posted, which is what happened before any of this existed.

What the card says is stored on the lead as `slack_announced` beside the `ts`, because the row
itself no longer knows — the second capture overwrote it. Only fields present in **both**
snapshots are compared, so adding one does not report an amendment on every lead captured
before the deploy.

A completed form is never resumed: someone who books and then opens the form again is starting
something new, and threading that onto the booked lead's card would bury it.

## Every button is a link

The lead alert's buttons — Open in portal, Watch session, Open in HubSpot — take you somewhere.
None of them changes a lead from inside Slack, so the app needs no signing secret, no
interactivity request URL and no handler: a card works the moment it posts, in every environment,
without a Slack app setting.

That was not always true. Claim, Mark contacted and Block were interactive buttons handled by a
`SlackInteractionController` at `/api/webhooks/slack/interactions`, gated behind
`SLACK_SIGNING_SECRET`; they were removed on 2026-09-21 along with the endpoint, the secret and
the in-thread confirmations they posted (`lead_claimed`, `lead_contacted`, `lead_blocked`).
Acting on a lead is done in the portal.

The cost alert's **Send new alert** button (2026-09-24) is still a link, which is the point: it
opens `/cost-alert/send` on whichever site posted the card, and that page POSTs back to
`/api/marketing/cost-alert/send`, which posts a fresh card exactly as the dashboard's **Send to
Slack now** does. The URL carries an expiring HMAC signature (`CostAlertSendLink`, keyed on the
auth salt, valid for a week), and that signature is the whole permission — anyone holding the card
can press it, which is the model the removed interactive buttons had too. The GET never sends, so
a link preview or a scanner cannot post a card, and one press per two minutes goes through, so a
double click posts one.

**If they ever come back**, the trap that made them awkward is still there: the interactivity
request URL is a property of the **app**, not of an environment, and every environment shares one
app and one bot token — so whichever host is in that box receives *every* button press, no matter
which environment posted the alert. Two environments with working buttons need two Slack apps,
and separate channels, or the alerts become impossible to tell apart.

### Credentials as admin settings

Environment wins where it is set; the setting is the fallback, and `SlackCredentials` is the one
place that decides. The setting is not a convenience — ECS maps Secrets Manager keys to
environment variables one at a time in the task definition, so a *newly added* credential cannot
reach staging any other way until that changes. The Lead settings blob is already in the
environment-sync whitelist (`config/rl-sync.php`), so the intended route to staging is:

1. Set it locally in **Leads → Settings**.
2. Push it with environment sync — it rides along inside `rl_lead_settings`, no new sync config.

That is how the bot token and channel reach staging, and neither has ever been on the settings
form. They survive a save of that screen: `save()` replaces the whole blob, and until 2026-09-16
any key the form did not post was written back as an empty string — so saving the Leads settings
screen for an unrelated reason silently unwired Slack, after which alerts fell back to the
incoming webhook with nothing anywhere to say why. A credential is now only cleared by submitting
it empty, never by omitting it.

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
