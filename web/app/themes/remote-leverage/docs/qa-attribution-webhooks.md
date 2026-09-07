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

## 2. Lead Capture & Event-Driven Telemetry QA (ADR-0008)

### 2.1 Lead Capture to Customer.io & HubSpot Identification
When any prospective lead submits the `MultistepBookingWizard` or consultation form:
1. `CaptureLeadAction::execute()` validates the contact details (including international phone E.164 standardization via `PhoneValidationService`).
2. `AttributionEngine::resolveLeadSource()` stamps canonical `source_type` (`ad`, `organic`, `referral_hub`, `partnership`) and `source_id`.
3. Dispatches `LeadCreated` lifecycle event.
4. Stage 1 write logs the dispatch to `rl_lead_activity_logs`.
5. `HandleLeadCreatedForTracking` intercepts `LeadCreated`, identifies the profile in Customer.io, records `Lead Captured` event in PostHog, and performs Stage 2 write to `rl_lead_activity_logs`.
6. `HubSpotGateway` synchronizes the contact with HubSpot CRM and logs the consumption outcome.
7. `HandleLeadCreatedForBooking` schedules the meeting in Calendly/Google Calendar and dispatches `LeadBookingCompleted`.

**Verification Checklist:**
- [ ] Submit consultation form in `MultistepBookingWizard` (`/book-consultation`).
- [ ] Query database:
  ```sql
  SELECT id, uuid, email, phone, source_type, source_id, status FROM wp_rl_leads ORDER BY id DESC LIMIT 1;
  SELECT * FROM wp_rl_lead_activity_logs WHERE lead_id = (SELECT MAX(id) FROM wp_rl_leads);
  ```
- [ ] Verify `stage = 'dispatch'` and `stage = 'consumption'` dual-logging records exist.
- [ ] Open Customer.io People dashboard -> Search by submitted email.
- [ ] Verify profile traits include:
  - `email`: user's email
  - `name`: user's full name
  - `source_type`: e.g. `referral_hub`, `partnership`, `ad`, or `organic`
  - `source_id`: partner slug or campaign ID
  - `status`: `booked`
- [ ] Open PostHog Events dashboard -> Search for event `Lead Captured`.

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
