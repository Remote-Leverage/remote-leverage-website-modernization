#!/bin/sh
set -eu

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

if [ "${SKIP_CHOWN:-0}" != "1" ]; then
  mkdir -p /var/www/html/web/app/uploads
  chown -R www-data:www-data /var/www/html/web/app/uploads || true
fi

php-fpm -D
exec nginx -g "daemon off;"
