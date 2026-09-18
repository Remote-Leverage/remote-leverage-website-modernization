# Legacy redirect audit — GoDaddy production vs v2

Source: `339104.us18.ssh.myftpupload.com` (`/html`), read 2026-09-18.

## Where redirects live on the legacy box

| Store | Rules | Notes |
|---|---:|---|
| `301-redirects` plugin — table `wp_add9221751_wf301_redirect_rules` | 326 enabled, 1 disabled | all 301, all `case_insensitive=enabled`, none regex; has hit counters |
| Yoast SEO Premium — option `wpseo-premium-redirects-export-plain` | 355 | all 301, no hit counters |
| `.htaccess` | 0 | file is empty |
| nginx / server config | n/a | GoDaddy Managed WP, not user-editable |

**Unique source paths across both stores: 682 rows → 470 unique paths** (211 paths exist in both stores).

## Status — merged 2026-09-18

The **107 trafficked entries (63 external + 44 internal) are now in `config/redirects.php`**,
taking it from 187 to 294 keys. Full suite green (1,537 passed), Pint clean.

What changed alongside the map:

- `tests/Feature/RoutesTest.php` — 60 new `$externalTargets`, a new `$contentTargets` class for
  blog-post and `case_study` targets (a page allowlist could not honestly hold them), and
  `referral-program` declared as a page.
- `tests/Unit/LegacyRedirectTest.php` — the pinned `externalHosts()` list grew from 3 to 18.

Targets were resolved against the v2 database, not copied from the legacy rows:

- **2 redirect chains collapsed** — legacy pointed `/marketing-assistants-b/` and
  `/live-session-what-to-automate-from-day-1/` at slugs that are themselves keys in this map.
- **15 case studies re-pointed** from `/blog/<slug>` to the `case_study` CPT at `/case-study/<slug>`.
- **1 renamed post** — `5-best-medical-receptionist-services-for-clinics-and-private-practices`
  is `best-medical-receptionist-services` in v2.
- **13 orphaned `-guide` slugs → `/blog/`.** Those posts were never migrated; fuzzy-matching them
  against surviving posts produced nothing convincing, so they follow the map's existing
  editorial policy rather than a guess. `/how-much-does-athena-virtual-assistant-cost/` was the
  one exception — v2 has `blog/athena-virtual-assistant`, so it points there.

The 8 conflicts in §4 were left pointing at **our** targets, by decision — legacy dumped most of
them on the homepage, ours go to the relevant page.

### Still open

- **338 Yoast blog-slug redirects not ported** (§3). Yoast keeps no hit counter, so zero there
  means *unknown*, not *unused*. Most target posts v2 does not have; porting them needs the blog
  migration gap closed first.
- **`/coldcallscript/` and `/followupscript/`** (24 hits each) served Download Monitor PDFs
  (`download/2637`, `download/2640`) that were never migrated. They fall back to the root under
  the map's no-live-equivalent policy — re-point them if the collateral is brought over.
- **Case sensitivity** (§6) is unfixed: legacy matched case-insensitively, v2 does not.
  `/Deposit/` and `/APPLY` still 404 in v2.

## Headline

| | Count |
|---|---:|
| Legacy redirects (unique paths) | 470 |
| Our `config/redirects.php` keys | 187 |
| **Missing from ours** | **445** |
| Present in both | 25 (17 same target, 8 conflicting) |
| Only in ours, no legacy counterpart | 162 |

---

## 1. MISSING — external-target vanity links (63)

These are operational short links (recruiting, Zoom rooms, Stripe, Calendly, forms). None exist in v2. `/apply` alone has 43,636 hits.

| Hits | Path | Target |
|---:|---|---|
| 43636 | `/apply` | https://remoteleveragejobs.com/?ref=https://remoteleverage.com/apply |
| 19272 | `/recruitinginterview` | https://calendly.com/pacific-recruiters/job-interview-remote-leverage-team-clone |
| 7648 | `/jobinterviewinstructions` | https://vimeo.com/1143608840/e4c8dc87eb?fl=tl&fe=ec |
| 5355 | `/recruiterszoom` | https://us02web.zoom.us/j/6153893236 |
| 4897 | `/zoom` | https://us02web.zoom.us/j/9794933436?pwd=dENpRHJLL2h2RVlTZjJaODRndVJnQT09 |
| 3999 | `/submitvideo` | https://forms.gle/rqHADHQk1M31TjGPA |
| 3367 | `/typing` | https://www.livechat.com/typing-speed-test/#/ |
| 2104 | `/adminassessment` | https://forms.gle/3oUqzMLizn6bfPAS9 |
| 1412 | `/nyxzoom` | https://us02web.zoom.us/j/9907147461?pwd=hwOW946MrYD3cpZYbIa9Fa6zkfpj1I.1 |
| 1235 | `/it` | https://remoteleveragetech.atlassian.net/servicedesk/customer/portal/1/group/1/create/1 |
| 1231 | `/zoom2` | https://us02web.zoom.us/j/7172910526?pwd=ryHFp0X4eFvSMerLFsae3LG2RArcno.1 |
| 1049 | `/claireinterview` | https://calendly.com/claire-remoteleverage/job-interview-remote-leverage-team-clone |
| 957 | `/salesassessment` | https://forms.gle/Ntxhgn9hNfxBKjQN7 |
| 943 | `/adminzoom` | https://us02web.zoom.us/j/4055248514?pwd=XwiKkIbXlEoaaWjCP3wOqzratPxmqV.1 |
| 813 | `/w9` | https://drive.google.com/file/d/1w1_Pi54QZBe5k2xxtbE3rCvClNaeWX0l/view?usp=sharing |
| 699 | `/interviewzoom` | https://us02web.zoom.us/j/4469787983?pwd=iwK8oQenKMxgxGHSeEiteUs1G34KD8.1 |
| 525 | `/seifszoom` | https://us02web.zoom.us/j/8438962979?pwd=BtNW4PHSBO1CXfkJ0kF46Kkzxrbonr.1 |
| 493 | `/dashboards` | https://sso.online.tableau.com/public/idp/SSO |
| 447 | `/estrecruitinginterview` | https://calendly.com/eastern-recruiters-remoteleverage/job-interview-remote-leverage-team |
| 439 | `/virtual-assistant-posts/salary-guide-for-businesses-hiring-virtual-assistants-guide` | https://anyshore.ai/blog/latin-american-va-salary-guide/ |
| 336 | `/googlereview` | https://g.page/r/CTuB-J467qJwEAE/review |
| 279 | `/angelicagomez` | https://recruitcrm.io/apply/17811178058780133835zgp |
| 278 | `/christinaszoom` | https://us02web.zoom.us/j/4821528296?pwd=aG5Q6HhLv8Bwuf0b2Sr6xtqmDvzMaw.1 |
| 276 | `/testimonial` | https://calendly.com/d/cs4r-k2g-d2p/remote-leverage-testimonial-session |
| 184 | `/angie` | https://recruitcrm.io/apply/17806985112870087768JmC |
| 144 | `/miry` | https://recruitcrm.io/apply/17811401732160133835JtX |
| 137 | `/marketingmaterials` | https://docs.google.com/document/d/1UUGApiJ0W21RKI3GQ5QHncYJF1Wf1RraPjg1LG4un1Q/edit?usp=sharing |
| 76 | `/uptime` | https://stats.uptimerobot.com/c7mpGQmbnC |
| 62 | `/deel` | https://get.deel.com/eotkl4au2m8w |
| 58 | `/interviewvideo` | https://vimeo.com/1124026646/649fff7922?share=copy |
| 55 | `/abbascalendar` | https://calendly.com/abbas-remoteleverage/30min |
| 46 | `/lead` | https://forms.gle/8ZMPNRKwBveBa67M7 |
| 36 | `/ideas` | https://form.jotform.com/260564755295164 |
| 35 | `/fire` | https://form.jotform.com/252558126272155 |
| 35 | `/natasha` | https://us02web.zoom.us/j/7543604107?pwd=cm1BQzRlOWNocklwOHNMTDBKODFRQT09 |
| 34 | `/vaonboarding` | https://calendly.com/remoteleverage/client-va-onboarding-call |
| 27 | `/introcall` | https://calendly.com/d/cqnx-7z2-2rq/remote-leverage-onboarding-applicant-criteria |
| 24 | `/jobinvitation` | https://form.jotform.com/243466875000052 |
| 19 | `/join` | https://buy.stripe.com/6oEeWM0EUegh1qgdQW |
| 18 | `/cordeposit` | https://buy.stripe.com/3cIfZh1B8cQT29D0eWfrW0F |
| 18 | `/princessinterview` | https://calendly.com/remoteleverage/job-interview-test |
| 15 | `/interview` | https://calendly.com/remoteleverage/job-interview-test |
| 7 | `/replit` | https://recruiting-helper.replit.app/ |
| 6 | `/laura` | https://recruitcrm.io/apply/17806984609920087768oJQ |
| 6 | `/lina` | https://recruitcrm.io/apply/17811180148840133835icy |
| 6 | `/splitpayment` | https://form.jotform.com/243395973807471 |
| 5 | `/firefighting` | https://form.jotform.com/252558126272155 |
| 3 | `/onboardingmeeting` | https://calendly.com/remoteleverage/onboarding |
| 2 | `/it2` | https://remoteleveragetech.atlassian.net/servicedesk/customer/portal/1/group/1/create/1 |
| 2 | `/training` | https://calendly.com/remoteleverage/coldcallingtraining |
| 2 | `/vatraining` | https://docs.google.com/document/d/1GoY3pWKRwPVmH7fyCWbNL-5PCeNDcxkX-eNp2mn91TA/edit?usp=sharing |
| 0 | `/12monthlyfee` | https://buy.stripe.com/7sIg0QcnCa01d8Y5kB |
| 0 | `/15` | https://calendly.com/remoteleverage/15-minute-meeting |
| 0 | `/1500` | https://buy.stripe.com/fZe4i81IY6NP2uk00o |
| 0 | `/2000` | https://buy.stripe.com/7sIdSI3R6fklfh6cNd |
| 0 | `/6monthlyfee` | https://buy.stripe.com/5kAbKAfzOdcdfh6aEU |
| 0 | `/cruzcalendar` | https://calendly.com/cruzremoteleverage/virtual-assistant-hiring-consultation-clone |
| 0 | `/estinterviewzoom` | https://us02web.zoom.us/j/6990050267?pwd=RpNwxbjq22OrcMAe36gJJfxUNuI0Ha.1 |
| 0 | `/extendedguarantee` | https://buy.stripe.com/6oE2a01IY2xz7OE6oT |
| 0 | `/followupmonthlyfee` | https://buy.stripe.com/14kaGwcnC7RT4Cs8wL |
| 0 | `/monthlyfee` | https://buy.stripe.com/eVacOEafu5JL1qg28g |
| 0 | `/natashacalendar` | https://calendly.com/remoteleveragesales/natasha-1-on-1-meeting |
| 0 | `/vaexam` | https://forms.gle/jGL2PVu11C9189WN6 |

---

## 2. MISSING — internal, with recorded traffic (44)

| Hits | Path | Target |
|---:|---|---|
| 604 | `/partnership-program` | `/referral-program` |
| 504 | `/case-studies` | `/case-study` |
| 467 | `/virtual-assistant-posts/how-much-does-athena-virtual-assistant-cost-guide` | `/blog/athena-virtual-assistant` |
| 373 | `/virtual-assistant-posts/virtual-medical-receptionist-revolutionizing-healthcare-support-guide` | `/blog/5-best-medical-receptionist-services-for-clinics-and-private-practices` |
| 317 | `/virtual-assistant-posts/medical-coordinator-key-requirements-duties-responsibilities-and-skills-guide` | `/blog/patient-care-coordinator-cost` |
| 250 | `/landing-page-2` | `/` |
| 225 | `/virtual-assistant-posts/why-hiring-a-marketing-assistant-can-transform-your-business-guide` | `/blog/marketing-virtual-assistant-vs-marketing-agency` |
| 202 | `/virtual-assistant-posts/understanding-the-role-of-an-executive-administrative-assistant-guide` | `/blog/executive-assistant-vs-virtual-assistant` |
| 149 | `/marketing-assistants-b` | `/marketing-assistants` |
| 143 | `/blog/how-ku%ca%bbulei-found-high-level-social-media-marketing-talent-through-remote-leverage` | `/case-study/hawaiian-philanthropy` |
| 135 | `/partnership-program-form` | `/` |
| 128 | `/blog/how-remote-leverage-helped-a-u-s-law-firm-build-a-high-performing-remote-team` | `/case-study/smiley-injury-law` |
| 80 | `/blog/case-study-how-a-texas-auto-shop-doubled-local-hiring-power-with-remote-leverage` | `/case-study/jeremis-auto-repair` |
| 76 | `/blog/case-study-how-on-the-outskirt-marketing-hired-their-first-virtual-employee-with-ease` | `/case-study/on-the-outskirt` |
| 71 | `/blog/finding-the-perfect-hire-for-a-private-practice-with-remote-leverage` | `/case-study/watson-psychiatry` |
| 71 | `/blog/how-anchorage-care-coordination-gained-reliable-daily-support-with-remote-leverage` | `/case-study/anchorage-care-coordination` |
| 70 | `/blog/case-study-mobile-mixologists-hires-a-bilingual-va-fast-and-frees-up-the-founder-to-scale` | `/case-study/mobile-mixologist` |
| 70 | `/blog/goldsoil-realty-investments-hires-a-closer-on-the-spot` | `/case-study/the-acre-hub` |
| 70 | `/blog/how-doran-industries-scaled-event-sales-with-a-virtual-assistant-from-remote-leverage` | `/case-study/doran-industries` |
| 70 | `/blog/how-haus-of-her-studios-saved-money-gained-a-highly-qualified-va-with-remote-leverage` | `/case-study/haus-of-her` |
| 66 | `/blog/how-bench-accounting-scaled-fast-by-hiring-31-virtual-assistants-through-remote-leverage` | `/case-study/bench-accounting` |
| 62 | `/blog/how-sales-leader-zack-beck-reclaimed-his-focus-with-a-virtual-assistant` | `/case-study/conservice` |
| 61 | `/blog/how-fast-real-estate-hired-a-cold-calling-va-and-built-scalable-systems-with-remote-leverage` | `/case-study/fast-real-estate` |
| 45 | `/footer-fix-test` | `/` |
| 34 | `/the-secret-to-scaling-your-ebay-store-hire-an-ebay-virtual-assistant` | `/the-secret-to-scaling-your-ebay-store-hire-an-ebay-virtual-assistant-guide` |
| 33 | `/partnership-program-thank-you` | `/` |
| 24 | `/coldcallscript` | `download/2637/?tmstv=1697591198` |
| 24 | `/followupscript` | `download/2640/?tmstv=1710178256` |
| 11 | `/real-estate-cold-calling-virtual-assistants-the-secret-to-real-estate-success` | `/real-estate-cold-calling-virtual-assistants-the-secret-to-real-estate-success-guide` |
| 8 | `/virtual-administrative-assistant` | `/virtual-administrative-assistant-guide-2` |
| 7 | `/hire-social-media-content-creator-everything-you-need-to-know` | `/hire-social-media-content-creator-everything-you-need-to-know-guide` |
| 5 | `/virtual-assistants-and-time-management-how-to-delegate-effectively` | `/virtual-assistants-and-time-management-how-to-delegate-effectively-guide` |
| 5 | `/virtual-assistants-for-different-industries-tailoring-services-to-your-needs` | `/virtual-assistants-for-different-industries-tailoring-services-to-your-needs-guide` |
| 5 | `/virtual-medical-administrative-assistant` | `/virtual-medical-administrative-assistant-guide` |
| 5 | `/what-is-an-example-kpi-for-administrative-assistant` | `/what-is-an-example-kpi-for-administrative-assistant-guide` |
| 4 | `/live-session-what-to-automate-from-day-1` | `/live-session-build-ai-tools-for-businesses` |
| 3 | `/cal` | `/` |
| 3 | `/tasks-landing-page` | `/` |
| 1 | `/essential-guide-to-a-virtual-assistant-contract-template` | `/essential-guide-to-a-virtual-assistant-contract-template-guide` |
| 1 | `/how-much-does-athena-virtual-assistant-cost` | `/how-much-does-athena-virtual-assistant-cost-guide` |
| 1 | `/the-virtual-financial-planning-assistant-your-secret-weapon-to-scaling-your-business` | `/the-virtual-financial-planning-assistant-your-secret-weapon-to-scaling-your-business-guide` |
| 1 | `/understanding-white-label-virtual-assistant-services` | `/understanding-white-label-virtual-assistant-services-guide` |
| 1 | `/virtual-assistant-vs-in-house-employee-pros-and-cons` | `/virtual-assistant-vs-in-house-employee-pros-and-cons-guide` |
| 1 | `/why-hiring-an-admin-assistant-working-from-home-is-a-game-changer-for-busy-business-owners` | `/why-hiring-an-admin-assistant-working-from-home-is-a-game-changer-for-busy-business-owners-guide` |

---

## 3. MISSING — internal, no hit counter (338)

Mostly Yoast-side blog-slug corrections (old post slug → current slug). Yoast does not record hits, so zero here means *unknown*, not *unused*.

