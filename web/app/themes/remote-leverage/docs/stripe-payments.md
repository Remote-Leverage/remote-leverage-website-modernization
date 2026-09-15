# Stripe payments (embedded card checkout)

The `acf/payment-gateway` block reproduces production's refundable-deposit checkout on
`/virtual-assistant-hiring-manager-refundable-deposit/` (page 38897). It is a port of the
`rl_payment_gateway` Elementor widget from the `rl-elementor-blocks` plugin.

This is separate from Stripe **Connect**, which pays referrers out (`StripeConnectGateway`,
`app/Domains/Referral`). They share a Stripe account and the same webhook endpoint, nothing else.

## Credentials — you have to move these by hand

> **The live key values are not in this repository and were not read during the port.**
> Credential access was blocked in the sandbox this work was done in. Copy each value from
> rl-testing's WordPress options table (`wp option get <option>` on that install, or
> **Remote Leverage → Settings → Stripe** in its admin) into the Bedrock root `.env`.
> Nothing below has been verified against a live key.

| `.env` variable | Legacy WP option | What it is |
| :--- | :--- | :--- |
| `STRIPE_TEST_MODE` | `rl_stripe_test_mode` | `true` selects the test pair, anything else selects live. The option stored `yes`/`no`; this is a boolean (`true`/`false`). |
| `STRIPE_KEY` | `rl_stripe_live_publishable_key` | Live publishable key (`pk_live_…`). Rendered into the page for Stripe Elements. Already present in `.env` for Connect. |
| `STRIPE_SECRET` | `rl_stripe_live_secret_key` | Live secret key (`sk_live_…`). Server-side only. Already present in `.env` for Connect. |
| `STRIPE_TEST_KEY` | `rl_stripe_test_publishable_key` | Test publishable key (`pk_test_…`). Already in `.env`, previously dead. |
| `STRIPE_TEST_SECRET` | `rl_stripe_test_secret_key` | Test secret key (`sk_test_…`). Already in `.env`, previously dead. |
| `STRIPE_WEBHOOK_SECRET` | `rl_stripe_webhook_secret` | Webhook signing secret (`whsec_…`). Already in `.env`. |
| `STRIPE_WEBHOOK_FORWARD_URL` | `rl_stripe_webhook_forward_url` | Where a successful payment is forwarded (n8n / Customer.io onboarding). **New variable.** |
| `STRIPE_DEFAULT_THANKYOU_URL` | `rl_stripe_default_thankyou_url` | Fallback thank-you page when a block leaves `success_url` blank. **New variable.** |

All of them are read through `config/services.php` → `services.stripe.*`. Nothing reads a WP
option, and no key is ever hardcoded.

Checks worth doing after moving the values:

- `STRIPE_KEY` and `STRIPE_SECRET` currently serve Connect. Confirm they are from the same
  Stripe account as the legacy `rl_stripe_live_*` options before reusing them for checkout —
  if the deposit was taken on a different account, the live pair needs to be that account's.
- Whatever `STRIPE_WEBHOOK_FORWARD_URL` points at must accept the payload shape below
  unchanged; the keys are the legacy ones and downstream mappings depend on them.

## How a payment flows

```
acf/payment-gateway block            resources/js/payment-gateway.js
  renders the form,        ───────>    mounts Stripe Elements, posts customer details
  publishable key,                     to POST /api/payments/intent
  post_id + block_index
                                              |
                                              v
                              PaymentIntentController
                                PaymentGatewayBlockResolver  ← resolves the AMOUNT
                                StripePaymentIntentGateway   → POST api.stripe.com/v1/payment_intents
                                              |
                                              v  client_secret
                              stripe.confirmPayment({ return_url })
                                              |
                                              v
                              Stripe → POST /api/webhooks/stripe
                                StripeWebhookController
                                  verify signature → enrich → forward
```

### The amount is resolved server-side, always

`PaymentGatewayBlockResolver` is the only thing that decides what a customer is charged. It
takes the `post_id` the browser posted, loads that post's content, expands pattern references
with `resolve_pattern_blocks()` (v2 pages hold a single `wp:pattern` reference, so the ACF
block is not in raw `post_content`), walks the block tree for `acf/payment-gateway`, and reads
`attrs.data.product_price` / `product_currency` / `payment_description_template`.

