# Instructions Claude Code — voila-cms

## ⚠️ Avant toute modification

Consulte `PROJECT_MAP.md` pour trouver les fichiers concernés par une demande. Ne pars pas en exploration aveugle.

## Contexte

Site vitrine basé sur voila-cms. Stack figée : PHP 8.2 natif + MySQL + Twig + Tailwind.

## Source de vérité

`_starter/brief.json` (quand le kit est utilisé pour un projet client) décrit le projet.

## Convention de code

- PHP 8.2, `declare(strict_types=1)` en tête de chaque fichier
- PSR-12, PSR-4 (namespace `App\`)
- Pas de framework, respecter la structure existante
- Twig pour tous les rendus HTML — jamais d'echo direct dans les contrôleurs
- Tout input utilisateur passe par PDO prepared statements
- CSRF token sur TOUS les formulaires POST

## Interdictions

- Ne jamais committer `.env`
- Ne pas créer de fichiers en racine sans nécessité
- Ne pas ajouter une dépendance Composer sans validation
- Ne pas modifier `vendor/` ou `node_modules/`

## Workflow modifications

- Toujours lire `PROJECT_MAP.md` avant de toucher un fichier
- Si une modification structurelle (nouveau module, nouvelle page, nouveau service), **mettre à jour `PROJECT_MAP.md` dans le même commit**
- Lancer les migrations après création de tables : `php scripts/migrate.php`
- Tester en local avant push : `composer test` + `php -S localhost:8000 -t public/`

## Qualité

- Alt text obligatoire sur toutes les images
- Responsive (mobile first)
- Respecter la charte définie dans `tailwind.config.js`