| Path | Target | Store |
|---|---|---|
| `/10976-2` | `accounting-virtual-assistant-your-key-to-effortless-financial-management` | yoast |
| `/__trashed-3` | `tools` | yoast |
| `/a-guide-to-help-desk-outsourcing-for-modern-businesses` | `/a-guide-to-help-desk-outsourcing-for-modern-businesses-guide` | both |
| `/a-thorough-look-at-virtual-assistants-platform-in-mexico` | `/a-thorough-look-at-virtual-assistants-platform-in-mexico-guide` | both |
| `/accounting-virtual-assistant-your-key-to-effortless-financial-management` | `/accounting-virtual-assistant-your-key-to-effortless-financial-management-guide` | both |
| `/affiliate` | `referralprogram` | yoast |
| `/affiliate-registered` | `referralpartnerregistered` | yoast |
| `/affiliatetoc` | `referraltoc` | yoast |
| `/ai-latin-american-virtual-assistant-placement-tool` | `anyshore` | yoast |
| `/appointment-calendar-2` | `appointment-calendar` | yoast |
| `/appointment-calendar-v1` | `appointment-calendar-2` | yoast |
| `/are-remote-workers-working-all-day` | `/are-remote-workers-working-all-day-guide` | both |
| `/attorney-lead-generation-strategies-and-virtual-assistant-support` | `/attorney-lead-generation-strategies-and-virtual-assistant-support-guide` | both |
| `/b2b-lead-generation-ultimate-guide-to-boost-your-sales` | `/b2b-lead-generation-ultimate-guide-to-boost-your-sales-guide` | both |
| `/b2c-telemarketing-how-to-do-it-well` | `/b2c-telemarketing-how-to-do-it-well-guide` | both |
| `/benefits-of-hiring-an-offsite-receptionist` | `/benefits-of-hiring-an-offsite-receptionist-guide` | both |
| `/benefits-of-outsourced-telemarketing` | `/benefits-of-outsourced-telemarketing-guide` | both |
| `/benefits-of-using-an-applicant-tracking-system-for-small-companies` | `/benefits-of-using-an-applicant-tracking-system-for-small-companies-guide` | both |
| `/best-local-advertising-for-small-business` | `/best-local-advertising-for-small-business-guide` | both |
| `/best-virtual-assistant-for-insurance-agents-the-secret-to-scaling-your-business` | `/best-virtual-assistant-for-insurance-agents-the-secret-to-scaling-your-business-guide` | both |
| `/blog-how-to-find-architecture-clients` | `how-to-find-architecture-clients` | yoast |
| `/blog/10-signs-youre-ready-to-hire-a-virtual-assistant` | `blog/10-signs-to-hire-a-virtual-assistant` | yoast |
| `/blog/5-best-medical-receptionist-services-for-clinics-and-private-practices` | `blog/best-medical-receptionist-services` | yoast |
| `/blog/best-healthcare-virtual-assistant-companies-for-medical-practices-2026` | `blog/best-healthcare-virtual-assistant-companies` | yoast |
| `/blog/category/salary-guide` | `blog/category/salary-guides` | yoast |
| `/blog/checklist-for-hiring-a-virtual-executive-assistant` | `blog/executive-assistant-hiring-checklist` | yoast |
| `/blog/content-engine-that-converts-virtual-assistants` | `blog/build-content-engine-that-converts` | yoast |
| `/blog/discover-how-a-virtual-assistant-for-construction-company-and-home-service-businesses-cuts-admin-handles-scheduling-and-helps-owners-get-back-in-the-field` | `blog/virtual-assistants-for-construction-company-and-home-service-businesseses` | yoast |
| `/blog/emporia-consulting-scales-faster-with-a-latin-america-va` | `case-study/emporia-consulting` | yoast |
| `/blog/executive-assistant-companies` | `blog/top-executive-assistant-companies` | yoast |
| `/blog/family-law-attorneys-virtual-staff` | `blog/family-law-manage-email-comms-vas` | yoast |
| `/blog/freelance-virtual-admin-vs-virtual-administrative-assistant` | `blog/virtual-asssitants-scale-lead-generation` | yoast |
| `/blog/freelance-virtual-admin-vs-virtual-administrative-assistant-which-should-you-hire` | `blog/freelance-virtual-admin-vs-virtual-administrative-assistant` | yoast |
| `/blog/freelance-virtual-admin-vs-virtual-administrative-assistant-which-should-you-hire-2` | `blog/freelance-virtual-admin-vs-virtual-admin-assistant` | yoast |
| `/blog/hiring-independent-contractors-internationally-here-s-why-yo` | `blog/hiring-international-contractors-contractor-of-record` | yoast |
| `/blog/how-a-contractor-of-record-platform-protects-your-business-f` | `blog/contractor-of-record-misclassification-protection` | yoast |
| `/blog/how-clean-cozy-home-found-the-right-bilingual-va-with-remote-leverage` | `case-study/clean-cozy-home` | yoast |
| `/blog/how-much-a-telehealth-virtual-assistant-cost` | `blog/telehealth-virtual-assistant-cost` | yoast |
| `/blog/how-much-does-a-patient-care-coordinator-cost` | `blog/patient-care-coordinator-cost` | yoast |
| `/blog/how-much-does-a-virtual-administrative-assistant-cost` | `blog/administrative-assistant-cost` | yoast |
| `/blog/how-much-does-a-virtual-administrative-assistant-cost-a-guide-for-business-owners` | `blog/how-much-does-a-virtual-administrative-assistant-cost` | yoast |
| `/blog/how-much-does-a-virtual-bookkeeper-cost` | `blog/virtual-bookkeeper-cost` | yoast |
| `/blog/how-much-does-a-virtual-bookkeeper-cost-and-whats-the-real-roi-for-your-business` | `blog/how-much-does-a-virtual-bookkeeper-cost` | yoast |
| `/blog/how-much-does-a-virtual-executive-assistant-cost` | `blog/virtual-executive-assistant-cost` | yoast |
| `/blog/how-much-does-a-virtual-executive-assistant-cost-pricing-breakdown-for-ceos-and-founders` | `blog/how-much-does-a-virtual-executive-assistant-cost` | yoast |
| `/blog/how-much-does-a-virtual-marketing-assistant-cost-pricing-breakdown-for-business-owners` | `blog/how-much-does-a-virtual-marketing-assistant-cost` | yoast |
| `/blog/how-private-practices-can-compete-with-large-healthcare-systems-using-virtual-assistants` | `blog/private-practices-vs-healthcare-systems-virtual-assistants` | yoast |
| `/blog/how-to-delegate-any-task-to-a-remote-assistant-virtual-assistant` | `blog/how-to-delegate-any-task` | yoast |
| `/blog/how-to-hire-an-admin-virtual-assistant-step-by-step-guide-2026` | `blog/how-to-hire-an-admin-virtual-assistant` | yoast |
| `/blog/how-virtual-assistant-scale-startups` | `blog/virtual-assistant-for-startups-how-early-stage-companies-use-vas-to-scale-without-overhead` | yoast |
| `/blog/how-virtual-medical-assistants-improve-patient-scheduling-intake-and-no-show-rates` | `blog/virtual-medical-assistants-scheduling-intake` | yoast |
| `/blog/latin-american-virtual-assistant-cost-20` | `blog/latin-american-virtual-assistant-cost-2026` | yoast |
| `/blog/latin-american-virtual-assistant-cost-2026` | `blog/latin-american-virtual-assistant-cost` | yoast |
| `/blog/legal-virtual-assistant-administrative-burden` | `blog/legal-virtual-assistant-to-reduce-admin-burden` | yoast |
| `/blog/manage-your-virtual-assistant-with-better-systems-communication-and-accountability-to-improve-remote-team-performance` | `blog/how-manage-a-virtual-assistant` | yoast |
| `/blog/mastering-marketing-lead-generation-strategies-and-virtual-assistants-guide` | `blog/marketing-lead-generation-strategies` | yoast |
| `/blog/optimize-social-media-marketing-virtual-assistant` | `blog/optimize-social-media-marketing` | yoast |
| `/blog/outsourcing-vs-in-house-how-business-owners-delegate` | `blog/outsourcing-vs-in-house-how-to-delegate` | yoast |
| `/blog/outsourcing-vs-in-house-how-smart-business-owners-decide-what-to-keep-and-what-to-delegate` | `blog/outsourcing-vs-in-house-how-business-owners-delegate` | yoast |
| `/blog/scale-starups-with-virtual-assistants` | `blog/scale-startups-with-virtual-assistants` | yoast |
| `/blog/top-26-virtual-assistant-companies-ranked-for-2026` | `blog/top-virtual-assistant-companies-ranked-for-2026` | yoast |
| `/blog/top-virtual-assistant-companies-ranked-for-2026` | `blog/top-virtual-assistant-companies-for-2026` | yoast |
| `/blog/transactional-coordinator` | `blog/transactional-coordinator-role-duties-hire` | yoast |
| `/blog/transactional-coordinator-role-duties-hire` | `blog/transaction-coordinator` | yoast |
| `/blog/upwork-vs-dedicated-virtual-assistant-services-which-gets-you-better-results` | `blog/upwork-vs-dedicated-virtual-assistant-services-which-is-better` | yoast |
| `/blog/upwork-vs-dedicated-virtual-assistant-services-which-is-better` | `blog/upwork-vs-virtual-assistant` | yoast |
| `/blog/virtual-assistant-for-startups-how-early-stage-companies-use-vas-to-scale-without-overhead` | `blog/scale-starups-with-virtual-assistants` | yoast |
| `/blog/virtual-assistant-vs-personal-assistant-whats-the-difference-and-which-do-you-need` | `blog/virtual-assistant-vs-personal-assistant` | yoast |
| `/blog/virtual-assistants-for-construction-company-and-home-service-businesseses` | `blog/virtual-assistants-construction-home-service` | yoast |
| `/blog/virtual-medical-assistants-scheduling-intake` | `blog/virtual-medical-assistants-response-times` | yoast |
| `/blog/what-can-a-virtual-assistant-do-for-your-business` | `blog/what-virtual-assistants-do-businesses` | yoast |
| `/blog/what-is-a-virtual-assistant-everything-business-owners-need-to-know` | `blog/what-is-a-virtual-assistant` | yoast |
| `/blog/what-to-look-for-in-a-virtual-assistant` | `blog/how-to-hire-virtual-assistant` | yoast |
| `/blog/what-to-look-for-in-a-virtual-legal-assistant-for-your-practice-checklist-for-hiring-managers` | `blog/virtual-legal-assistant-checklist` | yoast |
| `/blog/what-to-look-for-when-hiring-a-virtual-executive-assistant` | `blog/checklist-for-hiring-a-virtual-executive-assistant` | yoast |
| `/blog/what-to-look-for-when-hiring-a-virtual-executive-assistant-checklist-for-executives` | `blog/what-to-look-for-when-hiring-a-virtual-executive-assistant` | yoast |
| `/blog/what-to-look-for-when-hiring-a-virtual-marketing-assistant-checklist-for-business-owners` | `blog/virtual-marketing-assistant-hiring-checklist` | yoast |
| `/blog/what-virtual-assistants-do-businesses` | `blog/what-virtual-assistants-do-for-businesses` | yoast |
| `/blog/why-us-busineses-hire-from-latin-america` | `blog/why-us-businesses-hire-from-latin-america` | yoast |
| `/blog/why-us-business-owners-hire-latin-american-virtual-assistants` | `blog/why-us-busineses-hire-from-latin-america` | yoast |
| `/boost-your-business-financial-advisor-lead-generation` | `/boost-your-business-financial-advisor-lead-generation-guide` | both |
| `/boost-your-business-with-a-sales-team-for-hire-the-ultimate-growth-solution` | `/boost-your-business-with-a-sales-team-for-hire-the-ultimate-growth-solution-guide` | both |
| `/business-growth-strategies-for-modern-enterprises` | `/business-growth-strategies-for-modern-enterprises-guide` | both |
| `/calendar` | `vacalendar` | yoast |
| `/calendar-2` | `calendar` | yoast |
| `/call-center-services-an-essential-guide-for-businesses` | `/call-center-services-an-essential-guide-for-businesses-guide` | both |
| `/can-i-mention-another-company-in-my-ad` | `/can-i-mention-another-company-in-my-ad-guide` | both |
| `/case-study-bench-accounting` | `case-study/bench-accounting` | yoast |
| `/case-study/case-study-bench-accounting` | `case-study/bench-accounting` | yoast |
| `/case-study/mobile-mixologists` | `/case-study/mobile-mixologist` | both |
| `/choosing-the-best-accounting-software-for-chapter-s-corp` | `/choosing-the-best-accounting-software-for-chapter-s-corp-guide` | both |
| `/client-relationship-partner-building-strong-relationships-for-business-success` | `/client-relationship-partner-building-strong-relationships-for-business-success-guide` | both |
| `/cold-calling-virtual-assistant` | `/cold-calling-virtual-assistant-guide` | both |
| `/comparison-2` | `compare-athena` | yoast |
| `/comprehensive-guide-for-off-shore-cpa-hire` | `/comprehensive-guide-for-off-shore-cpa-hire-guide` | both |
| `/comprehensive-guide-to-hire-a-virtual-assistant-pinterest` | `/comprehensive-guide-to-hire-a-virtual-assistant-pinterest-guide` | both |
| `/comprehensive-guide-to-hire-an-email-management-virtual-assistant` | `/comprehensive-guide-to-hire-an-email-management-virtual-assistant-guide` | both |
| `/connect-learn-and-grow-virtual-assistant-agency-conference` | `/connect-learn-and-grow-virtual-assistant-agency-conference-guide` | both |
| `/content-creator-for-hire-dedicated-content-creator-for-your-business` | `/content-creator-for-hire-dedicated-content-creator-for-your-business-guide` | both |
| `/contractor-lead-generation` | `/contractor-lead-generation-guide` | both |
| `/contractor-management-2` | `live-session-june-26` | yoast |
| `/contractor-of-record-001` | `contractor-of-record` | yoast |
| `/contractor-of-record-c-piash-clone` | `cor` | yoast |
| `/cor-2` | `contractor-payments` | yoast |
| `/cor-legacy` | `cor-b` | yoast |
| `/cor-legacy-branding-home-reference` | `cor-legacy` | yoast |
| `/credit-repair-lead-generation-effective-ways-for-companies-to-generate-leads` | `/credit-repair-lead-generation-effective-ways-for-companies-to-generate-leads-guide` | both |
| `/data-entry-outsourcing-a-deep-dive-into-efficiency-and-cost-effective-solutions` | `/data-entry-outsourcing-a-deep-dive-into-efficiency-and-cost-effective-solutions-guide` | both |
| `/different-ways-to-generate-health-insurance-leads` | `/different-ways-to-generate-health-insurance-leads-guide` | both |
| `/dotloop-vs-docusign-e-signature-solutions-for-the-real-estate-industry` | `/dotloop-vs-docusign-e-signature-solutions-for-the-real-estate-industry-guide` | both |
| `/effective-lead-generation-examples-techniques-and-strategies` | `/effective-lead-generation-examples-techniques-and-strategies-guide` | both |
| `/effective-strategies-for-finding-accounting-leads` | `/effective-strategies-for-finding-accounting-leads-guide` | both |
| `/effective-strategies-for-generating-commercial-insurance-leads` | `/effective-strategies-for-generating-commercial-insurance-leads-guide` | both |
| `/effective-strategies-for-local-lead-generation` | `/effective-strategies-for-local-lead-generation-guide` | both |
| `/effective-strategies-for-plumbing-lead-generation-2` | `/effective-strategies-for-plumbing-lead-generation-guide` | both |
| `/effective-ways-for-auto-insurance-lead-generation` | `/effective-ways-for-auto-insurance-lead-generation-guide` | both |
| `/effective-ways-for-saas-companies-to-generate-leads` | `/effective-ways-for-saas-companies-to-generate-leads-guide` | both |
| `/elementor-31881` | `live-session` | yoast |
| `/employment-advantages-of-hiring-latin-american-remote-workers` | `/employment-advantages-of-hiring-latin-american-remote-workers-guide` | both |
| `/essential-executive-assistant-interview-questions-for-business-owners` | `/essential-executive-assistant-interview-questions-for-business-owners-guide` | both |
| `/essential-tasks-a-real-estate-investor-virtual-assistant-can-handle-from-lead-generation-to-closing` | `/essential-tasks-a-real-estate-investor-virtual-assistant-can-handle-from-lead-generation-to-closing-guide` | both |
| `/exclusive-business-networks-unlocking-hidden-potential` | `/exclusive-business-networks-unlocking-hidden-potential-guide` | both |
| `/expand-google-my-business-reach` | `/expand-google-my-business-reach-guide` | both |
| `/experience-premium-business-travel-services-like-never-before` | `/experience-premium-business-travel-services-like-never-before-guide` | both |
| `/exploring-it-support-services-for-modern-businesses` | `/exploring-it-support-services-for-modern-businesses-guide` | both |
| `/exploring-the-benefits-of-hiring-a-personal-concierge` | `/exploring-the-benefits-of-hiring-a-personal-concierge-guide` | both |
| `/exploring-the-growing-world-of-the-talent-marketplace` | `/exploring-the-growing-world-of-the-talent-marketplace-guide` | both |
| `/fast-real-estate` | `case-study/fast-real-estate` | yoast |
| `/finding-a-virtual-assistant-your-ultimate-guide-to-boost-productivity` | `/finding-a-virtual-assistant-your-ultimate-guide-to-boost-productivity-guide` | both |
| `/finding-kajabi-expert-skills-and-hiring-tips` | `/finding-kajabi-expert-skills-and-hiring-tips-guide` | both |
| `/finding-leads-in-network-marketing-strategies-and-the-role-of-virtual-assistants` | `/finding-leads-in-network-marketing-strategies-and-the-role-of-virtual-assistants-guide` | both |
| `/finding-motivated-seller-leads-tips-and-tricks` | `/finding-motivated-seller-leads-tips-and-tricks-guide` | both |
| `/finding-the-best-place-to-hire-appointment-setters` | `finding-the-best-place-to-hire-appointment-setters-guide` | yoast |
| `/fostering-client-relation-building-meaningful-connections-for-success` | `/fostering-client-relation-building-meaningful-connections-for-success-guide` | both |
| `/freelance-business-assistant-for-small-and-medium-business-your-secret-weapon-for-scaling-up` | `/freelance-business-assistant-for-small-and-medium-business-your-secret-weapon-for-scaling-up-guide` | both |
| `/freelance-salesman-boost-your-business-with-an-independent-expert` | `/freelance-salesman-boost-your-business-with-an-independent-expert-guide` | both |
| `/full-time-vs-part-time-virtual-assistants-which-is-right-for-you` | `/full-time-vs-part-time-virtual-assistants-which-is-right-for-you-guide` | both |
| `/generating-high-quality-business-opportunity-leads` | `/generating-high-quality-business-opportunity-leads-guide` | both |
| `/haus-of-her` | `case-study/haus-of-her` | yoast |
| `/haus-of-her-studios-saved-money-gained-a-highly-qualified-va-with-remote-leverage` | `haus-of-her` | yoast |
| `/hire-a-copywriter-to-boost-your-business-blog` | `hire-a-copywriter-to-boost-your-business-guide` | yoast |
| `/hire-a-freelance-research-assistant-benefits-for-small-businesses` | `/hire-a-freelance-research-assistant-benefits-for-small-businesses-guide` | both |
| `/hire-an-etsy-expert-to-boost-your-online-shop` | `/hire-an-etsy-expert-to-boost-your-online-shop-guide` | both |
| `/hire-offshore-accountant-a-smart-move-for-your-business` | `/hire-offshore-accountant-a-smart-move-for-your-business-guide` | both |
| `/hire-real-estate-virtual-assistants-from-latam-remote-leverage-form-collapsible` | `shopify-amazon-vas` | yoast |
| `/hire-salesman-a-comprehensive-guide-to-enhance-your-sales-strategy` | `/hire-salesman-a-comprehensive-guide-to-enhance-your-sales-strategy-guide` | both |
| `/hire-us-uk` | `hire-us-uk-now` | yoast |
| `/hire-va-4-0-to-start` | `hire-va-6` | yoast |
| `/hire-va-7` | `hire-us-uk` | yoast |
| `/hire-virtual-assistants-fast` | `hire-va-email` | yoast |
| `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b-copy` | `hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b-operators` | yoast |
| `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-d` | `1monthonus-flp` | yoast |
| `/hiring` | `services-hiring` | yoast |
| `/hiring-a-cheap-virtual-assistant-benefits-for-us-small-businesses-from-the-philippines-and-latin-america` | `/hiring-a-cheap-virtual-assistant-benefits-for-us-small-businesses-from-the-philippines-and-latin-america-guide` | both |
| `/hiring-a-va-in-south-america-a-smart-choice` | `/hiring-a-va-in-south-america-a-smart-choice-guide` | both |
| `/hiring-a-virtual-business-assistant-top-benefits-for-small-businesses` | `/hiring-a-virtual-business-assistant-top-benefits-for-small-businesses-guide` | both |
| `/hiring-a-virtual-recruiter-the-future-of-talent-acquisition` | `/hiring-a-virtual-recruiter-the-future-of-talent-acquisition-guide` | both |
| `/homepage-sept-26` | `elementor-50922` | yoast |
| `/homepage-sept-26-new` | `elementor-50922` | yoast |
| `/homepage-sept-26-old-2` | `homepage-sept-26-newer` | yoast |
| `/homepage-va2` | `va-hire` | yoast |
| `/hopepage-sep-26` | `homepage-sep-26` | yoast |
| `/how-cold-email-lead-gen-freelance-services-can-boost-your-sales-pipeline` | `/how-cold-email-lead-gen-freelance-services-can-boost-your-sales-pipeline-guide` | both |
| `/how-do-ad-agencies-find-clients` | `/how-do-ad-agencies-find-clients-guide` | both |
| `/how-do-i-get-leads-for-senior-life-insurance` | `/how-do-i-get-leads-for-senior-life-insurance-guide` | both |
| `/how-email-lead-generation-can-boost-your-business` | `/how-email-lead-generation-can-boost-your-business-guide` | both |
| `/how-long-should-advertisements-run-before-profit` | `/how-long-should-advertisements-run-before-profit-guide` | both |
| `/how-to-advertise-your-business-effectively` | `/how-to-advertise-your-business-effectively-guide` | both |
| `/how-to-analyze-cost-per-hire` | `/how-to-analyze-cost-per-hire-guide` | both |
| `/how-to-attract-talent-with-recruitment-marketing` | `/how-to-attract-talent-with-recruitment-marketing-guide` | both |
| `/how-to-choose-the-best-lead-generation-companies-for-small-businesses` | `/how-to-choose-the-best-lead-generation-companies-for-small-businesses-guide` | both |
| `/how-to-create-a-google-business-profile-as-an-influencer` | `/how-to-create-a-google-business-profile-as-an-influencer-guide` | both |
| `/how-to-find-architecture-clients` | `/how-to-find-architecture-clients-guide` | both |
| `/how-to-find-bookkeeping-clients-effective-strategies-for-growth` | `/how-to-find-bookkeeping-clients-effective-strategies-for-growth-guide` | both |
| `/how-to-find-web-design-clients` | `/how-to-find-web-design-clients-guide` | both |
| `/how-to-generate-leads-for-hvac-services` | `/how-to-generate-leads-for-hvac-services-guide` | both |
| `/how-to-generate-mlm-leads-effectively` | `/how-to-generate-mlm-leads-effectively-guide` | both |
| `/how-to-generate-organic-visits-for-google-business-profile` | `/how-to-generate-organic-visits-for-google-business-profile-guide` | both |
| `/how-to-generate-quality-home-based-business-leads` | `/how-to-generate-quality-home-based-business-leads-guide` | both |
| `/how-to-get-cleaning-leads-for-your-growing-business` | `/how-to-get-cleaning-leads-for-your-growing-business-guide` | both |
| `/how-to-get-construction-leads` | `/how-to-get-construction-leads-guide` | both |
| `/how-to-get-insurance-leads-strategies-for-brokers` | `/how-to-get-insurance-leads-strategies-for-brokers-guide` | both |
| `/how-to-get-leads-as-a-real-estate-agent` | `/how-to-get-leads-as-a-real-estate-agent-guide` | both |
| `/how-to-hire-child-as-a-business-owner` | `/how-to-hire-child-as-a-business-owner-guide` | both |
| `/how-to-hire-employees-in-the-philippines` | `/how-to-hire-employees-in-the-philippines-guide` | both |
| `/how-to-set-up-gbp-for-clients-effectively` | `/how-to-set-up-gbp-for-clients-effectively-guide` | both |
| `/how-to-verify-company-profile` | `/how-to-verify-company-profile-guide` | both |
| `/https-remoteleverage-com-hire-virtual-assistants-fast` | `hire-virtual-assistants-fast` | yoast |
| `/hvac-lead-generation-mastering-the-art-of-generating-leads` | `/hvac-lead-generation-mastering-the-art-of-generating-leads-guide` | both |
| `/inbox-management-professional-strategies-for-productive-work` | `/inbox-management-professional-strategies-for-productive-work-guide` | both |
| `/innovative-ways-to-generate-leads-in-commercial-real-estate` | `/innovative-ways-to-generate-leads-in-commercial-real-estate-guide` | both |
| `/innovative-ways-to-improve-life-insurance-lead-generation` | `/innovative-ways-to-improve-life-insurance-lead-generation-guide` | both |
| `/instagram-reel-creator-having-a-virtual-assistant-dedicated-to-instagram-reel-creation` | `/instagram-reel-creator-having-a-virtual-assistant-dedicated-to-instagram-reel-creation-guide` | both |
| `/insurance-telemarketing-revolutionizing-the-industry` | `/insurance-telemarketing-revolutionizing-the-industry-guide` | both |
| `/job-listing-generator` | `tools` | yoast |
| `/jotform-test` | `/hmchecklists` | both |
| `/keep-track-of-candidates-strategies-for-efficient-recruitment` | `/keep-track-of-candidates-strategies-for-efficient-recruitment-guide` | both |
| `/landing-page-hero-form-a` | `form-lp` | yoast |
| `/landing-page-hero-form-b` | `form-lp2` | yoast |
| `/landingpage-2` | `landingpage` | yoast |
| `/law-firm-virtual-assistant-enhancing-efficiency-and-productivity` | `/law-firm-virtual-assistant-enhancing-efficiency-and-productivity-guide` | both |
| `/lead-generation-for-commercial-cleaning-businesses` | `/lead-generation-for-commercial-cleaning-businesses-guide` | both |
| `/lead-generation-for-it-services` | `/lead-generation-for-it-services-guide` | both |
| `/lead-generation-for-lawyers-boost-your-practice-with-effective-strategies` | `/lead-generation-for-lawyers-boost-your-practice-with-effective-strategies-guide` | both |
| `/lead-generation-virtual-assistants-2` | `lead-generation-assistants` | yoast |
| `/lead-generator-virtual-assistant-the-key-to-scaling-your-business-without-losing-your-mind` | `/lead-generator-virtual-assistant-the-key-to-scaling-your-business-without-losing-your-mind-guide` | both |
| `/legalvirtualassistants` | `home` | yoast |
| `/live-session-june-26` | `live-session-10x-revenue-with-ai` | yoast |
| `/looking-for-activecampaign-alternatives-heres-what-you-need-to-know` | `/looking-for-activecampaign-alternatives-heres-what-you-need-to-know-guide` | both |
| `/making-a-new-hire-feel-part-of-the-team` | `/making-a-new-hire-feel-part-of-the-team-guide` | both |
| `/marketing-assistants-3` | `marketing-assistants` | yoast |
| `/marketing-virtual-assistants` | `marketing-virtual-assistants-2` | yoast |
| `/marketing-virtual-assistants-2` | `marketing-assistants-2` | yoast |
| `/mastering-marketing-lead-generation-strategies-and-virtual-assistants` | `/mastering-marketing-lead-generation-strategies-and-virtual-assistants-guide` | both |
| `/mastering-the-role-of-a-social-media-virtual-assistant` | `/mastering-the-role-of-a-social-media-virtual-assistant-guide` | both |
| `/micro-conversion-ideas-for-lead-generation` | `/micro-conversion-ideas-for-lead-generation-guide` | both |
| `/mortgage-lead-generation-strategies-and-the-role-of-virtual-assistants` | `/mortgage-lead-generation-strategies-and-the-role-of-virtual-assistants-guide` | both |
| `/need-an-assistant-heres-why-it-might-be-time-to-hire-help` | `/need-an-assistant-heres-why-it-might-be-time-to-hire-help-guide` | both |
| `/on-the-outskirt` | `case-study/on-the-outskirt` | yoast |
| `/outbound-telemarketing-services-understanding-the-benefits` | `/outbound-telemarketing-services-understanding-the-benefits-guide` | both |
| `/outsource-b2c-cold-calling-services-a-modern-approach-to-scale-your-business` | `/outsource-b2c-cold-calling-services-a-modern-approach-to-scale-your-business-guide` | both |
| `/outsourced-lead-generation-a-smart-strategy-for-business-growth` | `/outsourced-lead-generation-a-smart-strategy-for-business-growth-guide` | both |
| `/outsourced-lead-generation-boosting-your-sales-pipeline` | `/outsourced-lead-generation-boosting-your-sales-pipeline-guide` | both |
| `/outsourcing-hr-optimizing-your-business-efficiency` | `/outsourcing-hr-optimizing-your-business-efficiency-guide` | both |
| `/outsourcing-video-editing-a-gateway-to-efficiency-and-quality` | `/outsourcing-video-editing-a-gateway-to-efficiency-and-quality-guide` | both |
| `/partnership-inspired-landing-page` | `prtnrshp-insp-lp` | yoast |
| `/performance-payroll-package` | `thankyou-ppp` | yoast |
| `/performance-payroll-package-calendar` | `calendar-ppp` | yoast |
| `/pink-steff` | `va-store-5` | yoast |
| `/powering-business-success-through-a-content-creator-assistant` | `/powering-business-success-through-a-content-creator-assistant-guide` | both |
| `/pricing` | `vapricing` | yoast |
| `/real-estate-leads-pay-at-closing-where-to-find-them` | `/real-estate-leads-pay-at-closing-where-to-find-them-guide` | both |
| `/real-estate-posts/culture-add-vs-culture-fit-what-you-should-really-hire-for` | `real-estate-posts/culture-add-vs-culture-fit-2` | yoast |
| `/recruiter-checklists` | `recruiterchecklists` | yoast |
| `/remote-employee-management-communication-strategies` | `/remote-employee-management-communication-strategies-guide` | both |
| `/remote-employee-tracking-and-data-leak-prevention` | `/remote-employee-tracking-and-data-leak-prevention-guide` | both |
| `/remote-talent-acquisition-simplified-harnessing-the-power-of-a-global-workforce` | `/remote-talent-acquisition-simplified-harnessing-the-power-of-a-global-workforce-guide` | both |
| `/remote-work-tracking-software-the-best-options-for-managing-remote-teams` | `/remote-work-tracking-software-the-best-options-for-managing-remote-teams-guide` | both |
| `/resumes` | `resume-portal` | yoast |
| `/reviews-2` | `reviews` | yoast |
| `/rippling-login-issue-how-to-troubleshoot-and-resolve-common-problems` | `/rippling-login-issue-how-to-troubleshoot-and-resolve-common-problems-guide` | both |
| `/roles/executive-virtual-assistant-2` | `roles/executive-virtual-assistant` | yoast |
| `/roofing-lead-generation-strategies-and-tools-to-boost-your-roofing-business` | `/roofing-lead-generation-strategies-and-tools-to-boost-your-roofing-business-guide` | both |
| `/salary-guide-for-businesses-hiring-virtual-assistants` | `/salary-guide-for-businesses-hiring-virtual-assistants-guide` | both |
| `/sales-virtual-assistants-latam` | `sales-virtual-assistants-2` | yoast |
| `/sample` | `samples` | yoast |
| `/sample-applicant-voice-recordings` | `sample` | yoast |
| `/service-hiri` | `service-hiring` | yoast |
| `/services-hiring` | `service-hiring` | yoast |
| `/small-business-wealth-strategies-mastering-financial-success` | `/small-business-wealth-strategies-mastering-financial-success-guide` | both |
| `/small-company-growth` | `/small-company-growth-guide` | both |
| `/smart-background-checks-for-employers` | `/smart-background-checks-for-employers-guide` | both |
| `/spanish-hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b` | `spanish` | yoast |
| `/startup-bookkeeping-essentials-for-success` | `/startup-bookkeeping-essentials-for-success-guide` | both |
| `/thank-you-2` | `vathankyou` | yoast |
| `/thank-you-cor` | `cor-thank-you` | yoast |
| `/the-acre-hub` | `case-study/the-acre-hub` | yoast |
| `/the-advantages-of-being-a-white-label-hubspot-partner` | `/the-advantages-of-being-a-white-label-hubspot-partner-guide` | both |
| `/the-advantages-of-hiring-a-temp-secretary` | `/the-advantages-of-hiring-a-temp-secretary-guide` | both |
| `/the-advantages-of-hiring-a-virtual-sales-agent` | `/the-advantages-of-hiring-a-virtual-sales-agent-guide` | both |
| `/the-benefits-of-having-a-personal-virtual-assistant` | `/the-benefits-of-having-a-personal-virtual-assistant-guide` | both |
| `/the-benefits-of-having-an-isa-calling-team` | `/the-benefits-of-having-an-isa-calling-team-guide` | both |
| `/the-benefits-of-hiring-a-remote-appointment-setter` | `/the-benefits-of-hiring-a-remote-appointment-setter-guide` | both |
| `/the-benefits-of-hiring-a-remote-receptionist` | `/the-benefits-of-hiring-a-remote-receptionist-guide` | both |
| `/the-benefits-of-hiring-a-temp-admin-assistant` | `/the-benefits-of-hiring-a-temp-admin-assistant-guide` | both |
| `/the-benefits-of-hiring-a-virtual-appointment-setter-for-small-businesses` | `/the-benefits-of-hiring-a-virtual-appointment-setter-for-small-businesses-guide` | both |
| `/the-benefits-of-it-outsourcing-for-modern-businesses` | `/the-benefits-of-it-outsourcing-for-modern-businesses-guide` | both |
| `/the-benefits-of-virtual-customer-care-for-businesses` | `/the-benefits-of-virtual-customer-care-for-businesses-guide` | both |
| `/the-best-place-to-place-ads` | `/the-best-place-to-place-ads-guide` | both |
| `/the-best-virtual-office-options-for-your-business` | `/the-best-virtual-office-options-for-your-business-guide` | both |
| `/the-cost-effective-benefits-of-hiring-a-general-virtual-assistant` | `the-cost-effective-benefits-of-hiring-a-general-virtual-assistant-guide` | yoast |
| `/the-essential-administrative-assistant-skills` | `/the-essential-administrative-assistant-skills-guide` | both |
| `/the-essential-role-of-a-recruitment-assistant` | `/the-essential-role-of-a-recruitment-assistant-guide` | both |
| `/the-future-of-visitor-management-virtual-front-desk` | `/the-future-of-visitor-management-virtual-front-desk-guide` | both |
| `/the-future-of-visitor-management-virtual-front-desks` | `the-future-of-visitor-management-virtual-front-desk` | yoast |
| `/the-most-effective-kpi-for-finance-department` | `/the-most-effective-kpi-for-finance-department-guide` | both |
| `/the-rise-of-customer-service-virtual-assistants-scaling-your-business-with-a-human-touch` | `/the-rise-of-customer-service-virtual-assistants-scaling-your-business-with-a-human-touch-guide` | both |
| `/the-rise-of-virtual-project-management-navigating-modern-workplaces` | `/the-rise-of-virtual-project-management-navigating-modern-workplaces-guide` | both |
| `/the-rise-of-virtual-staffing-a-revolution-in-the-workforce` | `/the-rise-of-virtual-staffing-a-revolution-in-the-workforce-guide` | both |
| `/the-role-of-a-personal-executive-assistant` | `/the-role-of-a-personal-executive-assistant-guide` | both |
| `/the-smarter-way-to-hire-a-virtual-assistant` | `va-lap01` | yoast |
| `/the-ultimate-guide-to-b2b-sales-prospecting-with-virtual-assistants` | `/the-ultimate-guide-to-b2b-sales-prospecting-with-virtual-assistants-guide` | both |
| `/the-ultimate-guide-to-hire-a-monday-com-consultant` | `/the-ultimate-guide-to-hire-a-monday-com-consultant-guide` | both |
| `/the-ultimate-guide-to-hiring-a-remote-hr-assistant` | `/the-ultimate-guide-to-hiring-a-remote-hr-assistant-guide` | both |
| `/the-ultimate-guide-to-hiring-a-virtual-assistant-for-small-business-growth` | `/the-ultimate-guide-to-hiring-a-virtual-assistant-for-small-business-growth-guide` | both |
| `/the-unmatched-benefits-of-a-business-growth-strategist-for-your-company` | `/the-unmatched-benefits-of-a-business-growth-strategist-for-your-company-guide` | both |
| `/tools-2` | `tools` | yoast |
| `/transaction-desk-what-it-is` | `/transaction-desk-what-it-is-guide` | both |
| `/transaction-manager-real-estate-mastering-the-art-of-transaction-coordination` | `/transaction-manager-real-estate-mastering-the-art-of-transaction-coordination-guide` | both |
| `/understanding-hyperlocal-social-media-marketing` | `/understanding-hyperlocal-social-media-marketing-guide` | both |
| `/understanding-remote-work-time-tracking-solutions-and-best-practices` | `/understanding-remote-work-time-tracking-solutions-and-best-practices-guide` | both |
| `/understanding-telemarketing-outsourcing` | `/understanding-telemarketing-outsourcing-guide` | both |
| `/understanding-telesales-outsourcing-to-boost-your-business` | `/understanding-telesales-outsourcing-to-boost-your-business-guide` | both |
| `/understanding-the-benefits-of-a-virtual-assistant-for-cpa` | `/understanding-the-benefits-of-a-virtual-assistant-for-cpa-guide` | both |
| `/understanding-the-essential-benefits-of-a-virtual-reception-service` | `/understanding-the-essential-benefits-of-a-virtual-reception-service-guide` | both |
| `/understanding-the-role-of-a-growth-assistant` | `/understanding-the-role-of-a-growth-assistant-guide` | both |
| `/understanding-the-role-of-a-real-estate-isa-maximizing-your-real-estate-business` | `/understanding-the-role-of-a-real-estate-isa-maximizing-your-real-estate-business-guide` | both |
| `/understanding-the-role-of-a-virtual-real-estate-transaction-coordinator` | `/understanding-the-role-of-a-virtual-real-estate-transaction-coordinator-guide` | both |
| `/understanding-transaction-lifecycle-management-in-real-estate` | `/understanding-transaction-lifecycle-management-in-real-estate-guide` | both |
| `/va-lap01` | `vsl-lp` | yoast |
| `/va-store-5` | `hire-va-5` | yoast |
| `/va-vsl01` | `va-lap01` | yoast |
| `/vahiringonboardingguide2` | `vaonboardingguide2` | yoast |
| `/virtual-administrative-assistant-2` | `/virtual-administrative-assistant-guide` | both |
| `/virtual-assistant-for-acquisitions-of-apartment-complexes` | `/virtual-assistant-for-acquisitions-of-apartment-complexes-guide` | both |
| `/virtual-assistant-for-cleaning-business` | `/virtual-assistant-for-cleaning-business-guide` | both |
| `/virtual-assistant-for-real-estate` | `/virtual-assistant-for-real-estate-guide` | both |
| `/virtual-assistant-posts/best-practices-for-email-management-that-virtual-assistants-bring-to-remote-teams` | `virtual-assistant-posts/email-management-best-practices-virtual-assistants` | yoast |
| `/virtual-assistant-posts/employment-advantages-of-hiring-latin-american-remote-workers-guide` | `/blog/latin-american-virtual-assistant-cost` | 301-plugin |
| `/virtual-assistant-posts/why-hire-customer-service-for-optimal-business-success` | `virtual-assistant-posts/why-hire-customer-service` | yoast |
| `/virtual-assistant-roi-cost-benefit` | `why-us-entrepreneurs-hire-virtual-assistants` | yoast |
| `/virtual-assistant-roi-cost-benefit-2` | `why-us-entrepreneurs-hire-virtual-assistants` | yoast |
| `/virtual-assistant-tools-and-free-resources-for-effective-management` | `/virtual-assistant-tools-and-free-resources-for-effective-management-guide` | both |
| `/virtual-office-assistant-cost-effective-business-solutions` | `/virtual-office-assistant-cost-effective-business-solutions-guide` | both |
| `/virtual-sales-assistant-a-game-changer-for-small-us-businesses` | `/virtual-sales-assistant-a-game-changer-for-small-us-businesses-guide` | both |
| `/virtual-secretary-cost-a-game-changer-for-businesses` | `/virtual-secretary-cost-a-game-changer-for-businesses-guide` | both |
| `/virtual-team-building-exercises-to-boost-engagement-and-morale` | `/virtual-team-building-exercises-to-boost-engagement-and-morale-guide` | both |
| `/ways-for-solar-lead-generation-and-how-a-virtual-assistant-can-help` | `/ways-for-solar-lead-generation-and-how-a-virtual-assistant-can-help-guide` | both |
| `/website-designers-for-small-business` | `/website-designers-for-small-business-guide` | both |
| `/welcome-to-the-team-how-to-welcome-a-new-employee` | `/welcome-to-the-team-how-to-welcome-a-new-employee-guide` | both |
| `/what-is-a-personality-hire` | `/what-is-a-personality-hire-guide` | both |
| `/what-to-automate-from-day-1` | `live-session-what-to-automate-from-day-1` | yoast |
| `/where-can-i-advertise-my-business-for-free` | `/where-can-i-advertise-my-business-for-free-guide` | both |
| `/why-do-companies-choose-to-outsource-work` | `/why-do-companies-choose-to-outsource-work-guide` | both |
| `/why-every-business-needs-a-remote-administrative-assistant` | `/why-every-business-needs-a-remote-administrative-assistant-guide` | both |
| `/why-hire-a-blogger-for-your-small-business` | `/why-hire-a-blogger-for-your-small-business-guide` | both |
| `/why-hire-a-remote-data-entry-clerk-for-your-business` | `/why-hire-a-remote-data-entry-clerk-for-your-business-guide` | both |
| `/why-hire-a-telemarketer-for-your-small-business` | `/why-hire-a-telemarketer-for-your-small-business-guide` | both |
| `/why-hire-seo-expert-philippines-is-a-smart-move-for-your-business` | `/why-hire-seo-expert-philippines-is-a-smart-move-for-your-business-guide` | both |
| `/why-hire-workers-from-colombia` | `/why-hire-workers-from-colombia-guide` | both |
| `/why-hiring-a-marketing-assistant-can-transform-your-business` | `/why-hiring-a-marketing-assistant-can-transform-your-business-guide` | both |
| `/why-hiring-a-payroll-assistant-is-a-smart-move-for-small-business-owners` | `/why-hiring-a-payroll-assistant-is-a-smart-move-for-small-business-owners-guide` | both |
| `/why-hiring-a-virtual-assistant-for-realtors-is-a-game-changer` | `/why-hiring-a-virtual-assistant-for-realtors-is-a-game-changer-guide` | both |
| `/why-hiring-a-virtual-assistant-seo-expert-can-skyrocket-your-business-growth` | `/why-hiring-a-virtual-assistant-seo-expert-can-skyrocket-your-business-growth-guide` | both |
| `/why-outsource-debt-collections-can-enhance-your-business-efficiency` | `/why-outsource-debt-collections-can-enhance-your-business-efficiency-guide` | both |
| `/why-you-should-hire-an-airbnb-virtual-assistant` | `/why-you-should-hire-an-airbnb-virtual-assistant-guide` | both |
| `/why-your-business-needs-a-digital-marketing-virtual-assistant` | `/why-your-business-needs-a-digital-marketing-virtual-assistant-guide` | both |
| `/your-ultimate-guide-to-online-presence-management` | `/your-ultimate-guide-to-online-presence-management-guide` | both |