If it cannot find the block it returns `null` and the controller answers 422. **There is no
code path that charges an amount supplied by the browser.** This mirrors the legacy plugin,
which scanned Elementor's `_elementor_data` for the widget by id.

When several payment blocks sit on one page, `block_index` (the occurrence order of the block
on the page) selects between them. With one block — the normal case — the index is ignored.

### Webhook

`POST /api/webhooks/stripe` (`StripeWebhookController`) now handles
`payment_intent.succeeded` alongside the existing `account.updated` / `transfer.paid` /
`transfer.failed` Connect events.

Signature verification is HMAC-SHA256 over `"{timestamp}.{raw body}"` against
`STRIPE_WEBHOOK_SECRET`, compared with `hash_equals`, rejecting anything more than 300
seconds old — the same algorithm the legacy handler used, and what Stripe's own libraries do.

> **Fails closed as of 2026-09-15.** The legacy plugin logged a warning and processed the event
> anyway when `STRIPE_WEBHOOK_SECRET` was empty; that parity was dropped, because an unsigned
> endpoint that forwards its input to an onboarding automation can be driven by anyone who knows
> the URL. The controller now returns **503** with no secret configured and **403** on a bad
> signature, and acts on neither.
>
> **Deployment consequence:** an environment without `STRIPE_WEBHOOK_SECRET` set no longer
> processes Stripe events at all — it goes dark rather than going open. Set the secret in every
> environment *before* deploying this, or Connect payout events (`account.updated`,
> `transfer.paid`, `transfer.failed`) will be refused along with the payment events. Stripe
> retries failed deliveries, so events during a brief gap are recoverable, but a long one is not.

**Per environment:** staging uses Stripe **test** keys and a **test-mode** signing secret;
production uses the live Connect pair and its live secret. Stripe issues one signing secret per
endpoint, so these are different values — a secret copied from the other environment fails
verification with a 403.

The same verifier now serves the Calendly webhook, which had **no** signature verification at all:
`App\Application\Http\Support\WebhookSignature`. Calendly signs with the identical scheme under
the `Calendly-Webhook-Signature` header, keyed by `CALENDLY_WEBHOOK_SIGNING_KEY`.

The forwarded payload keys are the legacy set verbatim:

```
event, payment_intent_id, amount (major units), currency, status,
customer_name, customer_email, customer_phone, description,
payment_method_type, card_brand, card_last4,
widget_id, post_id, page_url, stripe_receipt_url, created_at, metadata
```

`card_brand`, `card_last4` and `stripe_receipt_url` are read from `charges.data[0]`, exactly as
the legacy handler did. Stripe removed `charges` from the PaymentIntent object in API versions
after 2022-11-15 in favour of `latest_charge`, so on a newer API version those three fields
arrive empty. This was already true of the legacy plugin and was deliberately not "fixed"
during the port — changing it changes what downstream automations receive. Fixing it properly
means expanding `latest_charge`, which should be a separate, tested change.

## Files

| File | Role |
| :--- | :--- |
| `app/Blocks/PaymentGatewayBlock.php` | ACF block: fields, publishable key, per-page block index |
| `resources/views/blocks/payment-gateway.blade.php` | Checkout markup (`rl-` class names are the JS contract) |
| `resources/js/payment-gateway.js` | Stripe Elements client, loaded on demand from `app.js` |
| `app/Application/Http/Controllers/PaymentIntentController.php` | `POST /api/payments/intent` |
| `app/Domains/Payment/Services/PaymentGatewayBlockResolver.php` | Server-side amount resolution |
| `app/Domains/Payment/Services/StripePaymentIntentGateway.php` | Stripe PaymentIntent API |
| `app/Application/Http/Controllers/StripeWebhookController.php` | Signature verification, enrichment, forwarding, success/failure telemetry |
| `app/Domains/Payment/Data/CheckoutFunnelStep.php` | The funnel steps and their event names, with legacy provenance |
| `app/Domains/Payment/Services/CheckoutTelemetry.php` | Builds the `AnalyticsEventData` and defers dispatch past the response |

## Checkout funnel telemetry

> **Rebuilt 2026-09-15.** The port originally removed the legacy widget's telemetry rather than
> stubbing it; this section previously said so. It is now instrumented again, through the
> Tracking domain. The legacy source it was reconstructed from is
> `rl-elementor-blocks/assets/js/payment-gateway.js` and
> `rl-elementor-blocks/src/Integrations/StripeWebhookHandler.php` — **not** present in this
> repository, but readable in the `rl-testing` install at
> `web/app/plugins/rl-elementor-blocks/`.

