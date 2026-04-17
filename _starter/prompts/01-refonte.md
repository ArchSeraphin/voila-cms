# Mode REFONTE — scraping + reprise de contenu existant

Si `brief.json.mode === 'refonte'` et qu'une `source_url` est fournie.

## 1. Scraper le site existant

Utilise WebFetch ou Playwright (si dispo via MCP) pour extraire :

- **Pages principales** : accueil, à propos, services, contact, mentions légales
- **Pages secondaires** : réalisations, actualités, équipe (si présentes)
- **Screenshots** : desktop (1280×) + mobile (375×) pour chaque page

Stocke dans :
- `_inputs/refonte/pages-scrapees/` — HTML nettoyé
- `_inputs/refonte/captures/{page}-{desktop,mobile}.png`
- `_inputs/refonte/textes-extraits.md` — texte converti en Markdown

## 2. Analyser le contenu existant

Avant de scaffolder :
- Liste les rubriques du menu actuel — détermine quels modules activer
- Repère le ton éditorial (vouvoiement / tutoiement, longueur des phrases)
- Note les mots-clés récurrents pour le SEO
- Identifie les contenus à **garder** vs **réécrire**

Rapporte cette analyse à l'utilisateur AVANT d'aller plus loin (1 paragraphe par page scrapée).

## 3. Reprendre les contenus dans le nouveau site

Pour chaque page statique avec contenu repris :
- Place les textes dans les blocs éditables correspondants (via `UPDATE static_pages_blocks`)
- Ou code en dur dans les templates si bloc non éditable

Pour les modules avec contenu (actualités, réalisations, services) :
- Insère les entrées via `INSERT INTO actualites/realisations/services (...)`
- Télécharge les images associées dans `public/uploads/{année}/{mois}/`
- Le champ `image`/`cover_image` prend le chemin relatif `uploads/...`

## 4. Ne pas faire

- Ne recrée pas une copie pixel-perfect. Le design doit être moderne (voila-cms défaut) avec la charte du client.
- Ne copie pas aveuglément les textes SEO (metadata) — ré-évalue-les par rapport aux nouveaux mots-clés du brief.

## 5. Spécificités par type de contenu

Si l'ancien site a :
- un **blog / actus** → module `actualites`
- des **réalisations / portfolio** → module `realisations`
- une **page équipe** → module `equipe`
- des **témoignages clients** → module `temoignages`
- une **page services détaillée** → module `services`
- une **page FAQ** → module `faq`

Coche-les dans `config/modules.php` (en plus de ce qui était déjà dans `brief.json.modules`).
