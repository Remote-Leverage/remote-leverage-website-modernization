#!/usr/bin/env bash
# Repeatable Lighthouse measurement against a live host, with the variance controls this repo
# has now been bitten by three times.
#
# WHY THIS EXISTS
#
# `docs/performance-baseline.md` Part 1 logged a 58/61/26 performance spread on one unchanged
# page. Part 3 had to re-measure its own "before" column because Part 1 disagreed with it by 3
# points on an unchanged build. Part 6's blocked-vendor arms saw TTFB range from 71ms to
# 20,509ms across five runs of the same URL -- which made every LCP, FCP, TTI and score
# comparison in that set unusable, because a run that missed the CDN is not measuring the thing
# being changed.
#
# A single Lighthouse run against production is not evidence. This script makes one that is:
#
#   1. N runs, median reported, every run's value printed so variance stays visible.
#   2. Every run's TTFB is checked against a band. Runs outside it are DISCARDED AND RETRIED,
#      because they measured a cold origin rather than the change under test. The count of
#      discards is reported -- it is a finding in its own right, not just bookkeeping.
#   3. The CDN is warmed immediately before each run, so arms start comparable. Note this
#      deliberately measures the CACHED path; see --ttfb-max to widen or disable the band.
#   4. Optional blocked-vendor "ceiling" arms, so the theoretical win from removing a third
#      party can be re-measured after each change and compared against what was actually banked.
#
# Only TBT is reliably comparable across arms -- it is main-thread CPU and is largely independent
# of how long the document took to arrive. Treat cross-arm LCP/FCP/TTI deltas as suspect unless
# the TTFB column says the arms really were comparable.
#
# USAGE
#
#   scripts/perf-measure.sh                              # 5 runs, mobile, production homepage
#   scripts/perf-measure.sh --runs 3 --url https://…/    # another page
#   scripts/perf-measure.sh --ceiling                    # + the blocked-vendor arms
#   scripts/perf-measure.sh --arm 'no-meta=*connect.facebook.net*'
#   scripts/perf-measure.sh --preset desktop --ttfb-max 0    # 0 disables the TTFB band
#
# Results land in --out (default .perf/<utc-timestamp>/): every run's raw JSON, plus a
# summary.md ready to paste into docs/performance-baseline.md.

set -euo pipefail

URL="https://remoteleverage.com/"
RUNS=5
TTFB_MAX=300
PRESET="mobile"
OUT=""
LH_VERSION="12.8.2"
CEILING=0
declare -a ARMS=()

die() { printf 'perf-measure: %s\n' "$1" >&2; exit 1; }

while [[ $# -gt 0 ]]; do
  case "$1" in
    --url)         URL="${2:-}"; shift 2 ;;
    --runs)        RUNS="${2:-}"; shift 2 ;;
    --ttfb-max)    TTFB_MAX="${2:-}"; shift 2 ;;
    --preset)      PRESET="${2:-}"; shift 2 ;;
    --out)         OUT="${2:-}"; shift 2 ;;
    --lighthouse)  LH_VERSION="${2:-}"; shift 2 ;;
    --ceiling)     CEILING=1; shift ;;
    --arm)         ARMS+=("${2:-}"); shift 2 ;;
    -h|--help)     sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *)             die "unknown option: $1 (try --help)" ;;
  esac
done

[[ "$RUNS" =~ ^[0-9]+$ && "$RUNS" -ge 1 ]] || die "--runs must be a positive integer"
[[ "$TTFB_MAX" =~ ^[0-9]+$ ]] || die "--ttfb-max must be an integer (0 disables the band)"
[[ "$PRESET" == "mobile" || "$PRESET" == "desktop" ]] || die "--preset must be mobile or desktop"
command -v npx >/dev/null 2>&1 || die "npx not found"
command -v python3 >/dev/null 2>&1 || die "python3 not found"
command -v curl >/dev/null 2>&1 || die "curl not found"

