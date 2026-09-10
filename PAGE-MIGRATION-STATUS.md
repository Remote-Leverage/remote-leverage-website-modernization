# Page Migration Status — v2 Redesign

Tracking which pages have been rebuilt in the new (v2) design system vs. which
still need migrating from the legacy templates. Local URLs point at the
`remoteleverage-v2.test` dev site; production URLs point at the live
`remoteleverage.com` site.

| Page | Status | Production URL | Local URL |
|---|---|---|---|
| Home | ✅ Done | https://remoteleverage.com/ | https://remoteleverage-v2.test/ |
| VA Pricing | ✅ Done (just finished) | https://remoteleverage.com/vapricing/ | https://remoteleverage-v2.test/vapricing/ |
| Client Reviews | ✅ Done | https://remoteleverage.com/reviews/ | https://remoteleverage-v2.test/reviews/ |
| About Us | ✅ Done | https://remoteleverage.com/about-us/ | https://remoteleverage-v2.test/about-us/ |
| Case Study Archive | ✅ Done | https://remoteleverage.com/case-study/ | https://remoteleverage-v2.test/case-study/ |
| Affiliate Program | ✅ Reviewed — 1 bug fixed | https://remoteleverage.com/affiliate-program/ | https://remoteleverage-v2.test/affiliate-program/ |
| Comparison (hub) | 🔧 Needs migration | https://remoteleverage.com/comparison/ | https://remoteleverage-v2.test/comparison/ |
| Wing Assistant vs Remote Leverage | 🔧 Needs migration | https://remoteleverage.com/wing-assistant-vs-remote-leverage/ | https://remoteleverage-v2.test/wing-assistant-vs-remote-leverage/ |
| Wing Assistant vs RL – Ads | 🔧 Needs migration | https://remoteleverage.com/comparison-wing-assistant-ads/ | https://remoteleverage-v2.test/comparison-wing-assistant-ads/ |
| Compare Athena | 🔧 Needs migration | https://remoteleverage.com/compare-athena/ | https://remoteleverage-v2.test/compare-athena/ |
| Referral | 🔧 Needs migration | https://remoteleverage.com/referral/ | https://remoteleverage-v2.test/referral/ |
| Thank You | 👀 Custom template, needs review | https://remoteleverage.com/vathankyou/ | https://remoteleverage-v2.test/vathankyou/ |
| Terms of Use | ✅ Done — Legal Document template | https://remoteleverage.com/terms-of-use/ | https://remoteleverage-v2.test/terms-of-use/ |
| Privacy Policy | ✅ Done — Legal Document template | https://remoteleverage.com/privacy-policy/ | https://remoteleverage-v2.test/privacy-policy/ |
| Partners Archive | 🆕 New in v2 — not live on production yet | *(doesn't exist yet)* | https://remoteleverage-v2.test/partners/ |
| Hire VA 4 Preview | — Local-only draft, not on production | *(doesn't exist)* | https://remoteleverage-v2.test/hire-va-4-preview/ |

## How status was determined

- **✅ Done**: page content is built from the new pattern/component system
  (a `-full` pattern wrapper, or the modern `rl-`/Tailwind block set).
- **🔧 Needs migration**: the 4 comparison pages and Referral still contain
  the *old* `hire-va-4-*` template blocks/patterns verbatim (same
  testimonials/FAQ/booking-footer patterns as the legacy `hire-va-4-preview`
  draft). Referral in particular looks like it's showing leftover
  placeholder content rather than real referral-program content — worth
  confirming with whoever owns that page.
- **👀 Needs review**: built with modern ACF Composer blocks, but not yet
  visually verified against the current design language the way vapricing
  just was.
- Case Study and Partners are CPT archives, not WP "pages," included because
  Case Study was recently redesigned and Partners is a v2-only addition not
  yet on production.

## Review notes

### Affiliate Program (reviewed)
- **Fixed**: the `booking-footer` block's "Headline" ACF field was dead code
  — the Blade view hardcoded its default text and ignored the field
  entirely, so any page-level headline override (like this page's "Let's
  map out your partnership strategy together.") silently did nothing. Fixed
  in `BookingFooterBlock.php` + `booking-footer.blade.php`. This affects
  every page using that block, not just this one — worth a quick check on
  other pages that override it.
- **Not fixed (flagged only)**: the "How It Works" section shows the same
  "No Earning Caps / High Conversion / Simple Payouts" cards duplicated
  from the section above, instead of real steps. Confirmed this exact
  duplication already exists on production — it's a content issue, not a
  migration bug, so left alone pending a copy decision.
