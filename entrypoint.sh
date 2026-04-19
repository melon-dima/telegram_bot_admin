#!/usr/bin/env bash
set -e

cd /var/www/html

#source /var/www/html/config/load-env.sh
#load_env_file /var/www/html/.env

# For bind mounts: try to align permissions, but do not fail if filesystem forbids it.
chown -R www-data:www-data /var/www/html 2>/dev/null || true
chmod -R 755 /var/www/html 2>/dev/null || true

if [ "${SKIP_COMPOSER_INSTALL:-0}" != "1" ]; then
  composer install --no-interaction --optimize-autoloader
fi

exec "$@"
