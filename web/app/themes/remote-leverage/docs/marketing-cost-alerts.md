# Marketing cost alerts

Phases 1 and 2 shipped 2026-09-19. Together they rebuild the legacy `#marketing-cost-alerts`
message — bookings, qualified counts, attribution, booking rates, calendar load, and Meta spend with
the cost-per-booking figures that divide into it — as one card per day that edits itself through the
working day.

**Google and Microsoft spend are not built.** Until they are, their rows carry bookings and no cost,
and the totals are reported only when every *configured* platform answered. Nothing in this repo read
back from an ad platform before phase 2: `MetaConversionsApiClient` and `GoogleEnhancedConversion`
push conversions outward, and [`MetaInsightsClient`](../app/Domains/Marketing/Gateways/MetaInsightsClient.php)
is the first thing to read anything in.

The rule running through all of it: **"we could not read it" is never rendered as zero.** "We spent
nothing on Google today" and "we cannot see what we spent" are different claims with opposite
consequences, and a zero asserts the first one.

This document is the reasoning behind the numbers. The class docblocks under
[`app/Domains/Marketing/`](../app/Domains/Marketing) are the reasoning behind the code, and they
are deliberately not duplicated here.

---

## 1. What ships today

| Piece | Where |
| :--- | :--- |
| The whole snapshot — every figure, computed once | [`FunnelMetricsService`](../app/Domains/Marketing/Services/FunnelMetricsService.php) |
| The snapshot as a value object, with the paid/blended arithmetic on it | [`FunnelSnapshot`](../app/Domains/Marketing/Data/FunnelSnapshot.php), [`PlatformSlice`](../app/Domains/Marketing/Data/PlatformSlice.php) |
| The internal consistency check that runs before anything is said | [`AlertReconciler`](../app/Domains/Marketing/Services/AlertReconciler.php) |
| Building the message and putting it in Slack, once a day, edited in place | [`SendCostAlertAction`](../app/Domains/Marketing/Actions/SendCostAlertAction.php) |
| The Block Kit layout | `marketing_cost_alert` in [`config/slack-notifications.php`](../config/slack-notifications.php) |
| The reporting hours, shared by the action and the reconciler | [`AlertWindow`](../app/Domains/Marketing/Support/AlertWindow.php) |
| Console entry point | [`SendCostAlertCommand`](../app/Domains/Marketing/Commands/SendCostAlertCommand.php) |
| Wiring, the hourly cron hook, the widget registration | [`MarketingServiceProvider`](../app/Infrastructure/Providers/MarketingServiceProvider.php) |
| The same snapshot on the wp-admin dashboard | [`MarketingCostAlertWidget`](../app/Infrastructure/WordPress/Admin/MarketingCostAlertWidget.php) |
| Whether a visit was paid, and if not what kind of free | [`LeadChannel`](../app/Domains/Lead/Services/LeadChannel.php) |
| Meta spend, currency, account status and reporting timezone | [`MetaInsightsClient`](../app/Domains/Marketing/Gateways/MetaInsightsClient.php) |
| One platform's answer, keeping "unreachable" distinct from "zero" | [`AdSpendReading`](../app/Domains/Marketing/Data/AdSpendReading.php), [`AdSpendSource`](../app/Domains/Marketing/Contracts/AdSpendSource.php) |
| Asking every platform, and deciding what may be totalled | [`AdSpendCollector`](../app/Domains/Marketing/Services/AdSpendCollector.php) |
| Settings, definitions and targets | [`config/marketing.php`](../config/marketing.php) |
| Tests | `tests/Unit/MarketingCostAlertTest.php` |

Six decisions inside that are worth knowing before reading a card.

