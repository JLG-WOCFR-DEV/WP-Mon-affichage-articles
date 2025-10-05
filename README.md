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

- **design_preset** (`custom`, `lcv-classique`, `dark-spotlight`, `editorial-focus`)
  - Contrôle l’application d’un préréglage complet (couleurs, ombres, espacements). Le modèle « Focus éditorial » est verrouillé et fige les réglages associés.
- **display_mode** (`grid`, `list`, `slideshow`)
- **thumbnail_aspect_ratio** (`1`, `4/3`, `3/2`, `16/9`) – contraint les images et squelettes à un ratio précis.
- **posts_per_page** (nombre d'articles, `0` pour illimité)
- **pagination_mode** (`none`, `load_more`, `numbered`)
- **aria_label** (libellé ARIA du module, basé par défaut sur le titre de l’instance sélectionnée)
- **show_category_filter** (activation du filtre par taxonomie et alignement associé)
- **show_category**, **show_author**, **show_date** (affichage des métadonnées)
- **show_excerpt** et **excerpt_length** (affichage et longueur de l'extrait)
- **columns_mobile**, **columns_tablet**, **columns_desktop**, **columns_ultrawide** (colonnes disponibles selon la largeur d'écran)
- **gap_size** (espacement horizontal en mode grille/diaporama) et **list_item_gap** (espacement vertical en mode liste)
- **module_padding_left**, **module_padding_right** (marges internes du module)
- **list_content_padding_top/right/bottom/left** (marges internes des éléments en mode liste)
- **border_radius** (arrondi des cartes), **title_font_size**, **meta_font_size**, **excerpt_font_size** (typographie)
- Couleurs principales : **module_bg_color**, **vignette_bg_color**, **title_wrapper_bg_color**, **title_color**, **meta_color**, **meta_color_hover**, **excerpt_color**, **pagination_color**, **shadow_color**, **shadow_color_hover**, **pinned_border_color**, **pinned_badge_bg_color**, **pinned_badge_text_color**

Les panneaux **Disposition**, **Espacements & typographie** et **Couleurs** regroupent l'ensemble de ces curseurs (RangeControl) et sélecteurs de couleurs. Chaque ajustement met immédiatement à jour l'aperçu (rendu serveur) du bloc pour faciliter la mise au point.

### Préréglages de design

Les modèles intégrés permettent de démarrer rapidement avec des combinaisons cohérentes :

- **Personnalisé** (`custom`) : aucun ajustement automatique, vos réglages manuels sont conservés.
- **Classique LCV** (`lcv-classique`) : fond clair, ombres légères et cartes arrondies.
- **Projecteur sombre** (`dark-spotlight`) : palette foncée à fort contraste pour des mises en avant immersives.
- **Focus éditorial** (`editorial-focus`) : présentation magazine verrouillée (mode liste, extraits activés) pour homogénéiser les modules éditoriaux.

Le panneau « Module » de l’éditeur propose un sélecteur de modèle ; les préréglages verrouillés grisent automatiquement les contrôles concernés.

Options principales :

- **Filtre de catégorie** : afficher une liste de catégories si l'option est activée.
- **Pagination** : modes `load_more` (bouton « Charger plus », avec option d’automatisation) ou `numbered` (pagination classique).
- **Chargement automatique** : en activant `load_more_auto`, le bouton « Charger plus » déclenche la requête dès qu’il devient visible (IntersectionObserver ou repli via l’événement de scroll). Le module désactive automatiquement ce comportement si le navigateur est incompatible ou après un clic manuel du visiteur.
- **Articles épinglés** : possibilité d'épingler certains articles, avec option d'ignorer les filtres.
- **Lazy load** : chargement différé des images pour optimiser les performances.
- **Étiquette ARIA** : personnalisez le libellé utilisé par les lecteurs d'écran depuis le panneau **Accessibilité** (par défaut, le titre du module sélectionné est proposé en suggestion).
- **Étiquette ARIA du filtre** : définissez un texte explicite pour la navigation des catégories ou laissez le module générer automatiquement un intitulé (« Filtre des catégories pour … »).

> ℹ️ **Diaporama et mode illimité** : lorsque `display_mode` vaut `slideshow`, la récupération des contenus respecte toujours le plafond défini par l'option `unlimited_query_cap` (50 par défaut via le filtre `my_articles_unlimited_batch_size`). Cela évite de charger un nombre excessif d'articles d'un coup tout en conservant un mode quasi illimité.

## Instrumentation et suivi

### Activer le suivi dans l'administration

Le menu **Tuiles – LCV** comporte désormais une section « Instrumentation » :

- **Activer l’instrumentation** : cochez cette case pour exposer des événements front-end lors des interactions (filtre, chargement progressif).
- **Canal de sortie** : choisissez le mode de collecte associé (`console`, `dataLayer` ou `fetch`). Lorsque « fetch » est sélectionné, un POST JSON est envoyé vers l’endpoint REST `my-articles/v1/track`.

Les scripts front-end reçoivent automatiquement la configuration via `window.myArticlesFilter.instrumentation` et `window.myArticlesLoadMore.instrumentation`.

### Écouter les événements côté front

Deux événements personnalisés sont systématiquement émis, quel que soit le canal choisi :

- `my-articles:filter`
- `my-articles:load-more`

Chaque événement fournit un objet `detail` contenant au minimum :

- `phase` (`request`, `success`, `error`)
- `instanceId`
- `category`, `search`, `sort`
- Des informations spécifiques (`totalPages`, `addedCount`, `requestedPage`, etc.) suivant le contexte.

Exemple de consommation côté navigateur :

```js
window.addEventListener('my-articles:filter', (event) => {
    if (event.detail.phase === 'success') {
        console.log('Filtre appliqué', event.detail);
    }
});

window.addEventListener('my-articles:load-more', (event) => {
    if (event.detail.phase === 'error') {
        // Relayer vers une solution d'analytics
    }
});
```

Vous pouvez également fournir un callback personnalisé via `myArticlesFilter.instrumentation.callback` ou `myArticlesLoadMore.instrumentation.callback` pour centraliser le traitement.

### Relayer les interactions côté serveur

Lorsque l’instrumentation est active, chaque réponse réussie déclenche également l’action PHP suivante :

```php
add_action( 'my_articles_track_interaction', function( $event, $context ) {
    if ( 'load_more_response' === $event ) {
        // Transmettre $context vers un outil externe
    }
} );
```

Si le canal « fetch » est sélectionné, les requêtes envoyées par le front à `wp-json/my-articles/v1/track` déclenchent la même action avec un troisième argument (`WP_REST_Request`) permettant de valider/adapter le flux.

## Structure du projet

```
mon-affichage-article/
├── includes/  # Classes PHP
├── assets/    # JS et CSS
└── mon-affichage-articles.php
```

## Internationalisation

Le fichier binaire `mon-articles-fr_FR.mo` n'est plus stocké directement dans le dépôt afin d'éviter les conflits lors des revues de code. Les chaînes localisées sont conservées sous forme encodée (`languages/mon-articles-fr_FR.mo.base64`).

### Décoder le fichier `.mo`

```bash
base64 --decode mon-affichage-article/languages/mon-articles-fr_FR.mo.base64 \
  > /chemin/vers/mon-articles-fr_FR.mo
```

### Régénérer l'encodage après mise à jour des traductions

1. Mettre à jour les traductions (par exemple dans un fichier `.po`).
2. Compiler le fichier `.mo` correspondant.
3. Ré-encoder le binaire :

   ```bash
   base64 /chemin/vers/mon-articles-fr_FR.mo \
     > mon-affichage-article/languages/mon-articles-fr_FR.mo.base64
   ```

Veiller à ne pas ajouter le fichier `.mo` généré au dépôt (il est ignoré par Git) et à ne commiter que la version encodée.

## Migration des modules existants

Les modules créés avant l’introduction des préréglages conservent leurs réglages manuels. Pour normaliser la nouvelle valeur `design_preset`, vous pouvez exécuter :

```bash
wp eval-file scripts/migrations/assign-design-preset.php
```

Le script affecte automatiquement le modèle « Personnalisé » (`custom`) aux contenus `mon_affichage` dépourvus de préréglage.

## Tests

- **Tests PHP** : `composer test`
- **Tests JS** : `npm run test:js`

## Accessibilité

- Le mode diaporama expose désormais un carrousel conforme aux recommandations ARIA (région labellisée, boutons de navigation et pagination explicitement décrits).
- La navigation clavier est activée par défaut dans Swiper et les messages d’assistance sont personnalisés pour les lecteurs d’écran.
- Chaque module est annoncé comme région dynamique (`role="region"`, `aria-live="polite"`, `aria-busy`) et peut utiliser une étiquette ARIA personnalisée pour faciliter l’identification par les lecteurs d’écran. Les modules existants héritent automatiquement de leur titre tant que le champ dédié reste vide.
- Le filtre de catégories (lorsqu’il est actif) dispose désormais d’un libellé ARIA explicite basé sur le titre du module. Ce texte peut être ajusté ou vidé dans la metabox et dans le panneau **Module** du bloc pour répondre aux besoins éditoriaux.

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
