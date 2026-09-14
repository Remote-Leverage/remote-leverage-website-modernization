# Tracking domain

`app/Domains/Tracking` — behavioural analytics and server-side feature flags.

Replaces the `rl-customer-io` and `rl-posthog-feature-flags` plugins.

---

## Why it exists

The two legacy plugins each had their own snippet injection, their own identify call and their own idea of who the user was. A lead could end up identified in one and anonymous in the other. This domain dispatches to both from one place, off one event, with one profile shape.

## Dual dispatch

```mermaid
flowchart LR
    LC["LeadCreated"] --> HL["HandleLeadCreatedForTracking"]
    HL --> UP["UserProfileData"]
    UP --> PH["PostHogClient::capture / identify"]
    UP --> CIO["CustomerIOClient::identify"]

    APP["Any domain action"] --> RBE["RecordBehaviorEventAction"]
    RBE --> AED["AnalyticsEventData"]
    AED --> PH
    AED --> CIO

    FLAG["EvaluateVariantAction"] --> PHF["PostHogClient::isFeatureEnabled"]
    PHF -->|"API unreachable"| CK["cookie fallback"]
```

Both destinations receive the same DTO. `RecordBehaviorEventAction` is the only sanctioned way to emit an analytics event — calling a gateway directly bypasses the dual dispatch and the audit log.

## The classes

| Class | Does |
| :--- | :--- |
| `RecordBehaviorEventAction` | Takes `AnalyticsEventData`, dispatches to PostHog and Customer.io |
| `EvaluateVariantAction` | `execute($flagKey, $distinctId = null, $properties = [])` — server-side flag evaluation with a cookie fallback when PostHog is unreachable |
| `PostHogClient` | `capture()`, `isFeatureEnabled()` |
| `CustomerIOClient` | `identify()`, `track()` |
| `HandleLeadCreatedForTracking` | Listener — identifies the person on both platforms the moment a lead is captured |
| `AnalyticsEventData`, `UserProfileData` | The DTOs |
| `TrackingHooks` (`app/Infrastructure/WordPress/Hooks`) | Injects the PostHog snippet in `<head>` and the Customer.io snippet in the footer |

## Configuration

| Variable | Used by |
| :--- | :--- |
| `POSTHOG_API_KEY`, `POSTHOG_HOST` | `PostHogClient`, front-end snippet |
| `CUSTOMERIO_SITE_ID`, `CUSTOMERIO_API_KEY`, `CUSTOMERIO_APP_API_KEY` | `CustomerIOClient` |

GTM, LinkedIn Insight and Meta Pixel are **not** injected by this domain. Google Site Kit is installed (`wp-plugin/google-site-kit`) and ships the GTM `<head>` snippet and `wp_body_open` noscript once a container is connected; LinkedIn and Meta are added as tags inside that container. This was a deliberate rescope of WR-99 from code to configuration — the remaining work is admin setup, not engineering.

## Verifying it

Browser console on any front-end page:

```js
window.posthog   // initialised PostHog client
window._cio      // Customer.io tracking array
```

Network tab, filtered to `posthog` or `customer.io`: calls to `/e/` and `/api/v1/customers/` should fire with a distinct ID.

## Tests

`tests/Unit/ActionsTest.php` (flag evaluation with cookie fallback) and `tests/Feature/TrackingSubscribersTest.php` (auto-identify on `LeadCreated`).

## Notes

- `PostHogRedirectMiddleware` (`app/Application/Http/Middleware`) exists for flag-driven redirects on Acorn-routed requests. It does not apply to WordPress page requests — see [architecture.md §2](../architecture.md#2-two-request-paths).
- Both gateways are called synchronously inside the request that captured the lead. See [architecture.md §4](../architecture.md#listeners-are-synchronous).
