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

Options principales :

- **Filtre de catégorie** : afficher une liste de catégories si l'option est activée.
- **Pagination** : modes `load_more` (bouton « Charger plus ») ou `numbered` (pagination classique).
- **Articles épinglés** : possibilité d'épingler certains articles, avec option d'ignorer les filtres.
- **Lazy load** : chargement différé des images pour optimiser les performances.

## Structure du projet

```
mon-affichage-article/
├── includes/  # Classes PHP
├── assets/    # JS et CSS
└── mon-affichage-articles.php
```

## Hooks AJAX

- `filter_articles`
- `load_more_articles`
- `search_posts_for_select2`

## Crédits

Développé par LCV.

Publié sous licence [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.txt).
