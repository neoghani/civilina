#!/usr/bin/env bash
# CIVILINA Engineering — deploy a version on the pilot server.
#
#   sudo -u civilina /srv/civilina/app/deploy/deploy.sh [git-ref]
#
# Runs in the application directory as the application user. The production
# .env is never read, printed or modified here: it must already exist.
set -euo pipefail

APP_DIR="${APP_DIR:-/srv/civilina/app}"
REF="${1:-civilina-hermes-preparation}"

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo "Fichier .env absent dans $APP_DIR : créez-le d'abord (voir deploy/env.production.example)." >&2
    exit 1
fi

echo "==> Récupération de $REF"
git fetch --prune origin
git checkout --force "$REF"
git reset --hard "origin/$REF" 2>/dev/null || git reset --hard "$REF"

echo "==> Mode maintenance"
php artisan down --retry=30 || true
trap 'php artisan up || true' EXIT

echo "==> Dépendances"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

echo "==> Base de données"
php artisan migrate --force

echo "==> Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>/dev/null || true

echo "==> Redémarrage des workers"
php artisan queue:restart

php artisan up
trap - EXIT
echo "==> Version $(git rev-parse --short HEAD) en ligne."
