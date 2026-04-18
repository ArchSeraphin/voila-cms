# PROJECT_MAP — voila-cms (starter)

## Stack (rappel)

PHP 8.2 • MySQL • Twig • Tailwind • Alpine (à venir)

## Tâches fréquentes → fichiers à modifier

### Front public

| Je veux modifier… | Fichier(s) |
|---|---|
| Header / navigation | `templates/partials/header.html.twig` |
| Footer | `templates/partials/footer.html.twig` |
| Page d'accueil | `templates/front/home.html.twig` + `app/Controllers/Front/HomeController.php` |
| Page 404 | `templates/front/404.html.twig` |

### Design / charte

| Je veux modifier… | Fichier(s) |
|---|---|
| Couleurs | `tailwind.config.js` (section `theme.extend.colors`) |
| Polices | `tailwind.config.js` + `templates/layouts/base.html.twig` |
| Styles globaux | `public/assets/css/app.css` |

### Backoffice

| Je veux modifier… | Fichier(s) |
|---|---|
| Layout admin | `templates/layouts/admin.html.twig` + `templates/partials/admin-sidebar.html.twig` |
| Dashboard | `templates/admin/dashboard.html.twig` + `app/Controllers/Admin/DashboardController.php` |
| Login | `templates/admin/login.html.twig` + `app/Controllers/Admin/AuthController.php` |

### Sécurité

| Je veux modifier… | Fichier(s) |
|---|---|
| Headers HTTP (CSP…) | `app/Middleware/SecurityHeaders.php` |
| Rate limit | `app/Middleware/RateLimit.php` + `app/Services/RateLimiter.php` |
| Durée de session | `app/Core/Session.php` + `.env` (`SESSION_LIFETIME`) |

### Base de données

| Je veux… | Fichier(s) |
|---|---|
| Ajouter une migration | Nouveau fichier `database/migrations/0XX_description.sql` |
| Exécuter migrations | `php scripts/migrate.php` |

### Config & déploiement

| Je veux… | Fichier(s) |
|---|---|
| Ajouter une variable d'env | `.env` + `.env.example` |
| Modifier une route | `config/routes.php` |
| Modifier le script déploiement | `deploy.sh` |
| Build Tailwind prod | `npm run build` (via `build.sh`) |

### SEO / méta

| Je veux modifier… | Fichier(s) |
|---|---|
| Règles title/description auto | `app/Services/Seo.php` |
| Types Schema.org | `app/Services/SchemaBuilder.php` |
| Partial meta tags front | `templates/partials/seo-meta.html.twig` |
| Partial JSON-LD front | `templates/partials/schema-jsonld.html.twig` |
| Sitemap dynamique | `app/Controllers/SitemapController.php` |
| Robots.txt | `public/robots.txt` |

### Images

| Je veux… | Fichier(s) |
|---|---|
| Ajouter / modifier un preset | `config/images.php` |
| Modifier le helper `{{ img() }}` | `app/Core/View.php` (méthode `renderImg`) |
| Modifier la validation d'upload | `app/Services/ImageService.php` |
| Modifier la signature des URLs | `app/Services/Glide.php` |
| Changer le chemin cache Glide | `storage/cache/glide/` (géré par deploy.sh) |
| Endpoint serveur images | `app/Controllers/Front/MediaController.php` |

### Analytics & consentement RGPD

| Je veux… | Fichier(s) |
|---|---|
| Changer le fournisseur (GA4/Plausible/Matomo/GTM) | Table `settings`, clé `analytics_provider` (en attendant Settings admin UI, via MySQL direct) |
| Modifier la bannière | `templates/partials/consent-banner.html.twig` |
| Modifier le partial analytics | `templates/partials/analytics.html.twig` |
| Modifier la page politique cookies | `templates/front/cookies-policy.html.twig` |
| Logique consentement (catégories, cookie) | `app/Services/Consent.php` |

