#!/bin/sh
set -eu

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

mkdir -p /var/www/html/web/app/cache/acorn/framework/views \
         /var/www/html/web/app/cache/acorn/framework/cache \
         /var/www/html/web/app/cache/acorn/logs
chown -R www-data:www-data /var/www/html/web/app/cache || true

if [ "${SKIP_CHOWN:-0}" != "1" ]; then
  mkdir -p /var/www/html/web/app/uploads
  chown -R www-data:www-data /var/www/html/web/app/uploads || true
fi

if wp core is-installed --allow-root >/dev/null 2>&1; then
  wp plugin activate redis-cache --allow-root || true
  wp acorn optimize --allow-root || true
  chown -R www-data:www-data /var/www/html/web/app/cache || true

  if [ -n "${REDIS_HOST:-}" ]; then
    wp plugin activate redis-cache --allow-root || true
    wp redis enable --allow-root || true
  fi
fi

php-fpm -D
exec nginx -g "daemon off;"
