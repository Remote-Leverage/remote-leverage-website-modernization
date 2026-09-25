# Booking rate after the website cutover — diagnostic

> **Revision 2 (2026-09-25).** Pixel 1430 is not the main account's pixel. The corrected root cause:
>
> - Every Meta ad set, in both accounts, sales and engagement, optimises on custom conversion `1361003065662161`. This comes from the warehouse `optimization_goal` and `results` fields.
> - Its volume held at about 27 → 28 credited a day.
> - Since the cutover, Meta credits it to the wrong ad sets. Credit per real booking went from 0.55 to 0.68 for the cheapest-click third, and from 0.46 to 0.36 for the priciest third.
> - So the optimiser shifted budget into cheap inventory.
> - The cached-page contamination (fixed in WR-379) is the only mechanism we found that re-routes credit this way. 1-day view-through attribution amplifies it.
>
> See `booking-rate-ceo-brief-2026-09-25.md`. Sections 3 and 5 below predate this correction.

2026-09-25. Covers 2026-08-25 to 2026-09-24. All days are US Eastern.

**Comparison window.** Unless stated otherwise:

- **Before** is Mon–Thu 09-08, 09-09, 09-10, 09-14, 09-15 and 09-16.
- **After** is Mon–Thu 09-21 to 09-24.
- The cutover days, 09-17 and 09-18, are shown separately.

## The answer

1. **Fewer people are booking, but only modestly, and none of the loss is qualified.**
   - Bookings in the warehouse (CRM deals) are down **5%**.
   - Unique first-time people booking in Calendly are down **12%**.
   - Qualified bookings ($10k+/month) are **up 13–17%**.
   - The whole loss is in companies under $10k/month.
2. **The booking *rate* collapsed because Meta is buying far more, far cheaper, far worse clicks.**
   - Meta spend rose about 14%.
   - Meta clicks rose 4.2x.
   - Meta bookings stayed flat.
   - Bookings per Meta landing-page view fell from **5.2% to 1.2%**, and to **0.55% on 09-24**.
3. **Two things happened in the same days, and together they produced the collapse.**
   - **The site change broke the conversion signal Meta's algorithm optimises on.**
     - From 09-18, sales ad sets whose click volume did not change converted 58% worse.
     - Engagement ad sets at unchanged volume held their rate, so the landing page and form are not the problem.
     - On the main ad account, the cost per 1,000 impressions for sales campaigns fell from about $70 to $11. That means Meta was buying cheaper, lower-value audiences.
     - This part is ours, and fixable in code today.
   - **Meta delivery was scaled up at the same time.**
     - New ad sets were added inside the existing campaigns.
     - Budgets stepped up on 09-19, 09-22 and 09-24.
     - The extra ~3,300 visitors a day produced almost no extra bookings.
     - About three quarters of the rate drop is that extra traffic.

## 1. What actually happened to bookings

| Measure (per weekday) | Before | After | Change |
|---|---|---|---|
| Warehouse bookings, all channels (RecruitCRM deals) | 81.0 | 76.8 | −5% |
| Warehouse qualified bookings | 43.8 | 49.8 | +14% |
| Unique first-time bookers, Calendly (reschedules, rebooks and internal tests removed; weekday-matched) | 89.1 | 78.5 | −12% |
| Step-1 form submissions | 141.7 | 136.0 | −4% |

By revenue band, first-time bookers changed as follows:

| Band | Change |
|---|---|
| $10k+ | **+17%** |
| $5–10k | −19% |
| Under $5k | **−63%** |

The warehouse, Calendly, PostHog and the production lead tables were each analysed independently. They agree within a few points.

Which channel lost the 5–6 bookings a weekday depends on the source:

- **The warehouse** puts it mostly in Meta: 66.8 → 63.8.
- **PostHog** has Meta flat (63.1 → 63.8) and non-Meta down 15% (email, Google Ads and organic, from 41.8 to 35.6). About 2.4 a day of that drop is one email blast on 09-11, which sits in the before window.
- **Either way** the absolute loss is small. The large change is in the rate.

The two real dips in the warehouse are the cutover days:

- **09-17:** 47 bookings.
- **09-18:** 33 bookings, while 67–78 people actually booked that day.

At least 53 bookings from the evening of 09-17 and 09-18 (up to 12:30 CT) never went out through the outgoing lead webhook or to Meta. If that webhook creates the CRM deal, those people are missing from the CRM and may not have been worked by sales. See action 6.

## 2. Meta cost (warehouse, `vw_mkt_home_daily` and `vw_mkt_meta_daily`)