**Attribution now falls back to click IDs.** [`LeadPlatform`](../app/Domains/Lead/Services/LeadPlatform.php)
read `utm_source` and nothing else, so every lead that lost its UTMs to a redirect, a link
shortener or an in-app browser landed in `direct` or `other` — indistinguishable from organic. That
bucket was 21.4% of bookings in the legacy sample, and each of those bookings is a paid booking
whose cost was being spread over the platforms that did tag their links. A recognised `utm_source`
still wins, always; the click ID only speaks for leads that have no statement of intent at all. The
precedence is `gclid`, `msclkid`, `li_fat_id`, then `fbclid` last, for the reason in section 6.
`msclkid` was promoted from the attribution JSON blob to a real column to make Microsoft able to
participate at all.

**Both qualified definitions are reported, separately.**
[`LeadQualification`](../app/Domains/Lead/Services/LeadQualification.php) holds them. **T10** is the
self-reported revenue band from the intake form — it is what the site routes on, and it is what the
legacy CPQB was computed from, so it is the only definition that keeps the new series comparable to
the old one. **HubSpot lifecycle** is what sales actually concluded, and it is the better definition
the day it can be trusted. They are shown as two figures, never blended, because a single number
whose definition moved is worse than two that disagree.

**VA applicants are excluded from every lead and booking count.** People looking for VA work fill in
the client intake form regularly; they are not customers and must not sit in the denominator of a
cost per booking. The filter is `LeadAudience`, and this alert is the first thing in the codebase to
*act* on that judgement rather than merely display it — so the number excluded is printed on the
card. The test is a phone number outside the US and Canada, so it will occasionally catch a real
client. Doing so removes a booking from the denominator and makes cost per booking look worse than
it is, which is the safe direction to be wrong in; printing the count is what makes a wrong one
checkable.

**The day is the ad account's day.** Timestamps are UTC, ad platforms bill in the account's
timezone, and if the two drift apart spend is counted over one window and bookings over another.
Boundaries are computed in `marketing.cost_alert.timezone` and converted to UTC before they reach a
query.

**A partial spend total is never reported.** If Meta answers and Google does not, the sum of what
came back is today's spend minus an unknown amount — and dividing bookings by it produces a cost per
booking that is *too low*, which is the reading somebody increases a budget on. So
`AdSpendCollector::total()` returns null the moment any configured platform fails, every cost figure
is suppressed, and the card names the platform that did not answer in the place the numbers would
have been. Platforms that are merely *not configured* are skipped rather than failed, or nothing
would total until all three existed.

**"Which platform sent this" and "did we pay for it" are different questions.**
[`LeadChannel`](../app/Domains/Lead/Services/LeadChannel.php) answers the second. It exists because
the two get conflated and the conflation is expensive: a booking from an organic Instagram post and
a booking from an Instagram ad are both Meta, and only one of them belongs in the denominator of
Meta's cost per booking. So a platform row counts every booking that platform sent, and the cost
figures divide by `paidBookings()` — the same set minus every booking that cannot be shown to have
been bought.

The signal is **`utm_medium`**, then HandL, then the referring host, and getting that order right
took two attempts. Section 3.8 has the measurements. The same subtraction applies to the qualified
count, because qualified bookings are a subset of bookings and reducing one denominator without the
other printed a cost per *qualified* booking below the cost per booking — an impossibility on a card
whose whole value is being trustworthy.

---

## 2. What the legacy alert actually said

Nobody ever wrote a metric dictionary for it, which is most of why it stopped being trusted.
Decoded from the 2026-09-18 15:00 sample by arithmetic:

| Line | What it meant | Now |
| :--- | :--- | :--- |
| Total New Appts: 14 | Bookings created today, all sources | Rebuilt. From the earliest `LeadBookingCompleted` activity log per lead, not `status = 'booked'`, which carries no timestamp |
| Total CPB: $266.59 | Total spend / **all 14** bookings | Blocked on spend |
| Total CPQB: $414.69 | Total spend / **all 9** qualified bookings | Blocked on spend |
| Spend by Platform | Google / Meta / Microsoft account spend, today | Blocked on vendor access |
| `<platform>` CPB | Platform spend / that platform's attributed bookings | Blocked on spend; the booking half is live |
| Unclassified: 3 / 2 | Bookings whose `utm_source` matched no platform | Rebuilt as the attribution gap, `LeadPlatform::DIRECT` + `::OTHER`, and now smaller because of the click-ID fallback |
| Last lead | Minutes since the newest lead row | Rebuilt, and now a reconciliation check rather than a neutral statistic |
| Last booking | Minutes since the newest booking, plus the name | Rebuilt. The name is what makes the line checkable by a human |
| Last 10 / last 1000 booking rate | Rolling conversion | Rebuilt |
| Consultation Appointments 97 / 29 | Meetings **on the calendar** today and next business day — not bookings created today | Rebuilt via `CalendlyClient::countBookedEvents()`, and the card now says which of the two it is |
| `<platform>` Ads: no active issues | Account health: disapprovals, budget exhaustion, billing | Blocked on vendor access |

