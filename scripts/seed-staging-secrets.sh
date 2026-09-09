#!/usr/bin/env python3
"""Seed /wordpress-staging/app in Secrets Manager from the local env file.

Does not upload local DB_* / WP_HOME / WP_SITEURL. Staging Stripe uses test keys.

Usage:
  APP_SECRET_ARN=arn:aws:secretsmanager:... AWS_REGION=us-east-1 \\
    ./scripts/seed-staging-secrets.sh [path-to-env]

Default env path: repo-root file named env
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / "env"

SKIP = {
    "DB_NAME",
    "DB_USER",
    "DB_PASSWORD",
    "DB_HOST",
    "DB_PREFIX",
    "DATABASE_URL",
    "WP_ENV",
    "WP_HOME",
    "WP_SITEURL",
    "WP_DEBUG_LOG",
    "WP_ENVIRONMENT_TYPE",
    "BARBA_ENABLED",
    "LOCOMOTIVE_ENABLED",
    "PRISM_SERVER_ENABLED",
}

LINE = re.compile(r"^([A-Z0-9_]+)=(.*)$")


def unquote(value: str) -> str:
    value = value.strip()
    if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
        return value[1:-1]
    return value


def parse_env(path: Path) -> dict[str, str]:
    parsed: dict[str, str] = {}
    for raw in path.read_text().splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        match = LINE.match(line)
        if not match:
            continue
        parsed[match.group(1)] = unquote(match.group(2))
    return parsed


def main() -> int:
    secret_arn = os.environ.get("APP_SECRET_ARN")
    region = os.environ.get("AWS_REGION", "us-east-1")
    if not secret_arn:
        sys.stderr.write("APP_SECRET_ARN is required\n")
        return 1
    if not ENV_PATH.is_file():
        sys.stderr.write(f"env file not found: {ENV_PATH}\n")
        return 1

    source = parse_env(ENV_PATH)
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
    for key, value in source.items():
        if key in SKIP:
            continue
        payload[key] = value

    if source.get("STRIPE_TEST_KEY"):
        payload["STRIPE_KEY"] = source["STRIPE_TEST_KEY"]
    if source.get("STRIPE_TEST_SECRET"):
        payload["STRIPE_SECRET"] = source["STRIPE_TEST_SECRET"]
    payload.pop("STRIPE_TEST_KEY", None)
    payload.pop("STRIPE_TEST_SECRET", None)

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
    print(f"Seeded {len(payload)} keys into {secret_arn}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
