#!/usr/bin/env bash
# Build the release artifact pair for this repo: a full SQL dump and a zip of
# web/app/uploads, written to releases/<tag>/.
#
# The pair is deliberately shaped for scripts/import-production-content.sh: a
# plain (ungzipped) db.sql, and an uploads.zip with a top-level uploads/
# directory. Do not "tidy" either of those without changing the importer.
#
# Usage (from anywhere in the repo):
#   npm run release                 # from web/app/themes/remote-leverage
#   ./scripts/release.sh            # directly
#   ./scripts/release.sh --force    # overwrite an existing releases/<tag>/
#
# Environment:
#   RELEASE_TAG        name the output directory explicitly (default: see below)
#   RELEASE_ZIP_LEVEL  zip -N compression level (default 1)
set -euo pipefail

# Resolve both to absolute paths before cd'ing: npm invokes this script by a
# relative path, which stops resolving the moment we leave the theme directory.
SELF="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$(basename "${BASH_SOURCE[0]}")"
ROOT="$(dirname "$(dirname "$SELF")")"
cd "$ROOT"

FORCE=0
for arg in "$@"; do
  case "$arg" in
    -f|--force) FORCE=1 ;;
    -h|--help) sed -n '2,16p' "$SELF" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "unknown argument: $arg" >&2; exit 2 ;;
  esac
done

# Release tags are v-YYYYMMDD-vN (see CLAUDE.md). If HEAD is already tagged,
# name the directory after that tag; otherwise reserve the next free N for
# today so two runs on the same day never collide. This never creates a tag --
# tagging stays a separate, deliberate step.
resolve_tag() {
  local today exact n
  # --points-at, not `git describe --exact-match`: HEAD can carry several tags
  # (the legacy v2.x.x series overlaps), and describe picks one arbitrarily.
  exact="$(git tag --points-at HEAD 2>/dev/null | grep -E '^v-[0-9]{8}-v[0-9]+$' | sort -V | tail -1)"
  if [[ -n "$exact" ]]; then
    printf '%s' "$exact"
    return
  fi
  today="$(date +%Y%m%d)"
  n=1
  while git rev-parse -q --verify "refs/tags/v-$today-v$n" >/dev/null \
     || [[ -d "$ROOT/releases/v-$today-v$n" ]]; do
    n=$((n + 1))
  done
  printf 'v-%s-v%s' "$today" "$n"
}

TAG="${RELEASE_TAG:-$(resolve_tag)}"
OUT="$ROOT/releases/$TAG"
ZIP_LEVEL="${RELEASE_ZIP_LEVEL:-1}"

if [[ -d "$OUT" && "$FORCE" -ne 1 ]]; then
  echo "releases/$TAG already exists. Re-run with --force to overwrite, or set RELEASE_TAG." >&2
  exit 1
fi

command -v wp >/dev/null || { echo "wp-cli not found on PATH" >&2; exit 1; }
command -v zip >/dev/null || { echo "zip not found on PATH" >&2; exit 1; }
[[ -d "$ROOT/web/app/uploads" ]] || { echo "web/app/uploads not found" >&2; exit 1; }

mkdir -p "$OUT"
echo "Release $TAG -> releases/$TAG ($(git rev-parse --short HEAD))"

# --add-drop-table so the dump is importable over an existing database.
echo "  dumping database ..."
wp db export "$OUT/db.sql" --add-drop-table --quiet

# Level 1: uploads are 99% already-compressed JPEG/PNG/WebP, so the default
# level buys a rounding error of size for several times the wall clock.
echo "  zipping uploads (771MB-ish, this takes a minute) ..."
rm -f "$OUT/uploads.zip"
(cd "$ROOT/web/app" && zip -r -q -X "-$ZIP_LEVEL" "$OUT/uploads.zip" uploads -x '*/.DS_Store' '*/.gitkeep')

echo
echo "Release $TAG ready:"
du -h "$OUT/db.sql" "$OUT/uploads.zip" | sed 's|'"$ROOT"'/|  |'