The arithmetic reconciles cleanly, so the legacy numbers were internally consistent with each
other:

```
14 appts x $266.59  = $3,732.26 ~ $3,732.24 total spend      correct
Google   737.46 / 737.46 = 1 booking,  1 qualified
Facebook 2892.84 / 321.43 = 9 bookings, 2892.84 / 578.57 = 5 qualified
Bing     101.94 / 101.94 = 1 booking,  1 qualified
                            11 attributed + 3 unclassified = 14   correct
                             7 attributed + 2 unclassified =  9   correct
```

Internally consistent is not the same as true, which is section 3.2.

---

## 3. The defects, and what happened to each

### 3.1 The unattributed bookings were silently subsidised — fixed

The alert reported blended CPB — total spend over *all* bookings, including the three carrying no
platform — while reporting per-platform CPB over attributed bookings only. Two different metrics
printed as if they were one:

```
Reported (blended)          CPB $266.59      CPQB $414.69
Paid-attributed only        CPB $339.29      CPQB $533.18
                                 +27%             +29%
```

If those three bookings came from organic or direct, the headline understated real paid CPB by 27%.
The legacy alert flagged the 21.4% but never priced it.

`FunnelSnapshot` carries both figures and `SendCostAlertAction` prints both, with a sentence naming
the gap in words. The gap is the size of the attribution debt, and naming it is the only thing that
ever gets it paid down. The attribution-gap line is printed even on the days the gap is zero: a line
that only appears on bad days is one people learn to dread rather than read.

### 3.2 The sample contained a real contradiction, and nothing caught it — fixed

The first version of this document claimed the contradiction was this pair:

```
Last lead:    1064 min ago   (17h 44m — so ~21:16 the previous evening)
Last booking:  269 min ago   (4h 29m)
```

**That reasoning was wrong and is not what the code checks.** A newest booking newer than a newest
lead is completely ordinary: an hour in which no new lead arrives and an older lead books produces
exactly that, and it is most afternoons. Only the same *person* has to arrive before they can book,
and nothing comparing two aggregate timestamps ever sees a person. A check that fires on ordinary
days is worse than no check, because it teaches people that the warnings at the top of the message
are noise. `AlertReconciler` deliberately does not make it.

The real contradiction in that sample is this:

```
Total New Appts: 14
Total Spend:     $3,732.24
Last lead:       1064 min ago
Booking rate, last 1000 leads: 84%
```

Fourteen appointments created that day, $3,732 spent to create them, and no lead since the night
before — while 84% of leads historically book. Every one of those fourteen would have to be an older
lead booking late, on a heavy-spend day, with a lead-to-booking rate near one to one. That is what a
dead capture path looks like while the calendar keeps working. The alert printed it with full
confidence because nothing in it compared one figure against another.

So the message now carries its own audit, and the findings go at the top in red rather than in a
footnote, because their whole purpose is to stop somebody acting on the figures below them.
`AlertReconciler` makes three checks: bookings with no lead at all today; per-platform bookings that
do not sum to the total, which means a cost figure is dividing by the wrong denominator; and silence
longer than `stale_lead_minutes` *during the reporting window only*, since outside it an overnight
gap is just the night.

### 3.3 CPB on one booking is not a rate — fixed