---

## 4. CONFLICTS — same path, different target (8)

| Hits | Path | Ours | Legacy | Store |
|---:|---|---|---|---|
| 264 | `/how-to-train-your-virtual-assistant` | `/blog` | `/` | 301-plugin |
| 91 | `/how-to-increase-your-virtual-assistants-productivity-with-bonuses` | `/blog` | `/` | 301-plugin |
| 81 | `/sales-landing-page` | `/sales-virtual-assistants` | `/sales-virtual-assistants-2/` | 301-plugin |
| 80 | `/tech-virtual-assistants` | `/hire-va-4` | `/` | 301-plugin |
| 17 | `/vainterview` | `/vacalendar` | `/` | 301-plugin |
| 7 | `/virtual-assistant-hiring-consultation-booked-v2` | `/vathankyou` | `/` | 301-plugin |
| 0 | `/hire-virtual-assistant` | `/hire-va-4` | `hire-virtual-assistants` | yoast |
| 0 | `/home` | `/` | `socialmediavirtualassistants` | yoast |

---

## 5. Only in ours (162)

Built from the production *page* audit, not from the redirect table — retired page slugs that legacy simply 404s or serves. Not a defect; nothing to reconcile.

<details><summary>Show all</summary>

- `/0-to-hire-a-virtual-assistant` → `/`
- `/0tostart` → `/1monthonus`
- `/0tostart-elite-talent` → `/1monthonus`
- `/1monthonus-eu` → `/1monthonus`
- `/accounting-virtual-assistants` → `/bookkeeping-virtual-assistants`
- `/anyshore` → `/https://anyshore.ai/`
- `/appointment-calendar` → `/vacalendar`
- `/appointment-setting-virtual-assistants` → `/lead-generation-virtual-assistants`
- `/b2b-sales-virtual-assistants` → `/sales-virtual-assistants`
- `/book-a-call` → `/book-consultation`
- `/booking-template-test` → `/vacalendar`
- `/bookkeeping-accounting-virtual-assistants` → `/bookkeeping-virtual-assistants`
- `/calendar-ppp` → `/vacalendar`
- `/categorized-testimonials` → `/reviews`
- `/cold-calling-virtual-assistants` → `/sales-virtual-assistants`
- `/cor` → `/contractor-management`
- `/cor-thank-you` → `/vathankyou`
- `/deposit-received` → `/vathankyou`
- `/dontpaytohire` → `/1monthonus`
- `/ecommerce-seo-expert` → `/ecommerce-virtual-assistants`
- `/elementor-50922` → `/hire-va-isolated-form`
- `/executive-assistants-eu-b` → `/executive-virtual-assistants`
- `/freethisweek` → `/1monthonus`
- `/freetrial` → `/1monthonus`
- `/get-sales-virtual-assistants` → `/sales-virtual-assistants`
- `/global-talent` → `/`
- `/guaranteed-fit` → `/`
- `/healthcare-virtual-assistants` → `/medical-virtual-assistants`
- `/high-volume-cold-callers` → `/sales-virtual-assistants`
- `/hire-admin-virtual-assistants` → `/admin-virtual-assistants`
- `/hire-direct` → `/hire-va-4`
- `/hire-direct-latam-eu` → `/hire-va-4`
- `/hire-direct-latam-ph` → `/hire-va-4`
- `/hire-executive-virtual-assistants` → `/executive-virtual-assistants`
- `/hire-marketing-virtual-assistants` → `/marketing-virtual-assistants`
- `/hire-sales-virtual-assistants` → `/sales-virtual-assistants`
- `/hire-today` → `/1monthonus`
- `/hire-us-uk-now` → `/hire-va-4`
- `/hire-va-2` → `/hire-va`
- `/hire-va-3` → `/hire-va`
- `/hire-va-5` → `/hire-va-isolated-form`
- `/hire-va-live-calling-feature` → `/hire-va-isolated-form`
- `/hire-va-new-live-calling-feature` → `/hire-va-6`
- `/hire-va-old` → `/hire-va-4`
- `/hire-va-t` → `/hire-va`
- `/hire-virtual-assistants` → `/hire-va-isolated-form`
- `/hire3forthecostof1` → `/1monthonus`
- `/hireva` → `/hire-va-isolated-form`
- `/home-eu` → `/`
- `/homepage-sept-26-newer` → `/`
- `/homepage-sept-26-old` → `/`
- `/job-description-generator` → `/`
- `/job-description-generator-2` → `/`
- `/job-posting-template-generator` → `/`
- `/join-live-call` → `/live-call/connect`
- `/latin-america-virtual-assistants` → `/hire-va-isolated-form`
- `/lead-generation-assistants` → `/lead-generation-virtual-assistants`
- `/lifecycle-marketing-managers` → `/marketing-virtual-assistants`
- `/live-call-test-remote-leverage-home` → `/`
- `/live-session` → `/`
- `/live-session-10x-revenue-with-ai` → `/`
- `/live-session-build-ai-tools-for-businesses` → `/`
- `/love-it-or-get-a-refund` → `/1monthonus`
- `/marketing-assistants` → `/marketing-virtual-assistants`
- `/marketing-assistants-2` → `/marketing-virtual-assistants`
- `/marketing-assistants-from-latin-america` → `/marketing-virtual-assistants`
- `/marketing-assistants-legacy` → `/marketing-virtual-assistants`
- `/marketing-email-generator` → `/`
- `/medical-assistant` → `/medical-virtual-assistants`
- `/medical-receptionist-virtual-assistants` → `/medical-virtual-assistants`
- `/midway-warning-step-test` → `/`
- `/moneyback` → `/1monthonus`
- `/new-home-26-v2` → `/`
- `/new-home-26-v2b` → `/`
- `/new-homepage-26-4` → `/`
- `/onboardingform` → `/vaonboardingform`
- `/paid-ads-managers` → `/marketing-virtual-assistants`
- `/paralegal` → `/legal-virtual-assistants`
- `/partner-capability-brief` → `/partners`
- `/partner-dashboard` → `/referrer-portal`
- `/personal-virtual-assistants` → `/hire-va-4`
- `/resume` → `/`
- `/reviewsva` → `/reviews`
- `/sales-new-2026` → `/sales-virtual-assistants`
- `/sales-virtual-assistants-2` → `/sales-virtual-assistants`
- `/sales-virtual-assistants-from-latin-america` → `/sales-virtual-assistants`
- `/samples-healthcare-industry` → `/samples`
- `/samples-legacy` → `/samples`
- `/samples-sales` → `/samples`
- `/scale-operators` → `/`
- `/service-cor` → `/book-consultation`
- `/shopify-amazon-vas` → `/ecommerce-virtual-assistants`
- `/simplified` → `/hire-va-isolated-form`
- `/socialmediavirtualassistants` → `/social-media-virtual-assistants`
- `/telemarketers` → `/sales-virtual-assistants`
- `/tellustherole` → `/1monthonus`
- `/test-landing-page-26` → `/`
- `/test-landing-page-26-2` → `/`
- `/test-variant-b` → `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b`
- `/text-optimizer` → `/`
- `/thank-you` → `/vathankyou`
- `/thankyou-ppp` → `/vathankyou`
- `/tools` → `/`
- `/tools/signature-generator` → `/social-media-kit`
- `/va-form` → `/hire-va-isolated-form`
- `/vainterview2` → `/vacalendar`
- `/vaonboardingguide2` → `/vaonboardingguide`
- `/vastore5-b` → `/vastore5`
- `/virtual-admin-assistants` → `/admin-virtual-assistants`
- `/virtual-assistant-consultation-scheduled` → `/vathankyou`
- `/virtual-assistants` → `/hire-va-4`
- `/virtual-assistants-from-latin-america` → `/hire-va-4`
- `/virtual-assistants-latin-america-and-philippines` → `/hire-va-4`
- `/virtual-attorney-assistants` → `/legal-virtual-assistants`
- `/virtual-ecommerce-assistants` → `/ecommerce-virtual-assistants`
- `/virtual-legal-assistants` → `/legal-virtual-assistants`
- `/virtual-legal-assistants-2` → `/legal-virtual-assistants`
- `/virtual-medical-assistants` → `/medical-virtual-assistants`
- `/virtual-telehealth-assistants` → `/medical-virtual-assistants`
- `/vsl-lp` → `/hire-va-isolated-form`
- `/withoutpayingfirst` → `/1monthonus`
- `/workshop` → `/`
- `/wp-content/plugins/rl-social-kit/assets/fb.png` → `/app/themes/remote-leverage/public/images/social-media-kit/brand/fb.png`
- `/wp-content/plugins/rl-social-kit/assets/ig.png` → `/app/themes/remote-leverage/public/images/social-media-kit/brand/ig.png`
- `/wp-content/plugins/rl-social-kit/assets/ln.png` → `/app/themes/remote-leverage/public/images/social-media-kit/brand/ln.png`
- `/wp-content/plugins/rl-social-kit/assets/logo-icon-black.svg` → `/app/themes/remote-leverage/public/images/social-media-kit/brand/logo-icon-black.svg`
- `/wp-content/plugins/rl-social-kit/assets/logo-icon-white.svg` → `/app/themes/remote-leverage/public/images/social-media-kit/brand/logo-icon-white.svg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_01_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_01_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_02_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_02_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_03_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_03_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_04_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_04_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_05_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_05_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_06_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_06_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_07_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_07_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_08_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_08_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_09_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_09_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/facebook/rl_fc_personal_banner_10_850x315.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/facebook/RL_FC_Personal_Banner_10_850x315.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_01_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_01_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_02_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_02_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_03_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_03_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_04_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_04_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_05_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_05_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_06_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_06_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_07_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_07_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/linkedin/rl_lkd_personalbanner_08_4400x1100.jpg` → `/app/themes/remote-leverage/public/images/social-media-kit/linkedin/RL_LKD_PersonalBanner_08_4400x1100.jpg`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/avatar.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/avatar.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/avatar_02-1.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/avatar_02-1.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/avatar_02.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/avatar_02.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-1.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-1.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-2.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-2.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-3.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-3.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-4.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-4.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-5.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-5.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-6.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-6.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-7.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-7.png`
- `/wp-content/plugins/rl-social-kit/assets/resources/other/rl-logo-8.png` → `/app/themes/remote-leverage/public/images/social-media-kit/other/rl-logo-8.png`
- `/wp-content/plugins/rl-social-kit/assets/x.png` → `/app/themes/remote-leverage/public/images/social-media-kit/brand/x.png`
- `/wp-content/plugins/rl-social-kit/assets/yt.png` → `/app/themes/remote-leverage/public/images/social-media-kit/brand/yt.png`
- `/youtube-script-generator` → `/`
- `/zero-to-hire` → `/`
- `/zero-to-hire-latam-eu` → `/`
- `/zero-to-hire-latam-ph` → `/`

</details>

---

## 6. Semantic differences that matter

- **Case.** Every legacy plugin rule is `case_insensitive=enabled`. `LegacyRedirectMiddleware` does an exact `isset($map[$path])`, which is case-**sensitive**. `/Deposit/` and `/APPLY` resolve on legacy and 404 in v2.
- **Query strings.** Legacy plugin rules are `query_parameters=ignore` (incoming query dropped). Ours always preserves and appends it. Harmless for pages, but it changes Stripe/Calendly links.
- **Hit counters are lifetime**, since 2024-10-20 for the plugin rules.
- **No regex rules exist on legacy** (all 328 are `regex=disabled`), so a literal key-for-key port is complete.

---

## Appendix A — complete `301-redirects` plugin table (328 rules)

Ordered by lifetime hits. `✓` = covered by our map.

| Hits | From | To | In v2? |
|---:|---|---|:--:|
| 43636 | `//apply` | https://remoteleveragejobs.com/?ref=https://remoteleverage.com/apply | — |
| 19272 | `//recruitinginterview` | https://calendly.com/pacific-recruiters/job-interview-remote-leverage-team-clone | — |
| 7648 | `//jobinterviewinstructions` | https://vimeo.com/1143608840/e4c8dc87eb?fl=tl&fe=ec | — |
| 5355 | `//recruiterszoom` | https://us02web.zoom.us/j/6153893236 | — |
| 4897 | `/zoom` | https://us02web.zoom.us/j/9794933436?pwd=dENpRHJLL2h2RVlTZjJaODRndVJnQT09 | — |
| 3999 | `//submitvideo` | https://forms.gle/rqHADHQk1M31TjGPA | — |
| 3367 | `//typing` | https://www.livechat.com/typing-speed-test/#/ | — |
| 2104 | `/adminassessment` | https://forms.gle/3oUqzMLizn6bfPAS9 | — |
| 1853 | `//deposit` | https://buy.stripe.com/cNi14nfrY18b7tX6DkfrW0E | ✓ |
| 1412 | `//nyxzoom` | https://us02web.zoom.us/j/9907147461?pwd=hwOW946MrYD3cpZYbIa9Fa6zkfpj1I.1 | — |
| 1235 | `//it` | https://remoteleveragetech.atlassian.net/servicedesk/customer/portal/1/group/1/create/1 | — |
| 1231 | `//zoom2` | https://us02web.zoom.us/j/7172910526?pwd=ryHFp0X4eFvSMerLFsae3LG2RArcno.1 | — |
| 1082 | `//vaguides` | /blog | ✓ |
| 1049 | `//claireinterview` | https://calendly.com/claire-remoteleverage/job-interview-remote-leverage-team-clone | — |
| 957 | `/salesassessment` | https://forms.gle/Ntxhgn9hNfxBKjQN7 | — |
| 943 | `//adminzoom` | https://us02web.zoom.us/j/4055248514?pwd=XwiKkIbXlEoaaWjCP3wOqzratPxmqV.1 | — |
| 813 | `//w9` | https://drive.google.com/file/d/1w1_Pi54QZBe5k2xxtbE3rCvClNaeWX0l/view?usp=sharing | — |
| 699 | `//interviewzoom` | https://us02web.zoom.us/j/4469787983?pwd=iwK8oQenKMxgxGHSeEiteUs1G34KD8.1 | — |
| 604 | `//partnership-program/` | https://remoteleverage.com/referral-program/ | — |
| 525 | `//seifszoom` | https://us02web.zoom.us/j/8438962979?pwd=BtNW4PHSBO1CXfkJ0kF46Kkzxrbonr.1 | — |
| 504 | `//case-studies` | /case-study | — |
| 493 | `//dashboards` | https://sso.online.tableau.com/public/idp/SSO | — |
| 467 | `//virtual-assistant-posts/how-much-does-athena-virtual-assistant-cost-guide/` | https://remoteleverage.com/blog/athena-virtual-assistant/ | — |
| 447 | `//estrecruitinginterview` | https://calendly.com/eastern-recruiters-remoteleverage/job-interview-remote-leverage-team | — |
| 439 | `//virtual-assistant-posts/salary-guide-for-businesses-hiring-virtual-assistants-guide/` | https://anyshore.ai/blog/latin-american-va-salary-guide/ | — |
| 391 | `//guides/` | https://remoteleverage.com/blog/ | ✓ |
| 373 | `//virtual-assistant-posts/virtual-medical-receptionist-revolutionizing-healthcare-support-guide/` | https://remoteleverage.com/blog/5-best-medical-receptionist-services-for-clinics-and-private-practices/ | — |
| 336 | `//googlereview` | https://g.page/r/CTuB-J467qJwEAE/review | — |
| 322 | `//refund` | https://form.jotform.com/252185178652665 | ✓ |
| 317 | `//virtual-assistant-posts/medical-coordinator-key-requirements-duties-responsibilities-and-skills-guide/` | https://remoteleverage.com/blog/patient-care-coordinator-cost/ | — |
| 279 | `//angelicagomez` | https://recruitcrm.io/apply/17811178058780133835zgp | — |
| 278 | `//christinaszoom` | https://us02web.zoom.us/j/4821528296?pwd=aG5Q6HhLv8Bwuf0b2Sr6xtqmDvzMaw.1 | — |
| 276 | `//testimonial` | https://calendly.com/d/cs4r-k2g-d2p/remote-leverage-testimonial-session | — |
| 264 | `//how-to-train-your-virtual-assistant/` | https://remoteleverage.com/ | ✓ |
| 262 | `//partnership-program-next-steps/` | https://remoteleverage.com/ | ✓ |
| 250 | `//landing-page-2/` | https://remoteleverage.com/ | — |
| 225 | `//virtual-assistant-posts/why-hiring-a-marketing-assistant-can-transform-your-business-guide/` | https://remoteleverage.com/blog/marketing-virtual-assistant-vs-marketing-agency/ | — |
| 221 | `//form-lp/` | https://remoteleverage.com/ | ✓ |
| 202 | `//virtual-assistant-posts/understanding-the-role-of-an-executive-administrative-assistant-guide/` | https://remoteleverage.com/blog/executive-assistant-vs-virtual-assistant/ | — |
| 184 | `//angie` | https://recruitcrm.io/apply/17806985112870087768JmC | — |
| 175 | `//vlet-lp/` | https://remoteleverage.com/ | ✓ |
| 174 | `//prtnrshp-insp-lp/` | https://remoteleverage.com/ | ✓ |
| 149 | `//marketing-assistants-b` | /marketing-assistants | — |
| 144 | `//miry` | https://recruitcrm.io/apply/17811401732160133835JtX | — |
| 143 | `//blog/how-ku%ca%bbulei-found-high-level-social-media-marketing-talent-through-remote-leverage/` | https://remoteleverage.com/case-study/hawaiian-philanthropy/ | — |
| 137 | `//marketingmaterials` | https://docs.google.com/document/d/1UUGApiJ0W21RKI3GQ5QHncYJF1Wf1RraPjg1LG4un1Q/edit?usp=sharing | — |
| 136 | `//howitworks/` | https://remoteleverage.com/ | ✓ |
| 135 | `//partnership-program-form/` | https://remoteleverage.com/ | — |
| 128 | `//blog/how-remote-leverage-helped-a-u-s-law-firm-build-a-high-performing-remote-team/` | https://remoteleverage.com/case-study/smiley-injury-law/ | — |
| 126 | `//va-hire/` | https://remoteleverage.com/ | ✓ |
| 99 | `//home-test/` | https://remoteleverage.com/ | ✓ |
| 91 | `//how-to-increase-your-virtual-assistants-productivity-with-bonuses/` | https://remoteleverage.com/ | ✓ |
| 84 | `//referral/` | https://remoteleverage.com/ | ✓ |
| 81 | `//sales-landing-page/` | /sales-virtual-assistants-2/ | ✓ |
| 80 | `//tech-virtual-assistants/` | https://remoteleverage.com/ | ✓ |
| 80 | `//blog/case-study-how-a-texas-auto-shop-doubled-local-hiring-power-with-remote-leverage/` | https://remoteleverage.com/case-study/jeremis-auto-repair/ | — |
| 76 | `//uptime` | https://stats.uptimerobot.com/c7mpGQmbnC | — |
| 76 | `//blog/case-study-how-on-the-outskirt-marketing-hired-their-first-virtual-employee-with-ease/` | https://remoteleverage.com/case-study/on-the-outskirt/ | — |
| 75 | `//form-lp2/` | https://remoteleverage.com/ | ✓ |
| 73 | `//time-activity-monitoring-for-your-virtual-assistant/` | https://remoteleverage.com | ✓ |
| 71 | `//home-tasks-variation/` | https://remoteleverage.com/ | ✓ |
| 71 | `//blog/how-anchorage-care-coordination-gained-reliable-daily-support-with-remote-leverage/` | https://remoteleverage.com/case-study/anchorage-care-coordination/ | — |
| 71 | `//blog/finding-the-perfect-hire-for-a-private-practice-with-remote-leverage/` | https://remoteleverage.com/case-study/watson-psychiatry/ | — |
| 70 | `//blog/goldsoil-realty-investments-hires-a-closer-on-the-spot/` | https://remoteleverage.com/case-study/the-acre-hub/ | — |
| 70 | `//blog/case-study-mobile-mixologists-hires-a-bilingual-va-fast-and-frees-up-the-founder-to-scale/` | https://remoteleverage.com/case-study/mobile-mixologist/ | — |
| 70 | `//blog/how-doran-industries-scaled-event-sales-with-a-virtual-assistant-from-remote-leverage/` | https://remoteleverage.com/case-study/doran-industries/ | — |
| 70 | `//blog/how-haus-of-her-studios-saved-money-gained-a-highly-qualified-va-with-remote-leverage/` | https://remoteleverage.com/case-study/haus-of-her/ | — |
| 69 | `//home-tasks-variation-dark/` | https://remoteleverage.com/ | ✓ |
| 66 | `//blog/how-bench-accounting-scaled-fast-by-hiring-31-virtual-assistants-through-remote-leverage/` | https://remoteleverage.com/case-study/bench-accounting/ | — |
| 62 | `/deel` | https://get.deel.com/eotkl4au2m8w | — |
| 62 | `//blog/how-sales-leader-zack-beck-reclaimed-his-focus-with-a-virtual-assistant/` | https://remoteleverage.com/case-study/conservice/ | — |
| 61 | `//blog/how-fast-real-estate-hired-a-cold-calling-va-and-built-scalable-systems-with-remote-leverage/` | https://remoteleverage.com/case-study/fast-real-estate/ | — |
| 58 | `//interviewvideo` | https://vimeo.com/1124026646/649fff7922?share=copy | — |
| 55 | `//abbascalendar` | https://calendly.com/abbas-remoteleverage/30min | — |
| 46 | `/lead` | https://forms.gle/8ZMPNRKwBveBa67M7 | — |
| 45 | `//footer-fix-test/` | https://remoteleverage.com/ | — |
| 36 | `//ideas` | https://form.jotform.com/260564755295164 | — |
| 35 | `/natasha` | https://us02web.zoom.us/j/7543604107?pwd=cm1BQzRlOWNocklwOHNMTDBKODFRQT09 | — |
| 35 | `//fire` | https://form.jotform.com/252558126272155 | — |
| 34 | `/vaonboarding` | https://calendly.com/remoteleverage/client-va-onboarding-call | — |
| 34 | `//the-secret-to-scaling-your-ebay-store-hire-an-ebay-virtual-assistant` | /the-secret-to-scaling-your-ebay-store-hire-an-ebay-virtual-assistant-guide | — |
| 33 | `//partnership-program-thank-you/` | https://remoteleverage.com/ | — |
| 27 | `//introcall` | https://calendly.com/d/cqnx-7z2-2rq/remote-leverage-onboarding-applicant-criteria | — |
| 24 | `/coldcallscript` | download/2637/?tmstv=1697591198 | — |
| 24 | `/followupscript` | download/2640/?tmstv=1710178256 | — |
| 24 | `//jobinvitation` | https://form.jotform.com/243466875000052 | — |
| 19 | `/join` | https://buy.stripe.com/6oEeWM0EUegh1qgdQW | — |
| 18 | `/princessinterview` | https://calendly.com/remoteleverage/job-interview-test | — |
| 18 | `//cordeposit` | https://buy.stripe.com/3cIfZh1B8cQT29D0eWfrW0F | — |
| 17 | `//vainterview/` | https://remoteleverage.com/ | ✓ |
| 15 | `/interview` | https://calendly.com/remoteleverage/job-interview-test | — |
| 11 | `//real-estate-cold-calling-virtual-assistants-the-secret-to-real-estate-success` | /real-estate-cold-calling-virtual-assistants-the-secret-to-real-estate-success-guide | — |
| 8 | `//virtual-administrative-assistant` | /virtual-administrative-assistant-guide-2 | — |
| 7 | `//hire-social-media-content-creator-everything-you-need-to-know` | /hire-social-media-content-creator-everything-you-need-to-know-guide | — |
| 7 | `//replit` | https://recruiting-helper.replit.app/ | — |
| 7 | `//virtual-assistant-hiring-consultation-booked-v2/` | https://remoteleverage.com/ | ✓ |
| 6 | `//splitpayment` | https://form.jotform.com/243395973807471 | — |
| 6 | `//laura` | https://recruitcrm.io/apply/17806984609920087768oJQ | — |
| 6 | `//lina` | https://recruitcrm.io/apply/17811180148840133835icy | — |
| 5 | `//what-is-an-example-kpi-for-administrative-assistant` | /what-is-an-example-kpi-for-administrative-assistant-guide | — |
| 5 | `//virtual-medical-administrative-assistant` | /virtual-medical-administrative-assistant-guide | — |
| 5 | `//virtual-assistants-for-different-industries-tailoring-services-to-your-needs` | /virtual-assistants-for-different-industries-tailoring-services-to-your-needs-guide | — |
| 5 | `//virtual-assistants-and-time-management-how-to-delegate-effectively` | /virtual-assistants-and-time-management-how-to-delegate-effectively-guide | — |
| 5 | `//firefighting` | https://form.jotform.com/252558126272155 | — |
| 4 | `//live-session-what-to-automate-from-day-1/` | https://remoteleverage.com/live-session-build-ai-tools-for-businesses/ | — |
| 3 | `/onboardingmeeting` | https://calendly.com/remoteleverage/onboarding | — |
| 3 | `//cal/` | https://remoteleverage.com/ | — |
| 3 | `//tasks-landing-page/` | https://remoteleverage.com/ | — |
| 3 | `//b/` | https://remoteleverage.com/ | ✓ |
| 2 | `/training` | https://calendly.com/remoteleverage/coldcallingtraining | — |
| 2 | `/vatraining` | https://docs.google.com/document/d/1GoY3pWKRwPVmH7fyCWbNL-5PCeNDcxkX-eNp2mn91TA/edit?usp=sharing | — |
| 2 | `//it2` | https://remoteleveragetech.atlassian.net/servicedesk/customer/portal/1/group/1/create/1 | — |
| 1 | `//understanding-white-label-virtual-assistant-services` | /understanding-white-label-virtual-assistant-services-guide | — |
| 1 | `//why-hiring-an-admin-assistant-working-from-home-is-a-game-changer-for-busy-business-owners` | /why-hiring-an-admin-assistant-working-from-home-is-a-game-changer-for-busy-business-owners-guide | — |
| 1 | `//essential-guide-to-a-virtual-assistant-contract-template` | /essential-guide-to-a-virtual-assistant-contract-template-guide | — |
| 1 | `//how-much-does-athena-virtual-assistant-cost` | /how-much-does-athena-virtual-assistant-cost-guide | — |
| 1 | `//the-virtual-financial-planning-assistant-your-secret-weapon-to-scaling-your-business` | /the-virtual-financial-planning-assistant-your-secret-weapon-to-scaling-your-business-guide | — |
| 1 | `//virtual-assistant-vs-in-house-employee-pros-and-cons` | /virtual-assistant-vs-in-house-employee-pros-and-cons-guide | — |
| 0 | `/monthlyfee` | https://buy.stripe.com/eVacOEafu5JL1qg28g | — |
| 0 | `/vaexam` | https://forms.gle/jGL2PVu11C9189WN6 | — |
| 0 | `/15` | https://calendly.com/remoteleverage/15-minute-meeting | — |
| 0 | `/6monthlyfee` | https://buy.stripe.com/5kAbKAfzOdcdfh6aEU | — |
| 0 | `/12monthlyfee` | https://buy.stripe.com/7sIg0QcnCa01d8Y5kB | — |
| 0 | `/followupmonthlyfee` | https://buy.stripe.com/14kaGwcnC7RT4Cs8wL | — |
| 0 | `/2000` | https://buy.stripe.com/7sIdSI3R6fklfh6cNd | — |
| 0 | `/1500` | https://buy.stripe.com/fZe4i81IY6NP2uk00o | — |
| 0 | `/extendedguarantee` | https://buy.stripe.com/6oE2a01IY2xz7OE6oT | — |
| 0 | `/natashacalendar` | https://calendly.com/remoteleveragesales/natasha-1-on-1-meeting | — |
| 0 | `//cruzcalendar` | https://calendly.com/cruzremoteleverage/virtual-assistant-hiring-consultation-clone | — |
| 0 | `//hiring-a-virtual-recruiter-the-future-of-talent-acquisition` | /hiring-a-virtual-recruiter-the-future-of-talent-acquisition-guide | — |
| 0 | `//the-essential-role-of-a-recruitment-assistant` | /the-essential-role-of-a-recruitment-assistant-guide | — |
| 0 | `//finding-a-virtual-assistant-your-ultimate-guide-to-boost-productivity` | /finding-a-virtual-assistant-your-ultimate-guide-to-boost-productivity-guide | — |
| 0 | `//understanding-the-role-of-a-virtual-real-estate-transaction-coordinator` | /understanding-the-role-of-a-virtual-real-estate-transaction-coordinator-guide | — |
| 0 | `//the-advantages-of-hiring-a-virtual-sales-agent` | /the-advantages-of-hiring-a-virtual-sales-agent-guide | — |
| 0 | `//the-benefits-of-having-an-isa-calling-team` | /the-benefits-of-having-an-isa-calling-team-guide | — |
| 0 | `//the-advantages-of-hiring-a-temp-secretary` | /the-advantages-of-hiring-a-temp-secretary-guide | — |
| 0 | `//the-benefits-of-hiring-a-temp-admin-assistant` | /the-benefits-of-hiring-a-temp-admin-assistant-guide | — |
| 0 | `//finding-motivated-seller-leads-tips-and-tricks` | /finding-motivated-seller-leads-tips-and-tricks-guide | — |
| 0 | `//how-to-set-up-gbp-for-clients-effectively` | /how-to-set-up-gbp-for-clients-effectively-guide | — |
| 0 | `//essential-tasks-a-real-estate-investor-virtual-assistant-can-handle-from-lead-generation-to-closing` | /essential-tasks-a-real-estate-investor-virtual-assistant-can-handle-from-lead-generation-to-closing-guide | — |
| 0 | `//how-to-generate-mlm-leads-effectively` | /how-to-generate-mlm-leads-effectively-guide | — |
| 0 | `//generating-high-quality-business-opportunity-leads` | /generating-high-quality-business-opportunity-leads-guide | — |
| 0 | `//finding-leads-in-network-marketing-strategies-and-the-role-of-virtual-assistants` | /finding-leads-in-network-marketing-strategies-and-the-role-of-virtual-assistants-guide | — |
| 0 | `//law-firm-virtual-assistant-enhancing-efficiency-and-productivity` | /law-firm-virtual-assistant-enhancing-efficiency-and-productivity-guide | — |
| 0 | `//effective-strategies-for-finding-accounting-leads` | /effective-strategies-for-finding-accounting-leads-guide | — |
| 0 | `//benefits-of-using-an-applicant-tracking-system-for-small-companies` | /benefits-of-using-an-applicant-tracking-system-for-small-companies-guide | — |
| 0 | `//welcome-to-the-team-how-to-welcome-a-new-employee` | /welcome-to-the-team-how-to-welcome-a-new-employee-guide | — |
| 0 | `//how-do-ad-agencies-find-clients` | /how-do-ad-agencies-find-clients-guide | — |
| 0 | `//how-to-analyze-cost-per-hire` | /how-to-analyze-cost-per-hire-guide | — |
| 0 | `//keep-track-of-candidates-strategies-for-efficient-recruitment` | /keep-track-of-candidates-strategies-for-efficient-recruitment-guide | — |
| 0 | `//how-to-find-architecture-clients` | /how-to-find-architecture-clients-guide | — |
| 0 | `//how-to-find-web-design-clients` | /how-to-find-web-design-clients-guide | — |
| 0 | `//how-to-find-bookkeeping-clients-effective-strategies-for-growth` | /how-to-find-bookkeeping-clients-effective-strategies-for-growth-guide | — |
| 0 | `//how-to-get-construction-leads` | /how-to-get-construction-leads-guide | — |
| 0 | `//how-to-get-insurance-leads-strategies-for-brokers` | /how-to-get-insurance-leads-strategies-for-brokers-guide | — |
| 0 | `//how-to-get-cleaning-leads-for-your-growing-business` | /how-to-get-cleaning-leads-for-your-growing-business-guide | — |
| 0 | `//how-to-get-leads-as-a-real-estate-agent` | /how-to-get-leads-as-a-real-estate-agent-guide | — |
| 0 | `//how-do-i-get-leads-for-senior-life-insurance` | /how-do-i-get-leads-for-senior-life-insurance-guide | — |
| 0 | `//powering-business-success-through-a-content-creator-assistant` | /powering-business-success-through-a-content-creator-assistant-guide | — |
| 0 | `//instagram-reel-creator-having-a-virtual-assistant-dedicated-to-instagram-reel-creation` | /instagram-reel-creator-having-a-virtual-assistant-dedicated-to-instagram-reel-creation-guide | — |
| 0 | `//why-your-business-needs-a-digital-marketing-virtual-assistant` | /why-your-business-needs-a-digital-marketing-virtual-assistant-guide | — |
| 0 | `//virtual-secretary-cost-a-game-changer-for-businesses` | /virtual-secretary-cost-a-game-changer-for-businesses-guide | — |
| 0 | `//the-benefits-of-virtual-customer-care-for-businesses` | /the-benefits-of-virtual-customer-care-for-businesses-guide | — |
| 0 | `//how-email-lead-generation-can-boost-your-business` | /how-email-lead-generation-can-boost-your-business-guide | — |
| 0 | `//how-to-generate-quality-home-based-business-leads` | /how-to-generate-quality-home-based-business-leads-guide | — |
| 0 | `//credit-repair-lead-generation-effective-ways-for-companies-to-generate-leads` | /credit-repair-lead-generation-effective-ways-for-companies-to-generate-leads-guide | — |
| 0 | `//effective-strategies-for-plumbing-lead-generation-2` | /effective-strategies-for-plumbing-lead-generation-guide | — |
| 0 | `//lead-generation-for-lawyers-boost-your-practice-with-effective-strategies` | /lead-generation-for-lawyers-boost-your-practice-with-effective-strategies-guide | — |
| 0 | `//contractor-lead-generation` | /contractor-lead-generation-guide | — |
| 0 | `//innovative-ways-to-improve-life-insurance-lead-generation` | /innovative-ways-to-improve-life-insurance-lead-generation-guide | — |
| 0 | `//boost-your-business-financial-advisor-lead-generation` | /boost-your-business-financial-advisor-lead-generation-guide | — |
| 0 | `//attorney-lead-generation-strategies-and-virtual-assistant-support` | /attorney-lead-generation-strategies-and-virtual-assistant-support-guide | — |
| 0 | `//effective-ways-for-saas-companies-to-generate-leads` | /effective-ways-for-saas-companies-to-generate-leads-guide | — |
| 0 | `//lead-generation-for-it-services` | /lead-generation-for-it-services-guide | — |
| 0 | `//effective-strategies-for-local-lead-generation` | /effective-strategies-for-local-lead-generation-guide | — |
| 0 | `//roofing-lead-generation-strategies-and-tools-to-boost-your-roofing-business` | /roofing-lead-generation-strategies-and-tools-to-boost-your-roofing-business-guide | — |
| 0 | `//hvac-lead-generation-mastering-the-art-of-generating-leads` | /hvac-lead-generation-mastering-the-art-of-generating-leads-guide | — |
| 0 | `//ways-for-solar-lead-generation-and-how-a-virtual-assistant-can-help` | /ways-for-solar-lead-generation-and-how-a-virtual-assistant-can-help-guide | — |
| 0 | `//innovative-ways-to-generate-leads-in-commercial-real-estate` | /innovative-ways-to-generate-leads-in-commercial-real-estate-guide | — |
| 0 | `//lead-generation-for-commercial-cleaning-businesses` | /lead-generation-for-commercial-cleaning-businesses-guide | — |
| 0 | `//effective-strategies-for-generating-commercial-insurance-leads` | /effective-strategies-for-generating-commercial-insurance-leads-guide | — |
| 0 | `//b2b-lead-generation-ultimate-guide-to-boost-your-sales` | /b2b-lead-generation-ultimate-guide-to-boost-your-sales-guide | — |
| 0 | `//mortgage-lead-generation-strategies-and-the-role-of-virtual-assistants` | /mortgage-lead-generation-strategies-and-the-role-of-virtual-assistants-guide | — |
| 0 | `//mastering-marketing-lead-generation-strategies-and-virtual-assistants` | /mastering-marketing-lead-generation-strategies-and-virtual-assistants-guide | — |
| 0 | `//the-unmatched-benefits-of-a-business-growth-strategist-for-your-company` | /the-unmatched-benefits-of-a-business-growth-strategist-for-your-company-guide | — |
| 0 | `//small-company-growth` | /small-company-growth-guide | — |
| 0 | `//different-ways-to-generate-health-insurance-leads` | /different-ways-to-generate-health-insurance-leads-guide | — |
| 0 | `//effective-ways-for-auto-insurance-lead-generation` | /effective-ways-for-auto-insurance-lead-generation-guide | — |
| 0 | `//virtual-office-assistant-cost-effective-business-solutions` | /virtual-office-assistant-cost-effective-business-solutions-guide | — |
| 0 | `//hiring-a-virtual-business-assistant-top-benefits-for-small-businesses` | /hiring-a-virtual-business-assistant-top-benefits-for-small-businesses-guide | — |
| 0 | `//expand-google-my-business-reach` | /expand-google-my-business-reach-guide | — |
| 0 | `//how-to-verify-company-profile` | /how-to-verify-company-profile-guide | — |
| 0 | `//the-advantages-of-being-a-white-label-hubspot-partner` | /the-advantages-of-being-a-white-label-hubspot-partner-guide | — |
| 0 | `//choosing-the-best-accounting-software-for-chapter-s-corp` | /choosing-the-best-accounting-software-for-chapter-s-corp-guide | — |
| 0 | `//how-to-hire-child-as-a-business-owner` | /how-to-hire-child-as-a-business-owner-guide | — |
| 0 | `//exclusive-business-networks-unlocking-hidden-potential` | /exclusive-business-networks-unlocking-hidden-potential-guide | — |
| 0 | `//micro-conversion-ideas-for-lead-generation` | /micro-conversion-ideas-for-lead-generation-guide | — |
| 0 | `//how-to-generate-organic-visits-for-google-business-profile` | /how-to-generate-organic-visits-for-google-business-profile-guide | — |
| 0 | `//are-remote-workers-working-all-day` | /are-remote-workers-working-all-day-guide | — |
| 0 | `//how-to-advertise-your-business-effectively` | /how-to-advertise-your-business-effectively-guide | — |
| 0 | `//can-i-mention-another-company-in-my-ad` | /can-i-mention-another-company-in-my-ad-guide | — |
| 0 | `//how-long-should-advertisements-run-before-profit` | /how-long-should-advertisements-run-before-profit-guide | — |
| 0 | `//where-can-i-advertise-my-business-for-free` | /where-can-i-advertise-my-business-for-free-guide | — |
| 0 | `//the-best-place-to-place-ads` | /the-best-place-to-place-ads-guide | — |
| 0 | `//the-best-virtual-office-options-for-your-business` | /the-best-virtual-office-options-for-your-business-guide | — |
| 0 | `//remote-employee-management-communication-strategies` | /remote-employee-management-communication-strategies-guide | — |
| 0 | `//remote-employee-tracking-and-data-leak-prevention` | /remote-employee-tracking-and-data-leak-prevention-guide | — |
| 0 | `//the-benefits-of-it-outsourcing-for-modern-businesses` | /the-benefits-of-it-outsourcing-for-modern-businesses-guide | — |
| 0 | `//exploring-it-support-services-for-modern-businesses` | /exploring-it-support-services-for-modern-businesses-guide | — |
| 0 | `//a-guide-to-help-desk-outsourcing-for-modern-businesses` | /a-guide-to-help-desk-outsourcing-for-modern-businesses-guide | — |
| 0 | `//call-center-services-an-essential-guide-for-businesses` | /call-center-services-an-essential-guide-for-businesses-guide | — |
| 0 | `//the-future-of-visitor-management-virtual-front-desk` | /the-future-of-visitor-management-virtual-front-desk-guide | — |
| 0 | `//understanding-the-essential-benefits-of-a-virtual-reception-service` | /understanding-the-essential-benefits-of-a-virtual-reception-service-guide | — |
| 0 | `//the-benefits-of-hiring-a-remote-receptionist` | /the-benefits-of-hiring-a-remote-receptionist-guide | — |
| 0 | `//why-outsource-debt-collections-can-enhance-your-business-efficiency` | /why-outsource-debt-collections-can-enhance-your-business-efficiency-guide | — |
| 0 | `//understanding-remote-work-time-tracking-solutions-and-best-practices` | /understanding-remote-work-time-tracking-solutions-and-best-practices-guide | — |
| 0 | `//outsourcing-hr-optimizing-your-business-efficiency` | /outsourcing-hr-optimizing-your-business-efficiency-guide | — |
| 0 | `//b2c-telemarketing-how-to-do-it-well` | /b2c-telemarketing-how-to-do-it-well-guide | — |
| 0 | `//outbound-telemarketing-services-understanding-the-benefits` | /outbound-telemarketing-services-understanding-the-benefits-guide | — |
| 0 | `//insurance-telemarketing-revolutionizing-the-industry` | /insurance-telemarketing-revolutionizing-the-industry-guide | — |
| 0 | `//why-hire-a-remote-data-entry-clerk-for-your-business` | /why-hire-a-remote-data-entry-clerk-for-your-business-guide | — |
| 0 | `//why-hiring-a-payroll-assistant-is-a-smart-move-for-small-business-owners` | /why-hiring-a-payroll-assistant-is-a-smart-move-for-small-business-owners-guide | — |
| 0 | `//remote-work-tracking-software-the-best-options-for-managing-remote-teams` | /remote-work-tracking-software-the-best-options-for-managing-remote-teams-guide | — |
| 0 | `//exploring-the-benefits-of-hiring-a-personal-concierge` | /exploring-the-benefits-of-hiring-a-personal-concierge-guide | — |
| 0 | `//rippling-login-issue-how-to-troubleshoot-and-resolve-common-problems` | /rippling-login-issue-how-to-troubleshoot-and-resolve-common-problems-guide | — |
| 0 | `//dotloop-vs-docusign-e-signature-solutions-for-the-real-estate-industry` | /dotloop-vs-docusign-e-signature-solutions-for-the-real-estate-industry-guide | — |
| 0 | `//transaction-desk-what-it-is` | /transaction-desk-what-it-is-guide | — |
| 0 | `//understanding-transaction-lifecycle-management-in-real-estate` | /understanding-transaction-lifecycle-management-in-real-estate-guide | — |
| 0 | `//transaction-manager-real-estate-mastering-the-art-of-transaction-coordination` | /transaction-manager-real-estate-mastering-the-art-of-transaction-coordination-guide | — |
| 0 | `//looking-for-activecampaign-alternatives-heres-what-you-need-to-know` | /looking-for-activecampaign-alternatives-heres-what-you-need-to-know-guide | — |
| 0 | `//what-is-a-personality-hire` | /what-is-a-personality-hire-guide | — |
| 0 | `//smart-background-checks-for-employers` | /smart-background-checks-for-employers-guide | — |
| 0 | `//why-hiring-a-marketing-assistant-can-transform-your-business` | /why-hiring-a-marketing-assistant-can-transform-your-business-guide | — |
| 0 | `//how-to-attract-talent-with-recruitment-marketing` | /how-to-attract-talent-with-recruitment-marketing-guide | — |
| 0 | `//exploring-the-growing-world-of-the-talent-marketplace` | /exploring-the-growing-world-of-the-talent-marketplace-guide | — |
| 0 | `//remote-talent-acquisition-simplified-harnessing-the-power-of-a-global-workforce` | /remote-talent-acquisition-simplified-harnessing-the-power-of-a-global-workforce-guide | — |
| 0 | `//the-ultimate-guide-to-b2b-sales-prospecting-with-virtual-assistants` | /the-ultimate-guide-to-b2b-sales-prospecting-with-virtual-assistants-guide | — |
| 0 | `//business-growth-strategies-for-modern-enterprises` | /business-growth-strategies-for-modern-enterprises-guide | — |
| 0 | `//real-estate-leads-pay-at-closing-where-to-find-them` | /real-estate-leads-pay-at-closing-where-to-find-them-guide | — |
| 0 | `//the-rise-of-virtual-staffing-a-revolution-in-the-workforce` | /the-rise-of-virtual-staffing-a-revolution-in-the-workforce-guide | — |
| 0 | `//how-to-choose-the-best-lead-generation-companies-for-small-businesses` | /how-to-choose-the-best-lead-generation-companies-for-small-businesses-guide | — |
| 0 | `//effective-lead-generation-examples-techniques-and-strategies` | /effective-lead-generation-examples-techniques-and-strategies-guide | — |
| 0 | `//outsourced-lead-generation-boosting-your-sales-pipeline` | /outsourced-lead-generation-boosting-your-sales-pipeline-guide | — |
| 0 | `//hiring-a-va-in-south-america-a-smart-choice` | /hiring-a-va-in-south-america-a-smart-choice-guide | — |
| 0 | `//connect-learn-and-grow-virtual-assistant-agency-conference` | /connect-learn-and-grow-virtual-assistant-agency-conference-guide | — |
| 0 | `//the-benefits-of-hiring-a-remote-appointment-setter` | /the-benefits-of-hiring-a-remote-appointment-setter-guide | — |
| 0 | `//employment-advantages-of-hiring-latin-american-remote-workers` | /employment-advantages-of-hiring-latin-american-remote-workers-guide | — |
| 0 | `//understanding-the-role-of-a-real-estate-isa-maximizing-your-real-estate-business` | /understanding-the-role-of-a-real-estate-isa-maximizing-your-real-estate-business-guide | — |
| 0 | `//experience-premium-business-travel-services-like-never-before` | /experience-premium-business-travel-services-like-never-before-guide | — |
| 0 | `//making-a-new-hire-feel-part-of-the-team` | /making-a-new-hire-feel-part-of-the-team-guide | — |
| 0 | `//virtual-team-building-exercises-to-boost-engagement-and-morale` | /virtual-team-building-exercises-to-boost-engagement-and-morale-guide | — |
| 0 | `//small-business-wealth-strategies-mastering-financial-success` | /small-business-wealth-strategies-mastering-financial-success-guide | — |
| 0 | `//need-an-assistant-heres-why-it-might-be-time-to-hire-help` | /need-an-assistant-heres-why-it-might-be-time-to-hire-help-guide | — |
| 0 | `//the-role-of-a-personal-executive-assistant` | /the-role-of-a-personal-executive-assistant-guide | — |
| 0 | `//understanding-telemarketing-outsourcing` | /understanding-telemarketing-outsourcing-guide | — |
| 0 | `//outsourced-lead-generation-a-smart-strategy-for-business-growth` | /outsourced-lead-generation-a-smart-strategy-for-business-growth-guide | — |
| 0 | `//the-rise-of-virtual-project-management-navigating-modern-workplaces` | /the-rise-of-virtual-project-management-navigating-modern-workplaces-guide | — |
| 0 | `//data-entry-outsourcing-a-deep-dive-into-efficiency-and-cost-effective-solutions` | /data-entry-outsourcing-a-deep-dive-into-efficiency-and-cost-effective-solutions-guide | — |
| 0 | `//understanding-the-role-of-a-growth-assistant` | /understanding-the-role-of-a-growth-assistant-guide | — |
| 0 | `//why-do-companies-choose-to-outsource-work` | /why-do-companies-choose-to-outsource-work-guide | — |
| 0 | `//inbox-management-professional-strategies-for-productive-work` | /inbox-management-professional-strategies-for-productive-work-guide | — |
| 0 | `//fostering-client-relation-building-meaningful-connections-for-success` | /fostering-client-relation-building-meaningful-connections-for-success-guide | — |
| 0 | `//essential-executive-assistant-interview-questions-for-business-owners` | /essential-executive-assistant-interview-questions-for-business-owners-guide | — |
| 0 | `//startup-bookkeeping-essentials-for-success` | /startup-bookkeeping-essentials-for-success-guide | — |
| 0 | `//client-relationship-partner-building-strong-relationships-for-business-success` | /client-relationship-partner-building-strong-relationships-for-business-success-guide | — |
| 0 | `//why-hiring-a-virtual-assistant-seo-expert-can-skyrocket-your-business-growth` | /why-hiring-a-virtual-assistant-seo-expert-can-skyrocket-your-business-growth-guide | — |
| 0 | `//the-benefits-of-hiring-a-virtual-appointment-setter-for-small-businesses` | /the-benefits-of-hiring-a-virtual-appointment-setter-for-small-businesses-guide | — |
| 0 | `//why-you-should-hire-an-airbnb-virtual-assistant` | /why-you-should-hire-an-airbnb-virtual-assistant-guide | — |
| 0 | `//virtual-sales-assistant-a-game-changer-for-small-us-businesses` | /virtual-sales-assistant-a-game-changer-for-small-us-businesses-guide | — |
| 0 | `//why-hire-a-telemarketer-for-your-small-business` | /why-hire-a-telemarketer-for-your-small-business-guide | — |
| 0 | `//why-hire-a-blogger-for-your-small-business` | /why-hire-a-blogger-for-your-small-business-guide | — |
| 0 | `//hire-a-freelance-research-assistant-benefits-for-small-businesses` | /hire-a-freelance-research-assistant-benefits-for-small-businesses-guide | — |
| 0 | `//hire-offshore-accountant-a-smart-move-for-your-business` | /hire-offshore-accountant-a-smart-move-for-your-business-guide | — |
| 0 | `//mastering-the-role-of-a-social-media-virtual-assistant` | /mastering-the-role-of-a-social-media-virtual-assistant-guide | — |
| 0 | `//hiring-a-cheap-virtual-assistant-benefits-for-us-small-businesses-from-the-philippines-and-latin-america` | /hiring-a-cheap-virtual-assistant-benefits-for-us-small-businesses-from-the-philippines-and-latin-america-guide | — |
| 0 | `//outsourcing-video-editing-a-gateway-to-efficiency-and-quality` | /outsourcing-video-editing-a-gateway-to-efficiency-and-quality-guide | — |
| 0 | `//website-designers-for-small-business` | /website-designers-for-small-business-guide | — |
| 0 | `//best-local-advertising-for-small-business` | /best-local-advertising-for-small-business-guide | — |
| 0 | `//the-ultimate-guide-to-hiring-a-remote-hr-assistant` | /the-ultimate-guide-to-hiring-a-remote-hr-assistant-guide | — |
| 0 | `//why-hiring-a-virtual-assistant-for-realtors-is-a-game-changer` | /why-hiring-a-virtual-assistant-for-realtors-is-a-game-changer-guide | — |
| 0 | `//why-hire-seo-expert-philippines-is-a-smart-move-for-your-business` | /why-hire-seo-expert-philippines-is-a-smart-move-for-your-business-guide | — |
| 0 | `//finding-kajabi-expert-skills-and-hiring-tips` | /finding-kajabi-expert-skills-and-hiring-tips-guide | — |
| 0 | `//the-ultimate-guide-to-hire-a-monday-com-consultant` | /the-ultimate-guide-to-hire-a-monday-com-consultant-guide | — |
| 0 | `//the-benefits-of-having-a-personal-virtual-assistant` | /the-benefits-of-having-a-personal-virtual-assistant-guide | — |
| 0 | `//a-thorough-look-at-virtual-assistants-platform-in-mexico` | /a-thorough-look-at-virtual-assistants-platform-in-mexico-guide | — |
| 0 | `//virtual-assistant-for-cleaning-business` | /virtual-assistant-for-cleaning-business-guide | — |
| 0 | `//virtual-assistant-tools-and-free-resources-for-effective-management` | /virtual-assistant-tools-and-free-resources-for-effective-management-guide | — |
| 0 | `//comprehensive-guide-to-hire-a-virtual-assistant-pinterest` | /comprehensive-guide-to-hire-a-virtual-assistant-pinterest-guide | — |
| 0 | `//comprehensive-guide-to-hire-an-email-management-virtual-assistant` | /comprehensive-guide-to-hire-an-email-management-virtual-assistant-guide | — |
| 0 | `//virtual-assistant-for-acquisitions-of-apartment-complexes` | /virtual-assistant-for-acquisitions-of-apartment-complexes-guide | — |
| 0 | `//comprehensive-guide-for-off-shore-cpa-hire` | /comprehensive-guide-for-off-shore-cpa-hire-guide | — |
| 0 | `//how-to-hire-employees-in-the-philippines` | /how-to-hire-employees-in-the-philippines-guide | — |
| 0 | `//why-hire-workers-from-colombia` | /why-hire-workers-from-colombia-guide | — |
| 0 | `//why-every-business-needs-a-remote-administrative-assistant` | /why-every-business-needs-a-remote-administrative-assistant-guide | — |
| 0 | `//the-most-effective-kpi-for-finance-department` | /the-most-effective-kpi-for-finance-department-guide | — |
| 0 | `//hire-an-etsy-expert-to-boost-your-online-shop` | /hire-an-etsy-expert-to-boost-your-online-shop-guide | — |
| 0 | `//understanding-the-benefits-of-a-virtual-assistant-for-cpa` | /understanding-the-benefits-of-a-virtual-assistant-for-cpa-guide | — |
| 0 | `//your-ultimate-guide-to-online-presence-management` | /your-ultimate-guide-to-online-presence-management-guide | — |
| 0 | `//how-to-create-a-google-business-profile-as-an-influencer` | /how-to-create-a-google-business-profile-as-an-influencer-guide | — |
| 0 | `//the-essential-administrative-assistant-skills` | /the-essential-administrative-assistant-skills-guide | — |
| 0 | `//understanding-hyperlocal-social-media-marketing` | /understanding-hyperlocal-social-media-marketing-guide | — |
| 0 | `//the-ultimate-guide-to-hiring-a-virtual-assistant-for-small-business-growth` | /the-ultimate-guide-to-hiring-a-virtual-assistant-for-small-business-growth-guide | — |
| 0 | `//the-rise-of-customer-service-virtual-assistants-scaling-your-business-with-a-human-touch` | /the-rise-of-customer-service-virtual-assistants-scaling-your-business-with-a-human-touch-guide | — |
| 0 | `//lead-generator-virtual-assistant-the-key-to-scaling-your-business-without-losing-your-mind` | /lead-generator-virtual-assistant-the-key-to-scaling-your-business-without-losing-your-mind-guide | — |
| 0 | `//freelance-business-assistant-for-small-and-medium-business-your-secret-weapon-for-scaling-up` | /freelance-business-assistant-for-small-and-medium-business-your-secret-weapon-for-scaling-up-guide | — |
| 0 | `//outsource-b2c-cold-calling-services-a-modern-approach-to-scale-your-business` | /outsource-b2c-cold-calling-services-a-modern-approach-to-scale-your-business-guide | — |
| 0 | `//best-virtual-assistant-for-insurance-agents-the-secret-to-scaling-your-business` | /best-virtual-assistant-for-insurance-agents-the-secret-to-scaling-your-business-guide | — |
| 0 | `//cold-calling-virtual-assistant` | /cold-calling-virtual-assistant-guide | — |
| 0 | `//accounting-virtual-assistant-your-key-to-effortless-financial-management` | /accounting-virtual-assistant-your-key-to-effortless-financial-management-guide | — |
| 0 | `//virtual-administrative-assistant-2` | /virtual-administrative-assistant-guide | — |
| 0 | `//virtual-assistant-for-real-estate` | /virtual-assistant-for-real-estate-guide | — |
| 0 | `//salary-guide-for-businesses-hiring-virtual-assistants` | /salary-guide-for-businesses-hiring-virtual-assistants-guide | — |
| 0 | `//full-time-vs-part-time-virtual-assistants-which-is-right-for-you` | /full-time-vs-part-time-virtual-assistants-which-is-right-for-you-guide | — |
| 0 | `//boost-your-business-with-a-sales-team-for-hire-the-ultimate-growth-solution` | /boost-your-business-with-a-sales-team-for-hire-the-ultimate-growth-solution-guide | — |
| 0 | `//content-creator-for-hire-dedicated-content-creator-for-your-business` | /content-creator-for-hire-dedicated-content-creator-for-your-business-guide | — |
| 0 | `//benefits-of-outsourced-telemarketing` | /benefits-of-outsourced-telemarketing-guide | — |
| 0 | `//understanding-telesales-outsourcing-to-boost-your-business` | /understanding-telesales-outsourcing-to-boost-your-business-guide | — |
| 0 | `//how-to-generate-leads-for-hvac-services` | /how-to-generate-leads-for-hvac-services-guide | — |
| 0 | `//benefits-of-hiring-an-offsite-receptionist` | /benefits-of-hiring-an-offsite-receptionist-guide | — |
| 0 | `//freelance-salesman-boost-your-business-with-an-independent-expert` | /freelance-salesman-boost-your-business-with-an-independent-expert-guide | — |
| 0 | `//how-cold-email-lead-gen-freelance-services-can-boost-your-sales-pipeline` | /how-cold-email-lead-gen-freelance-services-can-boost-your-sales-pipeline-guide | — |
| 0 | `//hire-salesman-a-comprehensive-guide-to-enhance-your-sales-strategy` | /hire-salesman-a-comprehensive-guide-to-enhance-your-sales-strategy-guide | — |
| 0 | `//estinterviewzoom` | https://us02web.zoom.us/j/6990050267?pwd=RpNwxbjq22OrcMAe36gJJfxUNuI0Ha.1 | — |
| 0 | `//jotform-test` | /hmchecklists | — |
| 0 | `//virtual-assistant-posts/employment-advantages-of-hiring-latin-american-remote-workers-guide/` | https://remoteleverage.com/blog/latin-american-virtual-assistant-cost/ | — |
| 0 | `//case-study/mobile-mixologists` | /case-study/mobile-mixologist | — |
| 0 | `//onboarding/` | https://remoteleverage.com/ | off |

