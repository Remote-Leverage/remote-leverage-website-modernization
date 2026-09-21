#!/usr/bin/env python3
"""Merge GitHub Actions secrets into the environment's Secrets Manager JSON.

Reads either GITHUB_SECRETS_JSON (toJSON(secrets) from Actions) or individual
environment variables. Empty values are skipped so a missing GitHub secret
does not blank a key that already exists in Secrets Manager.

Does not touch DB_* / WP_HOME / WP_SITEURL — those stay on the ECS task.

Usage:
  APP_SECRET_ARN=arn:aws:secretsmanager:... AWS_REGION=us-east-1 \\
    GITHUB_SECRETS_JSON='{"STRIPE_KEY":"..."}' \\
    ./scripts/sync-app-secrets-from-env.py
"""

from __future__ import annotations

import json
import os
import subprocess
import sys

ALLOWLIST = [
    "AUTH_KEY",
    "SECURE_AUTH_KEY",
    "LOGGED_IN_KEY",
    "NONCE_KEY",
    "AUTH_SALT",
    "SECURE_AUTH_SALT",
    "LOGGED_IN_SALT",
    "NONCE_SALT",
    "APP_KEY",
    "ACF_PRO_KEY",
    # Scheduling — Calendly
    "CALENDLY_API_KEY",
    "CALENDLY_API_KEY_2",
    "CALENDLY_API_KEY_3",
    "CALENDLY_API_KEY_4",
    "CALENDLY_USER_URI",
    "CALENDLY_DEFAULT_EVENT_TYPE",
    "CALENDLY_T10_EVENT_TYPE",
    "CALENDLY_T0_EVENT_TYPE",
    "CALENDLY_LIVE_CALL_EVENT_TYPE",
    # Without this the Calendly webhook returns 503 and processes nothing.
    "CALENDLY_WEBHOOK_SIGNING_KEY",
    # Scheduling — Google Calendar
    "GOOGLE_CALENDAR_CLIENT_ID",
    "GOOGLE_CALENDAR_CLIENT_SECRET",
    "GOOGLE_CALENDAR_REFRESH_TOKEN",
    "GOOGLE_CALENDAR_ID",
    # Tracking
    # Meta Conversions API — without this the server-side Lead never sends and Ads Manager
    # reports 0 conversions, which is the failure this was added to fix (2026-09-18).
    "META_CAPI_ACCESS_TOKEN",
    "CUSTOMERIO_SITE_ID",
    "CUSTOMERIO_API_KEY",
    # Browser CDP snippet — a different credential from CUSTOMERIO_SITE_ID.
    "CUSTOMERIO_CDP_WRITE_KEY",
    "POSTHOG_API_KEY",
    "POSTHOG_HOST",
    # Marketing cost alert — the warehouse.
    #
    # Spend, channel attribution and the booking counts that divide into them come from the data
    # team's BigQuery view. The whole service account is one JSON blob rather than a private key
    # split across variables: a PEM contains newlines, and a newline in an ECS task definition
    # value works locally and produces an opaque OpenSSL error in production.
    "BIGQUERY_CREDENTIALS_JSON",
    "BIGQUERY_PROJECT_ID",
    # Ad platform read credentials. Nothing consumes these any more — the Meta, Google and
    # Microsoft clients were deleted when the warehouse landed, because two sources for one number
    # is how a Slack card and a dashboard start disagreeing. Kept on the allowlist so that any
    # already set in an environment are not stranded, and so a future diagnostic comparing the
    # warehouse against a platform directly has somewhere to resolve them from.
    "META_ADS_ACCESS_TOKEN",
    "META_ADS_ACCOUNT_ID",
    "GOOGLE_ADS_DEVELOPER_TOKEN",
    "GOOGLE_ADS_CLIENT_ID",
    "GOOGLE_ADS_CLIENT_SECRET",
    "GOOGLE_ADS_REFRESH_TOKEN",
    "GOOGLE_ADS_CUSTOMER_ID",
    "GOOGLE_ADS_LOGIN_CUSTOMER_ID",
    "MICROSOFT_ADS_DEVELOPER_TOKEN",
    "MICROSOFT_ADS_CLIENT_ID",
    "MICROSOFT_ADS_CLIENT_SECRET",
    "MICROSOFT_ADS_REFRESH_TOKEN",
    "MICROSOFT_ADS_CUSTOMER_ID",
    "MICROSOFT_ADS_ACCOUNT_ID",
    # Operational switches for the alert. Not secrets, but this script is the only path an
    # environment variable has into a running task, and a kill switch that needs a code change is
    # not a kill switch.
    "MARKETING_COST_ALERT_ENABLED",
    "MARKETING_COST_ALERT_CHANNEL",
    "MARKETING_TARGET_CPB",
    "MARKETING_TARGET_CPQB",
    # Payments — Stripe
    "STRIPE_KEY",
    "STRIPE_SECRET",
    "STRIPE_TEST_KEY",
    "STRIPE_TEST_SECRET",
    # Without this the Stripe webhook returns 503 and processes nothing.
    "STRIPE_WEBHOOK_SECRET",
    "STRIPE_CONNECT_CLIENT_ID",
    "STRIPE_WEBHOOK_FORWARD_URL",
    # Lead / CRM
    "ZEROBOUNCE_API_KEY",
    "HUBSPOT_ACCESS_TOKEN",
    "HUBSPOT_PORTAL_ID",
    "SLACK_BOT_TOKEN",
    "SLACK_CHANNEL",
    "SLACK_SIGNING_SECRET",
    "SLACK_WEBHOOK_URL",
    "LEAD_WEBHOOK_URL",
    "REFERRAL_WEBHOOK_URL",
    # Monitoring
    "SENTRY_LARAVEL_DSN",
    # AI
    "GEMINI_API_KEY",
    # Legacy browser tools (config/job-widget.php) — the OpenAI proxy behind the vastore5 job
    # description generator and the other Elementor-era tool pages. Unset means the REST routes
    # are never registered and every one of those widgets stays dark, so this is the one key
    # that turns them on. Same variable config/ai.php reads; there is deliberately not a second.
    "OPENAI_API_KEY",
    # The proxy's kill switch and its rate-limit ceiling, on the same reasoning as the marketing
    # alert switches above: this script is the only path an environment variable has into a
    # running task, and a limit that needs a deploy to loosen is no use on the day a real
    # visitor is being turned away by it.
    "JOB_WIDGET_PROXY_ENABLED",
    "JOB_WIDGET_RATE_LIMIT",
    # ai-content-agent capability flags (config/ai-wordpress.php). Not secrets, but they ride
    # with the rest of the container env, and ContentAgentProvisioner reconciles against them
    # on every deploy — so leaving one unset actively revokes the capability rather than
    # leaving an earlier grant in place.
    "AI_AGENT_CAN_PUBLISH",
    "AI_AGENT_CAN_EDIT_PUBLISHED",
    "AI_AGENT_CAN_READ_LEADS",
    "AI_AGENT_CAN_UPLOAD_MEDIA",
    # Mail
    "MAIL_HOST",
    "MAIL_PORT",
    "MAIL_USERNAME",
    "MAIL_PASSWORD",
    "MAIL_FROM_ADDRESS",
    "MAIL_FROM_NAME",
]