# The baseline arm always runs first and is the comparison point for the rest.
if [[ ${#ARMS[@]} -eq 0 ]]; then
  ARMS=("baseline=")
else
  ARMS=("baseline=" "${ARMS[@]}")
fi

# The three vendors that `docs/performance-homepage-plan.md` is about. Blocking all three is the
# ceiling: it is what the page would cost if they were gone, not a proposal to remove them.
if [[ "$CEILING" -eq 1 ]]; then
  ARMS+=(
    "no-meta=*connect.facebook.net*"
    "no-google-tag=*googletagmanager.com*"
    "no-posthog=*posthog.com*"
    "no-three=*connect.facebook.net*,*googletagmanager.com*,*posthog.com*"
  )
fi

if [[ -z "$OUT" ]]; then
  OUT=".perf/$(date -u +%Y%m%dT%H%M%SZ)"
fi
mkdir -p "$OUT"

# Max attempts per arm. A discarded run costs a Lighthouse execution, so this bounds the damage
# when an origin is simply slow rather than intermittently slow.
MAX_ATTEMPTS=$(( RUNS * 3 ))

printf 'perf-measure\n'
printf '  url       %s\n' "$URL"
printf '  runs      %s (preset %s, lighthouse %s)\n' "$RUNS" "$PRESET" "$LH_VERSION"
if [[ "$TTFB_MAX" -gt 0 ]]; then
  printf '  ttfb band <= %sms (runs above are discarded and retried, max %s attempts/arm)\n' "$TTFB_MAX" "$MAX_ATTEMPTS"
else
  printf '  ttfb band disabled\n'
fi
printf '  out       %s\n\n' "$OUT"

for arm_spec in "${ARMS[@]}"; do
  arm_name="${arm_spec%%=*}"
  arm_patterns="${arm_spec#*=}"

  declare -a block_flags=()
  if [[ -n "$arm_patterns" ]]; then
    IFS=',' read -r -a pats <<< "$arm_patterns"
    for p in "${pats[@]}"; do
      [[ -n "$p" ]] && block_flags+=(--blocked-url-patterns="$p")
    done
  fi

  printf '== arm: %s' "$arm_name"
  [[ -n "$arm_patterns" ]] && printf '  (blocking %s)' "$arm_patterns"
  printf '\n'

  kept=0
  discarded=0
  attempt=0

  while [[ "$kept" -lt "$RUNS" && "$attempt" -lt "$MAX_ATTEMPTS" ]]; do
    attempt=$(( attempt + 1 ))
    candidate="$OUT/$arm_name.attempt-$attempt.json"

    # Warm the CDN so the run measures the cached path rather than a cold origin render. Two
    # requests: the first may itself be the miss that populates the edge.
    curl -sS -o /dev/null --max-time 60 "$URL" >/dev/null 2>&1 || true
    curl -sS -o /dev/null --max-time 60 "$URL" >/dev/null 2>&1 || true

    # `--preset` only takes desktop/perf/experimental; mobile is the default and passing an
    # empty value is an error, so the flag is added conditionally rather than interpolated.
    declare -a preset_flag=()
    [[ "$PRESET" == "desktop" ]] && preset_flag=(--preset=desktop)

    if ! npx -y "lighthouse@$LH_VERSION" "$URL" \
        --only-categories=performance \
        "${preset_flag[@]+"${preset_flag[@]}"}" \
        --output=json --output-path="$candidate" \
        --chrome-flags="--headless --no-sandbox" \
        "${block_flags[@]+"${block_flags[@]}"}" \
        --quiet >/dev/null 2>&1; then
      printf '   attempt %-2s  lighthouse failed, retrying\n' "$attempt"
      rm -f "$candidate"
      discarded=$(( discarded + 1 ))
      continue
    fi

    read -r ttfb perf lcp tti tbt < <(python3 - "$candidate" <<'PY'
import json, sys
d = json.load(open(sys.argv[1]))
a = d['audits']
def n(k):
    v = a.get(k, {}).get('numericValue')
    return 0.0 if v is None else v
print(
    round(n('server-response-time')),
    round(d['categories']['performance']['score'] * 100),
    round(n('largest-contentful-paint')),
    round(n('interactive')),
    round(n('total-blocking-time')),
)
PY
)

    # A Lighthouse run can exit 0 having produced a report with no audit values (a page error
    # captured as a result). Without this the arithmetic below fails the whole script under
    # `set -e` rather than discarding one bad run.
    if [[ -z "${ttfb:-}" || ! "$ttfb" =~ ^[0-9]+$ ]]; then
      printf '   attempt %-2s  no usable metrics in report -- DISCARDED\n' "$attempt"
      mv "$candidate" "$OUT/$arm_name.unparsed-$attempt.json"
      discarded=$(( discarded + 1 ))
      continue
    fi

    if [[ "$TTFB_MAX" -gt 0 && "$ttfb" -gt "$TTFB_MAX" ]]; then
      printf '   attempt %-2s  TTFB %sms > %sms -- DISCARDED (cache miss / cold origin)\n' "$attempt" "$ttfb" "$TTFB_MAX"
      mv "$candidate" "$OUT/$arm_name.discarded-$attempt.json"
      discarded=$(( discarded + 1 ))
      continue
    fi

    kept=$(( kept + 1 ))
    mv "$candidate" "$OUT/$arm_name.run-$kept.json"
    printf '   run %-2s      perf %-3s  LCP %6sms  TTI %6sms  TBT %5sms  (TTFB %sms)\n' \
      "$kept" "$perf" "$lcp" "$tti" "$tbt" "$ttfb"
  done

  if [[ "$kept" -lt "$RUNS" ]]; then
    printf '   !! only %s/%s runs stayed inside the TTFB band after %s attempts.\n' "$kept" "$RUNS" "$attempt"
    printf '      That is a finding: this origin is not reliably cached. Widen --ttfb-max or\n'
    printf '      investigate the origin before trusting any number here.\n'
  fi
  printf '   kept %s, discarded %s\n\n' "$kept" "$discarded"
done

python3 - "$OUT" "$URL" "$PRESET" "$RUNS" "$TTFB_MAX" <<'PY'
import glob, json, os, statistics, sys

out, url, preset, runs, ttfb_max = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5]

METRICS = [
    ('perf', 'Perf', None),
    ('largest-contentful-paint', 'LCP', 'ms'),
    ('interactive', 'TTI', 'ms'),
    ('total-blocking-time', 'TBT', 'ms'),
    ('cumulative-layout-shift', 'CLS', None),
    ('server-response-time', 'TTFB', 'ms'),
]

# `baseline` first, then the remaining arms alphabetically; within an arm, run-1..run-N.
arms = {}
for path in sorted(glob.glob(os.path.join(out, '*.run-*.json'))):
    arm = os.path.basename(path).split('.run-')[0]
    arms.setdefault(arm, []).append(path)

if 'baseline' in arms:
    arms = {'baseline': arms.pop('baseline'), **arms}

def med(vals):
    return statistics.median(vals) if vals else float('nan')

rows = {}
for arm, paths in arms.items():
    vals = {k: [] for k, _, _ in METRICS}
    for p in paths:
        d = json.load(open(p))
        vals['perf'].append(d['categories']['performance']['score'] * 100)
        for k, _, _ in METRICS:
            if k == 'perf':
                continue
            v = d['audits'].get(k, {}).get('numericValue')
            if v is not None:
                vals[k].append(v)
    rows[arm] = vals

lines = []
lines.append(f'Measured `{url}` with `lighthouse` ({preset} preset), **median of {runs} runs**, ')
lines.append(f'runs with TTFB above {ttfb_max}ms discarded and retried.\n')
lines.append('| Arm | Perf | LCP | TTI | TBT | CLS | TTFB |')
lines.append('| :--- | ---: | ---: | ---: | ---: | ---: | ---: |')
for arm, vals in rows.items():
    lines.append(
        f"| `{arm}` | {med(vals['perf']):.0f} "
        f"| {med(vals['largest-contentful-paint'])/1000:.2f} s "
        f"| {med(vals['interactive'])/1000:.2f} s "
        f"| {med(vals['total-blocking-time']):.0f} ms "
        f"| {med(vals['cumulative-layout-shift']):.3f} "
        f"| {med(vals['server-response-time']):.0f} ms |"
    )

lines.append('\nPer-run values, so variance stays visible:\n')
lines.append('| Arm | Perf | LCP (s) | TBT (ms) | TTFB (ms) |')
lines.append('| :--- | :--- | :--- | :--- | :--- |')
for arm, vals in rows.items():
    lines.append(
        f"| `{arm}` | {' / '.join(f'{v:.0f}' for v in vals['perf'])} "
        f"| {' / '.join(f'{v/1000:.2f}' for v in vals['largest-contentful-paint'])} "
        f"| {' / '.join(f'{v:.0f}' for v in vals['total-blocking-time'])} "
        f"| {' / '.join(f'{v:.0f}' for v in vals['server-response-time'])} |"
    )

if len(rows) > 1:
    lines.append(
        '\n**Only TBT is reliably comparable across arms.** It is main-thread CPU and is largely '
        'independent of document arrival time; LCP/TTI/Perf move with TTFB. Check the TTFB column '
        'agrees before reading anything else across rows.'
    )

body = '\n'.join(lines) + '\n'
open(os.path.join(out, 'summary.md'), 'w').write(body)
print(body)
print(f'written: {os.path.join(out, "summary.md")}')
PY
