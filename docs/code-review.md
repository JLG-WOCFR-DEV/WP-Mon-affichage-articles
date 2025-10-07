# Revue de code

## Points forts
- L'architecture du plugin est modulaire : chaque responsabilité (shortcode, assets, REST) est isolée dans une classe dédiée, ce qui facilite la maintenance.
- Le socle CSS expose de nombreux jetons (`--my-articles-*`) et adopte `clamp()` pour gérer la fluidité typographique, ce qui offre une bonne base pour construire des thèmes personnalisés.

## Correctifs appliqués
- Protection supplémentaire dans la méthode `sanitize_scalar_argument()` pour éviter toute invocation d'un sanitizer non callable (ex. extensions tierces injectant un mauvais callback). Sans cette vérification, WordPress lèverait une erreur fatale avant même que la requête AJAX ne soit traitée.
- Correction d'un bug CSS introduit lors de la refonte : en vue liste, l'image était contrainte par `aspect-ratio` et ne s'étirait plus sur toute la hauteur de la carte. Le wrapper et l'image sont désormais forcés à `height:100%` (et réinitialisés en mobile) ce qui restaure la parité visuelle avec la description.

## Améliorations proposées
1. **Fiabiliser le fallback des permaliens** : `get_permalink()` peut retourner `false` pour un contenu non publié. Dans `render_article_common_block()` on pourrait prévoir un `if ( ! $permalink )` pour désactiver l'ancre ou afficher un message, afin d'éviter des liens vides.
2. **Traductions composées** : l'affichage de l'auteur repose sur `printf('%s %s', __('par'), get_the_author())`. Utiliser `sprintf( __( 'par %s', 'mon-articles' ), ... )` laisserait davantage de latitude aux traducteurs (certaines langues inversent l'ordre).
3. **Test de non-régression pour le cache** : la logique d'invalidation dépend de `my_articles_get_cache_tracked_post_types()`. Ajouter un test unitaire/fonctionnel garantissant qu'un type personnalisé filtré invalide bien le cache évitera les régressions quand de nouveaux hooks seront ajoutés.
4. **Squelettes personnalisables** : les placeholders HTML/CSS sont actuellement générés côté PHP sans thème alternatif. En externalisant la structure dans un template (ou en exposant plus de variables CSS), on faciliterait la création de skins skeleton via le Customizer ou le bloc.

## Suivi visuel
Un gabarit statique a été ajouté dans `docs/visual-debug.html` afin de tester rapidement les variations de styles sans avoir à lancer WordPress. Ce fichier référence directement les assets du plugin et peut être intégré à la CI visuelle (Percy, Loki, …).
