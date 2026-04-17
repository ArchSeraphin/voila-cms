#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "→ Composer install (prod optimized)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ NPM build (Tailwind)"
npm ci --silent
npm run build

echo "→ Build done."