Every step becomes an `AnalyticsEventData` handed to
`App\Domains\Tracking\Actions\RecordBehaviorEventAction`, which dual-dispatches to PostHog and
Customer.io — the same path `CalendlyWebhookController` and `HandleLeadCreatedForTracking` use.
There is no second telemetry mechanism.

| Funnel step | Event name | Fired from | Provenance |
| :--- | :--- | :--- | :--- |
| Gateway viewed | `Payment Gateway Viewed` | `resources/js/payment-gateway.js`, IntersectionObserver at 25% | **Proposed.** The legacy widget had no viewed event. |
| Checkout started | `form_started` | same, first `input` on the form | Legacy, verbatim (PostHog only in the legacy widget). |
| Partial email captured | `Payment Partial Email Captured` | same, on email blur/change, with `posthog.identify(email)` | Legacy `payment_partial_email_captured` / `Payment Partial_email_captured`, tidied. |
| Intent created | `Payment Intent Loaded` | `PaymentIntentController::store()` | Legacy `payment_intent_loaded` / `Payment Intent_loaded`, tidied. |
| Payment submitted | `Payment Attempted` | `resources/js/payment-gateway.js`, before `confirmPayment()` | Legacy, **verbatim**. |
| Payment succeeded | `Payment Succeeded` | `StripeWebhookController` (`payment_intent.succeeded`) **and** the client, on redirect return and on inline confirm | Legacy, **verbatim**. |
| Payment failed | `Payment Failed` | `PaymentIntentController` (422/502), `StripeWebhookController` (`payment_intent.payment_failed`), and the client on Stripe.js load failure, intent failure and confirm error | Legacy, **verbatim**. |

Plus one legacy PostHog-only event kept verbatim because production insights are keyed to the
literal string: `payment_stripe_load_failed`, with `correlation_id`, `widget_id`, `post_id`,
`form_type`.

The canonical mapping lives in `App\Domains\Payment\Data\CheckoutFunnelStep`, which also
records each step's `legacyLogEvent()` / `legacyCustomerIoEvent()` / `legacyPostHogEvent()` so a
downstream insight or campaign built on an old name can be remapped deliberately.
`tests/Unit/PaymentCheckoutTelemetryTest.php` covers the mapping and asserts the JS `FUNNEL`
map has not drifted from the PHP enum.

### Two naming decisions worth knowing

- **`form_started` keeps its lowercase legacy spelling.** It was never payment-specific — the
  legacy booking widgets fired it too, and production's funnel report aggregates on the literal
  string (`rl-elementor-blocks/src/Settings.php`, the `SUM(CASE WHEN event_name =
  'form_started' ...)` column). Title-casing it would silently detach the checkout from that
  report.
- **`Payment Attempted` / `Succeeded` / `Failed` are verbatim.** They were already clean Title
  Case in the legacy Customer.io calls and n8n / Customer.io mappings key off those exact
  strings. The two that were artefacts of PHP's `ucfirst()` on a snake_case key
  (`Payment Intent_loaded`, `Payment Partial_email_captured`) were tidied, since nothing
  sensible can be keyed to the underscore.

### Deduplicating success

`Payment Succeeded` fires from three places, discriminated by a `confirmation_source` property:
`stripe_webhook`, `client_redirect`, `client_inline`. All three carry `payment_intent_id`, so
downstream dedupes on that. The webhook one is authoritative — the client events do not fire if
the customer closes the tab, and the client cannot be trusted about money anyway. `source`
(`server` / `client`) separates the halves the same way for `Payment Failed`.

### Event properties

Server-side events carry what the server knows: `widget_id` (the block id, legacy key),
`post_id`, `page_url`, `payment_intent_id`, `amount` (major units), `currency`, `customer_name`,
`customer_phone`, and on failures `failure_stage`, `error`, `error_code`, `decline_code`,
`severity`.

Client-side events additionally carry what only the browser knows, which is what the legacy
`/wp-json/rl/v1/log` table existed to hold: `posthog_session_id`, `posthog_replay_url`,
`utm_source`, `utm_campaign`, `gclid`, `fbclid`, and `duration` (seconds on the form, from a
`sessionStorage` start marker keyed by block id — the legacy `rl_session_start_<id>` key).

