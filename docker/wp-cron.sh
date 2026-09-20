#!/bin/sh
#
# One WordPress cron tick, fired by real cron rather than by a visitor's request.
#
# ## Why an HTTP request and not `wp cron event run`
#
# Two reasons, and the first is decisive. Cron strips the environment: a job gets HOME, LOGNAME,
# PATH and SHELL and nothing else. The database credentials this application needs come from the
# ECS task definition as environment variables, so WP-CLI started from cron would boot without
# them and fail on every tick. Going over the loopback hands the work to php-fpm, which has the
# full environment because docker/www.conf sets `clear_env = no`.
#
# The second is that this keeps hooks running in exactly the request context they ran in before
# real cron existed — same SAPI, same superglobals, same everything. Switching transport and
# execution context in one change would make any resulting breakage hard to attribute.
#
# ## The request
#
# nginx listens as `server_name _` on `listen 80 default_server`, so no Host header is needed.
# X-Forwarded-Proto matches what the ALB sends and docker/nginx.conf maps it to the HTTPS
# fastcgi param, so home_url() builds https URLs exactly as it does on a real request. Without
# it, anything a hook generates — a Slack link, a webhook payload — would carry http:// URLs.

set -u

# No `doing_wp_cron` query parameter, and that is load-bearing rather than an omission.
#
# wp-cron.php branches on it (web/wp/wp-cron.php, around line 96). With the parameter *absent or
# empty* it takes the "called from external script/job" path: it checks the `doing_cron`
# transient, takes the lock itself, and runs the due jobs. With the parameter carrying a value
# it instead trusts that value as the lock key, immediately compares it against the transient,
# and returns when they differ — which for any fixed value they always do.
#
# So `?doing_wp_cron=1` would make every tick a silent no-op: HTTP 200, empty body, zero jobs
# run, nothing in any log saying so. A bare `?doing_wp_cron` works too, since PHP reads it as an
# empty string, but sending nothing is unambiguous.
CRON_URL="http://127.0.0.1/wp/wp-cron.php"

# PID 1 is nginx, which inherited the container's stdout, so this reaches CloudWatch.
log() {
    echo "wp-cron: $1" > /proc/1/fd/1 2>/dev/null || echo "wp-cron: $1" >&2
}

# Silent on success, deliberately. A line a minute on every task is noise nobody reads and
# CloudWatch charges to store. A failing tick means scheduled work has stopped, which is the
# only thing here worth an alarm.
#
# --max-time is well above the slowest hook (the marketing snapshot warms Calendly and Meta,
# worst case around 40s) and well under the point where ticks would pile up. Overlap is
# harmless regardless: wp-cron.php takes WordPress's own `doing_cron` lock and a second caller
# returns immediately.
if ! output=$(curl -fsS --max-time 180 -H 'X-Forwarded-Proto: https' "$CRON_URL" 2>&1); then
    log "tick failed: ${output:-no output}"
    exit 1
fi

exit 0