---

## Appendix B — complete Yoast Premium redirect list (355 rules)

| From | To | In v2? |
|---|---|:--:|
| `/10976-2` | `/accounting-virtual-assistant-your-key-to-effortless-financial-management` | — |
| `/__trashed-3` | `/tools` | — |
| `/a-guide-to-help-desk-outsourcing-for-modern-businesses` | `/a-guide-to-help-desk-outsourcing-for-modern-businesses-guide` | — |
| `/a-thorough-look-at-virtual-assistants-platform-in-mexico` | `/a-thorough-look-at-virtual-assistants-platform-in-mexico-guide` | — |
| `/accounting-virtual-assistant-your-key-to-effortless-financial-management` | `/accounting-virtual-assistant-your-key-to-effortless-financial-management-guide` | — |
| `/affiliate` | `/referralprogram` | — |
| `/affiliate-registered` | `/referralpartnerregistered` | — |
| `/affiliatetoc` | `/referraltoc` | — |
| `/ai-latin-american-virtual-assistant-placement-tool` | `/anyshore` | — |
| `/appointment-calendar-2` | `/appointment-calendar` | — |
| `/appointment-calendar-v1` | `/appointment-calendar-2` | — |
| `/are-remote-workers-working-all-day` | `/are-remote-workers-working-all-day-guide` | — |
| `/attorney-lead-generation-strategies-and-virtual-assistant-support` | `/attorney-lead-generation-strategies-and-virtual-assistant-support-guide` | — |
| `/b2b-lead-generation-ultimate-guide-to-boost-your-sales` | `/b2b-lead-generation-ultimate-guide-to-boost-your-sales-guide` | — |
| `/b2c-telemarketing-how-to-do-it-well` | `/b2c-telemarketing-how-to-do-it-well-guide` | — |
| `/benefits-of-hiring-an-offsite-receptionist` | `/benefits-of-hiring-an-offsite-receptionist-guide` | — |
| `/benefits-of-outsourced-telemarketing` | `/benefits-of-outsourced-telemarketing-guide` | — |
| `/benefits-of-using-an-applicant-tracking-system-for-small-companies` | `/benefits-of-using-an-applicant-tracking-system-for-small-companies-guide` | — |
| `/best-local-advertising-for-small-business` | `/best-local-advertising-for-small-business-guide` | — |
| `/best-virtual-assistant-for-insurance-agents-the-secret-to-scaling-your-business` | `/best-virtual-assistant-for-insurance-agents-the-secret-to-scaling-your-business-guide` | — |
| `/blog-how-to-find-architecture-clients` | `/how-to-find-architecture-clients` | — |
| `/blog/10-signs-youre-ready-to-hire-a-virtual-assistant` | `/blog/10-signs-to-hire-a-virtual-assistant` | — |
| `/blog/5-best-medical-receptionist-services-for-clinics-and-private-practices` | `/blog/best-medical-receptionist-services` | — |
| `/blog/best-healthcare-virtual-assistant-companies-for-medical-practices-2026` | `/blog/best-healthcare-virtual-assistant-companies` | — |
| `/blog/category/salary-guide` | `/blog/category/salary-guides` | — |
| `/blog/checklist-for-hiring-a-virtual-executive-assistant` | `/blog/executive-assistant-hiring-checklist` | — |
| `/blog/content-engine-that-converts-virtual-assistants` | `/blog/build-content-engine-that-converts` | — |
| `/blog/discover-how-a-virtual-assistant-for-construction-company-and-home-service-businesses-cuts-admin-handles-scheduling-and-helps-owners-get-back-in-the-field` | `/blog/virtual-assistants-for-construction-company-and-home-service-businesseses` | — |
| `/blog/emporia-consulting-scales-faster-with-a-latin-america-va` | `/case-study/emporia-consulting` | — |
| `/blog/executive-assistant-companies` | `/blog/top-executive-assistant-companies` | — |
| `/blog/family-law-attorneys-virtual-staff` | `/blog/family-law-manage-email-comms-vas` | — |
| `/blog/freelance-virtual-admin-vs-virtual-administrative-assistant` | `/blog/virtual-asssitants-scale-lead-generation` | — |
| `/blog/freelance-virtual-admin-vs-virtual-administrative-assistant-which-should-you-hire` | `/blog/freelance-virtual-admin-vs-virtual-administrative-assistant` | — |
| `/blog/freelance-virtual-admin-vs-virtual-administrative-assistant-which-should-you-hire-2` | `/blog/freelance-virtual-admin-vs-virtual-admin-assistant` | — |
| `/blog/hiring-independent-contractors-internationally-here-s-why-yo` | `/blog/hiring-international-contractors-contractor-of-record` | — |
| `/blog/how-a-contractor-of-record-platform-protects-your-business-f` | `/blog/contractor-of-record-misclassification-protection` | — |
| `/blog/how-clean-cozy-home-found-the-right-bilingual-va-with-remote-leverage` | `/case-study/clean-cozy-home` | — |
| `/blog/how-much-a-telehealth-virtual-assistant-cost` | `/blog/telehealth-virtual-assistant-cost` | — |
| `/blog/how-much-does-a-patient-care-coordinator-cost` | `/blog/patient-care-coordinator-cost` | — |
| `/blog/how-much-does-a-virtual-administrative-assistant-cost` | `/blog/administrative-assistant-cost` | — |
| `/blog/how-much-does-a-virtual-administrative-assistant-cost-a-guide-for-business-owners` | `/blog/how-much-does-a-virtual-administrative-assistant-cost` | — |
| `/blog/how-much-does-a-virtual-bookkeeper-cost` | `/blog/virtual-bookkeeper-cost` | — |
| `/blog/how-much-does-a-virtual-bookkeeper-cost-and-whats-the-real-roi-for-your-business` | `/blog/how-much-does-a-virtual-bookkeeper-cost` | — |
| `/blog/how-much-does-a-virtual-executive-assistant-cost` | `/blog/virtual-executive-assistant-cost` | — |
| `/blog/how-much-does-a-virtual-executive-assistant-cost-pricing-breakdown-for-ceos-and-founders` | `/blog/how-much-does-a-virtual-executive-assistant-cost` | — |
| `/blog/how-much-does-a-virtual-marketing-assistant-cost-pricing-breakdown-for-business-owners` | `/blog/how-much-does-a-virtual-marketing-assistant-cost` | — |
| `/blog/how-private-practices-can-compete-with-large-healthcare-systems-using-virtual-assistants` | `/blog/private-practices-vs-healthcare-systems-virtual-assistants` | — |
| `/blog/how-to-delegate-any-task-to-a-remote-assistant-virtual-assistant` | `/blog/how-to-delegate-any-task` | — |
| `/blog/how-to-hire-an-admin-virtual-assistant-step-by-step-guide-2026` | `/blog/how-to-hire-an-admin-virtual-assistant` | — |
| `/blog/how-virtual-assistant-scale-startups` | `/blog/virtual-assistant-for-startups-how-early-stage-companies-use-vas-to-scale-without-overhead` | — |
| `/blog/how-virtual-medical-assistants-improve-patient-scheduling-intake-and-no-show-rates` | `/blog/virtual-medical-assistants-scheduling-intake` | — |
| `/blog/latin-american-virtual-assistant-cost-20` | `/blog/latin-american-virtual-assistant-cost-2026` | — |
| `/blog/latin-american-virtual-assistant-cost-2026` | `/blog/latin-american-virtual-assistant-cost` | — |
| `/blog/legal-virtual-assistant-administrative-burden` | `/blog/legal-virtual-assistant-to-reduce-admin-burden` | — |
| `/blog/manage-your-virtual-assistant-with-better-systems-communication-and-accountability-to-improve-remote-team-performance` | `/blog/how-manage-a-virtual-assistant` | — |
| `/blog/mastering-marketing-lead-generation-strategies-and-virtual-assistants-guide` | `/blog/marketing-lead-generation-strategies` | — |
| `/blog/optimize-social-media-marketing-virtual-assistant` | `/blog/optimize-social-media-marketing` | — |
| `/blog/outsourcing-vs-in-house-how-business-owners-delegate` | `/blog/outsourcing-vs-in-house-how-to-delegate` | — |
| `/blog/outsourcing-vs-in-house-how-smart-business-owners-decide-what-to-keep-and-what-to-delegate` | `/blog/outsourcing-vs-in-house-how-business-owners-delegate` | — |
| `/blog/scale-starups-with-virtual-assistants` | `/blog/scale-startups-with-virtual-assistants` | — |
| `/blog/top-26-virtual-assistant-companies-ranked-for-2026` | `/blog/top-virtual-assistant-companies-ranked-for-2026` | — |
| `/blog/top-virtual-assistant-companies-ranked-for-2026` | `/blog/top-virtual-assistant-companies-for-2026` | — |
| `/blog/transactional-coordinator` | `/blog/transactional-coordinator-role-duties-hire` | — |
| `/blog/transactional-coordinator-role-duties-hire` | `/blog/transaction-coordinator` | — |
| `/blog/upwork-vs-dedicated-virtual-assistant-services-which-gets-you-better-results` | `/blog/upwork-vs-dedicated-virtual-assistant-services-which-is-better` | — |
| `/blog/upwork-vs-dedicated-virtual-assistant-services-which-is-better` | `/blog/upwork-vs-virtual-assistant` | — |
| `/blog/virtual-assistant-for-startups-how-early-stage-companies-use-vas-to-scale-without-overhead` | `/blog/scale-starups-with-virtual-assistants` | — |
| `/blog/virtual-assistant-vs-personal-assistant-whats-the-difference-and-which-do-you-need` | `/blog/virtual-assistant-vs-personal-assistant` | — |
| `/blog/virtual-assistants-for-construction-company-and-home-service-businesseses` | `/blog/virtual-assistants-construction-home-service` | — |
| `/blog/virtual-medical-assistants-scheduling-intake` | `/blog/virtual-medical-assistants-response-times` | — |
| `/blog/what-can-a-virtual-assistant-do-for-your-business` | `/blog/what-virtual-assistants-do-businesses` | — |
| `/blog/what-is-a-virtual-assistant-everything-business-owners-need-to-know` | `/blog/what-is-a-virtual-assistant` | — |
| `/blog/what-to-look-for-in-a-virtual-assistant` | `/blog/how-to-hire-virtual-assistant` | — |
| `/blog/what-to-look-for-in-a-virtual-legal-assistant-for-your-practice-checklist-for-hiring-managers` | `/blog/virtual-legal-assistant-checklist` | — |
| `/blog/what-to-look-for-when-hiring-a-virtual-executive-assistant` | `/blog/checklist-for-hiring-a-virtual-executive-assistant` | — |
| `/blog/what-to-look-for-when-hiring-a-virtual-executive-assistant-checklist-for-executives` | `/blog/what-to-look-for-when-hiring-a-virtual-executive-assistant` | — |
| `/blog/what-to-look-for-when-hiring-a-virtual-marketing-assistant-checklist-for-business-owners` | `/blog/virtual-marketing-assistant-hiring-checklist` | — |
| `/blog/what-virtual-assistants-do-businesses` | `/blog/what-virtual-assistants-do-for-businesses` | — |
| `/blog/why-us-busineses-hire-from-latin-america` | `/blog/why-us-businesses-hire-from-latin-america` | — |
| `/blog/why-us-business-owners-hire-latin-american-virtual-assistants` | `/blog/why-us-busineses-hire-from-latin-america` | — |
| `/boost-your-business-financial-advisor-lead-generation` | `/boost-your-business-financial-advisor-lead-generation-guide` | — |
| `/boost-your-business-with-a-sales-team-for-hire-the-ultimate-growth-solution` | `/boost-your-business-with-a-sales-team-for-hire-the-ultimate-growth-solution-guide` | — |
| `/business-growth-strategies-for-modern-enterprises` | `/business-growth-strategies-for-modern-enterprises-guide` | — |
| `/calendar` | `/vacalendar` | — |
| `/calendar-2` | `/calendar` | — |
| `/call-center-services-an-essential-guide-for-businesses` | `/call-center-services-an-essential-guide-for-businesses-guide` | — |
| `/can-i-mention-another-company-in-my-ad` | `/can-i-mention-another-company-in-my-ad-guide` | — |
| `/case-studies` | `/case-study` | — |
| `/case-study-bench-accounting` | `/case-study/bench-accounting` | — |
| `/case-study/case-study-bench-accounting` | `/case-study/bench-accounting` | — |
| `/case-study/mobile-mixologists` | `/case-study/mobile-mixologist` | — |
| `/choosing-the-best-accounting-software-for-chapter-s-corp` | `/choosing-the-best-accounting-software-for-chapter-s-corp-guide` | — |
| `/client-relationship-partner-building-strong-relationships-for-business-success` | `/client-relationship-partner-building-strong-relationships-for-business-success-guide` | — |
| `/cold-calling-virtual-assistant` | `/cold-calling-virtual-assistant-guide` | — |
| `/comparison-2` | `/compare-athena` | — |
| `/comprehensive-guide-for-off-shore-cpa-hire` | `/comprehensive-guide-for-off-shore-cpa-hire-guide` | — |
| `/comprehensive-guide-to-hire-a-virtual-assistant-pinterest` | `/comprehensive-guide-to-hire-a-virtual-assistant-pinterest-guide` | — |
| `/comprehensive-guide-to-hire-an-email-management-virtual-assistant` | `/comprehensive-guide-to-hire-an-email-management-virtual-assistant-guide` | — |
| `/connect-learn-and-grow-virtual-assistant-agency-conference` | `/connect-learn-and-grow-virtual-assistant-agency-conference-guide` | — |
| `/content-creator-for-hire-dedicated-content-creator-for-your-business` | `/content-creator-for-hire-dedicated-content-creator-for-your-business-guide` | — |
| `/contractor-lead-generation` | `/contractor-lead-generation-guide` | — |
| `/contractor-management-2` | `/live-session-june-26` | — |
| `/contractor-of-record-001` | `/contractor-of-record` | — |
| `/contractor-of-record-c-piash-clone` | `/cor` | — |
| `/cor-2` | `/contractor-payments` | — |
| `/cor-legacy` | `/cor-b` | — |
| `/cor-legacy-branding-home-reference` | `/cor-legacy` | — |
| `/credit-repair-lead-generation-effective-ways-for-companies-to-generate-leads` | `/credit-repair-lead-generation-effective-ways-for-companies-to-generate-leads-guide` | — |
| `/data-entry-outsourcing-a-deep-dive-into-efficiency-and-cost-effective-solutions` | `/data-entry-outsourcing-a-deep-dive-into-efficiency-and-cost-effective-solutions-guide` | — |
| `/different-ways-to-generate-health-insurance-leads` | `/different-ways-to-generate-health-insurance-leads-guide` | — |
| `/dotloop-vs-docusign-e-signature-solutions-for-the-real-estate-industry` | `/dotloop-vs-docusign-e-signature-solutions-for-the-real-estate-industry-guide` | — |
| `/effective-lead-generation-examples-techniques-and-strategies` | `/effective-lead-generation-examples-techniques-and-strategies-guide` | — |
| `/effective-strategies-for-finding-accounting-leads` | `/effective-strategies-for-finding-accounting-leads-guide` | — |
| `/effective-strategies-for-generating-commercial-insurance-leads` | `/effective-strategies-for-generating-commercial-insurance-leads-guide` | — |
| `/effective-strategies-for-local-lead-generation` | `/effective-strategies-for-local-lead-generation-guide` | — |
| `/effective-strategies-for-plumbing-lead-generation-2` | `/effective-strategies-for-plumbing-lead-generation-guide` | — |
| `/effective-ways-for-auto-insurance-lead-generation` | `/effective-ways-for-auto-insurance-lead-generation-guide` | — |
| `/effective-ways-for-saas-companies-to-generate-leads` | `/effective-ways-for-saas-companies-to-generate-leads-guide` | — |
| `/elementor-31881` | `/live-session` | — |
| `/employment-advantages-of-hiring-latin-american-remote-workers` | `/employment-advantages-of-hiring-latin-american-remote-workers-guide` | — |
| `/essential-executive-assistant-interview-questions-for-business-owners` | `/essential-executive-assistant-interview-questions-for-business-owners-guide` | — |
| `/essential-guide-to-a-virtual-assistant-contract-template` | `/essential-guide-to-a-virtual-assistant-contract-template-guide` | — |
| `/essential-tasks-a-real-estate-investor-virtual-assistant-can-handle-from-lead-generation-to-closing` | `/essential-tasks-a-real-estate-investor-virtual-assistant-can-handle-from-lead-generation-to-closing-guide` | — |
| `/exclusive-business-networks-unlocking-hidden-potential` | `/exclusive-business-networks-unlocking-hidden-potential-guide` | — |
| `/expand-google-my-business-reach` | `/expand-google-my-business-reach-guide` | — |
| `/experience-premium-business-travel-services-like-never-before` | `/experience-premium-business-travel-services-like-never-before-guide` | — |
| `/exploring-it-support-services-for-modern-businesses` | `/exploring-it-support-services-for-modern-businesses-guide` | — |
| `/exploring-the-benefits-of-hiring-a-personal-concierge` | `/exploring-the-benefits-of-hiring-a-personal-concierge-guide` | — |
| `/exploring-the-growing-world-of-the-talent-marketplace` | `/exploring-the-growing-world-of-the-talent-marketplace-guide` | — |
| `/fast-real-estate` | `/case-study/fast-real-estate` | — |
| `/finding-a-virtual-assistant-your-ultimate-guide-to-boost-productivity` | `/finding-a-virtual-assistant-your-ultimate-guide-to-boost-productivity-guide` | — |
| `/finding-kajabi-expert-skills-and-hiring-tips` | `/finding-kajabi-expert-skills-and-hiring-tips-guide` | — |
| `/finding-leads-in-network-marketing-strategies-and-the-role-of-virtual-assistants` | `/finding-leads-in-network-marketing-strategies-and-the-role-of-virtual-assistants-guide` | — |
| `/finding-motivated-seller-leads-tips-and-tricks` | `/finding-motivated-seller-leads-tips-and-tricks-guide` | — |
| `/finding-the-best-place-to-hire-appointment-setters` | `/finding-the-best-place-to-hire-appointment-setters-guide` | — |
| `/fostering-client-relation-building-meaningful-connections-for-success` | `/fostering-client-relation-building-meaningful-connections-for-success-guide` | — |
| `/freelance-business-assistant-for-small-and-medium-business-your-secret-weapon-for-scaling-up` | `/freelance-business-assistant-for-small-and-medium-business-your-secret-weapon-for-scaling-up-guide` | — |
| `/freelance-salesman-boost-your-business-with-an-independent-expert` | `/freelance-salesman-boost-your-business-with-an-independent-expert-guide` | — |
| `/full-time-vs-part-time-virtual-assistants-which-is-right-for-you` | `/full-time-vs-part-time-virtual-assistants-which-is-right-for-you-guide` | — |
| `/generating-high-quality-business-opportunity-leads` | `/generating-high-quality-business-opportunity-leads-guide` | — |
| `/haus-of-her` | `/case-study/haus-of-her` | — |
| `/haus-of-her-studios-saved-money-gained-a-highly-qualified-va-with-remote-leverage` | `/haus-of-her` | — |
| `/hire-a-copywriter-to-boost-your-business-blog` | `/hire-a-copywriter-to-boost-your-business-guide` | — |
| `/hire-a-freelance-research-assistant-benefits-for-small-businesses` | `/hire-a-freelance-research-assistant-benefits-for-small-businesses-guide` | — |
| `/hire-an-etsy-expert-to-boost-your-online-shop` | `/hire-an-etsy-expert-to-boost-your-online-shop-guide` | — |
| `/hire-offshore-accountant-a-smart-move-for-your-business` | `/hire-offshore-accountant-a-smart-move-for-your-business-guide` | — |
| `/hire-real-estate-virtual-assistants-from-latam-remote-leverage-form-collapsible` | `/shopify-amazon-vas` | — |
| `/hire-salesman-a-comprehensive-guide-to-enhance-your-sales-strategy` | `/hire-salesman-a-comprehensive-guide-to-enhance-your-sales-strategy-guide` | — |
| `/hire-social-media-content-creator-everything-you-need-to-know` | `/hire-social-media-content-creator-everything-you-need-to-know-guide` | — |
| `/hire-us-uk` | `/hire-us-uk-now` | — |
| `/hire-va-4-0-to-start` | `/hire-va-6` | — |
| `/hire-va-7` | `/hire-us-uk` | — |
| `/hire-virtual-assistant` | `/hire-virtual-assistants` | ✓ |
| `/hire-virtual-assistants-fast` | `/hire-va-email` | — |
| `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b-copy` | `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b-operators` | — |
| `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-d` | `/1monthonus-flp` | — |
| `/hiring` | `/services-hiring` | — |
| `/hiring-a-cheap-virtual-assistant-benefits-for-us-small-businesses-from-the-philippines-and-latin-america` | `/hiring-a-cheap-virtual-assistant-benefits-for-us-small-businesses-from-the-philippines-and-latin-america-guide` | — |
| `/hiring-a-va-in-south-america-a-smart-choice` | `/hiring-a-va-in-south-america-a-smart-choice-guide` | — |
| `/hiring-a-virtual-business-assistant-top-benefits-for-small-businesses` | `/hiring-a-virtual-business-assistant-top-benefits-for-small-businesses-guide` | — |
| `/hiring-a-virtual-recruiter-the-future-of-talent-acquisition` | `/hiring-a-virtual-recruiter-the-future-of-talent-acquisition-guide` | — |
| `/home` | `/socialmediavirtualassistants` | ✓ |
| `/homepage-sept-26` | `/elementor-50922` | — |
| `/homepage-sept-26-new` | `/elementor-50922` | — |
| `/homepage-sept-26-old-2` | `/homepage-sept-26-newer` | — |
| `/homepage-va2` | `/va-hire` | — |
| `/hopepage-sep-26` | `/homepage-sep-26` | — |
| `/how-cold-email-lead-gen-freelance-services-can-boost-your-sales-pipeline` | `/how-cold-email-lead-gen-freelance-services-can-boost-your-sales-pipeline-guide` | — |
| `/how-do-ad-agencies-find-clients` | `/how-do-ad-agencies-find-clients-guide` | — |
| `/how-do-i-get-leads-for-senior-life-insurance` | `/how-do-i-get-leads-for-senior-life-insurance-guide` | — |
| `/how-email-lead-generation-can-boost-your-business` | `/how-email-lead-generation-can-boost-your-business-guide` | — |
| `/how-long-should-advertisements-run-before-profit` | `/how-long-should-advertisements-run-before-profit-guide` | — |
| `/how-much-does-athena-virtual-assistant-cost` | `/how-much-does-athena-virtual-assistant-cost-guide` | — |
| `/how-to-advertise-your-business-effectively` | `/how-to-advertise-your-business-effectively-guide` | — |
| `/how-to-analyze-cost-per-hire` | `/how-to-analyze-cost-per-hire-guide` | — |
| `/how-to-attract-talent-with-recruitment-marketing` | `/how-to-attract-talent-with-recruitment-marketing-guide` | — |
| `/how-to-choose-the-best-lead-generation-companies-for-small-businesses` | `/how-to-choose-the-best-lead-generation-companies-for-small-businesses-guide` | — |
| `/how-to-create-a-google-business-profile-as-an-influencer` | `/how-to-create-a-google-business-profile-as-an-influencer-guide` | — |
| `/how-to-find-architecture-clients` | `/how-to-find-architecture-clients-guide` | — |
| `/how-to-find-bookkeeping-clients-effective-strategies-for-growth` | `/how-to-find-bookkeeping-clients-effective-strategies-for-growth-guide` | — |
| `/how-to-find-web-design-clients` | `/how-to-find-web-design-clients-guide` | — |
| `/how-to-generate-leads-for-hvac-services` | `/how-to-generate-leads-for-hvac-services-guide` | — |
| `/how-to-generate-mlm-leads-effectively` | `/how-to-generate-mlm-leads-effectively-guide` | — |
| `/how-to-generate-organic-visits-for-google-business-profile` | `/how-to-generate-organic-visits-for-google-business-profile-guide` | — |
| `/how-to-generate-quality-home-based-business-leads` | `/how-to-generate-quality-home-based-business-leads-guide` | — |
| `/how-to-get-cleaning-leads-for-your-growing-business` | `/how-to-get-cleaning-leads-for-your-growing-business-guide` | — |
| `/how-to-get-construction-leads` | `/how-to-get-construction-leads-guide` | — |
| `/how-to-get-insurance-leads-strategies-for-brokers` | `/how-to-get-insurance-leads-strategies-for-brokers-guide` | — |
| `/how-to-get-leads-as-a-real-estate-agent` | `/how-to-get-leads-as-a-real-estate-agent-guide` | — |
| `/how-to-hire-child-as-a-business-owner` | `/how-to-hire-child-as-a-business-owner-guide` | — |
| `/how-to-hire-employees-in-the-philippines` | `/how-to-hire-employees-in-the-philippines-guide` | — |
| `/how-to-set-up-gbp-for-clients-effectively` | `/how-to-set-up-gbp-for-clients-effectively-guide` | — |
| `/how-to-verify-company-profile` | `/how-to-verify-company-profile-guide` | — |
| `/https-remoteleverage-com-hire-virtual-assistants-fast` | `/hire-virtual-assistants-fast` | — |
| `/hvac-lead-generation-mastering-the-art-of-generating-leads` | `/hvac-lead-generation-mastering-the-art-of-generating-leads-guide` | — |
| `/inbox-management-professional-strategies-for-productive-work` | `/inbox-management-professional-strategies-for-productive-work-guide` | — |
| `/innovative-ways-to-generate-leads-in-commercial-real-estate` | `/innovative-ways-to-generate-leads-in-commercial-real-estate-guide` | — |
| `/innovative-ways-to-improve-life-insurance-lead-generation` | `/innovative-ways-to-improve-life-insurance-lead-generation-guide` | — |
| `/instagram-reel-creator-having-a-virtual-assistant-dedicated-to-instagram-reel-creation` | `/instagram-reel-creator-having-a-virtual-assistant-dedicated-to-instagram-reel-creation-guide` | — |
| `/insurance-telemarketing-revolutionizing-the-industry` | `/insurance-telemarketing-revolutionizing-the-industry-guide` | — |
| `/job-listing-generator` | `/tools` | — |
| `/jotform-test` | `/hmchecklists` | — |
| `/keep-track-of-candidates-strategies-for-efficient-recruitment` | `/keep-track-of-candidates-strategies-for-efficient-recruitment-guide` | — |
| `/landing-page-hero-form-a` | `/form-lp` | — |
| `/landing-page-hero-form-b` | `/form-lp2` | — |
| `/landingpage-2` | `/landingpage` | — |
| `/law-firm-virtual-assistant-enhancing-efficiency-and-productivity` | `/law-firm-virtual-assistant-enhancing-efficiency-and-productivity-guide` | — |
| `/lead-generation-for-commercial-cleaning-businesses` | `/lead-generation-for-commercial-cleaning-businesses-guide` | — |
| `/lead-generation-for-it-services` | `/lead-generation-for-it-services-guide` | — |
| `/lead-generation-for-lawyers-boost-your-practice-with-effective-strategies` | `/lead-generation-for-lawyers-boost-your-practice-with-effective-strategies-guide` | — |
| `/lead-generation-virtual-assistants-2` | `/lead-generation-assistants` | — |
| `/lead-generator-virtual-assistant-the-key-to-scaling-your-business-without-losing-your-mind` | `/lead-generator-virtual-assistant-the-key-to-scaling-your-business-without-losing-your-mind-guide` | — |
| `/legalvirtualassistants` | `/home` | — |
| `/live-session-june-26` | `/live-session-10x-revenue-with-ai` | — |
| `/live-session-what-to-automate-from-day-1` | `/live-session-build-ai-tools-for-businesses` | — |
| `/looking-for-activecampaign-alternatives-heres-what-you-need-to-know` | `/looking-for-activecampaign-alternatives-heres-what-you-need-to-know-guide` | — |
| `/making-a-new-hire-feel-part-of-the-team` | `/making-a-new-hire-feel-part-of-the-team-guide` | — |
| `/marketing-assistants-3` | `/marketing-assistants` | — |
| `/marketing-virtual-assistants` | `/marketing-virtual-assistants-2` | — |
| `/marketing-virtual-assistants-2` | `/marketing-assistants-2` | — |
| `/mastering-marketing-lead-generation-strategies-and-virtual-assistants` | `/mastering-marketing-lead-generation-strategies-and-virtual-assistants-guide` | — |
| `/mastering-the-role-of-a-social-media-virtual-assistant` | `/mastering-the-role-of-a-social-media-virtual-assistant-guide` | — |
| `/micro-conversion-ideas-for-lead-generation` | `/micro-conversion-ideas-for-lead-generation-guide` | — |
| `/mortgage-lead-generation-strategies-and-the-role-of-virtual-assistants` | `/mortgage-lead-generation-strategies-and-the-role-of-virtual-assistants-guide` | — |
| `/need-an-assistant-heres-why-it-might-be-time-to-hire-help` | `/need-an-assistant-heres-why-it-might-be-time-to-hire-help-guide` | — |
| `/on-the-outskirt` | `/case-study/on-the-outskirt` | — |
| `/outbound-telemarketing-services-understanding-the-benefits` | `/outbound-telemarketing-services-understanding-the-benefits-guide` | — |
| `/outsource-b2c-cold-calling-services-a-modern-approach-to-scale-your-business` | `/outsource-b2c-cold-calling-services-a-modern-approach-to-scale-your-business-guide` | — |
| `/outsourced-lead-generation-a-smart-strategy-for-business-growth` | `/outsourced-lead-generation-a-smart-strategy-for-business-growth-guide` | — |
| `/outsourced-lead-generation-boosting-your-sales-pipeline` | `/outsourced-lead-generation-boosting-your-sales-pipeline-guide` | — |
| `/outsourcing-hr-optimizing-your-business-efficiency` | `/outsourcing-hr-optimizing-your-business-efficiency-guide` | — |
| `/outsourcing-video-editing-a-gateway-to-efficiency-and-quality` | `/outsourcing-video-editing-a-gateway-to-efficiency-and-quality-guide` | — |
| `/partnership-inspired-landing-page` | `/prtnrshp-insp-lp` | — |
| `/performance-payroll-package` | `/thankyou-ppp` | — |
| `/performance-payroll-package-calendar` | `/calendar-ppp` | — |
| `/pink-steff` | `/va-store-5` | — |
| `/powering-business-success-through-a-content-creator-assistant` | `/powering-business-success-through-a-content-creator-assistant-guide` | — |
| `/pricing` | `/vapricing` | — |
| `/real-estate-cold-calling-virtual-assistants-the-secret-to-real-estate-success` | `/real-estate-cold-calling-virtual-assistants-the-secret-to-real-estate-success-guide` | — |
| `/real-estate-leads-pay-at-closing-where-to-find-them` | `/real-estate-leads-pay-at-closing-where-to-find-them-guide` | — |
| `/real-estate-posts/culture-add-vs-culture-fit-what-you-should-really-hire-for` | `/real-estate-posts/culture-add-vs-culture-fit-2` | — |
| `/recruiter-checklists` | `/recruiterchecklists` | — |
| `/remote-employee-management-communication-strategies` | `/remote-employee-management-communication-strategies-guide` | — |
| `/remote-employee-tracking-and-data-leak-prevention` | `/remote-employee-tracking-and-data-leak-prevention-guide` | — |
| `/remote-talent-acquisition-simplified-harnessing-the-power-of-a-global-workforce` | `/remote-talent-acquisition-simplified-harnessing-the-power-of-a-global-workforce-guide` | — |
| `/remote-work-tracking-software-the-best-options-for-managing-remote-teams` | `/remote-work-tracking-software-the-best-options-for-managing-remote-teams-guide` | — |
| `/resumes` | `/resume-portal` | — |
| `/reviews-2` | `/reviews` | — |
| `/rippling-login-issue-how-to-troubleshoot-and-resolve-common-problems` | `/rippling-login-issue-how-to-troubleshoot-and-resolve-common-problems-guide` | — |
| `/roles/executive-virtual-assistant-2` | `/roles/executive-virtual-assistant` | — |
| `/roofing-lead-generation-strategies-and-tools-to-boost-your-roofing-business` | `/roofing-lead-generation-strategies-and-tools-to-boost-your-roofing-business-guide` | — |
| `/salary-guide-for-businesses-hiring-virtual-assistants` | `/salary-guide-for-businesses-hiring-virtual-assistants-guide` | — |
| `/sales-virtual-assistants-latam` | `/sales-virtual-assistants-2` | — |
| `/sample` | `/samples` | — |
| `/sample-applicant-voice-recordings` | `/sample` | — |
| `/service-hiri` | `/service-hiring` | — |
| `/services-hiring` | `/service-hiring` | — |
| `/small-business-wealth-strategies-mastering-financial-success` | `/small-business-wealth-strategies-mastering-financial-success-guide` | — |
| `/small-company-growth` | `/small-company-growth-guide` | — |
| `/smart-background-checks-for-employers` | `/smart-background-checks-for-employers-guide` | — |
| `/spanish-hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b` | `/spanish` | — |
| `/startup-bookkeeping-essentials-for-success` | `/startup-bookkeeping-essentials-for-success-guide` | — |
| `/thank-you-2` | `/vathankyou` | — |
| `/thank-you-cor` | `/cor-thank-you` | — |
| `/the-acre-hub` | `/case-study/the-acre-hub` | — |
| `/the-advantages-of-being-a-white-label-hubspot-partner` | `/the-advantages-of-being-a-white-label-hubspot-partner-guide` | — |
| `/the-advantages-of-hiring-a-temp-secretary` | `/the-advantages-of-hiring-a-temp-secretary-guide` | — |
| `/the-advantages-of-hiring-a-virtual-sales-agent` | `/the-advantages-of-hiring-a-virtual-sales-agent-guide` | — |
| `/the-benefits-of-having-a-personal-virtual-assistant` | `/the-benefits-of-having-a-personal-virtual-assistant-guide` | — |
| `/the-benefits-of-having-an-isa-calling-team` | `/the-benefits-of-having-an-isa-calling-team-guide` | — |
| `/the-benefits-of-hiring-a-remote-appointment-setter` | `/the-benefits-of-hiring-a-remote-appointment-setter-guide` | — |
| `/the-benefits-of-hiring-a-remote-receptionist` | `/the-benefits-of-hiring-a-remote-receptionist-guide` | — |
| `/the-benefits-of-hiring-a-temp-admin-assistant` | `/the-benefits-of-hiring-a-temp-admin-assistant-guide` | — |
| `/the-benefits-of-hiring-a-virtual-appointment-setter-for-small-businesses` | `/the-benefits-of-hiring-a-virtual-appointment-setter-for-small-businesses-guide` | — |
| `/the-benefits-of-it-outsourcing-for-modern-businesses` | `/the-benefits-of-it-outsourcing-for-modern-businesses-guide` | — |
| `/the-benefits-of-virtual-customer-care-for-businesses` | `/the-benefits-of-virtual-customer-care-for-businesses-guide` | — |
| `/the-best-place-to-place-ads` | `/the-best-place-to-place-ads-guide` | — |
| `/the-best-virtual-office-options-for-your-business` | `/the-best-virtual-office-options-for-your-business-guide` | — |
| `/the-cost-effective-benefits-of-hiring-a-general-virtual-assistant` | `/the-cost-effective-benefits-of-hiring-a-general-virtual-assistant-guide` | — |
| `/the-essential-administrative-assistant-skills` | `/the-essential-administrative-assistant-skills-guide` | — |
| `/the-essential-role-of-a-recruitment-assistant` | `/the-essential-role-of-a-recruitment-assistant-guide` | — |
| `/the-future-of-visitor-management-virtual-front-desk` | `/the-future-of-visitor-management-virtual-front-desk-guide` | — |
| `/the-future-of-visitor-management-virtual-front-desks` | `/the-future-of-visitor-management-virtual-front-desk` | — |
| `/the-most-effective-kpi-for-finance-department` | `/the-most-effective-kpi-for-finance-department-guide` | — |
| `/the-rise-of-customer-service-virtual-assistants-scaling-your-business-with-a-human-touch` | `/the-rise-of-customer-service-virtual-assistants-scaling-your-business-with-a-human-touch-guide` | — |
| `/the-rise-of-virtual-project-management-navigating-modern-workplaces` | `/the-rise-of-virtual-project-management-navigating-modern-workplaces-guide` | — |
| `/the-rise-of-virtual-staffing-a-revolution-in-the-workforce` | `/the-rise-of-virtual-staffing-a-revolution-in-the-workforce-guide` | — |
| `/the-role-of-a-personal-executive-assistant` | `/the-role-of-a-personal-executive-assistant-guide` | — |
| `/the-secret-to-scaling-your-ebay-store-hire-an-ebay-virtual-assistant` | `/the-secret-to-scaling-your-ebay-store-hire-an-ebay-virtual-assistant-guide` | — |
| `/the-smarter-way-to-hire-a-virtual-assistant` | `/va-lap01` | — |
| `/the-ultimate-guide-to-b2b-sales-prospecting-with-virtual-assistants` | `/the-ultimate-guide-to-b2b-sales-prospecting-with-virtual-assistants-guide` | — |
| `/the-ultimate-guide-to-hire-a-monday-com-consultant` | `/the-ultimate-guide-to-hire-a-monday-com-consultant-guide` | — |
| `/the-ultimate-guide-to-hiring-a-remote-hr-assistant` | `/the-ultimate-guide-to-hiring-a-remote-hr-assistant-guide` | — |
| `/the-ultimate-guide-to-hiring-a-virtual-assistant-for-small-business-growth` | `/the-ultimate-guide-to-hiring-a-virtual-assistant-for-small-business-growth-guide` | — |
| `/the-unmatched-benefits-of-a-business-growth-strategist-for-your-company` | `/the-unmatched-benefits-of-a-business-growth-strategist-for-your-company-guide` | — |
| `/the-virtual-financial-planning-assistant-your-secret-weapon-to-scaling-your-business` | `/the-virtual-financial-planning-assistant-your-secret-weapon-to-scaling-your-business-guide` | — |
| `/tools-2` | `/tools` | — |
| `/transaction-desk-what-it-is` | `/transaction-desk-what-it-is-guide` | — |
| `/transaction-manager-real-estate-mastering-the-art-of-transaction-coordination` | `/transaction-manager-real-estate-mastering-the-art-of-transaction-coordination-guide` | — |
| `/understanding-hyperlocal-social-media-marketing` | `/understanding-hyperlocal-social-media-marketing-guide` | — |
| `/understanding-remote-work-time-tracking-solutions-and-best-practices` | `/understanding-remote-work-time-tracking-solutions-and-best-practices-guide` | — |
| `/understanding-telemarketing-outsourcing` | `/understanding-telemarketing-outsourcing-guide` | — |
| `/understanding-telesales-outsourcing-to-boost-your-business` | `/understanding-telesales-outsourcing-to-boost-your-business-guide` | — |
| `/understanding-the-benefits-of-a-virtual-assistant-for-cpa` | `/understanding-the-benefits-of-a-virtual-assistant-for-cpa-guide` | — |
| `/understanding-the-essential-benefits-of-a-virtual-reception-service` | `/understanding-the-essential-benefits-of-a-virtual-reception-service-guide` | — |
| `/understanding-the-role-of-a-growth-assistant` | `/understanding-the-role-of-a-growth-assistant-guide` | — |
| `/understanding-the-role-of-a-real-estate-isa-maximizing-your-real-estate-business` | `/understanding-the-role-of-a-real-estate-isa-maximizing-your-real-estate-business-guide` | — |
| `/understanding-the-role-of-a-virtual-real-estate-transaction-coordinator` | `/understanding-the-role-of-a-virtual-real-estate-transaction-coordinator-guide` | — |
| `/understanding-transaction-lifecycle-management-in-real-estate` | `/understanding-transaction-lifecycle-management-in-real-estate-guide` | — |
| `/understanding-white-label-virtual-assistant-services` | `/understanding-white-label-virtual-assistant-services-guide` | — |
| `/va-lap01` | `/vsl-lp` | — |
| `/va-store-5` | `/hire-va-5` | — |
| `/va-vsl01` | `/va-lap01` | — |
| `/vahiringonboardingguide2` | `/vaonboardingguide2` | — |
| `/virtual-administrative-assistant` | `/virtual-administrative-assistant-guide-2` | — |
| `/virtual-administrative-assistant-2` | `/virtual-administrative-assistant-guide` | — |
| `/virtual-assistant-for-acquisitions-of-apartment-complexes` | `/virtual-assistant-for-acquisitions-of-apartment-complexes-guide` | — |
| `/virtual-assistant-for-cleaning-business` | `/virtual-assistant-for-cleaning-business-guide` | — |
| `/virtual-assistant-for-real-estate` | `/virtual-assistant-for-real-estate-guide` | — |
| `/virtual-assistant-posts/best-practices-for-email-management-that-virtual-assistants-bring-to-remote-teams` | `/virtual-assistant-posts/email-management-best-practices-virtual-assistants` | — |
| `/virtual-assistant-posts/why-hire-customer-service-for-optimal-business-success` | `/virtual-assistant-posts/why-hire-customer-service` | — |
| `/virtual-assistant-roi-cost-benefit` | `/why-us-entrepreneurs-hire-virtual-assistants` | — |
| `/virtual-assistant-roi-cost-benefit-2` | `/why-us-entrepreneurs-hire-virtual-assistants` | — |
| `/virtual-assistant-tools-and-free-resources-for-effective-management` | `/virtual-assistant-tools-and-free-resources-for-effective-management-guide` | — |
| `/virtual-assistant-vs-in-house-employee-pros-and-cons` | `/virtual-assistant-vs-in-house-employee-pros-and-cons-guide` | — |
| `/virtual-assistants-and-time-management-how-to-delegate-effectively` | `/virtual-assistants-and-time-management-how-to-delegate-effectively-guide` | — |
| `/virtual-assistants-for-different-industries-tailoring-services-to-your-needs` | `/virtual-assistants-for-different-industries-tailoring-services-to-your-needs-guide` | — |
| `/virtual-medical-administrative-assistant` | `/virtual-medical-administrative-assistant-guide` | — |
| `/virtual-office-assistant-cost-effective-business-solutions` | `/virtual-office-assistant-cost-effective-business-solutions-guide` | — |
| `/virtual-sales-assistant-a-game-changer-for-small-us-businesses` | `/virtual-sales-assistant-a-game-changer-for-small-us-businesses-guide` | — |
| `/virtual-secretary-cost-a-game-changer-for-businesses` | `/virtual-secretary-cost-a-game-changer-for-businesses-guide` | — |
| `/virtual-team-building-exercises-to-boost-engagement-and-morale` | `/virtual-team-building-exercises-to-boost-engagement-and-morale-guide` | — |
| `/ways-for-solar-lead-generation-and-how-a-virtual-assistant-can-help` | `/ways-for-solar-lead-generation-and-how-a-virtual-assistant-can-help-guide` | — |
| `/website-designers-for-small-business` | `/website-designers-for-small-business-guide` | — |
| `/welcome-to-the-team-how-to-welcome-a-new-employee` | `/welcome-to-the-team-how-to-welcome-a-new-employee-guide` | — |
| `/what-is-a-personality-hire` | `/what-is-a-personality-hire-guide` | — |
| `/what-is-an-example-kpi-for-administrative-assistant` | `/what-is-an-example-kpi-for-administrative-assistant-guide` | — |
| `/what-to-automate-from-day-1` | `/live-session-what-to-automate-from-day-1` | — |
| `/where-can-i-advertise-my-business-for-free` | `/where-can-i-advertise-my-business-for-free-guide` | — |
| `/why-do-companies-choose-to-outsource-work` | `/why-do-companies-choose-to-outsource-work-guide` | — |
| `/why-every-business-needs-a-remote-administrative-assistant` | `/why-every-business-needs-a-remote-administrative-assistant-guide` | — |
| `/why-hire-a-blogger-for-your-small-business` | `/why-hire-a-blogger-for-your-small-business-guide` | — |
| `/why-hire-a-remote-data-entry-clerk-for-your-business` | `/why-hire-a-remote-data-entry-clerk-for-your-business-guide` | — |
| `/why-hire-a-telemarketer-for-your-small-business` | `/why-hire-a-telemarketer-for-your-small-business-guide` | — |
| `/why-hire-seo-expert-philippines-is-a-smart-move-for-your-business` | `/why-hire-seo-expert-philippines-is-a-smart-move-for-your-business-guide` | — |
| `/why-hire-workers-from-colombia` | `/why-hire-workers-from-colombia-guide` | — |
| `/why-hiring-a-marketing-assistant-can-transform-your-business` | `/why-hiring-a-marketing-assistant-can-transform-your-business-guide` | — |
| `/why-hiring-a-payroll-assistant-is-a-smart-move-for-small-business-owners` | `/why-hiring-a-payroll-assistant-is-a-smart-move-for-small-business-owners-guide` | — |
| `/why-hiring-a-virtual-assistant-for-realtors-is-a-game-changer` | `/why-hiring-a-virtual-assistant-for-realtors-is-a-game-changer-guide` | — |
| `/why-hiring-a-virtual-assistant-seo-expert-can-skyrocket-your-business-growth` | `/why-hiring-a-virtual-assistant-seo-expert-can-skyrocket-your-business-growth-guide` | — |
| `/why-hiring-an-admin-assistant-working-from-home-is-a-game-changer-for-busy-business-owners` | `/why-hiring-an-admin-assistant-working-from-home-is-a-game-changer-for-busy-business-owners-guide` | — |
| `/why-outsource-debt-collections-can-enhance-your-business-efficiency` | `/why-outsource-debt-collections-can-enhance-your-business-efficiency-guide` | — |
| `/why-you-should-hire-an-airbnb-virtual-assistant` | `/why-you-should-hire-an-airbnb-virtual-assistant-guide` | — |
| `/why-your-business-needs-a-digital-marketing-virtual-assistant` | `/why-your-business-needs-a-digital-marketing-virtual-assistant-guide` | — |
| `/your-ultimate-guide-to-online-presence-management` | `/your-ultimate-guide-to-online-presence-management-guide` | — |

