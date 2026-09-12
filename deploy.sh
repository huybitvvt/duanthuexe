#!/usr/bin/env bash
set -euo pipefail

# Render deploys this project from render.yaml. This script only performs a
# local production build and intentionally contains no hosts or credentials.
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build:static
php artisan config:clear
php artisan route:clear
