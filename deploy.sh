#!/usr/bin/env bash
# Deploy Kuncia. Runs on the server as deploy.
set -euo pipefail

main() {
    cd /var/www/kuncia
    php artisan down --retry=15 || true
    trap 'php artisan up' EXIT

    git pull --ff-only origin main
    composer install --no-dev --optimize-autoloader --no-interaction
    npm ci --no-audit --no-fund
    npm run build
    php artisan migrate --force
    php artisan optimize
    php artisan queue:restart
}

main "$@"
