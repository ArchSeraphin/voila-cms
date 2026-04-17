#!/usr/bin/env bash
# Executed by Plesk post-deploy hook after git pull.
set -euo pipefail
cd "$(dirname "$0")"

echo "→ Composer install"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Running migrations"
php scripts/migrate.php

echo "→ Clearing caches"
php scripts/cache-clear.php

echo "→ Fixing perms on storage"
chmod -R 775 storage/ 2>/dev/null || true

echo "→ Deploy done."
