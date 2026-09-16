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
    "CALENDLY_API_KEY",
    "CALENDLY_API_KEY_2",
    "CALENDLY_API_KEY_3",
    "CALENDLY_API_KEY_4",
    "CALENDLY_USER_URI",
    "CALENDLY_DEFAULT_EVENT_TYPE",
    "CALENDLY_T10_EVENT_TYPE",
    "CALENDLY_T0_EVENT_TYPE",
    "GOOGLE_CALENDAR_CLIENT_ID",
    "GOOGLE_CALENDAR_CLIENT_SECRET",
    "GOOGLE_OAUTH_CLIENT_ID",
    "GOOGLE_OAUTH_CLIENT_SECRET",
    "GOOGLE_CALENDAR_REFRESH_TOKEN",
    "GOOGLE_CALENDAR_ID",
    "CUSTOMERIO_SITE_ID",
    "CUSTOMERIO_API_KEY",
    "CUSTOMERIO_APP_API_KEY",
    "STRIPE_KEY",
    "STRIPE_SECRET",
    "STRIPE_TEST_KEY",
    "STRIPE_TEST_SECRET",
    "STRIPE_WEBHOOK_SECRET",
    "STRIPE_CONNECT_CLIENT_ID",
    "GEMINI_API_KEY",
    "REFERRAL_WEBHOOK_SECRET",
    "ZEROBOUNCE_API_KEY",
    "POSTHOG_API_KEY",
    "POSTHOG_HOST",
    "NOTION_API_KEY",
    "NOTION_PARTNERS_DATABASE_ID",
    "SLACK_WEBHOOK_URL",
    "LEAD_WEBHOOK_URL",
    "MAIL_HOST",
    "MAIL_PORT",
    "MAIL_USERNAME",
    "MAIL_PASSWORD",
    "MAIL_FROM_ADDRESS",
    "MAIL_FROM_NAME",
]


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
