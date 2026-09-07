# ADR-0006: Parallel Staging with Zero-Downtime DNS Cutover

- Status: Accepted (documented as-is from `README.md`)
- Date: 2026-09-06 (recorded — original decision predates this record)
- Deciders: Adrián Salvatori (original author)

## Context

Migrating `remoteleverage.com` off Elementor risks breaking referral
attribution, lead-generation funnels, telemetry pixels, and SEO rankings if
done carelessly (`README.md` §7, Business Continuity & Risk Mitigation
table).

## Decision

Build the new platform in parallel on a staging environment, then perform a
single staged production cutover (`README.md` §1 subtitle, §6 Sprint 3, §9):

- **Staging deployment**: deploy with Bedrock `.env` secrets, run
  `wp acorn migrate`, build production assets (`npm run build`).
- **Staged content cutover**: instant cutover for VA Guides & Blog archives
  (per ADR-0005's `single.blade.php`), then review of Homepage and primary
  marketing landing pages.
- **Performance benchmarking**: PageSpeed Insights on staging, target
  Mobile 96+, LCP < 1.2s, CLS 0.00.
- **Production DNS cutover**: flip DNS to the modern platform; validate
  SSL, redirects, and transactional email deliverability.

Risk-specific mitigations recorded in §7:
- `ReferralAttributionMiddleware` preserves the exact cookie schema
  (`rl_ref`, 90-day persistence) and database structure to avoid
  unattributed sales/commission disputes.
- Gravity Forms + GP Advanced Phone Field preserved; Livewire booking
  wizard includes retry logic and fallback Google Meet/email routing if
  Calendly API limits are reached.
- GTM tags, LinkedIn Insight, Meta Pixels, and Customer.io pipelines
  centralized in `TrackingServiceProvider`, validated in staging first.
- Identical permalink structures, automated OpenGraph/Yoast SEO schema
  pass-through, canonical URL preservation, and mapped 301 redirects for
  retired testing variants.
- Visual regression snapshot tests against the live site for design token
  parity.

## Tradeoffs & Impact on Prior Decisions

- Directly depends on ADR-0001 (clean-slate rebuild) — a parallel-staging
  cutover is only meaningful because the new codebase is being built
  independently of the live site rather than in-place.
- Depends on ADR-0004's `AttributionEngine`/`ReferralAttributionMiddleware`
  and ADR-0005's `single.blade.php`/`index.blade.php` as the concrete
  mechanisms the mitigation table relies on.

## Consequences

- Cutover is a single event (DNS flip) preceded by staging validation,
  rather than a gradual, reversible rollout — the proposal does not
  describe a per-route or percentage-based rollout mechanism, only
  staging-then-flip.
- Expected outcomes are quantified in §8: Mobile PageSpeed 42→96+, LCP
  4.8s→<1.2s, CLS 0.28→0.00, TBT 850ms→<50ms, asset payload 4.2MB→<450KB,
  active plugins 27→~10.