### Settings (configuration applicative)

| Je veux… | Fichier(s) |
|---|---|
| Lire/écrire une clé | `app/Services/Settings.php` |
| Ajouter une clé par défaut | Nouvelle migration `database/migrations/0XX_seed_YYY.sql` |
| Voir toutes les clés | Table `settings` en MySQL |

### Système de modules

| Je veux… | Fichier(s) |
|---|---|
| Activer / désactiver un module | `config/modules.php` (liste des slugs actifs) |
| Ajouter un module custom | Créer `app/modules/{slug}/` avec `module.json`, `routes.php`, `Model.php`, `AdminController.php`, `FrontController.php` puis ajouter le slug dans `config/modules.php` et créer la migration `0XX_create_{slug}.sql` |
| Loader + registre | `app/Core/ModuleRegistry.php` |
| Namespace PSR-4 d'un module | `composer.json` → bloc `autoload.psr-4` (ajouter `App\\Modules\\{Slug}\\` ⇒ `app/modules/{slug}/`) puis `composer dump-autoload` |

### Settings admin (réglages via UI)

| Je veux modifier… | Fichier(s) |
|---|---|
| Contenu d'un onglet Réglages | `templates/admin/settings/{site|contact|seo|analytics}.html.twig` |
| Layout Réglages (tabs) | `templates/admin/settings/layout.html.twig` |
| Champs autorisés par onglet | `app/Controllers/Admin/SettingsController.php` (`TAB_FIELDS`) |
| Ajouter un onglet | Ajouter un template + entrée dans `TABS` et `TAB_FIELDS` dans `SettingsController` |

### Compte admin

| Je veux… | Fichier(s) |
|---|---|
| Formulaire "Mon compte" | `templates/admin/account.html.twig` |
| Logique changement mot de passe | `app/Controllers/Admin/AccountController.php` |

### Upload d'images via l'admin

| Je veux… | Fichier(s) |
|---|---|
| Endpoint upload | `app/Controllers/Admin/UploadController.php` (POST `/admin/upload`) |
| JS côté form (fetch + preview) | fonction `voilaUpload()` inline dans les templates de formulaire module |

### Éditeur riche (TinyMCE)

| Je veux… | Fichier(s) |
|---|---|
| Configurer TinyMCE | Bloc `<script>tinymce.init(...)</script>` dans `templates/layouts/admin.html.twig` |
| Activer sur un textarea | Ajouter la classe `js-tinymce` au `<textarea>` |
| Assets TinyMCE | `public/assets/vendor/tinymce/` (self-hosted, ~5 Mo) |

### Module Actualités (référence)

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/009_create_actualites.sql` |
| Modèle (requêtes PDO) | `app/modules/actualites/Model.php` |
| Admin CRUD | `app/modules/actualites/AdminController.php` |
| Admin templates | `templates/admin/modules/actualites/{list,form}.html.twig` |
| Front list + détail | `app/modules/actualites/FrontController.php` + `templates/front/actualites/{list,single}.html.twig` |
| Routes (admin + front) | `app/modules/actualites/routes.php` |
| Manifest | `app/modules/actualites/module.json` |

### Sitemap dynamique (étendu)

| Je veux… | Fichier(s) |
|---|---|
| Étendre pour un nouveau module | `app/Controllers/SitemapController.php` (ajouter un bloc conditionnel sur `$reg->has('{slug}')`) |

### Navigation / dashboard

| Je veux… | Fichier(s) |
|---|---|
| Liens dans la nav header front | `templates/partials/header.html.twig` (itère `admin_modules` pour ceux avec `has_detail`) |
| Sidebar admin (modules dynamiques) | `templates/partials/admin-sidebar.html.twig` (itère `admin_modules`) |
| Stats dashboard | `app/Controllers/Admin/DashboardController.php` + `templates/admin/dashboard.html.twig` |

### Pagination

| Je veux… | Fichier(s) |
|---|---|
| Helper Paginator (math offset/limit) | `app/Core/Paginator.php` |
| Usage dans un listing | Instancier `new Paginator($total, $perPage, $page)` puis lire `.offset`, `.lastPage`, `.hasPrev/hasNext` dans le template |

### FileService (uploads PDF)

| Je veux… | Fichier(s) |
|---|---|
| Changer limite taille PDF | `config/uploads.php` (`max_size_bytes`) |
| Logique validation PDF (magic bytes) | `app/Services/FileService.php` |
| Routing image vs PDF | `app/Controllers/Admin/UploadController.php` (dispatch par MIME) |

### Module Partenaires

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/010_create_partenaires.sql` |
| Modèle | `app/modules/partenaires/Model.php` |
| Admin CRUD | `app/modules/partenaires/AdminController.php` |
| Front (grille logos) | `app/modules/partenaires/FrontController.php` + `templates/front/partenaires/list.html.twig` |
| Templates admin | `templates/admin/modules/partenaires/{list,form}.html.twig` |