Google's CPB, its CPQB and its total spend were all $737.46, because Google had exactly one booking.
Three of the six per-platform figures in that sample were a single event presented as performance.
`PlatformSlice::isSmallSample()` marks any platform under `marketing.cost_alert.small_sample`
bookings with an asterisk and a note saying to read it as a count, not a rate. The 7-day rolling
figure beside it arrives with spend.

### 3.4 No baseline, so no meaning — partly fixed

`Total CPB: $266.59` — good or bad? The alert never said, and a number with nothing to compare it to
gets skimmed within a week. Leads, bookings and qualified now all carry a delta against the same
hour on the previous `baseline_days` days. Targets exist in config
(`marketing.cost_alert.targets.cpb` / `.cpqb`) and print an over-or-on-target verdict, but they only
have anything to judge once spend lands — **and nobody has set them yet**.

### 3.5 Mid-day spend and mid-day bookings are not the same clock — fixed

Bookings lag the spend that produced them, so an intraday CPB is structurally inflated against an
end-of-day one, and comparing a 15:00 figure to a full-day target compares two different things.
Every baseline is a same-*hour* average, and the card states how much of the day has elapsed.

### 3.6 Three green lines every run — fixed

"No active issues" three times, every time, was most of the message's vertical space on the days
nothing was wrong. It is one line now, and it expands only when there is something to expand about.
Today it reads "not monitored yet".

### 3.7 Emoji — fixed by not doing it

The legacy message used `:gráfico_de_barras:` and `:círculo_verde_grande:`, non-ASCII Spanish emoji
aliases that do not resolve in most workspaces, and one line carried a stray `*` breaking its
markdown. This codebase has a documented no-emoji rule for Slack with tests enforcing it
([`docs/slack-app.md`](slack-app.md)). Hierarchy comes from headers, dividers and field grouping.

### 3.8 Attribution was last-touch `utm_source` only — fixed, and it found something

`LeadPlatform` now falls back to click IDs. Measured against the live table on 2026-09-19, before
and after, over the 2,571 booked leads:

```
unattributed before   436   (17.0%)
unattributed after    395   (15.4%)
rescued by click id    41   — every single one of them via fbclid
```

Two things in that worth more than the headline.

**The gain is smaller than expected, and entirely in the weakest signal.** Not one booking was
rescued by `gclid`, `msclkid` or `li_fat_id`, because Google's auto-tagging already travels
alongside a `utm_source` — the leads carrying those click IDs were never unattributed. The whole
effect is `fbclid`, which is the one click ID that does not prove anybody paid. That is what sent
the next part of this sideways.

**So a gate was built to keep unpaid bookings out of the cost denominator — and the first version
of it was wrong in an instructive way.** It read HandL's `traffic_source` as the authority, and it
was applied only to bookings the click-ID fallback had rescued. Both halves were mistakes, and a
review against the live table found them.

*Wrong signal.* Cross-tabulating booked leads by `utm_medium` against what HandL says:

```
Meta,   utm_medium = paid-social (1,784)    HandL says paid on   471
Google, utm_medium = ppc         (  101)    HandL says paid on   101
```

Google's paid clicks carry a `gclid`, which HandL recognises, so the two agree perfectly. Meta's
carry an `fbclid`, which HandL does not treat as proof of payment — it classifies by referring host,
and facebook.com is "social" whether or not money changed hands. **HandL cannot see paid Meta
traffic at all.** Trusting it would have thrown 1,313 genuinely paid bookings out of Meta's
denominator and inflated Meta's cost per booking roughly fourfold — the opposite error, equally
wrong, and this time in the direction that gets a working channel switched off.

`utm_medium` has no such blind spot, because it is not inferred: somebody building a campaign link
wrote `paid-social`, and that is a statement about money. The live values are a short clean set —
`paid-social` 1,785, `email` 136, `ppc` 113, `social` 84, `paid` 7, none 446 — so the order is now a
paid-certain click ID, then `utm_medium`, then HandL, then the referring host. HandL still earns its
place on the 446 rows carrying no medium at all, which include every booking the `fbclid` fallback
rescues; there is no declaration to read there and its guess is the best signal available.

*Wrong scope.* The gate only ran for bookings resolved by click ID. But organic Instagram bio-link
traffic arrives tagged `utm_source=instagram`, resolves by UTM, and was going straight into Meta's
cost denominator — a population forty times the click-ID one. Every booking is now asked whether it
was paid for, not just the rescued ones.

**And the remaining gap turned out not to be a tracking failure at all.** Of the 395 booked leads
still carrying no platform, 392 carry a HandL first-touch blob, and it says:

```
organic  140      social  128      direct  98      referral  13      paid  6
```

None of them is a lost UTM with a surviving click ID; they have no click ID and no `utm_medium`
either. They are overwhelmingly traffic nobody paid for. That reframes the legacy alert's 21.4%
entirely — it is not broken attribution to be chased, it is roughly a sixth of bookings arriving
free, and it is further evidence that the blended cost per booking in section 3.1 was crediting paid
budget with unpaid bookings. Every card now breaks the gap down by channel for exactly this reason:
"21.4% unattributed" reads as an incident and gets escalated as one, where "2 organic search, 1
direct" reads as a fact about the business.

---

## 4. Spend: what reads it, and what still does not

Meta is built. Google and Microsoft are not, so their platform rows carry bookings and no cost, and
any total is withheld whenever a configured platform fails to answer.

`GOOGLE_CALENDAR_*` is Calendar, not Ads; `META_CAPI_*` is the write-only conversions grant.

### What Meta now reads, and why it takes two calls

`/insights` returns spend. It does not return the account's timezone or its status, and both change
how the spend figure should be read:

- **Timezone.** Meta sums a date range in the *ad account's* timezone. If the account sits on
  `America/Los_Angeles` while this alert counts an `America/New_York` day, three hours of spend land
  in the wrong bucket every day, worst first thing and last thing — and nothing about the resulting
  cost per booking looks wrong. `timezone_name` is read and compared against
  `marketing.cost_alert.timezone`; a mismatch is reported as a finding rather than silently
  corrected, because which of the two settings is the mistake is not knowable from the code.
- **Account status.** A disabled or unsettled ad account returns a perfectly successful response
  reporting `0.00`. Without `account_status`, "Meta spent nothing today" and "Meta's account is
  suspended" are the same HTTP 200, and the first is the reading people make. That check is what
  turns the legacy alert's permanently-green "Meta Ads: No active issues" line into one that can
  actually go red.

The request uses an explicit `time_range` rather than `date_preset=today`, because a preset is
resolved against the account timezone with no way to see which day it chose.

**Spend will come from native API clients, not from a warehouse table.** A sheet or a Supabase table
fed by each platform's scheduled report is far less code and one place to fix, but it is stale by
construction and it breaks silently — a scheduled export that stops running looks exactly like an ad
account that stopped spending. The alert's whole job is to be checkable, so it reads the platforms
directly.

| Needed for | Integration | Access cost |
| :--- | :--- | :--- |
| Google spend + account health | Google Ads API | Developer token issued against an MCC and **approved by hand by Google, often several days' wait**; OAuth client, refresh token, customer ID, and a login customer ID when calling through a manager account |
| ~~Meta spend + account health~~ | ~~Marketing API, Insights endpoint~~ | **Built.** Needs a Business Manager system user with `ads_read` and the ad account id; no app review to read your own account |
| Microsoft spend + account health | Microsoft Advertising API | Developer token, Entra OAuth, and **reporting is SOAP** — materially more work than the other two, for 2.7% of spend in the legacy sample |

The credentials themselves already have somewhere to live:
[`AdPlatformCredentials`](../app/Domains/Marketing/Support/AdPlatformCredentials.php) resolves them
environment-first, admin-setting-second, exactly as Slack and HubSpot do, and **Leads → Settings →
Ad Platform Read Credentials** renders a field for every key in `AdPlatformCredentials::KEYS`. That
exists ahead of the clients on purpose: the slow part of phase 3 is Google's approval queue, and
having somewhere to paste the token is what lets that clock start now. The settings card says
plainly that nothing reads them yet, because a screen full of fields that do nothing gets filled in
once and then reported as broken.

