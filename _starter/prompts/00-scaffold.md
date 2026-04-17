# Scaffolding voila-cms — prompt détaillé

Ce fichier est référencé par le prompt copié depuis `_starter/brief.html`. Il te guide pour scaffolder un nouveau site voila-cms à partir de `_starter/brief.json`.

## 1. Lire les inputs

- `_starter/brief.json` — configuration complète
- `_inputs/charte/` — logo, favicon, éventuellement charte.pdf (pour contexte visuel)
- `_inputs/photos/` — photos fournies par le client (hero, équipe, réalisations…)
- `_inputs/textes/contenus.md` — contenus rédactionnels (bio, descriptifs services, etc.)

## 2. Activer les modules

Édite `config/modules.php` pour ne lister QUE les modules cochés dans `brief.json.modules`. Exemple :

```php
return ['actualites', 'realisations', 'services'];
```

Si certains modules ne sont PAS activés, tu n'as rien à supprimer — le code existe mais ne sera pas chargé.

## 3. Appliquer la charte Tailwind

Édite `tailwind.config.js` :

- Remplace `theme.extend.colors.primary` par `brief.charte.color_primary`
- Remplace `theme.extend.colors.secondary` par `brief.charte.color_secondary`
- Remplace `theme.extend.colors.accent` par `brief.charte.color_accent`
- Met `fontFamily.display` à `['<font_title>', 'sans-serif']`
- Met `fontFamily.sans` à `['<font_body>', 'sans-serif']`

Puis édite `templates/layouts/base.html.twig` et `templates/layouts/admin.html.twig` : ajoute les `<link>` Google Fonts pour les deux polices dans `<head>`, avant le `<link>` vers `app.compiled.css`.

Ensuite : `npm run build`.

## 4. Adapter les blocs éditables

Édite `config/pages.php` pour ne conserver que les blocs listés dans `brief.static_pages_blocks`. Pour chaque page (home/about/contact/legal), garde uniquement les clés listées. Les blocs retirés deviennent des parties FIGÉES (à coder en dur dans les templates front).

Pour les blocs retirés, édite directement les templates correspondants (`templates/front/home.html.twig` etc.) pour y mettre le contenu en dur adapté au ton éditorial et aux contenus fournis.

## 5. Appliquer les settings

Exécute des `UPDATE settings SET value=? WHERE key=?` pour chaque clé listée dans la section "Settings par défaut" du prompt principal.

Pour `logo_path` et `favicon_path` : après avoir uploadé logo.svg et favicon.png depuis `_inputs/charte/` vers `public/uploads/`, renseigne les chemins dans `settings.site_logo_path` et `settings.site_favicon_path`.

## 6. Adapter les templates front

Les templates existent et fonctionnent par défaut. Adapte les parties figées :
- **home** : hero visuel (image d'intro), section services/réalisations inline si module non activé
- **about** : ajoute photos équipe, valeurs
- **services, actualités, etc.** : laisse le pattern par défaut

Selon le ton éditorial :
- `pro` : phrases courtes, factuel, vouvoiement
- `chaleureux` : tutoiement possible, anecdotes, tournures humaines
- `créatif` : métaphores, titres accrocheurs, formulations originales
- `institutionnel` : formalisme, références au secteur, ton sobre

## 7. Créer l'admin initial

```bash
php scripts/create-admin.php admin@{domain}
```

Note le mot de passe dans un endroit sûr (1Password / gestionnaire).

## 8. Mettre à jour PROJECT_MAP.md

Supprime de `PROJECT_MAP.md` les lignes des modules NON activés (sections "Module X"). Garde le reste.

Ajoute en tête du fichier :
```markdown
# PROJECT_MAP — <nom-client>

Client : <Nom>
Domaine cible : <domain>
Modules actifs : <liste>
```

## 9. Tests fumants

```bash
php -S localhost:8000 -t public/
```

Teste :
- `/` — hero + contenu éditorial
- `/a-propos`
- `/contact` — formulaire fonctionne
- `/mentions-legales`
- Chaque page module activé (ex: `/actualites`, `/services`…)
- `/admin/login` — login fonctionne
- `/sitemap.xml` — contient toutes les bonnes URLs

## 10. Commit & rapport

```bash
git add -A
git commit -m "chore: scaffold <Nom-client>"
```

Rapport final à l'utilisateur :
- Résumé des modules activés
- Charte appliquée (couleurs, polices)
- Credentials admin (à transmettre au client sécuriquement)
- Prochaines étapes : import des contenus, mise en ligne Plesk
