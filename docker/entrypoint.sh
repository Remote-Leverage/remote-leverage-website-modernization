#!/bin/sh
set -eu

if [ "$#" -gt 0 ]; then
  exec "$@"
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

  if [ -n "${REDIS_HOST:-}" ]; then
    wp plugin activate redis-cache --allow-root || true
    wp redis enable --allow-root || true
  fi
fi

php-fpm -D
exec nginx -g "daemon off;"