**Meta's token defaults to `META_CAPI_ACCESS_TOKEN`.** One Business Manager system user can hold
both `ads_read` and the Conversions API grant, so in the common case there is nothing new to paste.
It is still its own setting, because they are not the same permission: a CAPI-only token
authenticates fine and returns no insights, and that failure reads as an ad account that spent
nothing rather than as a missing scope. `AdPlatformCredentials::metaTokenIsShared()` is why the
settings screen says so out loud.

---

## 5. Operating it

```bash
wp acorn marketing:cost-alert          # post or refresh today's card
wp acorn marketing:cost-alert --dry    # print the figures, post nothing
wp acorn marketing:cost-alert --force  # send outside the reporting window
```

`--dry` goes through the same `FunnelMetricsService` the real run does, so what it prints is what
the message would be computed from; it just stops short of Slack. Use it rather than posting a test
card into a channel people read. `--force` exists so a human can look at the output without waiting
for 09:00.

The scheduled run is an **hourly** WP-Cron job on `rl_marketing_cost_alert`
(`MarketingServiceProvider::CRON_HOOK`). The action, not the schedule, decides what each tick means
— post the day's first card, edit the existing one, or do nothing because the clock is outside the
window — which is what makes the window a config value rather than a cron expression somebody has to
redeploy. Disabling the alert unschedules the event rather than leaving it firing into an early
return, so a disabled alert does not look live on every diagnostics screen. WP-Cron fires on a
request, so an hour with no traffic simply does not tick; each run recomputes the whole card from the
database, so a skipped hour costs freshness and nothing else.

| Key | Default | What it does |
| :--- | :--- | :--- |
| `marketing.cost_alert.enabled` | true | Off also unschedules the cron event |
| `marketing.cost_alert.channel` | empty | The channel to post in. Empty falls back to the default `#new-appts`, which is almost certainly not what you want |
| `marketing.cost_alert.timezone` | `America/New_York` | The ad accounts' clock, which defines "today" |
| `marketing.cost_alert.currency` | `USD` | |
| `marketing.cost_alert.window.from` / `.to` | 9 / 18 | Local hours, inclusive of both ends |
| `marketing.cost_alert.targets.cpb` / `.cpqb` | null | Null prints the figure with no verdict beside it |
| `marketing.cost_alert.small_sample` | 3 | Below this many bookings a platform's cost figure is marked as not a rate |
| `marketing.cost_alert.baseline_days` | 7 | How many prior days the same-hour average covers |
| `marketing.cost_alert.stale_lead_minutes` | 180 | Silence longer than this, inside the window, is a finding |
| `marketing.qualified.hubspot_stages` | `salesqualifiedlead` onward | A portal convention, not a fact about the code |
| `marketing.qualified.hubspot_min_coverage` | 0.5 | Below this, the HubSpot figure is suppressed and the card says why |
| `marketing.ads.meta.access_token` | `META_CAPI_ACCESS_TOKEN` | Needs `ads_read`, which the CAPI grant does not imply |
| `marketing.ads.meta.ad_account_id` | empty | With or without the `act_` prefix; both work |
| `marketing.ads.meta.api_version` | `v21.0` | |

Every one of these has an environment variable; see `config/marketing.php`.

**Meta is live once `access_token` and `ad_account_id` are both set, and not before.** A source
missing either is treated as not configured, so it is skipped silently rather than reported as
broken — which is correct for Google and Microsoft today, and is also how a half-filled Meta
configuration hides itself. If the card says "no ad platform connected yet" when you expect Meta,
that is the first thing to check.

### The two dashboard widgets, and which period each covers

There are now two marketing cards on the wp-admin dashboard, and they deliberately answer different
questions over different windows:

| Widget | Window | What it is for |
| :--- | :--- | :--- |
| **Marketing Cost Alert** | Today | The same snapshot the Slack card renders. Bookings, qualified, the attribution gap, cost per booking |
| **Marketing Performance** | Trailing 7 days | Submissions, qualified, consultations and conversion for the capture cohort, over the daily ingestion chart it sits above |

The second one used to report **lifetime** totals — 3,973 submissions, 2,571 consultations, a 64.7%
conversion rate — directly above its own trailing-7-day chart, with nothing on either saying so. A
lifetime conversion rate barely moves, so the largest numbers on the card were the ones that could
least tell you whether last week worked, and the chart underneath was measuring something else
entirely. Both halves now cover `MarketingDashboard::KPI_WINDOW_DAYS`, and every tile says so.

Its figures are a **cohort**: leads captured in the window, and how many of *those* have since
booked — not bookings that happened in the window. That distinction is what keeps the conversion
rate meaningful; dividing this week's bookings by this week's leads mixes two populations and can
exceed 100% in a week that works through a backlog. The all-time numbers have not gone anywhere,
they live on the domain overview widget and the Leads screen, which is where a lifetime figure
belongs.

The cost-alert snapshot renders as a wp-admin dashboard widget, registered whether or not Slack is wired —
an environment with no bot token is exactly the one where the dashboard is the only place the
figures can appear. The widget reads a five-minute cache and prints the age of the figures in its
heading, because a snapshot is around twenty-five queries plus Calendly round trips and that is the
wrong cost for every admin page load. The scheduled alert deliberately does not use the cache.
Neither surface computes anything of its own: if the two ever disagree it is a rendering bug, not a
measurement one.

---

## 5b. Shipping it to production

Most of it needs nothing. The parts that do are listed here in the order they bite.

### Carried by the deploy itself

| Piece | How it gets there |
| :--- | :--- |
| The `msclkid` column | `docker/entrypoint.sh` runs `wp acorn rl:deploy`, which runs `migrate --force` under a MySQL named lock |
| The Slack channel | Committed default in `config/marketing.php`, so no task-definition change is needed |
| Platform logos | Committed, and the emoji already exist in the workspace |
| The hourly job | Registers itself on `init`; `AlertWindow::enabledHere()` gates it to `production` |

**The alert starts posting on the first deploy.** `marketing.cost_alert.environments` is
production-only by default, which means production is exactly where it switches on. If it should
land quietly first, set `MARKETING_COST_ALERT_ENABLED=false` before the release and flip it after
a look at the wp-admin widget, which renders the same snapshot without posting anything.

### Needs a decision or an action

1. **Confirm WP-Cron actually runs.** `DISABLE_WP_CRON` defaults to false and nothing in the
   workflows or the entrypoint sets it, so WP-Cron fires on visitor requests — fine for a site
   with traffic. If it is ever disabled, this job stops silently, because a skipped tick is
   indistinguishable from a quiet hour by design.
2. **Check the bot can post.** `chat:write.public` covers a public channel it has not joined. A
   private `#marketing-cost-alerts` needs the bot invited first, or Slack answers
   `not_in_channel` and the run logs a warning nobody is watching for.
3. **Meta spend needs `META_ADS_ACCOUNT_ID`.** There is no default for it, and without it the
   Meta source reports itself unconfigured and is skipped — the card reports bookings and no
   cost, exactly as it does today. `META_ADS_ACCESS_TOKEN` falls back to `META_CAPI_ACCESS_TOKEN`,
   which is already in production, **but that token must carry `ads_read`** — see the warning in
   section 4. Both keys are on the allowlist in `scripts/sync-app-secrets-from-env.py`, so they
   travel as GitHub Environment secrets; they can also be pasted into Leads → Settings, which is
   the faster path and the reason that screen exists.
4. **Set the CPB and CPQB targets**, or the card prints the figures with no verdict beside them —
   defect 3.4, only half fixed until somebody supplies a number.
5. **Check the ad account timezone matches `marketing.cost_alert.timezone`.** Meta sums a date
   range in the account's own timezone. A mismatch is now reported as a reconciliation finding
   rather than silently skewing the cost figures, but it is better caught before the first card.

