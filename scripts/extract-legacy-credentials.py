#!/usr/bin/env python3
"""Extract the legacy integration credentials v2 still needs, from the production DB dump.

Scope is deliberately narrow: it reads ONE file, matches an explicit allowlist of
`wp_options` names, and prints only those rows. It cannot be pointed at another file and
has no pattern argument, so allowlisting it in .claude/settings.json grants exactly this
lookup rather than general search over the filesystem.

Why this exists: the migration needs credentials that live only in the legacy site's
options table (the Slack bot token in particular — production dispatches lead alerts
through the Slack Bot API to a channel, not through an incoming webhook, and the webhook
option is empty). See docs/domains/tracking.md and the Slack section of
docs/cutover-decisions.md.

Usage:
  ./scripts/extract-legacy-credentials.py [path-to-dump]

Default dump path: the most recent ~/Downloads/*_full_*/mwp_db/*.sql
"""

from __future__ import annotations

import glob
import os
import re
import sys

# Exact option names only — no prefixes, no wildcards, no caller-supplied patterns.
#
# Corrected 2026-09-16 after the first run returned "not present" for most of these. The
# guessed `rl_*` names did not exist; the real ones were read out of the legacy plugin
# source (`rl-elementor-blocks/src`), which is code rather than credential data:
#   - there is NO rl_jlc slack *token* option at all. JoinLiveCallIntegration posts to
#     slack.com/api/chat.postMessage with a bearer token it borrows from the
#     **gravityformsslack** add-on's own settings, so the token lives there.
#   - Customer.io is `rl_cio_write_key` / `rl_cio_region`, not `rl_customerio_*`.
#   - HubSpot likewise belongs to the **gravityformshubspot** add-on (OAuth).
WANTED = [
    # Slack — production dispatches through the Bot API to a channel; the webhook is empty.
    "rl_jlc_slack_channel",
    "rl_jlc_slack_webhook_url",
    "gravityformsaddon_gravityformsslack_settings",
    # Customer.io — the browser CDP source.
    "rl_cio_write_key",
    "rl_cio_region",
    # HubSpot — the Gravity Forms add-on's OAuth credential.
    "gravityformsaddon_gravityformshubspot_settings",
]

MAX_VALUE_BYTES = 400


def default_dump() -> str | None:
    matches = glob.glob(os.path.expanduser("~/Downloads/*_full_*/mwp_db/*.sql"))
    return max(matches, key=os.path.getmtime) if matches else None


def main() -> int:
    path = sys.argv[1] if len(sys.argv) > 1 else default_dump()

    if not path or not os.path.isfile(path):
        sys.stderr.write(f"dump not found: {path}\n")
        return 1

    # One pass, line by line: the dump is ~350MB and must not be read into memory.
    patterns = {name: re.compile(rf"'{re.escape(name)}','((?:[^']|\\')*)'") for name in WANTED}
    found: dict[str, str] = {}

    with open(path, encoding="utf-8", errors="replace") as handle:
        for line in handle:
            for name, pattern in patterns.items():
                if name in found:
                    continue
                match = pattern.search(line)
                if match:
                    found[name] = match.group(1)
            if len(found) == len(WANTED):
                break

    for name in WANTED:
        if name not in found:
            print(f"  {name:<32} — not present in dump")
        elif found[name] == "":
            print(f"  {name:<32} — present but EMPTY")
        else:
            value = found[name][:MAX_VALUE_BYTES]
            print(f"  {name:<32} = {value}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
