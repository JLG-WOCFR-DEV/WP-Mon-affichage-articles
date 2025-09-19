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

## Tests manuels

Pour vérifier la prise en charge d'un slug de taxonomie égal à `"0"` :

1. Créez ou identifiez une catégorie (ou tout terme de la taxonomie utilisée) dont le slug vaut exactement `0`.
2. Configurez un module **Tuiles – LCV** afin qu'il utilise ce terme comme valeur par défaut et activez, si besoin, le filtre de catégories en frontal.
3. Affichez le module côté public et vérifiez que les articles associés au terme `0` apparaissent bien, que le filtre est sélectionné et que la pagination/chargement additionnel respecte ce terme.

## Crédits

Développé par LCV.

Publié sous licence [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.txt).