Both PostHog and Customer.io are keyed on the **lowercased email** as the distinct id, matching
`HandleLeadCreatedForTracking` and `CalendlyWebhookController`, so a checkout stitches onto the
same person as their lead and booking. With no usable email the distinct id is `anonymous` and
no `email` property is sent.

### `/wp-json/rl/v1/log` was deliberately NOT reinstated

It was registered with `permission_callback => '__return_true'` — an unauthenticated write
endpoint sitting on the money path. Its three jobs are all covered elsewhere:

1. **Funnel breadcrumbs** → PostHog, via the events above.
2. **Attribution and session context** (UTM, gclid, fbclid, PostHog session + replay URL,
   duration) → carried as properties on the events, rather than needing their own indexed
   columns.
3. **Operational record** → `Log::` in `PaymentIntentController` and `StripeWebhookController`,
   which the port already added, and the forwarded webhook payload.

The Tracking-adjacent audit log, `rl_lead_activity_logs` via `LeadActivityLogger`, was checked
and **cannot** host these steps: its `lead_id` is a non-nullable foreign key onto `rl_leads`, and
an anonymous visitor half-way through a checkout has no `Lead` row. Writing one purely to hold a
funnel breadcrumb would also pollute the table that `findRecentBookingForSlot()` reads as the
booking duplicate guard.

### Latency and missing credentials

Nothing here may slow a payment down.

- Client-side events go straight to the PostHog and Customer.io browser SDKs that
  `TrackingHooks` injects. No request to our own server, so no added latency at all. Every call
  is wrapped — an analytics SDK throwing must not take a checkout down.
- Server-side events are dispatched with `->afterResponse()`, the same deferral
  `LeadServiceProvider` / `TrackingServiceProvider` use and for the same reason: no queue worker
  is deployed (`queue.default` is `sync`), so an un-deferred PostHog + Customer.io call pair
  would run inside the customer's request. The closure is `static` and resolves through the
  global `app()` helper so it never captures `$this` — a non-static closure there captures the
  container graph and fatally OOMs inside serializable-closure, silently dropping the work.
- With credentials unset — the normal local state — nothing throws and nothing is logged as an
  error. Verified, not assumed: `PostHogClient::capture()` and `CustomerIOClient::track()` both
  return `false` immediately when their config keys are null, and the browser SDKs are simply
  never injected (`TrackingHooks` returns early), so `window.posthog` / `window.cioanalytics` /
  `window._cio` are undefined and every client call site guards for that.

`TrackingHooks` injects Customer.io's **classic** tracker (`window._cio`); production ran the
CDP snippet (`window.cioanalytics`), which is what the legacy widget called. The client
dispatcher prefers `cioanalytics` and falls back to `_cio`, so it works against either.

## Known gaps

- **Not exercised against Stripe.** No key was available in this environment, so the
  PaymentIntent call, the Elements mount, the redirect return and the webhook were not run
  end to end. Test in `STRIPE_TEST_MODE=true` with a test card before pointing a live page here.
- **No page or pattern ships this block yet** — placement is handled separately.
- **`docs/block-inventory.md` has not been regenerated** (`wp acorn blocks:inventory` needs a
  booted WordPress). Run it so `acf/payment-gateway` appears in the inventory.
- **No automated test** covers `PaymentGatewayBlockResolver` or the webhook's
  `payment_intent.succeeded` path. Both are worth a Pest test; the resolver especially, since
  it is the thing standing between a page and a client-chosen price.
  (`tests/Unit/PaymentCheckoutTelemetryTest.php` covers the funnel event mapping, but it
  deliberately makes no network calls and so proves nothing about delivery.)
- **Telemetry delivery is unverified.** `POSTHOG_API_KEY` and the Customer.io credentials are
  unset in this environment, so no event has been seen to arrive. What is verified is the
  mapping, the no-credentials no-op, and that the browser makes no request to our own server.
  Before trusting the funnel: set the keys, load the deposit page, and confirm
  `Payment Gateway Viewed` → `form_started` → `Payment Attempted` → `Payment Succeeded` land in
  PostHog with a shared `distinct_id`, and that the Customer.io person timeline shows the same.
- **The legacy dashboards are not migrated.** Production's funnel report read the
  `wp_rl_calendly_logs` table that `/wp-json/rl/v1/log` wrote to. That table is not reproduced
  (see above for why), so any saved report or automation reading it directly needs rebuilding
  as a PostHog insight against the event names in the table above.
