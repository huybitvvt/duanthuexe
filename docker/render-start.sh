#!/usr/bin/env bash
set -euo pipefail

APP_PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

if [[ -z "${APP_URL:-}" && -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]]; then
    export APP_URL="https://${RENDER_EXTERNAL_HOSTNAME}"
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache

# Production deployments must apply pending migrations before serving traffic.
# Keep RUN_MIGRATIONS as an explicit opt-in for non-production environments.
if [[ "${APP_ENV:-}" == "production" || "${RUN_MIGRATIONS:-false}" == "true" ]]; then
    php artisan migrate --force
fi

exec apache2-foreground
