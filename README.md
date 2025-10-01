# Tuiles - LCV

Affiche les articles d'une catégorie spécifique via un shortcode, avec un design personnalisable.

## Installation

1. Copier le dossier `mon-affichage-article` dans le répertoire `wp-content/plugins/` de votre installation WordPress.
2. Activer **Tuiles – LCV** depuis le menu **Extensions** de l'administration WordPress.

## Utilisation

Utiliser le shortcode :

```php
[mon_affichage_articles id="123"]
```

## Utilisation dans l'éditeur de blocs

1. Depuis l'éditeur Gutenberg, ajouter le bloc **Tuiles – LCV**.
2. Sélectionner l'instance `mon_affichage` à afficher via le panneau latéral.
3. Ajuster les principaux réglages (mode d'affichage, filtres, pagination...) depuis les contrôles du bloc.
4. Utiliser le champ de recherche du panneau **Module** pour retrouver un contenu `mon_affichage`. Les résultats sont chargés par lots (20 éléments) depuis l'API REST et le bouton « Charger plus de résultats » permet de parcourir l'ensemble des contenus disponibles.

### Attributs disponibles dans l'éditeur

- **display_mode** (`grid`, `list`, `slideshow`)
- **posts_per_page** (nombre d'articles, `0` pour illimité)
- **pagination_mode** (`none`, `load_more`, `numbered`)
- **show_category_filter** (activation du filtre par taxonomie et alignement associé)
- **show_category**, **show_author**, **show_date** (affichage des métadonnées)
- **show_excerpt** et **excerpt_length** (affichage et longueur de l'extrait)

Options principales :

- **Filtre de catégorie** : afficher une liste de catégories si l'option est activée.
- **Pagination** : modes `load_more` (bouton « Charger plus ») ou `numbered` (pagination classique).
- **Articles épinglés** : possibilité d'épingler certains articles, avec option d'ignorer les filtres.
- **Lazy load** : chargement différé des images pour optimiser les performances.

> ℹ️ **Diaporama et mode illimité** : lorsque `display_mode` vaut `slideshow`, la récupération des contenus respecte toujours le plafond défini par l'option `unlimited_query_cap` (50 par défaut via le filtre `my_articles_unlimited_batch_size`). Cela évite de charger un nombre excessif d'articles d'un coup tout en conservant un mode quasi illimité.

## Structure du projet

```
mon-affichage-article/
├── includes/  # Classes PHP
├── assets/    # JS et CSS
└── mon-affichage-articles.php
```

## Tests

- **Tests PHP** : `composer test`
- **Tests JS** : `npm run test:js`

## Accessibilité

- Le mode diaporama expose désormais un carrousel conforme aux recommandations ARIA (région labellisée, boutons de navigation et pagination explicitement décrits).
- La navigation clavier est activée par défaut dans Swiper et les messages d’assistance sont personnalisés pour les lecteurs d’écran.

## Hooks AJAX

- `filter_articles`
- `load_more_articles`
- `search_posts_for_select2`

## Tests manuels

Pour vérifier la prise en charge d'un slug de taxonomie égal à `"0"` :

1. Créez ou identifiez une catégorie (ou tout terme de la taxonomie utilisée) dont le slug vaut exactement `0`.
2. Configurez un module **Tuiles – LCV** afin qu'il utilise ce terme comme valeur par défaut et activez, si besoin, le filtre de catégories en frontal.
3. Affichez le module côté public et vérifiez que les articles associés au terme `0` apparaissent bien, que le filtre est sélectionné et que la pagination/chargement additionnel respecte ce terme.

Pour valider la prise en compte des réglages globaux :

1. Modifiez un ou plusieurs réglages dans le menu **Tuiles – LCV** (par exemple le mode d'affichage ou les couleurs).
2. Créez un nouveau contenu de type **mon_affichage** sans surcharger ces options dans la metabox.
3. Affichez le shortcode correspondant en frontal et vérifiez que le rendu reflète les réglages globaux enregistrés.

Pour valider la gestion d'un identifiant d'instance invalide lors des appels AJAX :

1. Déclenchez les actions `filter_articles` et `load_more_articles` avec un identifiant qui ne correspond pas à un contenu de type `mon_affichage`.
2. Vérifiez que la réponse est une erreur JSON comportant un code HTTP `400` et le message « Type de contenu invalide pour cette instance. ».

## Crédits

Développé par LCV.

Publié sous licence [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.txt).
