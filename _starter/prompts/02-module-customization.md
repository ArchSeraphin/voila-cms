# Personnalisation par module

Guide pour adapter les templates front des modules selon le ton éditorial / style visuel du projet. Le code par défaut est générique — à toi de l'enrichir.

## Actualités
- Template list : `templates/front/actualites/list.html.twig`
- Template single : `templates/front/actualites/single.html.twig`
- Customs possibles : ajouter une image hero en haut de la liste, afficher les catégories en filtres, ajouter pagination styliée.

## Partenaires
- Template : `templates/front/partenaires/list.html.twig`
- Customs : varier la grille (2/3/4 colonnes), passer d'un effet grayscale à un effet colorisé au hover, regrouper par "catégorie de partenaire".

## Équipe
- Template : `templates/front/equipe/list.html.twig`
- Customs : remplacer les photos rondes par des cartes rectangulaires, ajouter une bio longue cliquable (modale), mettre en avant les liens sociaux.

## Témoignages
- Template : `templates/front/temoignages/list.html.twig`
- Customs : transformer la grille en carousel Alpine.js, mettre en valeur les notes 5★ avec un fond coloré, afficher 3 témoignages sur la home comme widget.

## Services
- Templates : `templates/front/services/list.html.twig` + `single.html.twig`
- Customs : ajouter des icônes (Heroicons / Lucide) en fonction du champ `icone`, afficher un CTA "Demander un devis" → /contact.

## FAQ
- Template : `templates/front/faq/list.html.twig`
- Customs : ajouter un champ de recherche JS pour filtrer les questions.

## Documents
- Template : `templates/front/documents/list.html.twig`
- Customs : ajouter des icônes par catégorie (brochure, CGV, tarifs), regrouper par année.

## Réalisations
- Templates : `templates/front/realisations/list.html.twig` + `single.html.twig`
- Customs : ajouter un lightbox pour la gallery (via Alpine + div absolute), afficher les catégories comme onglets plutôt que pills, ajouter "Projets similaires" en bas du détail.

## Bonnes pratiques

Adaptations guidées par le brief :

- Style **sobre** : espacement généreux, peu de couleurs, typo serif possible
- Style **moderne** : asymétries, gradients subtils, animations discrètes
- Style **premium** : noir + accents dorés, grandes photos, typographie display
- Style **vivant** : couleurs primaires, illustrations, micro-animations

N'écrase JAMAIS la logique PHP (controllers, Model) — adapte UNIQUEMENT les templates Twig et les classes Tailwind.
