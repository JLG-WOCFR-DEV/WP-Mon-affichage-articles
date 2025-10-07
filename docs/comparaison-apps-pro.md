# Comparaison avec des extensions professionnelles et pistes d'amélioration

Ce mémo met en parallèle **Tuiles – LCV** avec des extensions WordPress professionnelles de mise en avant de contenus (ex. Essential Grid, JetEngine Listings, WP Grid Builder) et propose des améliorations pour combler les écarts fonctionnels et UX.

## Forces actuelles

- **Paramétrage éditorial avancé** : le plugin expose un type de contenu dédié, des réglages complets côté administration et un large éventail de curseurs pour contrôler le rendu (mode d'affichage, colonnes, couleurs, métadonnées, etc.).【F:mon-affichage-article/includes/class-my-articles-settings.php†L111-L200】
- **Rendu front riche** : la couche shortcode gère les articles épinglés, la pagination et un fallback visuel (squelettes, placeholder, badge d'épingle) comparable aux solutions premium.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1972-L2075】
- **Instrumentation déjà préparée** : l'onglet tutoriel documente l'émission d'événements JavaScript et la possibilité de pousser les interactions vers un endpoint REST, ce qui est rare dans les plugins gratuits.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L140】

### Tableau comparatif synthétique

| Dimension | Tuiles – LCV (actuel) | Extensions pro (Essential Grid, JetEngine, WP Grid Builder) | Opportunité d'évolution |
| --- | --- | --- | --- |
| **Sélection de templates** | Liste déroulante textuelle sans aperçu.【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L806-L881】 | Bibliothèque visuelle filtrable, preview responsive et cas d'usage prédéfinis. | Créer un catalogue de presets versionnés avec vignettes et tags de contexte. |
| **Composition des cartes** | Structure figée (image, titre, métadonnées, extrait).【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1972-L2075】 | Builder drag & drop avec champs dynamiques (ACF, WooCommerce, taxonomies). | Introduire des slots configurables et la prise en charge de champs personnalisés. |
| **Moteur de requête** | Filtres basés sur taxonomies natives et ordre simple.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L397-L440】 | Meta queries avancées, relations complexes, sources externes. | Exposer une configuration pour meta queries, connecteurs API et hooks. |
| **Analytics** | Émission d'événements sans interface de restitution.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L140】 | Dashboards intégrés avec indicateurs de performance et A/B testing. | Consolider un module analytics dans l'admin en exploitant les événements existants. |
| **Performance** | Assets injectés systématiquement (Swiper, LazySizes, styles).【F:mon-affichage-article/includes/class-my-articles-enqueue.php†L44-L53】 | Chargement conditionnel et optimisation Lighthouse (code splitting). | Mettre en place un enqueuing conditionnel et une stratégie de bundles modulaires. |

## Écarts observés vs. extensions professionnelles

1. **Expérience de conception**
   - Les presets de design sont codés en dur et se limitent à un jeu de couleurs/espacements sans aperçu visuel ni variations guidées par l'usage (grille éditoriale, slider produit, mosaïque e-commerce).【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L32-L134】 Les solutions premium proposent généralement une bibliothèque de modèles prévisualisables et filtrables par objectif.

2. **Personnalisation du contenu**
   - Le rendu des cartes est figé autour du trio image/titre/métadonnées/extrait et ne permet pas d'ajouter des champs dynamiques (ACF, WooCommerce, taxonomies personnalisées) ou des CTA, contrairement aux constructeurs professionnels.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1972-L2075】

3. **Moteur de requête**
   - Les requêtes s'articulent autour des filtres taxonomiques natifs (`tax_query`, `s`) et de l'ordre, sans prise en charge des conditions complexes sur les métadonnées ou des sources externes (API, index de recherche).【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L397-L440】

4. **Tableau de bord analytics**
   - L'instrumentation actuelle se limite à émettre des événements ; aucune interface ne permet de visualiser le trafic, la profondeur de scroll ou la performance des filtres au sein du back-office, là où les offres pro embarquent des dashboards synthétiques.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L140】

5. **Performance et chargement conditionnel**
   - Les assets (Swiper, LazySizes, layout) sont systématiquement injectés dans l'éditeur de blocs même si le module n'utilise pas le diaporama ou le lazy-load, ce que les solutions premium optimisent via du code splitting ou des chargements différés.【F:mon-affichage-article/includes/class-my-articles-enqueue.php†L44-L53】

## Pistes d'amélioration prioritaires

1. **Bibliothèque de modèles interactive**
   - Externaliser les presets dans des fichiers JSON versionnés (ou un endpoint) avec vignettes et métadonnées d'usage, puis ajouter un sélecteur visuel côté bloc/shortcode. Cela rapprocherait l'expérience de « template kits » fournis par les extensions pro tout en simplifiant la contribution design.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L32-L134】

2. **Builder de carte modulaire**
   - Introduire un système de « slots » ou de blocs imbriqués pour composer les cartes (image, taxonomie secondaire, badge personnalisé, bouton). Cela permettrait d'afficher des champs personnalisés récupérés via `get_post_meta` ou REST, un différenciateur clé face aux concurrents haut de gamme.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1972-L2075】

3. **Requêtes avancées et connecteurs**
   - Ajouter une couche de configuration pour les meta queries, les jointures multiples (relation AND/OR), voire des connecteurs vers des index ElasticSearch ou des APIs tierces. L'actuelle construction de requêtes pourrait exposer des hooks pour déléguer la collecte de contenus à un service spécialisé.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L397-L440】

4. **Dashboard d'engagement intégré**
   - Capitaliser sur les événements déjà envoyés pour bâtir une page d'analyse dans l'admin : graphiques de clics par filtre, taux d'usage du « charger plus », erreurs. Un stockage léger (custom table ou option sérialisée) et l'utilisation de `wp.data` pour le rendu permettraient d'offrir une valeur proche des analytics embarqués des solutions premium.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L140】

5. **Optimisation du chargement des assets**
   - Implémenter un enqueuing conditionnel basé sur les options (charger Swiper uniquement si `display_mode` = `slideshow`, LazySizes si lazy-load activé) et proposer une version `module federation` pour mutualiser les scripts entre instances. Les professionnels mettent en avant ces optimisations pour gagner des points Lighthouse, surtout sur mobile.【F:mon-affichage-article/includes/class-my-articles-enqueue.php†L44-L53】

6. **Personnalisation des parcours**
   - Exploiter l'action `my_articles_track_interaction` pour introduire des règles de personnalisation (par exemple, remonter les contenus les plus cliqués, adapter l'ordre selon le profil utilisateur). Couplé à un cache segmenté, cela amènerait des capacités de « smart listing » recherchées dans les outils pro.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L140】【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L397-L440】

### Feuille de route recommandée

1. **Court terme (1 à 2 versions)**
   - Extraire les presets dans des manifestes JSON et intégrer un sélecteur visuel basique pour valider la démarche de templating.
   - Conditionner l'enqueue des scripts lourds (Swiper, LazySizes) à l'activation des fonctionnalités correspondantes.【F:mon-affichage-article/includes/class-my-articles-enqueue.php†L44-L53】

2. **Moyen terme (3 à 5 versions)**
   - Lancer un MVP du builder de cartes : slots configurables, injection de champs personnalisés via `get_post_meta` et `wp.data` pour l'aperçu dans l'éditeur.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1972-L2075】【F:mon-affichage-article/blocks/mon-affichage-articles/preview.js†L64-L205】
   - Exposer une interface de configuration des meta queries et documenter des hooks pour déléguer la récupération de contenus à un service externe.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L397-L440】

3. **Long terme (feuille de route annuelle)**
   - Développer un dashboard analytics dédié (tableau de bord React avec `wp.data`) exploitant les événements existants, incluant segmentation et export CSV.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L140】
   - Proposer une couche de personnalisation comportementale (réordonnancement dynamique, recommandations) supportée par un cache segmenté et des tests A/B.

### Focus UX/UI détaillé

#### 1. Sélecteur visuel et onboarding
Dans l'inspecteur Gutenberg, le choix du preset se limite à une simple liste déroulante `SelectControl` sans illustration ni catégories d'usage, et c'est également le cas pour la sélection du mode (grille/liste/slider) qui dépend d'autres menus textuels.【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L806-L881】 Une expérience plus professionnelle passerait par :

- **Panneau modal de découverte** : déclencher un panneau pleine largeur présentant chaque preset sous forme de vignette avec aperçu direct (mobile/tablette/desktop) et tags d'usage (« Editorial », « E-commerce », « Slider »). L'utilisateur y choisirait aussi le mode initial pour gagner un clic.
- **Filtres et recherche** : ajouter des filtres contextuels (ex. « mode sombre », « compatible autoplay », « mise en avant d'auteurs ») appuyés sur des métadonnées stockées dans un registre JSON des presets pour se rapprocher du fonctionnement d'Essential Grid ou WP Grid Builder.
- **Parcours d'onboarding** : proposer une visite guidée après insertion du bloc (tooltip séquentiel sur les contrôles clés, rappel des limitations quand un preset verrouille des options) afin d'accélérer la prise en main par les équipes non techniques.

#### 2. Prévisualisation locale thémée
La prévisualisation actuelle repose sur un appel REST qui renvoie le HTML public, injecté tel quel dans l'éditeur Gutenberg via `dangerouslySetInnerHTML`, sans appliquer les styles du thème actif ni simuler les variations responsive dans l'interface.【F:mon-affichage-article/blocks/mon-affichage-articles/preview.js†L64-L205】【F:mon-affichage-article/blocks/mon-affichage-articles/preview.js†L224-L294】 Pour se rapprocher des solutions premium :

- **Mode « édition rapide »** : rendre les composants internes éditables directement dans l'éditeur (drag & drop des blocs enfant, ajustement instantané des espacements, sélection de palettes). Ce mode pourrait s'appuyer sur un rendu React local plutôt que sur la réponse HTML statique.
- **Injection des styles du thème** : charger dynamiquement les feuilles de style globales du thème (ou une sélection de tokens) dans la preview et exposer un switch « Palette thème / Palette bloc » pour valider la cohérence graphique avant publication.
- **Simulateur responsive** : intégrer un contrôleur de viewport (Mobile/Tablet/Desktop) et une bascule « Mode sombre » afin d'aligner la preview avec les parcours d'audit UX observés dans les outils pro.

#### 3. Micro-interactions et états vides
La feuille de style front assure les fondamentaux (squelettes animés, transitions d'opacité, variations grille/liste), mais aucune option ne permet de personnaliser ces micro-interactions ou de définir un état vide riche (CTA, recommandations, intégrations marketing).【F:mon-affichage-article/assets/css/styles.css†L1-L120】【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1955-L2046】 Les pistes à couvrir :

- **Animations conditionnelles** : exposer des réglages pour choisir le type d'animation (fondu, slide, scale), définir la durée par breakpoint et respecter automatiquement `prefers-reduced-motion` en désactivant les transitions par défaut.
- **État vide scénarisé** : permettre d'ajouter un bouton d'action, une liste d'articles suggérés ou un formulaire d'abonnement lorsque la requête ne retourne aucun contenu, plutôt que le simple message statique généré actuellement.
- **Feedbacks interactifs** : intégrer des micro-effets (hover avec élévation, focus accentué, lottie ou icônes animées pour les badges) et des réglages d'accessibilité associés (contraste renforcé, annonce ARIA personnalisée) pour se rapprocher du niveau de finition des produits concurrents.

En priorisant ces axes, Tuiles – LCV pourra rivaliser plus sereinement avec les extensions professionnelles, tant sur le confort d'utilisation que sur la richesse fonctionnelle attendue par les équipes marketing et éditoriales exigeantes.

### Compléments UX/UI inspirés des suites professionnelles

1. **Barre de filtres orientée usage**
   - L'interface actuelle laisse l'éditeur définir uniquement l'alignement, l'intitulé ARIA et une liste brute de filtres, ce qui limite les expériences guidées par persona.【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L1251-L1338】【F:mon-affichage-article/assets/css/styles.css†L479-L596】 Les solutions pro affichent des barres multi-niveaux avec recherche par tag, segmentation par canal et favoris.
   - Faire évoluer le panneau pour proposer des collections de filtres pré-définies (Actualités, Contenu evergreen, Promotions) combinées à des chips dynamiques. Chaque chip pourrait exposer des compteurs en temps réel et une option « épingler » pour reproduire les comportements d'Essential Grid ou JetEngine.
   - Ajouter un configurateur d'états (filtre vide, surcharge marketing, recommandations) afin de transformer la barre en véritable cockpit éditorial plutôt qu'un simple repeater de taxonomies.

2. **Canvas d'édition front « live »**
   - La preview Gutenberg repose sur une réponse HTML figée injectée via `dangerouslySetInnerHTML`, sans interaction directe avec les composants internes.【F:mon-affichage-article/blocks/mon-affichage-articles/preview.js†L1-L206】 Les éditeurs haut de gamme autorisent un mode « live edit » où l'on ajuste directement titres, badges ou CTA depuis le canvas.
   - Introduire un rendu React déclaratif dans l'éditeur, capable de simuler les layouts grille/liste/slider avec les tokens du thème actif. On pourrait activer un mode « Inspect » affichant les espacements, les points de rupture et les états de focus, comme sur les constructeurs de pages pros.
   - Coupler ce mode à un historique de modifications (undo/redo contextualisé) et à un comparateur de variantes A/B pour encourager les itérations de design directement depuis Gutenberg.

3. **Overlays de pilotage et d'analytics in-situ**
   - Le back-office détaille les canaux d'instrumentation mais ne restitue pas la donnée ni ne propose de retours visuels au moment du paramétrage.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L118】 Les outils professionnels affichent des overlays d'engagement (taux de clic, temps passé) directement au-dessus du listing.
   - Ajouter, dans le panneau de réglages, une bascule « Mode pilotage » qui superpose aux cartes des badges d'indicateurs en s'appuyant sur les événements déjà émis (`my-articles:filter`, `my-articles:load-more`). Cela offrirait un retour immédiat sur la performance des filtres ou du bouton « Charger plus ».
   - Prévoir des exports (CSV, PNG de heatmap) et une intégration avec les dashboards internes pour aligner l'expérience sur les plateformes analytiques spécialisées.
