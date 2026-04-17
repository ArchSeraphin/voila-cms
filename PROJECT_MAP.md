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

## Sections à compléter (plans futurs)

- [Plan 02] Pipelines transverses : Glide, SEO, Sitemap, Analytics, Consent
- [Plan 03] Système de modules + Actualités/Partenaires/Réalisations
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
