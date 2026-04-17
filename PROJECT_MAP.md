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

## Sections à compléter (plans futurs)

- [Plan 03] Système de modules + Actualités/Partenaires/Réalisations + Settings admin UI
- [Plan 04] Modules Équipe, Témoignages, Services, FAQ, Documents
- [Plan 05] Outillage brief & scaffolding

## Commandes utiles

- Serveur dev : `composer serve` (ou `php -S localhost:8000 -t public/`)
- Tailwind watch : `npm run dev`
- Tailwind build prod : `npm run build`
- Migrations : `php scripts/migrate.php`
- Créer admin : `php scripts/create-admin.php email@domain`
- Clear cache : `php scripts/cache-clear.php`
- Tests : `composer test`
- Audit sécu : `composer audit`
