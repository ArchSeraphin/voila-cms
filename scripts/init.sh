#!/usr/bin/env bash
# init.sh — Setup local zéro-friction pour voila-cms
# Pré-requis : Herd (PHP + .test domains) + MAMP (MySQL sur 8889)
# Usage : ./scripts/init.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PROJECT_NAME="$(basename "$ROOT")"
APP_URL="https://${PROJECT_NAME}.test"

# MAMP defaults
MAMP_SOCK="/Applications/MAMP/tmp/mysql/mysql.sock"
DB_HOST="127.0.0.1"
DB_PORT="8889"
DB_USER="root"
DB_PASS="root"
DB_NAME="${PROJECT_NAME//[^a-zA-Z0-9_]/_}"

echo "▶︎ voila-cms init — projet: ${PROJECT_NAME}"
echo "  URL  : ${APP_URL}"
echo "  DB   : ${DB_NAME} @ ${DB_HOST}:${DB_PORT}"
echo ""

# ---------- 1. Vérifs prérequis ----------
command -v php >/dev/null || { echo "✗ PHP introuvable (Herd lancé ?)"; exit 1; }
command -v composer >/dev/null || { echo "✗ Composer introuvable"; exit 1; }
command -v npm >/dev/null || { echo "✗ npm introuvable"; exit 1; }

MYSQL_BIN=""
for p in /Applications/MAMP/Library/bin/mysql /Applications/MAMP/Library/bin/mysql80/bin/mysql $(command -v mysql 2>/dev/null || true); do
    [ -x "$p" ] && { MYSQL_BIN="$p"; break; }
done
[ -z "$MYSQL_BIN" ] && { echo "✗ mysql introuvable — démarre MAMP (onglet Servers → Start)"; exit 1; }

if ! "$MYSQL_BIN" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" >/dev/null 2>&1; then
    echo "✗ MySQL MAMP ne répond pas sur ${DB_HOST}:${DB_PORT} — démarre MAMP"
    exit 1
fi
echo "✓ Prérequis OK"

# ---------- 2. .env ----------
if [ ! -f .env ]; then
    cp .env.example .env
    SECRET="$(php -r "echo bin2hex(random_bytes(32));")"

    # Patch .env avec valeurs MAMP + nom projet + secret
    php -r "
        \$f = '.env';
        \$c = file_get_contents(\$f);
        \$c = preg_replace('/^APP_URL=.*/m', 'APP_URL=${APP_URL}', \$c);
        \$c = preg_replace('/^APP_NAME=.*/m', 'APP_NAME=\"${PROJECT_NAME}\"', \$c);
        \$c = preg_replace('/^DB_HOST=.*/m', 'DB_HOST=${DB_HOST}', \$c);
        \$c = preg_replace('/^DB_PORT=.*/m', 'DB_PORT=${DB_PORT}', \$c);
        \$c = preg_replace('/^DB_DATABASE=.*/m', 'DB_DATABASE=${DB_NAME}', \$c);
        \$c = preg_replace('/^DB_USERNAME=.*/m', 'DB_USERNAME=${DB_USER}', \$c);
        \$c = preg_replace('/^DB_PASSWORD=.*/m', 'DB_PASSWORD=${DB_PASS}', \$c);
        \$c = preg_replace('/^IMAGE_URL_SECRET=.*/m', 'IMAGE_URL_SECRET=${SECRET}', \$c);
        \$c = preg_replace('/^MAIL_TRANSPORT=.*/m', 'MAIL_TRANSPORT=null', \$c);
        file_put_contents(\$f, \$c);
    "
    echo "✓ .env généré (MAMP + Herd, mail transport=null)"
else
    echo "✓ .env existe déjà — conservé"
fi

# ---------- 3. Composer + npm ----------
if [ ! -d vendor ]; then
    echo "▶︎ composer install..."
    composer install --no-interaction --prefer-dist
else
    echo "✓ vendor/ présent"
fi

if [ ! -d node_modules ]; then
    echo "▶︎ npm install..."
    npm install --silent
else
    echo "✓ node_modules/ présent"
fi

# ---------- 4. DB + migrations ----------
"$MYSQL_BIN" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
    -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "✓ Base ${DB_NAME} prête"

php scripts/migrate.php

# ---------- 5. Build Tailwind ----------
npm run build --silent
echo "✓ Tailwind buildé"

# ---------- 6. Done ----------
cat <<EOF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✓ Setup terminé

  Front   : ${APP_URL}
  Admin   : ${APP_URL}/admin/login
  Brief   : ${APP_URL}/_starter/brief.html

  Créer un compte admin :
    php scripts/create-admin.php toi@example.com

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOF
