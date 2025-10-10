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
| **Stratégie de cache** | Clés de cache REST concaténées sans namespace, collisions possibles entre filtres et recherches.【F:mon-affichage-article/mon-affichage-articles.php†L474-L820】 | Convention d’identifiants stable, invalidation ciblée et observabilité intégrée. | Normaliser les fragments (`search:`, `sort:`) et instrumenter les collisions pour la QA.【F:docs/code-review.md†L5-L16】 |

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

6. **Robustesse du cache serveur**
   - Les clés générées pour les réponses REST partagent le même format quelle que soit la combinaison de paramètres, ce qui peut renvoyer une réponse d'un contexte différent (recherche vs tri). Les plugins premium exposent des conventions strictes et des tableaux de bord d'invalidation afin de diagnostiquer les ratés avant mise en production.【F:docs/code-review.md†L5-L16】

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

7. **Durcissement du cache REST**
   - Encapsuler la génération des clés dans un service dédié (`My_Articles_Cache_Key`) qui applique automatiquement les préfixes et expose des métriques (hits/miss) consultables dans l'administration. Coupler ce service avec une page de diagnostic inspirée des dashboards concurrents pour visualiser l'état des caches par instance.【F:docs/code-review.md†L5-L23】【F:docs/roadmap-technique.md†L8-L28】

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
   - **Objectif UX** : proposer un véritable assistant de segmentation éditoriale (personae, canaux, contextes de campagne) pour rapprocher le produit de l'expérience multi-critères des catalogues pro.
   - **Approche fonctionnelle** : faire évoluer le panneau pour proposer des collections de filtres pré-définies (Actualités, Evergreen, Promotions) combinées à des chips dynamiques. Chaque chip pourrait exposer des compteurs en temps réel et une option « épingler » pour reproduire les comportements d'Essential Grid ou JetEngine. Prévoir un moteur de suggestion alimenté par les métadonnées des contenus les plus consultés.
   - **Approche technique** : 
     - stocker les configurations de collections dans un registre JSON (`filters-presets.json`) versionné côté plugin, chargeable via `wp.data` pour permettre l'édition collaborative ;
     - exposer un composant React de type `TokenField` personnalisé avec recherche incrémentale et regroupement par taxonomie ;
     - écouter les événements `my-articles:filter` pour afficher en direct les compteurs et proposer un raccourci « Enregistrer comme favoris » qui crée une entrée utilisateur (`user_meta`).
   - **Livrables UI** : barre multi-niveaux avec étiquettes contextuelles, messages d'état (ex. aucun résultat → CTA « Modifier les filtres »), onboarding inline guidant sur l'usage des collections.
   - **Indicateurs de succès** : réduction du temps moyen de configuration d'une barre, augmentation du recours aux favoris, meilleure conversion sur les filtres recommandés.

2. **Canvas d'édition front « live »**
   - La preview Gutenberg repose sur une réponse HTML figée injectée via `dangerouslySetInnerHTML`, sans interaction directe avec les composants internes.【F:mon-affichage-article/blocks/mon-affichage-articles/preview.js†L1-L206】 Les éditeurs haut de gamme autorisent un mode « live edit » où l'on ajuste directement titres, badges ou CTA depuis le canvas.
   - **Objectif UX** : réduire le va-et-vient entre back-office et front-office et offrir un ressenti WYSIWYG professionnel.
   - **Approche fonctionnelle** : introduire un rendu React déclaratif dans l'éditeur, capable de simuler les layouts grille/liste/slider avec les tokens du thème actif. Ajouter un mode « Inspect » affichant les espacements, les points de rupture et les états de focus, comme sur les constructeurs de pages pros. Prévoir des handles de drag & drop pour réordonner les cartes ou ajuster les colonnes au survol.
   - **Approche technique** :
     - remplacer l'iframe statique par un composant `PreviewCanvas` basé sur `@wordpress/block-editor` et `@wordpress/components`, alimenté par un store `wp.data` synchronisé avec les attributs du bloc ;
     - charger dynamiquement les styles du thème actif via `wp_get_global_stylesheet()` et permettre à l'utilisateur de basculer entre « Palette thème » et « Palette bloc » ;
     - implémenter un module d'historique utilisant `useUndo` pour offrir undo/redo contextualisé et un comparateur de variantes (A/B) exploitant deux snapshots d'attributs.
   - **Livrables UI** : canvas plein écran avec toolbar flottante (toggle responsive, bascule mode sombre, info-bulles sur les marges), panneau d'inspection contextuel affichant typographie, assets et mesures Lighthouse simulées.
   - **Indicateurs de succès** : baisse des aller-retours prévisualisation, augmentation du taux d'activation du mode live, meilleure satisfaction des designers internes (enquêtes NPS).

3. **Overlays de pilotage et d'analytics in-situ**
   - Le back-office détaille les canaux d'instrumentation mais ne restitue pas la donnée ni ne propose de retours visuels au moment du paramétrage.【F:mon-affichage-article/includes/class-my-articles-settings.php†L62-L118】 Les outils professionnels affichent des overlays d'engagement (taux de clic, temps passé) directement au-dessus du listing.
   - **Objectif UX** : rendre l'optimisation continue accessible aux équipes éditoriales sans quitter l'environnement de configuration.
   - **Approche fonctionnelle** : ajouter, dans le panneau de réglages, une bascule « Mode pilotage » qui superpose aux cartes des badges d'indicateurs en s'appuyant sur les événements déjà émis (`my-articles:filter`, `my-articles:load-more`). Afficher des heatmaps simplifiées (zones de clic) et un résumé de performance par filtre directement dans la barre latérale.
   - **Approche technique** :
     - créer une table `wp_my_articles_metrics` alimentée par un cron quotidien agrégant les événements collectés, avec une API REST dédiée pour récupérer les données par instance de bloc ;
     - développer un overlay React (`AnalyticsOverlay`) qui, en mode édition, consomme cette API et affiche badges, tendances (+/-) et temps de lecture estimé ;
     - proposer des exports CSV/PNG via `wp_ajax` et intégrer un connecteur facultatif vers des outils internes (ex. Matomo) via webhooks.
   - **Livrables UI** : boutons de bascule analytics, légende des indicateurs, heatmap progressive sur les cartes, panneau latéral « Insights » avec graph sparklines et recommandations automatiques.
   - **Indicateurs de succès** : volume d'activations du mode pilotage, adoption des exports, amélioration du taux de clic moyen sur les filtres optimisés.

### Comparatif accessibilité, UI et fiabilité

| Dimension | Tuiles – LCV (actuel) | Extensions pro (JetEngine, WP Grid Builder, Stackable Pro) | Opportunités d’évolution |
| --- | --- | --- | --- |
| **Accessibilité sémantique** | Le shortcode applique des libellés ARIA configurables pour le wrapper et la barre de filtres, mais laisse l’utilisateur final rédiger chaque message manuellement.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1844-L1884】 | Les solutions premium pré-remplissent des libellés localisés, détectent l’absence de description et proposent des messages adaptés aux filtres actifs. | Ajouter un générateur de descriptions contextuelles (avec suggestions automatiques et contrôle de contraste) et des messages d’état dynamiques annoncés via `aria-live`. |
| **Organisation des réglages** | Tous les contrôles (disposition, pagination, slideshow, accessibilité, recherche) cohabitent dans la même sidebar Gutenberg avec de multiples `PanelBody`, ce qui crée des parcours longs à scroller.【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L1661-L1755】 | Les extensions pro distinguent un mode « Essentiel » (affichage, contenu, interactions de base) d’un mode « Avancé » (animation, fine tuning) et intègrent une recherche instantanée. | Structurer un double panneau « Simple » vs « Expert », avec une vue condensée basée sur les presets et une vue détaillée munie de favoris et de filtres par catégorie de réglage. |
| **Preview & design system** | L’aperçu repose sur l’injection directe du HTML rendu via `dangerouslySetInnerHTML`, sans mise à jour instantanée des composants ni simulation des tokens du thème.【F:mon-affichage-article/blocks/mon-affichage-articles/preview.js†L562-L583】 | Les extensions pro offrent un canvas React en temps réel, synchronisé avec le design system du thème et les points de rupture. | Introduire un `PreviewCanvas` déclaratif, capable de puiser dans `wp_get_global_styles()` et d’afficher des modes responsive/sombre dans l’éditeur. |
| **Contrôle du slider** | Les options Swiper sont regroupées dans la même section que les réglages de grille, sans garde-fous d’accessibilité (pause automatique selon `prefers-reduced-motion`, focus piégé).【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L1704-L1745】 | Les plugins professionnels appliquent des presets sûrs (autoplay limité, pause annoncée aux lecteurs d’écran) et affichent des alertes lorsque les paramètres deviennent risqués. | Ajouter des scénarios validés (Autoplay marketing, Témoignages, Focus accessibilité) qui ajustent automatiquement délais, pauses et navigation au clavier, avec badges d’état. |
| **Fiabilité & cache** | Les clés de cache REST concatènent les paramètres sans namespace métier détaillé, augmentant le risque de collisions entre filtres et recherche, et aucune métrique n’est exposée en back-office.【F:mon-affichage-article/mon-affichage-articles.php†L474-L520】 | Les solutions pro tracent les hits/miss et exposent une console d’invalidation ciblée. | Introduire un service `CacheKey` dédié (namespace + hash stable) et un tableau de bord d’observabilité (compteurs, collisions, purge à la demande). |

### Améliorations ciblées UI/UX & accessibilité

1. **Mode Simple vs Expert dans l’éditeur**
   - **Simple** : présenter uniquement les choix critiques (mode d’affichage, pagination, filtres) et un sélecteur de preset visuel, avec possibilité d’enregistrer un « pack » d’options préférées pour l’équipe éditoriale.【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L1661-L1714】
   - **Expert** : regrouper le reste des contrôles dans un panneau accordéon avec moteur de recherche et tags (« Accessibilité », « Animations », « Performances »), à la manière des filtres contextuels déjà présents dans JetEngine.
   - **Technique** : créer un store `wp.data` qui mémorise la dernière vue utilisée par l’utilisateur et autorise les favoris de réglages pour éviter les reconfigurations répétées.

2. **Guides d’accessibilité intégrés**
   - Profiter du champ `aria_label` existant pour afficher, dans l’inspecteur, des suggestions générées (ex. « Articles à la une – mars ») et signaler automatiquement les contrastes insuffisants via la palette admin.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1844-L1872】【F:mon-affichage-article/assets/css/admin.css†L1-L48】
   - Ajouter un toggle « Respecter les préférences de réduction de mouvement » qui neutralise autoplay et transitions en cas de `prefers-reduced-motion`, ainsi qu’un `aria-live` pour annoncer les nouveaux articles chargés.

3. **Preview immersive et testable**
   - Remplacer l’injection HTML statique par des composants React permettant d’ajuster visuellement espacements, typographies et ordonnancement par drag & drop, avec support du mode sombre et des breakpoints natifs.【F:mon-affichage-article/blocks/mon-affichage-articles/preview.js†L562-L583】
   - Ajouter un panneau « Audit express » qui affiche contrastes, tailles de tap target et avertissements d’accessibilité basés sur les données du design token.

4. **Fiabilisation de la collecte & du cache**
   - Encapsuler la génération des clés dans un service (`My_Articles_Cache_Key`) qui crée des fragments normalisés (`mode:grid`, `filter:category-slug`, `search:query`) et expose un compteur de collisions accessible depuis l’onglet « Maintenance ».【F:mon-affichage-article/mon-affichage-articles.php†L474-L520】
   - Ajouter une page « Journal des erreurs » listant les réponses REST en échec, les expirations de cache et les temps de rendu des requêtes (`build_display_state`) pour aider au diagnostic.

5. **Scénarios de configuration guidée**
   - Introduire des « assistants » orientés objectifs (ex. « Magazine éditorial », « Landing produit ») qui pré-configurent pagination, recherche et filtres, tout en expliquant les compromis UX (densité des cartes, temps de chargement).【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L1661-L1704】
   - Chaque assistant pourrait proposer une check-list d’accessibilité (focus visible, labels explicites, durée d’autoplay) avant publication, inspirée des workflows pro.
