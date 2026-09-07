# Remote Leverage - End-to-End Tracking & Webhook QA Runbook

This runbook outlines standard verification procedures for the Remote Leverage referral attribution engine, analytics subscribers, and third-party webhook integrations (Stripe Connect, Calendly, Customer.io, PostHog).

---

## 1. Referral Attribution & Cookie QA

### 1.1 Query Parameter Priority & Sanitization
The `AttributionEngine` evaluates referral attribution using the following priority hierarchy:
1. Direct URL query parameter: `via`
2. Secondary query parameter: `ref`
3. Fallback query parameter: `r`
4. Persistent browser cookie: `rl_referrer`
5. Referer header query string: `HTTP_REFERER`

**Manual Verification Steps:**
1. Open an Incognito/Private browser window.
2. Navigate to: `https://remoteleverage.com/?via=demo-partner`
3. In Developer Tools -> Application -> Cookies -> `remoteleverage.com`:
   - Verify `rl_referrer` cookie is created with value `demo-partner`.
   - Verify expiration date is set to 60 days in the future (or configured `rl_ref_cookie_days`).
   - Verify `SameSite=Lax` and `Secure` attributes are set.
4. Navigate to an internal page (e.g. `/partners/` or `/book-consultation`).
5. Verify the cookie persists and is accessible across all subpaths.

### 1.2 24-Hour Click Deduplication
To prevent click spamming and referral inflation, clicks from the same IP address for a specific partner are deduplicated over a 24-hour rolling window.

**Database Verification:**
```sql
-- Check recorded clicks for partner
SELECT id, referrer_user_id, ip_address, created_at
FROM wp_rl_referral_clicks
WHERE referrer_user_id = 12
ORDER BY created_at DESC;
```
- First visit: A new record is inserted into `wp_rl_referral_clicks`.
- Subsequent visits within 24 hours: `AttributionEngine::isRecentClick()` returns `true`, skipping duplicate row insertion while still maintaining the session cookie.

---

## 2. Lead Capture & Tracking QA

### 2.1 Gravity Forms to Customer.io Identification
When any lead form (e.g., Executive Assistant inquiry, Partner application) is submitted:
1. `GravityFormsSubmissionSubscriber::handleSubmission()` intercepts `gform_after_submission`.
2. Extracts customer email, full name, and active `rl_ref` referral cookie.
3. Dispatches `UserProfileData` to `CustomerIOClient::identify()`.
4. Dispatches `AnalyticsEventData` (`"Form Submitted"`) to PostHog and internal event bus.

**Verification Checklist:**
- [ ] Submit form at `/book-consultation`.
- [ ] Open Customer.io People dashboard -> Search by submitted email.
- [ ] Verify profile traits include:
  - `email`: user's email
  - `name`: user's name
  - `referral_code`: attributed partner slug (if present)
  - `source_form`: Form title
- [ ] Open PostHog Events dashboard -> Search for event `Form Submitted`.

---

## 3. Stripe Connect Webhooks QA

The `StripeWebhookController` listens at `POST /api/webhooks/stripe`.

### 3.1 Local & Staging Simulation with Stripe CLI
```bash
# 1. Forward webhooks to local/staging endpoint
stripe listen --forward-to https://staging.remoteleverage.com/api/webhooks/stripe

# 2. Trigger account.updated (payouts enabled)
stripe trigger account.updated

# 3. Trigger transfer.paid
stripe trigger transfer.paid

# 4. Trigger transfer.failed
stripe trigger transfer.failed
```

### 3.2 Expected Database Outcomes:
- **`account.updated` (payouts_enabled = true)**:
  `wp_rl_partners.status` updates from `pending` -> `active`.
- **`account.updated` (payouts_enabled = false)**:
  `wp_rl_partners.status` updates to `pending`.
- **`transfer.paid`**:
  `wp_rl_payouts.status` updates to `completed`.
- **`transfer.failed`**:
  `wp_rl_payouts.status` updates to `failed` with error description in `notes`.

---

## 4. Calendly Webhooks QA

The `CalendlyWebhookController` listens at `POST /api/webhooks/calendly`.

### 4.1 Sample Test Payload (`invitee.created`)
```bash
curl -X POST https://staging.remoteleverage.com/api/webhooks/calendly \
  -H "Content-Type: application/json" \
  -d '{
    "event": "invitee.created",
    "payload": {
      "invitee": {
        "name": "Sarah Connor",
        "email": "sarah@resistance.org"
      },
      "event_type": {
        "name": "45-Min Remote Talent Architecture Consultation"
      },
      "event": {
        "start_time": "2026-09-15T15:00:00.000000Z"
      }
    }
  }'
```

**Expected Result:**
- HTTP status `200 OK` with `{"status": "received"}`.
- Dispatches event `"Consultation Scheduled"` to PostHog and logs to `acorn.log`.
