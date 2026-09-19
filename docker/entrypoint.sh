#!/bin/sh
set -eu

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

# ---------------------------------------------------------------------------
# Load the application secret into the environment.
#
# ECS maps Secrets Manager keys to environment variables one at a time, in the
# task definition. That means every new key — a new integration credential, a
# feature flag — needs an infrastructure change before the application can see
# it, and until then the app reads it as unset. Unset is indistinguishable from
# "deliberately blank" to every guard in the codebase, so the integration is
# simply dark and nothing says why. That is how SENTRY_LARAVEL_DSN and
# CUSTOMERIO_CDP_WRITE_KEY were live in Secrets Manager and invisible to the app
# at the same time.
#
# Reading the whole secret here decouples the two: adding a key becomes a
# deploy, not a ticket.
#
# Deliberate properties:
#   - No-op when APP_SECRET_ARN is unset, so this is safe to ship before the
#     task role has been granted access.
#   - Existing environment variables always win. Anything the task definition
#     already maps keeps its value, so this cannot change current behaviour.
#   - Soft failure. A secret that cannot be fetched leaves the container serving
#     on whatever the task definition provided rather than refusing to start;
#     the warning goes to CloudWatch. DB credentials come from the task
#     definition, so the site stays up and only optional integrations degrade.
#   - Values are never echoed.
# ---------------------------------------------------------------------------
if [ -n "${APP_SECRET_ARN:-}" ]; then
  echo "entrypoint: loading application secret from Secrets Manager..."

  if _secret_json=$(aws secretsmanager get-secret-value \
        --secret-id "$APP_SECRET_ARN" \
        --region "${AWS_REGION:-us-east-1}" \
        --query SecretString \
        --output text 2>/dev/null); then

    # PHP rather than jq: it is guaranteed present in this image, and it can do
    # the shell-quoting correctly. Only scalars are exported, and only keys that
    # are not already set.
    #
    # The PHP must live in a quoted heredoc. php -r ' ... $quoted = "'" ... $value'
    # closes the single-quoted string and lets the shell expand $value; with
    # set -u that is `value: parameter not set` and the container exits 2.
    _exports=$(printf '%s' "$_secret_json" | php -r "$(cat <<'PHP'
$raw = stream_get_contents(STDIN);
$data = json_decode($raw, true);
if (!is_array($data)) { fwrite(STDERR, "entrypoint: secret is not a JSON object\n"); exit(0); }
$set = 0;
foreach ($data as $key => $value) {
    if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', (string) $key)) { continue; }
    if (getenv($key) !== false) { continue; }
    if (!is_scalar($value) && $value !== null) { continue; }
    $quoted = "'" . str_replace("'", "'\\''", (string) $value) . "'";
    echo "export {$key}={$quoted}\n";
    $set++;
}
fwrite(STDERR, "entrypoint: exported {$set} key(s) from the application secret\n");
PHP
)")

    eval "$_exports"
    unset _exports _secret_json
  else
    echo "entrypoint: WARNING could not read $APP_SECRET_ARN; continuing with the task definition environment only" >&2
  fi
fi

mkdir -p /var/www/html/web/app/cache/acorn/framework/views \
         /var/www/html/web/app/cache/acorn/framework/cache \
         /var/www/html/web/app/cache/acorn/logs \
         /var/cache/nginx/fastcgi
chown -R www-data:www-data /var/www/html/web/app/cache /var/cache/nginx || true

if [ "${SKIP_CHOWN:-0}" != "1" ]; then
  mkdir -p /var/www/html/web/app/uploads
  chown -R www-data:www-data /var/www/html/web/app/uploads || true
fi

if wp core is-installed --allow-root >/dev/null 2>&1; then
  wp plugin activate redis-cache --allow-root || true

  # Apply pending schema changes and refresh rewrite rules before this task
  # serves anything. Serialised across containers by a MySQL named lock, since
  # a rolling deploy can start several tasks at once — see RunDeployTasksCommand.
  #
  # Fatal on purpose: a container serving against a schema the code does not
  # expect fails quietly and can corrupt data, whereas refusing to start fails
  # the ECS deployment loudly and leaves the previous tasks serving.
  if ! wp acorn rl:deploy --allow-root; then
    echo "entrypoint: post-deploy tasks failed; refusing to start" >&2
    exit 1
  fi

  wp acorn optimize --allow-root || true
  chown -R www-data:www-data /var/www/html/web/app/cache || true

  # Gate on WP_REDIS_HOST, which is what config/application.php and docker-compose.yml
  # actually set. This read REDIS_HOST until 2026-09-19 — a variable nothing defined — so
  # `wp redis enable` never ran in any environment. It was survivable only because the
  # Dockerfile already copies the drop-in to web/app/object-cache.php at image build, which
  # is what really turns the object cache on; the visible symptom was the redis-cache plugin
  # reporting itself disabled on a site whose object cache was working.
  #
  # REDIS_HOST is Laravel's variable, read by Acorn's config/database.php for CACHE_STORE=redis.
  # The two are deliberately distinct and an environment using Redis for both sets both.
  if [ -n "${WP_REDIS_HOST:-}" ]; then
    wp redis enable --allow-root || true
  fi
fi

php-fpm -D
exec nginx -g "daemon off;"
