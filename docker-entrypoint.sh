#!/bin/sh
set -eu

cd /var/www/html

# Only the consolidated schema migration; other files under database/migrations are skipped.
php artisan migrate --force --path=database/migrations/2026_05_15_000001_only_migration_file_you_need_2.php
php artisan db:seed --force
php artisan config:cache
php artisan route:cache

PORT="${PORT:-80}"

exec php -S "0.0.0.0:${PORT}" -t public