---

## 7. Collision check (before porting)

Only two missing paths collide with a live v2 pattern or route. Both have **0 recorded hits** and
must **not** be ported — `LegacyRedirectMiddleware` runs on `template_redirect` for every request,
not just 404s, so a key equal to a live slug 301s that page away:

| Path | Legacy target | Why skip |
|---|---|---|
| `/marketing-virtual-assistants` | `marketing-virtual-assistants-2` | live v2 role page |
| `/affiliate` | `referralprogram` | live v2 pattern |

The remaining 443 are safe to port key-for-key.

## 8. Reproducing this audit

```bash
ssh client_acf29921ef_339104@339104.us18.ssh.myftpupload.com
cd /html
wp --skip-plugins --skip-themes db query \
  "SELECT id,url_from,url_to,type,status,regex,case_insensitive,query_parameters,last_count,last_access \
   FROM wp_add9221751_wf301_redirect_rules ORDER BY id;" --skip-column-names
wp --skip-plugins --skip-themes eval \
  '$o=get_option("wpseo-premium-redirects-export-plain");
   foreach($o as $f=>$v){echo $f."\t".(is_array($v)?$v["url"]:$v)."\n";}'
```

A paste-ready PHP block for the 107 trafficked entries is in
[`legacy-redirects-missing.php.txt`](legacy-redirects-missing.php.txt).
