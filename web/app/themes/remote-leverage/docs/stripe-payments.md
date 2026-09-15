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
| `app/Application/Http/Controllers/StripeWebhookController.php` | Signature verification, enrichment, forwarding |

## Telemetry dropped in the port

The legacy client script fired PostHog `capture`/`identify`, Customer.io `cioanalytics.track`,
and POSTed every step of the funnel to `/wp-json/rl/v1/log` (the plugin's `LoggingProvider`
table). v2 has no `LoggingProvider` and no equivalent endpoint, and inventing an
unauthenticated write endpoint on the money path to replace it would be a poor trade. All of it
was removed rather than stubbed.

What replaces it: `PaymentIntentController` logs every intent creation and every failure, and
`StripeWebhookController` logs and forwards every successful payment. That is the
authoritative record. If client-side funnel attribution is wanted back, attach it to the
`window.posthog` instance `app.js` already loads — do not add a logging endpoint.

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