| Meta (Facebook), per weekday | Before | After | Change |
|---|---|---|---|
| Spend | $15,761 | $17,954 | +14% |
| Link clicks | 1,457 | 6,131 | 4.2x |
| Landing-page views | 1,299 | 5,162 | 4.0x |
| Bookings | 66.8 | 63.8 | −5% |
| Bookings per landing-page view | 5.2% | 1.2% | −76% |
| Cost per booking | $236 | $282 | +19% |
| Qualified bookings | 35.3 | 40.5 | +15% |
| Cost per qualified booking | $446 | $443 | flat |

About 87% of Meta spend runs through the **Remote Leverage 5** ad account. In that account, weekday-matched:

| RL5 campaigns | Spend/day | Impressions/day | CPM | CPC |
|---|---|---|---|---|
| Sales, before | $7,488 | 110k | $67.79 | $11.53 |
| Sales, after | $7,520 | 411k | $18.31 | $2.48 |
| Engagement, before | $6,126 | 85k | $72.08 | $10.81 |
| Engagement, after | $8,113 | 307k | $26.39 | $3.16 |

The sales campaigns ran on the same budget but bought 3.7x the impressions at a quarter of the price. That is what Meta does when it cannot find people who convert.

### Daily detail

| Day | Meta spend | Link clicks | LPVs | Meta bookings | Bookings / LPV | Cost / booking | RL5 sales CPM | All bookings (CRM) | First-time bookers (Calendly) |
|---|---|---|---|---|---|---|---|---|---|
| 09-08 Tue | $14,120 | 1,278 | 1,134 | 46 | 4.1% | $307 | $71.9 | 56 | 72 |
| 09-09 Wed | $17,915 | 1,469 | 1,305 | 96 | 7.4% | $187 | $68.9 | 104 | 116 |
| 09-10 Thu | $16,448 | 1,484 | 1,333 | 63 | 4.7% | $261 | $65.7 | 75 | 93 |
| 09-11 Fri | $10,964 | 1,119 | 1,015 | 48 | 4.7% | $228 | $63.7 | 59 | 82 |
| 09-12 Sat | $8,578 | 732 | 670 | 38 | 5.7% | $226 | $78.4 | 41 | 49 |
| 09-13 Sun | $7,939 | 694 | 642 | 34 | 5.3% | $233 | $78.4 | 36 | 43 |
| 09-14 Mon | $12,456 | 1,393 | 1,257 | 49 | 3.9% | $254 | $69.9 | 73 | 69 |
| 09-15 Tue | $17,546 | 1,586 | 1,468 | 83 | 5.7% | $211 | $67.3 | 101 | 109 |
| 09-16 Wed | $16,082 | 1,532 | 1,295 | 64 | 4.9% | $251 | $65.4 | 77 | 92 |
| **09-17 Thu (cutover)** | $8,187 | 1,142 | 855 | 38 | 4.4% | $215 | **$35.2** | 47 | 54 |
| **09-18 Fri** | $12,292 | 1,471 | 1,283 | 26 | 2.0% | $473 | $39.8 | 33 | 67 |
| 09-19 Sat | $19,174 | 3,061 | 2,669 | 59 | 2.2% | $325 | $40.4 | 60 | 73 |
| 09-20 Sun | $10,035 | 1,796 | 1,580 | 39 | 2.5% | $257 | $22.4 | 41 | 46 |
| 09-21 Mon | $13,842 | 3,371 | 2,797 | 57 | 2.0% | $243 | $21.2 | 70 | 71 |
| 09-22 Tue | $18,351 | 4,967 | 3,862 | 71 | 1.8% | $258 | $21.6 | 79 | 75 |
| 09-23 Wed | $19,757 | 4,299 | 3,172 | 67 | 2.1% | $295 | $28.4 | 81 | 92 |
| 09-24 Thu | $19,867 | 11,886 | 10,819 | 60 | 0.55% | $331 | $11.3 | 77 | 76 |

## 3. Root cause: the Meta conversion signal

**What the old site sent Meta.**

- The HandL plugin sent a server-side `Lead` event to pixel **1430907207548734** ("RL 5/6 Meta Pixel").
- It sent one when a visitor finished step 1 and another when they booked. That was about 230 events a day, weighted toward people who booked.
- The browser also sent PageView to that pixel and to 1482937899395718.

**What the new site sends.** Measured from production's own send log:

| Period | Pixel 1430 (RL 5/6) | Pixel 1482 |
|---|---|---|
| Old site, 09-10 to 09-16 | ~230 Leads/day | PageView only, no Leads |
| 09-17 21:48 ET to 09-18 14:58 ET | **nothing** | nothing |
| 09-18 15:00 ET to 09-22 00:43 ET | ~115 Leads/day (one per person, bookers no longer counted twice) | same |
| **09-22 00:59 ET to now** | **nothing: no Lead, no PageView** | 124–167 Leads/day |

- A deploy on the night of 09-21 removed 1430 from the site's default pixel list (commit 3075368, released as v-20260921-v19). It's the only pixel with Lead history. **Correction (revision 2): it is not the RL5 account's pixel.** RL5 never received those Leads, and every ad set optimises on custom conversion `1361003065662161`. See the revision below.

**Most of what still reaches Meta is corrupted.**

- The CDN serves a cached copy of the booking form. That copy carries the Facebook click ID, IP address and browser of whoever loaded the page first.
- The form keeps those values instead of the real visitor's.
- Matching each lead against their own browser session shows that about **75–80% of Meta Leads since 09-22 carry another visitor's click ID, IP and browser**.
- Clean conversions reaching Meta are now about 15–22 a day, against about 65 a day before.
- The `_fbp` browser ID was also missing on about 85% of Leads until a fix went live on 09-24 at 19:00 ET.
- None of this shows in Meta's Events Manager. The fields are present; they just belong to the wrong person.

**How this lines up with cost and conversion.**

- RL5 sales CPM held at $64–78 for weeks. It fell to about $35 on 09-17 and 09-18, to about $21 from 09-20 to 09-22, and to $11 on 09-24.
- Sales ad sets that ran in both periods at unchanged volume went from 5.9% booked per visitor (09-08 to 09-17) to 1.7% (09-18 to 09-21). That was 22 bookings where 52 were expected.
  - Those same ad sets recovered partly, to 3.9%, on 09-22 to 09-24.
  - So the break lines up with the cutover itself: the 17-hour blackout, half the volume, missing `_fbp` and the start of contamination.
  - No separate further step shows up in the ad-set data after pixel 1430 went dark on 09-22. That pixel should still be restored.
- Engagement ad sets at unchanged volume held their rate (3.4% → 3.9%). That rules out a site-wide conversion problem.

**The media changes in the same days.**

All Meta traffic still comes from the same 5 long-running campaigns. The growth is at ad-set level:

- 37 new ad sets since 09-17 16:27 now bring 41% of Meta visitors.
- 28 continuing ad sets went to 2.6x their volume.
- 24 ad sets were switched off.

Delivery events:

| When (ET) | What |
|---|---|
| 09-17 09:00–13:00 | Meta throttled to 4–14 visitors/hour |
| 09-18 11:00–19:00 | Ads off, then relaunched |
| 09-20 17:00 → 09-21 10:00 | Main account (RL5) dark for about 17 hours |
| 09-22 14:21–17:53 | 12 new ad sets (11 engagement) |
| 09-24 from 21:00 | 36 ad sets across both accounts stepped up together |

What that bought:

- The ad sets scaled more than 3x added about 1,000 visitors a day and about 2 bookings.
- Engagement bookings rose from 23.9 to 33.5 a day.
- Sales bookings fell from 36.4 to 27.8 a day.

## 4. Other differences between the old and new site

**Still live:**

- **Under-$5k booking rate fell from 47% to 22%.**
  - The pricing warning shown to this band has the same text, button and calendar as on the old site.
  - Its lead volume is unchanged.
  - So the drop fits lower-intent leads from the degraded Meta targeting better than a screen change. This is an inference, not proven.
- **The "add to calendar" link is dead.**
  - The old site stored a `/scheduler-link?id=…` link on every booking. It served an `.ics` file, or a Google Calendar link on Android.
  - Its HubSpot feed sent that link as `schedule_link`, so confirmation emails and texts could offer it.
  - The new site never set it, and the route was not ported, so all 1,585 links already in the CRM return 404. That hurts show rate, not bookings.
  - Separately, about 8.6 people a weekday used to rebook through Calendly without re-filling the form. They now re-fill it. That was not through this link, and the cause is unconfirmed.
- **$5–10k leads from the `/hire-va-4/` hero form used to go to the T10 calendar.** They now go to T0, so any "qualified by calendar" count drops by design.
- **Non-US phone numbers now see a "Looking for VA work?" screen.** This is small: 48 people, mostly genuine job seekers.
- **The CRM no longer gets booking data from the site.**
  - The old HubSpot feed never sent a booking status either. Its only booking-specific field was `schedule_link`.
  - It also set a lifecycle stage on every submission: `lead`, or `marketingqualifiedlead` from `/hire-va-4/`. The new site sends neither.
  - The HubSpot browser tracking script was removed on 09-19.
  - No Calendly webhook points at the site.
  - Campaign-level attribution after cutover is also unreliable, because of the cache contamination above.