### Module Équipe

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/011_create_equipe.sql` |
| Modèle + Admin + Front | `app/modules/equipe/{Model,AdminController,FrontController}.php` |
| Templates | `templates/admin/modules/equipe/` + `templates/front/equipe/list.html.twig` |

### Module Témoignages

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/012_create_temoignages.sql` |
| Modèle + Admin + Front | `app/modules/temoignages/{Model,AdminController,FrontController}.php` |
| Templates (avec étoiles) | `templates/admin/modules/temoignages/` + `templates/front/temoignages/list.html.twig` |

### Module FAQ (avec FAQPage JSON-LD)

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/013_create_faq.sql` |
| Modèle + Admin + Front | `app/modules/faq/{Model,AdminController,FrontController}.php` |
| Templates (accordion `<details>`) | `templates/admin/modules/faq/` + `templates/front/faq/list.html.twig` |
| JSON-LD FAQPage | Généré par `FrontController` via `SchemaBuilder::faq()` |

### Module Documents (PDF)

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/014_create_documents.sql` |
| Modèle + Admin + Front | `app/modules/documents/{Model,AdminController,FrontController}.php` |
| Templates (upload PDF, liste téléchargement) | `templates/admin/modules/documents/` + `templates/front/documents/list.html.twig` |
| Upload PDF | Routé par `UploadController` vers `FileService` selon MIME |

