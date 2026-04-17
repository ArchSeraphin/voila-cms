# voila-cms

Starter kit PHP pour sites vitrine (TPE/PME). Clone, remplis le brief, laisse Claude Code scaffolder le site, déploie sur Plesk.

## Prérequis
- PHP 8.2+ avec extensions: pdo_mysql, mbstring, fileinfo
- MySQL 8 / MariaDB 10.6+
- Composer
- Node 20+ (dev local pour Tailwind)

## Démarrage rapide (nouveau projet)

### Option A — Avec le brief HTML (recommandé)

```bash
git clone <this-repo> mon-client.fr
cd mon-client.fr

# Lance le serveur du brief (port 9000)
php -S localhost:9000 -t _starter/ &
# Ouvre http://localhost:9000/brief.html, remplis les 9 sections,
# clique "💾 Sauvegarder brief.json" puis "📋 Copier le prompt Claude Code"
# Stoppe le serveur brief : kill %1

# Setup technique
cp .env.example .env
# Éditer .env (DB credentials + SMTP)
composer install
npm install
mysql -u root -e "CREATE DATABASE voila_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php scripts/migrate.php

# Colle le prompt dans Claude Code → Claude scaffolde le site selon le brief
# Puis :
php scripts/create-admin.php admin@mon-client.fr
npm run build
php -S localhost:8000 -t public/
```

### Option B — Manuel (sans brief)

Même chose mais édite `config/modules.php`, `config/pages.php`, `tailwind.config.js` et les settings (via MySQL ou via /admin/settings une fois admin connecté) à la main.

- Front : http://localhost:8000
- Admin : http://localhost:8000/admin/login

## Tests

```bash
composer test
```

## Déploiement Plesk

Voir `deploy.sh`. Connecter le repo dans Plesk, brancher sur `main`, déposer `deploy.sh` comme hook post-deploy, Let's Encrypt auto.

## Documentation

- `docs/superpowers/specs/` — design du projet
- `docs/superpowers/plans/` — plans d'implémentation
- `PROJECT_MAP.md` — qui modifie quoi
- `CLAUDE.md` — instructions pour Claude Code
