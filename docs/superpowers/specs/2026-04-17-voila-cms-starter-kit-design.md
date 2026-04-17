# voila-cms — Design du starter kit

**Date :** 2026-04-17
**Auteur :** Nicolas (ArchSeraphin)
**Statut :** Design validé — en attente de plan d'implémentation

---

## 1. Vue d'ensemble

### 1.1 Objectif

**voila-cms** est un **starter kit auto-hébergé** (boilerplate) pour produire rapidement des sites vitrine de qualité professionnelle pour des clients TPE/PME/artisans locaux. Le workflow vise à être :

- Automatisé par Claude Code (scaffolding à partir d'un brief structuré)
- Reproductible et homogène d'un projet à l'autre (stack figée)
- Simple à maintenir et à déployer (Plesk / VPS OVH)
- Sûr à livrer (backoffice client sécurisé, périmètre d'édition maîtrisé)

### 1.2 Modèle d'architecture

Chaque site client est **autonome** : son propre repo (cloné depuis le starter), sa propre base MySQL, son propre domaine. **Aucun service partagé entre projets.** Pas de multi-tenancy, pas de service central, pas de mises à jour synchrones à gérer. Isolation maximale → simplicité, sécurité, facilité de maintenance.

### 1.3 Stack technique figée

| Couche | Choix | Justification |
|---|---|---|
| Langage serveur | PHP 8.2+ | Natif Plesk, zéro config déploiement, stable à long terme |
| Base de données | MySQL 8 / MariaDB 10.6+ | Standard Plesk, backups intégrés |
| Framework | Aucun (micro-libs ciblées) | Contrôle total, surface d'attaque minimale, pas de mises à jour framework à subir |
| Templating | Twig 3 | Auto-escape XSS, hérite proprement, Claude le maîtrise |
| CSS | TailwindCSS 3 | Productivité design, 1 seul fichier compilé en local |
| JS front | Alpine.js | Interactions légères sans bundler, 15 Ko |
| Images | league/glide | Pipeline à la volée avec cache, AVIF/WebP auto |
| Mails | symfony/mailer | Standard, support SMTP Plesk |
| Env | vlucas/phpdotenv | Séparation config / secrets |
| Richtext | TinyMCE 7 (self-hosted) | Éditeur mature, gratuit, bien maîtrisé |
| Build | build.sh (Tailwind compile) + deploy.sh (server-side) | Pas de Node en prod |

### 1.4 Principes directeurs

1. **Un site = un clone** : `git clone voila-cms mon-client.fr`
2. **Convention over configuration** : tout a sa place par défaut, peu de choix par projet
3. **`brief.json` = source de vérité** du projet
4. **Le client ne casse rien** : backoffice limité à ce qui est sûr
5. **Zéro dépendance Node en prod**
6. **Le code non utilisé n'est pas embarqué** : les modules non activés ne sont pas copiés

### 1.5 Flux global

```
git clone voila-cms → remplir brief.html → brief.json
                                                │
                                        Claude Code scaffolde
                                        (lit brief.json + _inputs/*)
                                                │
                                        site en local opérationnel
                                                │
                                        git push → Plesk auto-deploy
```

---

## 2. Architecture du contenu

### 2.1 Modèle : modules pré-faits "à activer"

La bibliothèque standard contient **8 modules fonctionnels** prêts à l'emploi. Chaque projet active uniquement ceux dont il a besoin. Si un client a un besoin exotique, Claude génère un module sur mesure ponctuel (pas de moteur générique de content types).

### 2.2 Bibliothèque de modules

| Module | Champs principaux |
|---|---|
| **Actualités** | titre, slug, date_publication, image, extrait, contenu riche, published, seo_title, seo_description |
| **Partenaires** | nom, logo, url, description, ordre, published |
| **Réalisations** | titre, slug, client, date, description, catégorie, cover_image, gallery_json, published, seo_* |
| **Équipe** | nom, fonction, photo, bio, linkedin, ordre, published |
| **Témoignages** | auteur, entreprise, photo, citation, note, ordre, published |
| **Services** | titre, slug, icône, description_courte, contenu, image, ordre, published, seo_* |
| **FAQ** | question, réponse, catégorie, ordre, published |
| **Documents** | titre, fichier_path (PDF), catégorie, date, ordre, published |

### 2.3 Modules système (toujours présents)

- **Réglages globaux** : identité site, logo, coordonnées, réseaux sociaux, SEO par défaut, couleurs, analytics
- **Pages statiques éditables** : Accueil, À Propos, Services, Contact, Mentions légales — mix de blocs éditables (choisis par projet) et de parties figées codées par Claude
- **Messages de contact** : boîte de réception des formulaires reçus + archivage
- **Comptes admin** : gestion du mot de passe + 2FA optionnel

### 2.4 Pages statiques : blocs éditables vs parties figées

Pour chaque page statique, le brief définit précisément quels blocs sont éditables par le client (ex: hero title, hero subtitle, CTA principal, paragraphes de présentation). Les sections visuellement complexes (hero avec composition graphique, layouts multi-colonnes avec logique spécifique) restent codées en dur par Claude pour préserver le design. Le client édite seulement du texte dans des champs clairement identifiés.

### 2.5 Structure d'un module

Chaque module vit dans `app/modules/{nom}/` et contient son code auto-contenu :

```
app/modules/actualites/
├── migration.sql
├── Model.php
├── Controller.php
├── routes.php
├── admin-form.twig
├── admin-list.twig
├── front-list.twig
├── front-single.twig
└── module.json       # métadonnées (nom affiché, icône admin, champs…)
```

Au scaffolding, seuls les dossiers des modules cochés dans `brief.json` sont copiés → zéro code mort.

---

## 3. Arborescence d'un projet cloné

```
mon-client.fr/
├── _starter/                   # Outils de scaffolding
│   ├── brief.html              # Formulaire de brief
│   ├── save.php                # Endpoint local pour écrire brief.json
│   ├── brief.json              # Source de vérité du projet
│   └── prompts/                # Prompts système pour Claude
│       ├── 00-scaffold.md
│       ├── 01-modules/         # 1 prompt par module
│       └── 02-refonte.md
│
├── _inputs/                    # Assets bruts fournis
│   ├── charte/                 # logo.svg, favicon, charte.pdf
│   ├── photos/                 # originaux HD
│   ├── textes/contenus.md
│   └── refonte/                # rempli par Claude si mode refonte
│
├── public/                     # ← document root Plesk
│   ├── index.php
│   ├── .htaccess
│   ├── assets/{css,js,fonts}
│   ├── media/                  # endpoint Glide
│   ├── uploads/                # fichiers clients (PHP désactivé via .htaccess)
│   ├── robots.txt
│   └── sitemap.xml             # généré dynamiquement
│
├── app/
│   ├── Core/                   # Router, Request, DB, Auth, CSRF, Session, Mailer
│   ├── Controllers/{Front,Admin}/
│   ├── Models/
│   ├── Services/               # Glide, SEO, Sitemap, Auth, RateLimiter, Consent
│   ├── Middleware/             # AuthAdmin, CSRF, RateLimit, SecurityHeaders
│   ├── modules/                # modules copiés à la carte
│   └── helpers.php
│
├── templates/
│   ├── layouts/base.html.twig
│   ├── front/
│   ├── admin/
│   └── partials/               # header, footer, nav, seo-meta, schema-jsonld, analytics, consent-banner
│
├── config/
│   ├── app.php
│   ├── modules.php             # issu de brief.json
│   ├── images.php              # presets Glide
│   └── routes.php
│
├── database/
│   ├── migrations/             # fichiers SQL numérotés
│   └── seeds/
│
├── storage/                    # hors webroot
│   ├── cache/                  # Twig + Glide
│   ├── logs/                   # admin.log, auth.log, app.log
│   └── sessions/
│
├── vendor/
├── scripts/
│   ├── migrate.php
│   └── cache-clear.php
├── .env.example
├── .env                        # gitignored
├── composer.json
├── tailwind.config.js
├── package.json                # Tailwind dev only
├── build.sh
├── deploy.sh                   # hook post-deploy Plesk
├── CLAUDE.md
├── PROJECT_MAP.md              # index des fichiers à modifier par intention
└── README.md
```

---

## 4. Schéma base de données

### 4.1 Tables système

| Table | Rôle |
|---|---|
| `users` | id, email, password_hash (Argon2id), 2fa_secret (nullable), last_login_at, failed_attempts, locked_until |
| `settings` | key/value (nom_site, logo_path, couleurs, coordonnées, réseaux, seo_defaults, analytics_*, consent_banner_enabled) |
| `static_pages_blocks` | page_slug, block_key, content (pour blocs éditables des pages statiques) |
| `contact_messages` | id, nom, email, sujet, message, ip, created_at, read_at |
| `login_attempts` | ip, email, attempted_at (rate limiting) |
| `admin_logs` | user_id, action, entity, entity_id, ip, created_at |

Sessions stockées dans `storage/sessions/` (handler PHP custom file-based, pas `/tmp`).

### 4.2 Tables modules

Une table par module activé (cf. section 2.2). Champs standardisés : `id`, `published`, `created_at`, `updated_at` sur tous. Les modules avec page détail ont `slug` + `seo_title` + `seo_description`.

### 4.3 Migrations

Fichiers SQL numérotés dans `database/migrations/` (ex: `001_create_users.sql`). Script `scripts/migrate.php` tient à jour une table `schema_migrations` et applique les migrations en attente. Exécuté par `deploy.sh` à chaque déploiement.

---

## 5. Workflow de démarrage d'un projet

### 5.1 Le formulaire `brief.html`

Page HTML/JS vanilla, huit sections :

1. **Infos client** — nom du site, raison sociale, domaine, secteur, coordonnées, horaires, réseaux sociaux
2. **Mode projet** — radio `Nouveau site` / `Refonte` (avec champ URL source si refonte)
3. **Charte graphique** — 3 color pickers (primaire / secondaire / accent), 2 selects Google Fonts avec preview (titre / corps), ton éditorial, style visuel
4. **Modules** — 8 cases à cocher + pour chaque module "affiché sur l'accueil : oui/non"
5. **Pages statiques** — pour chaque page, cases à cocher des blocs éditables souhaités
6. **Contenu** — textarea pour coller texte brut exploitable
7. **Instructions spéciales** — textarea libre pour consignes Claude
8. **SEO** — mots-clés cibles, zone géographique (pour JSON-LD LocalBusiness)
9. **Analytics** — radio fournisseur (Aucun / GA4 / Plausible / Matomo / GTM)

Deux boutons :
- **`Sauvegarder le brief`** → POST JSON vers `save.php` → écrit `_starter/brief.json`
- **`Copier le prompt Claude Code`** → assemble un prompt Markdown structuré à partir de `brief.json` + instructions de scaffolding + copie dans le presse-papier

### 5.2 Le prompt Claude généré

Contient :
- Référence à `_starter/brief.json` (source de vérité)
- Instructions de lecture de `_inputs/charte/`, `_inputs/photos/`, `_inputs/textes/`
- Mode refonte : instruction de scraper `url-source.txt` via WebFetch/Playwright, peupler `_inputs/refonte/` (pages HTML, screenshots desktop/mobile, textes extraits), puis analyser avant de scaffolder
- Référence à `_starter/prompts/01-modules/` (prompts modulaires à consulter au besoin)
- Checklist de scaffolding : init `.env`, `composer install`, génération Tailwind config depuis charte, copie des modules activés, création des migrations, seeder admin (avec mdp aléatoire affiché UNE FOIS), templates front respectant ton/style, personnalisation pages statiques, données de démo
- Critère d'arrêt : `php -S localhost:8000 -t public/` fonctionnel, `/admin` accessible, modules activés avec données de démo

### 5.3 Cas "refonte" — scraping automatique par Claude

Au lieu d'un script fourni, Claude Code utilise ses capacités (WebFetch, Playwright via MCP si dispo) pour :
1. Crawler les pages principales du site existant
2. Capturer screenshots desktop + mobile
3. Extraire le contenu textuel proprement
4. Stocker le tout dans `_inputs/refonte/` pour usage ultérieur (inspiration design + reprise des contenus)

Tu mets juste l'URL dans `_inputs/refonte/url-source.txt` et Claude fait le reste au début du projet.

### 5.4 Gestion des assets

- **Photos** : tu déposes des originaux HD bruts dans `_inputs/photos/` (organisés par section si utile). Le pipeline Glide (section 7) génère les variantes responsive à la volée au runtime — pas besoin de pré-optimisation.
- **Logo** : format vectoriel SVG préféré (fallback PNG haute résolution), favicon PNG 512×512.
- **Charte** : PDF optionnel pour contexte Claude.

---

## 6. Backoffice

### 6.1 Structure des routes

```
/admin/login
/admin                    # dashboard : stats + derniers messages + liens modules
/admin/settings           # réglages globaux (onglets : Site, Coordonnées, SEO, Analytics, Sécurité)
/admin/pages              # liste pages statiques
/admin/pages/{slug}/edit  # édition des blocs éditables
/admin/messages           # inbox formulaires contact
/admin/{module}           # liste d'un module
/admin/{module}/new
/admin/{module}/{id}/edit
/admin/account            # mon compte, 2FA
/admin/logout
```

### 6.2 Conventions UX

- **Layout** : sidebar gauche verticale (logo site, nav : Dashboard, Pages, chaque module activé, Messages, Réglages) + topbar (nom user, logout)
- **Formulaires** : 2 colonnes — champs principaux à gauche, sidebar droite (publication, image principale, statut)
- **Éditeur riche** : TinyMCE 7 self-hosted, configuration restreinte (titres, gras, italique, liens, listes, images via médiathèque du module, pas d'HTML brut)
- **Upload d'images** : drag & drop ou bouton, preview immédiat, **alt text obligatoire** (champ bloquant à la soumission)
- **Slugs** : auto-générés depuis titre, bouton "modifier" pour override, unicité vérifiée
- **Listes** : colonnes clés, pagination, recherche, tri, actions inline (éditer / dé-publier / supprimer), confirmation modale avant suppression
- **Messages flash** (succès / erreur) en haut après chaque action
- **Responsive** : backoffice utilisable sur tablette / mobile

### 6.3 Authentification

- 1 seul compte admin par site, créé au scaffolding avec mot de passe aléatoire affiché UNE FOIS au terminal
- Hash Argon2id
- Sessions PHP custom file-based dans `storage/sessions/`
- Cookie : `HttpOnly`, `Secure`, `SameSite=Strict`, durée 2h glissante
- Logout invalide la session côté serveur
- Rate limiting login : 5 échecs → verrou 15 min exponentiel (table `login_attempts`)
- Reset mot de passe : email + token à usage unique (durée 30 min)
- 2FA TOTP optionnel activable par le client dans `/admin/account`

---

## 7. Pipeline d'images (Glide)

### 7.1 Principe

Pas de build step. Les images originales HD sont stockées dans `public/uploads/{annee}/{mois}/uuid.ext`. Un endpoint PHP (`/media/{path}?...`) génère les variantes à la demande, les met en cache disque (`storage/cache/glide/`), et les sert avec `Cache-Control: public, max-age=31536000, immutable`.

### 7.2 Intégration Twig

Helper `{{ img('uploads/.../photo.jpg', preset='card') }}` → génère automatiquement une balise `<picture>` avec `srcset` AVIF/WebP/JPEG, tailles responsive (320, 640, 960, 1280, 1920), et négociation `Accept` header.

### 7.3 Presets (config/images.php)

- `thumb` — 200px carré
- `card` — 640px (aspect ratio 16/9 ou free selon usage)
- `hero` — 1920px large
- `gallery` — 1280px
- `full` — 2560px

Ajustables projet par projet.

### 7.4 Sécurité

- URL signée HMAC : empêche la génération abusive de variantes par un attaquant (secret dans `.env`)
- Upload : max 10 Mo, extensions `jpg/jpeg/png/webp/avif`, validation MIME réelle (`finfo_file`), magic bytes check
- Dimensions min/max configurables par champ

---

## 8. Pipeline SEO

### 8.1 Service central `Seo::build($context)`

Appelé par chaque contrôleur front, injecte dans le layout :
- `<title>` : règle par contexte (`{titre entité} | {nom site}` sur détail, titre SEO custom si renseigné)
- `<meta name="description">` : champ SEO custom, sinon extrait auto (155 premiers caractères du contenu nettoyé)
- `<link rel="canonical">`
- OG / Twitter Cards : og:type, og:title, og:description, og:image, og:url, og:locale=fr_FR, twitter:card=summary_large_image

### 8.2 Schema.org JSON-LD (`SchemaBuilder`)

- **Partout** : `LocalBusiness` + `Organization` dans le footer (depuis `settings`)
- **Accueil** : ajoute `WebSite` avec `potentialAction` si recherche activée
- **Pages internes** : `BreadcrumbList`
- **Actualité détail** : `Article` (auteur, datePublished, image)
- **Réalisation détail** : `CreativeWork`
- **Page FAQ** : `FAQPage`
- **Module Services** : `Service` par prestation

### 8.3 Sitemap & robots

- `/sitemap.xml` : `SitemapController` interroge toutes les tables modules publiées + pages statiques, cache 1h
- `/robots.txt` : statique dans `public/`, `Disallow: /admin/`, `Sitemap: {baseUrl}/sitemap.xml`
- URLs propres via `.htaccess` (slugs SEO-friendly sur entrées dynamiques, `/actualites/mon-article`)
- Redirect 301 si modification d'un slug

### 8.4 Détails

- **Canonical tags** sur chaque page
- **404 personnalisée** avec barre de recherche + liens accueil / actus récentes
- **Alt text** obligatoire à l'upload d'image (bloquant backoffice)
- **hreflang** : non (FR only)

---

## 9. Analytics & consentement RGPD

### 9.1 Fournisseurs supportés

Configurables dans `/admin/settings` onglet Analytics :
- Aucun
- Google Analytics 4 (`G-XXXXXXXXXX`)
- Plausible (domaine)
- Matomo (URL + Site ID)
- Google Tag Manager (`GTM-XXXXXXX`) — indépendant, peut s'ajouter par-dessus

### 9.2 Bannière de consentement

Requise dès qu'un fournisseur nécessitant cookies est activé (verrou UX côté backoffice).

- Bannière légère custom (pas de lib externe lourde), 3 boutons `Tout accepter` / `Tout refuser` / `Personnaliser`
- "Personnaliser" → modale avec toggles par catégorie : `Nécessaires` (toujours ON, grisé), `Analytics`, `Marketing`
- Choix stocké dans cookie `consent` (6 mois, conforme CNIL)
- Lien "Gérer les cookies" dans le footer (rouvre la modale)
- **Google Consent Mode v2** implémenté (pings anonymisés tant que consentement non donné → mesure approximative RGPD-compatible)

### 9.3 Politique de cookies

Page auto-générée au scaffolding (template Twig pré-rempli avec placeholders à compléter), liée dans le footer à côté des Mentions Légales.

### 9.4 Implémentation

- Partial `partials/analytics.twig` inclus dans `base.html.twig` avant `</body>`
- Service `Consent::has('analytics')` lit le cookie de consentement
- Aucune table `consent_logs` côté serveur (choix stocké côté client uniquement) → zéro donnée perso
- `brief.json` contient `analytics.provider` et `analytics.require_consent_banner` — Claude scaffolde les partials conditionnellement

---

## 10. Sécurité

### 10.1 Middlewares (ordre d'exécution)

1. **SecurityHeaders** : CSP (avec nonce par requête), X-Frame-Options=DENY, X-Content-Type-Options=nosniff, Referrer-Policy=strict-origin-when-cross-origin, Permissions-Policy restrictif, HSTS (prod uniquement)
2. **Session** : démarrage sécurisé, régénération ID à privilège
3. **CSRF** : vérification token sur POST/PUT/DELETE, génération pour formulaires
4. **RateLimit** : routes sensibles (login, contact, reset mdp)
5. **AuthAdmin** : routes `/admin/*`, redirige login si non authentifié

### 10.2 CSP

`default-src 'self'; img-src 'self' data:; font-src 'self'; script-src 'self' 'nonce-{random}'; style-src 'self' 'unsafe-inline'`

Ajouts conditionnels selon fournisseur analytics (domaines GA, Plausible, Matomo, GTM).

### 10.3 Uploads

- Stockage `public/uploads/` avec `.htaccess` désactivant PHP (ceinture + bretelles)
- Nom fichier `uuid.ext` (jamais le nom original)
- Validation MIME réelle `finfo_file` + magic bytes
- Taille max configurable, 10 Mo par défaut

### 10.4 Logs

- `storage/logs/admin.log` : actions admin (qui, quoi, quand, IP)
- `storage/logs/auth.log` : login réussi / échoué
- `storage/logs/app.log` : erreurs applicatives
- Rotation journalière, rétention 30 jours

### 10.5 Backup

Cron Plesk quotidien : `mysqldump` + archive `/uploads/` → rotation 7 jours locale + snapshot OVH hebdomadaire.

### 10.6 Hygiène

- `composer audit` avant push (CI local ou rappel dans `CLAUDE.md`)
- `.env` jamais committé
- `vendor/` commité ? Non : `composer install` sur serveur via `deploy.sh`

---

## 11. Déploiement (Plesk / VPS OVH)

### 11.1 Configuration initiale par projet

1. Créer le site dans Plesk, domaine + Let's Encrypt auto
2. Document root → `public/`
3. PHP 8.2+, activer OPcache, `expose_php=Off`
4. Connecter le repo Git via Plesk (branch `main`, deploy automatique)
5. Déposer `deploy.sh` en script post-deploy :
   ```bash
   composer install --no-dev --optimize-autoloader
   php scripts/migrate.php
   php scripts/cache-clear.php
   chmod -R 775 storage/
   ```
6. Créer la base MySQL via Plesk, renseigner credentials dans `.env` (via interface Plesk, jamais dans le repo)
7. Activer les backups cron Plesk

### 11.2 Flux quotidien

- Dev local : `php -S localhost:8000 -t public/` + `npm run dev` (Tailwind watch)
- `git push origin main` → Plesk tire → `deploy.sh` → site à jour en ~30s
- Rollback : `git revert` + push, ou Plesk "Deploy previous version"

### 11.3 Gitignore

```
.env
vendor/
node_modules/
storage/cache/*
storage/sessions/*
storage/logs/*
public/uploads/*
!*/.gitkeep
```

---

## 12. `CLAUDE.md` du projet

Fichier à la racine, lu par Claude Code à chaque session. Contient :

- **Contexte** : "Projet basé sur voila-cms — site vitrine pour {client}"
- **⚠️ Directive en tête** : *"Avant toute modification, consulte `PROJECT_MAP.md` pour repérer les fichiers concernés."*
- **Source de vérité** : référence à `_starter/brief.json`
- **Prompts modulaires** : référence à `_starter/prompts/`
- **Convention de code** : PHP 8.2 strict types, PSR-12, pas de framework, structure existante à respecter
- **Interdictions explicites** : ne pas créer de fichiers en racine, ne pas installer de deps Composer sans validation, jamais commit `.env`, ne pas modifier `vendor/`
- **Workflow** : scaffold depuis `brief.json` + `_inputs/`, demander confirmation avant suppression, lancer migrations après création de tables, **mettre à jour `PROJECT_MAP.md` si on ajoute/supprime un module ou une page**
- **Test local** : commandes `php -S localhost:8000 -t public/` + `npm run dev`
- **Qualité** : respecter charte (couleurs/polices de `brief.json`), alt text sur toutes les images, SEO meta sur toutes les pages

---

## 13. `PROJECT_MAP.md` — index d'orientation rapide pour Claude

### 13.1 Rôle

Fichier court (< 200 lignes) à la racine du projet, généré par Claude au scaffolding et maintenu à jour. Il répond à la question *"quand on veut modifier X, on touche quoi ?"* et permet à Claude de trouver le bon fichier en 1 lecture au lieu de 3-4 explorations — utile à chaque demande de modification post-livraison.

### 13.2 Contenu (sections fixes)

- **Stack** (rappel 1 ligne)
- **Tâches fréquentes → fichiers** : tables de correspondance par domaine
  - Front public (header, footer, chaque page statique, 404, bannière cookies)
  - Design / charte (Tailwind, polices, composants, CSS custom)
  - Modules dynamiques (pour chaque module activé : front-list, front-single, admin)
  - Backoffice (layout, dashboard, settings, login, styles admin)
  - SEO (service, SchemaBuilder, sitemap, robots)
  - Analytics (fournisseur, tags custom)
  - Images (presets, helper)
  - Sécurité (headers, rate limit, sessions)
  - Base de données (migrations, modèles, seeds)
  - Emails (templates, config SMTP)
  - Config & déploiement (env, routes, deploy.sh, modules activés)
- **Modules activés** sur ce projet (généré depuis `brief.json`)
- **Pages statiques — blocs éditables** (généré depuis `brief.json`)
- **Commandes utiles** (dev local, build, migrations, audit)

### 13.3 Génération et maintenance

- **Au scaffolding** : Claude génère `PROJECT_MAP.md` adapté au projet en lisant `brief.json` (n'inclut que les modules réellement activés, liste les blocs statiques choisis)
- **À chaque modification structurelle** (ajout/retrait d'un module, nouvelle page, nouveau service) : Claude met à jour le fichier dans le même commit — imposé par `CLAUDE.md`
- **Versionné dans git** : toujours synchrone avec le code

### 13.4 Référencement

Le `CLAUDE.md` du projet contient en tête la directive : *"Avant toute modification, consulte `PROJECT_MAP.md` pour repérer les fichiers concernés."*

---

## 14. Périmètre hors V1

Explicitement **non inclus** dans cette première version (à documenter comme extensions futures possibles) :

- Multilingue (FR only en V1)
- Multi-utilisateurs backoffice (1 admin unique en V1)
- Médiathèque centralisée (upload par champ en V1)
- Système brouillon / workflow validation (publication directe en V1)
- Historique de versions / audit log détaillé (logs simples en V1)
- Prévisualisation avant publication (V1 : publication directe)
- Module E-commerce (pas dans la bibliothèque standard)
- Module Événements / Agenda (candidat pour V2)
- Content types configurables (V1 : uniquement modules pré-codés)

---

## 15. Critères de succès

La V1 est considérée livrée quand :

1. Cloner le starter + remplir un brief → site local fonctionnel en < 30 min (brief + génération Claude inclus)
2. 8 modules standards opérationnels (CRUD complet, front + back)
3. Pipeline images Glide fonctionnel avec cache
4. Pipeline SEO auto (title, meta, OG, JSON-LD, sitemap, robots)
5. Bannière consentement + Consent Mode v2 GA4
6. Déploiement Plesk en 1 `git push` après config initiale
7. Score PageSpeed Insights mobile ≥ 90 sur un site de démo
8. Aucune vulnérabilité critique `composer audit`
9. Documentation README claire (setup local + nouveau projet + déploiement)
10. Un projet test complet de bout en bout validé (démarrage → mise en ligne)