**Fixed, but they cost bookings while live:**

| Issue | Live |
|---|---|
| Slot times shown in the wrong timezone. Show rate for 09-19/20 bookings was 32% and 44%, against about 60% normally. | cutover → 09-21 |
| Phone field could lock visitors out of step 1 | 09-21 → 09-22 |
| ZeroBounce rejected role emails (info@, sales@) | cutover → 09-22 23:52 CT |
| Server capacity exhausted on /vacalendar/ during the 09-23 email send | 09-23 → 09-24 21:38 CT |

**Ruled out:**

- **Page speed:** the new pages are much faster. LCP p75 went from 3.1s to 0.7–1.0s.
- **The booking step itself:** 82% of $10k+ step-1 submitters book, the same as before.
- **Duplicate or phantom bookings:** 5 in total, removed from the counts.
- **Reps or direct links filling a gap:** no.

## 5. Actions

1. **Today: put pixel 1430907207548734 back on the site, browser and server-side.** One config value (`META_PIXEL_IDS` or the default in `config/pixels.php`).
2. **Today: stop the cached form from carrying another visitor's details.** The form should take click ID, IP and browser from the actual visitor's request, not from the cached page.
3. **Confirm in Ads Manager / Events Manager:**
   - which pixel and conversion event each sales ad set optimises on
   - whether the sales ad sets are flagged "learning limited"
   - whether any custom conversions (for example a `/VAThankYou/` URL rule) sit on pixel 1430
4. **Hold new ad sets and budget increases** until the clean signal has run for a few days. Review the ad sets added or scaled since 09-18: they buy clicks at $1.44–$4 that almost never book.
5. **Consider restoring the booking-weighted signal the old site gave Meta:** a second server-side event when someone books. Agree it with the media buyer first, because changing an optimisation event resets learning.
6. **Backfill the 53 bookings from 09-17/18** that never went out through the outgoing lead webhook. Check whether the CRM has them.
7. **Restore `/scheduler-link/` and send it to HubSpot as `schedule_link`.**
8. **Decide the HubSpot lifecycle stage.** The old feed set `lead` or `marketingqualifiedlead` on every submission. Check it against the portal first: HubSpot rejects an entire update over one bad value, and every other field goes with it.

Actions 1, 2 and 7 are implemented and tested on branch `fix/booking-signal-attribution`, not yet merged or deployed. Section "Fix status" below has the details.

## Fix status

Branch `fix/booking-signal-attribution`, based on production (`v-20260924-v10`). Full suite: 2,242 passed. Pint passes.

| Fix | Change |
|---|---|
| Pixel 1430 restored | `config/pixels.php` default is both pixels again. Browser PageView and the server-side Lead go to 1430907207548734 and 1482937899395718. |
| Cached attribution | The booking form re-reads everything from the visitor's own submit request: IP, browser, cookies, and the page's query string. Nothing is kept from the cached page. It also gets a new session ID. The browser no longer writes an `_fbc` into `fbclid`. |
| `/scheduler-link/` | New route. Serves `.ics`, or Google Calendar on Android. Never cached, lookups cached, capped at 60 Calendly calls a minute. |
| HubSpot `schedule_link` | Every completed Calendly booking stamps the link on the lead and re-syncs the contact. This covers the retry ladder too. |

## Not verified

- ~~Which pixel the ad sets optimise on.~~ Answered in revision 2: custom conversion `1361003065662161`. Its definition (rule, data source) is still unverified.
- Why RL5 sales CPM already halved on 09-17, before the server-side blackout began at 21:48 ET. The old site's pixel snippets were re-saved that morning (09:19–09:27 PT), and the change cannot be diffed.
- How Meta treats a Lead whose email and phone are the visitor's but whose click ID, IP and browser are someone else's.
- ZeroBounce rejections. The rejected-leads table was not part of the data pull.

## Sources

- Warehouse: BigQuery `rl-data-platform-dev.sandbox_victorluz`, views `vw_mkt_home_daily` and `vw_mkt_meta_daily`.
- Calendly API: 1,642 sales-consultation invitees.
- PostHog: project 282594.
- Production lead tables and send log, pulled 2026-09-25 06:31 UTC.
- The old server's database and plugin source (read-only), plus the 09-19 page snapshots.

All queries were read-only.