### Module Services (avec Service JSON-LD)

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/015_create_services.sql` |
| Modèle + Admin + Front | `app/modules/services/{Model,AdminController,FrontController}.php` |
| Admin (slug + SEO + TinyMCE) | Pattern Actualités, voir `AdminController.php` |
| Front (liste + détail) | `templates/front/services/{list,single}.html.twig` |
| JSON-LD Service | `SchemaBuilder::service()` appelé par `FrontController::show()` |

### Module Réalisations (avec gallery + CreativeWork JSON-LD)

| Je veux modifier… | Fichier(s) |
|---|---|
| Schéma BDD | `database/migrations/016_create_realisations.sql` (gallery_json JSON) |
| Modèle | `app/modules/realisations/Model.php` (avec `listCategories()`) |
| Admin CRUD + gallery | `app/modules/realisations/AdminController.php` + `templates/admin/modules/realisations/form.html.twig` (avec `voilaGalleryUpload` JS multi-fichiers) |
| Front (liste + filtre catégorie + détail + gallery) | `app/modules/realisations/FrontController.php` + `templates/front/realisations/{list,single}.html.twig` |
| JSON-LD CreativeWork | `SchemaBuilder::creativeWork()` appelé par `FrontController::show()` |

### Mailer (SMTP)

| Je veux… | Fichier(s) |
|---|---|
| Configurer SMTP | `.env` (MAIL_*) |
| Changer la config mail | `config/mail.php` |
| Service mail wrapper | `app/Core/Mailer.php` |
| Template email reset mot de passe | `templates/emails/password-reset.html.twig` |
| Template email notification contact | `templates/emails/contact-notification.html.twig` |

### Password reset

| Je veux… | Fichier(s) |
|---|---|
| Logique tokens | `app/Services/PasswordReset.php` |
| UI forgot + reset | `templates/admin/auth/{forgot,reset}.html.twig` |
| Controller forgot/reset | `app/Controllers/Admin/AuthController.php` |
| TTL (30 min) | Config directement dans `PasswordReset::__construct` |

### Pages statiques éditables (page_block)

| Je veux… | Fichier(s) |
|---|---|
| Déclarer les blocs d'une page | `config/pages.php` |
| Lire un bloc dans un template | `{{ page_block('home', 'hero_title', 'Défaut') }}` |
| Service | `app/Services/PagesBlocks.php` |
| UI admin (liste + édition) | `app/Controllers/Admin/PagesController.php` + `templates/admin/pages/{list,edit}.html.twig` |
| Seed des blocs défaut | `database/migrations/018_seed_default_pages_blocks.sql` |

### Pages front statiques

| Je veux modifier… | Fichier(s) |
|---|---|
| Accueil | `templates/front/home.html.twig` (utilise `page_block()`) |
| À propos | `templates/front/about.html.twig` + `app/Controllers/Front/AboutController.php` |
| Mentions légales | `templates/front/legal.html.twig` + `app/Controllers/Front/LegalController.php` |
| Contact | `templates/front/contact.html.twig` + `app/Controllers/Front/ContactController.php` |

### Formulaire de contact

| Je veux… | Fichier(s) |
|---|---|
| Champs du formulaire | `templates/front/contact.html.twig` |
| Validation + stockage + envoi email | `app/Controllers/Front/ContactController::submit()` |
| Anti-bot (honeypot) | Champ `website` caché dans le form, rejet silencieux si rempli |
| Rate limiting | `app/Middleware/RateLimit.php` — paths `/admin/login` + `/contact` |

### Messages admin (inbox)

| Je veux… | Fichier(s) |
|---|---|
| Liste + détail + suppression | `app/Controllers/Admin/MessagesController.php` |
| Templates | `templates/admin/messages/{list,show}.html.twig` |
| Table | `contact_messages` (migration 006) |

### Brief scaffolding tooling

| Je veux… | Fichier(s) |
|---|---|
| Formulaire brief | `_starter/brief.html` |
| Lancer le serveur brief | `./scripts/brief.sh` (port 9000 par défaut, override avec `BRIEF_PORT=9001`) |
| Endpoint sauvegarde | `_starter/save.php` |
| Schéma JSON | `_starter/brief.json.example` |
| Prompts scaffolding | `_starter/prompts/00-scaffold.md`, `01-refonte.md`, `02-module-customization.md` |

## Sections à compléter (plans futurs)

- [Plan 06] Maintenance & Hardening (upgrade Glide/Intervention, 2FA TOTP, HEAD→GET router, CSRF rate-limit, Slug transliterator, admin image Glide preview)

### Setup local (Herd + MAMP)

| Je veux… | Fichier(s) |
|---|---|
| Setup complet d'un nouveau projet | `./scripts/init.sh` (env + DB + composer + npm + migrations + build) |
| Modifier les valeurs par défaut DB locale | `scripts/init.sh` (vars `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`) |

## Commandes utiles

- Setup initial : `./scripts/init.sh`
- Serveur dev : Herd auto (`https://<dossier>.test`) — ou `composer serve` en fallback
- Tailwind watch : `npm run dev`
- Tailwind build prod : `npm run build`
- Migrations : `php scripts/migrate.php`
- Créer admin : `php scripts/create-admin.php email@domain`
- Clear cache : `php scripts/cache-clear.php`
- Tests : `composer test`
- Audit sécu : `composer audit`