# Deliberately NOT on the allowlist, so they are not resurrected by a stale GitHub secret:
#   REFERRAL_WEBHOOK_SECRET, NOTION_API_KEY, NOTION_PARTNERS_DATABASE_ID,
#   GOOGLE_OAUTH_CLIENT_ID, GOOGLE_OAUTH_CLIENT_SECRET
#     — nothing reads any of these; see docs/configuration.md "Keys removed from .env".
#   STRIPE_DEFAULT_THANKYOU_URL
#     — PaymentGatewayBlock defaults to home_url() of the thank-you page, which is correct on
#       every host. Setting it per environment is how a local .test URL once reached staging.
#   DB_*, WP_HOME, WP_SITEURL
#     — supplied by the ECS task definition, per this script's docstring.


def incoming_secrets() -> dict[str, str]:
    raw = os.environ.get("GITHUB_SECRETS_JSON", "").strip()
    parsed: dict[str, str] = {}
    if raw:
        loaded = json.loads(raw)
        if not isinstance(loaded, dict):
            raise SystemExit("GITHUB_SECRETS_JSON must be a JSON object")
        for key, value in loaded.items():
            if value is None:
                continue
            parsed[str(key)] = str(value)
    for key in ALLOWLIST:
        if key in parsed:
            continue
        value = os.environ.get(key)
        if value is not None:
            parsed[key] = value
    return parsed


def main() -> int:
    secret_arn = os.environ.get("APP_SECRET_ARN")
    region = os.environ.get("AWS_REGION", "us-east-1")
    if not secret_arn:
        sys.stderr.write("APP_SECRET_ARN is required\n")
        return 1

    source = incoming_secrets()
    fetched = subprocess.check_output(
        [
            "aws",
            "secretsmanager",
            "get-secret-value",
            "--region",
            region,
            "--secret-id",
            secret_arn,
            "--query",
            "SecretString",
            "--output",
            "text",
        ],
        text=True,
    )
    payload = json.loads(fetched)
    updated: list[str] = []
    skipped_empty: list[str] = []
    for key in ALLOWLIST:
        value = source.get(key)
        if value is None or value == "":
            skipped_empty.append(key)
            continue
        if payload.get(key) != value:
            updated.append(key)
        payload[key] = value

    if not updated:
        print("Secrets Manager already matches GitHub (no keys changed)")
        return 0

    subprocess.run(
        [
            "aws",
            "secretsmanager",
            "put-secret-value",
            "--region",
            region,
            "--secret-id",
            secret_arn,
            "--secret-string",
            json.dumps(payload),
        ],
        check=True,
    )
    print(f"Updated {len(updated)} keys in Secrets Manager: {', '.join(updated)}")
    print(f"Left unchanged (missing or empty in GitHub): {len(skipped_empty)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