### Expect this on the first real card

Production may carry the same sparse activity log the local copy has — 2,571 leads marked
`booked` against six booking-event rows, because the imported Gravity history never passed
through this application. If bookings are still arriving by a path that does not write a
`LeadBookingCompleted` row, the card will report zero bookings and a red finding saying so. That
finding is correct and is the one worth acting on first: every cost figure divides by that count.

---

## 6. Known gaps in what shipped

These are live today. None of them is a bug to be filed; each is a limit worth knowing before acting
on a number.

**The HubSpot qualified figure suppresses itself, and will keep doing so.**
`hubspot_lifecycle_stage` is only populated for leads attached to an open referral —
`SyncHubSpotLifecycleAction::pendingLeads()` scopes the poll that way on purpose, to keep a
rate-limited API's quota off rows nothing reads. So a HubSpot-qualified count over today's bookings
is not merely incomplete, it is a count of referrals wearing the word "qualified". The alert refuses
to print it below `hubspot_min_coverage` and says what the coverage actually is instead. **Widening
that sync is the prerequisite for a HubSpot CPQB**, and it is the only work standing between the
definition and being usable — everything downstream of it is already wired and tested.

**`wbraid` and `gbraid` are captured but not used.** They are Google's iOS click IDs, they stay in
the `attribution` JSON blob rather than in columns, and `LeadPlatform` does not look at them. They
are rare enough that `gclid` covers the paid Google traffic that matters here, but a Safari-on-iOS
Google click that lost its UTMs is still unattributed today, and that biases Google's booking count
slightly downward.

**The paid/unpaid split rests on `utm_medium` being set correctly on campaign links.** It is the
primary signal (section 3.8) and it is a human convention, not a platform guarantee. A paid campaign
launched without a medium, or with one this code does not recognise, falls through to HandL — which
cannot see paid Meta traffic — and its bookings drop out of the cost denominator, making that
platform's cost per booking read too high. The list of paid media is deliberately a whitelist for
this reason: an unfamiliar medium understates the denominator rather than inflating it, and a cost
per booking that reads too high is the one that gets questioned. **If you add a campaign convention,
add it to `LeadChannel::PAID_MEDIA`**, or the spend will be divided by too few bookings.

**446 booked leads carry no `utm_medium` at all**, including every one the `fbclid` fallback
rescues. Those fall to HandL and then to the referrer, and 3 of them reach neither — they come back
`unknown` and are treated as not paid, which again errs high rather than low.

**`wbraid` and `gbraid` would close part of the remaining gap and are not used.** See above; on
current data no Google booking is unattributed at all, so this is latent rather than live.

---

## 7. The phases from here

**Phase 2 — Meta spend. Done, 2026-09-19.** Cheapest access of the three and the largest share of
spend, 78% in the legacy sample. Real CPB and CPQB for most of the budget, and the paid-versus-blended
comparison in section 3.1 is now a live figure rather than a historical one. Meta account health came
with it, because `account_status` was one field away in a call already being made.

**Phase 3 — Google spend.** Start the developer token application now; the approval wait is the long
pole, not the code. The work itself is a second `AdSpendSource` — the contract, the collector, the
suppression rule and the reconciler findings all exist and are tested, so the new class is the only
new thing. Google's client IDs are paid-certain, so no `LeadChannel` gate is needed for it.

**Phase 4 — Microsoft.** 2.7% of spend against the heaviest integration of the three: developer
token, Entra OAuth, and reporting over SOAP. Worth asking whether a scheduled platform report into
one of the other sources is the better trade before writing a SOAP client for it.

**Unscheduled, and worth more than any of it: alert on exception, not only on schedule.** The daily
card is for reading. A separate, immediate message when CPB breaches its band, a platform stops
delivering or an account goes unhealthy is for acting.
[`AvailabilityHealthMonitor`](../app/Domains/Scheduling/Services/AvailabilityHealthMonitor.php) is
already the pattern for this and is already tested: alert on entering a worse band, stay quiet
inside one, keep a repeat for the worst band only.
