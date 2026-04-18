# voila-cms

Starter kit PHP pour sites vitrine (TPE/PME). Clone dans Herd, remplis le brief, lance `init.sh`, Claude Code scaffolde. Déploie sur Plesk.

## Prérequis

- **[Herd](https://herd.laravel.com/)** (PHP 8.2+ + `.test` auto-servis)
- **[MAMP](https://www.mamp.info/)** (MySQL sur port 8889) — **démarre-le avant tout**
- Composer
- Node 20+

## Démarrage rapide

```bash
# 1. Cloner dans Herd → auto-servi sur https://<nom-projet>.test
cd ~/Herd
git clone <voila-cms-repo> mon-client
cd mon-client

# 2. Setup auto (env, DB, composer, npm, migrations, build)
./scripts/init.sh

# 3. Remplir le brief → copier le prompt → coller dans Claude Code
open https://mon-client.test/_starter/brief.html

# 4. Créer un admin
php scripts/create-admin.php toi@mon-client.fr
```

C'est tout.

- Front : `https://mon-client.test`
- Admin : `https://mon-client.test/admin/login`
- Brief : `https://mon-client.test/_starter/brief.html`

## Ce que fait `init.sh`

1. Vérifie Herd/MAMP/composer/npm
2. Génère `.env` avec :
   - `APP_URL=https://<dossier>.test`
   - DB MAMP (`127.0.0.1:8889` / `root` / `root`)
   - `IMAGE_URL_SECRET` aléatoire
   - `MAIL_TRANSPORT=null` (pas de SMTP en local)
3. `composer install` + `npm install`
4. Crée la base `<dossier>` + lance les migrations
5. `npm run build` (Tailwind prod)

Relançable à volonté — skip ce qui existe déjà.

## Assets client (optionnel avant scaffolding)

```bash
mkdir -p _inputs/{charte,photos,textes}
# y déposer logo.svg, favicon.png, photos HD, contenus.md
```

Claude lit `_inputs/` pour personnaliser le site.

## Tests

```bash
composer test
```

## Déploiement Plesk

Voir `deploy.sh`. Connecter le repo dans Plesk, brancher sur `main`, déposer `deploy.sh` comme hook post-deploy, Let's Encrypt auto. Penser à remplir les `MAIL_*` dans le `.env` serveur.

## Documentation

- `docs/superpowers/specs/` — design du projet
- `docs/superpowers/plans/` — plans d'implémentation
- `PROJECT_MAP.md` — qui modifie quoi
- `CLAUDE.md` — instructions pour Claude Code
