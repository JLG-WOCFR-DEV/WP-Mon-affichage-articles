# Vérification des fonctionnalités implémentées

Cette note récapitule la présence (ou non) des fonctionnalités décrites dans le README pour **Tuiles – LCV**.

## Gestion du contenu
- **Type de contenu personnalisé** `mon_affichage` : enregistré avec exposition REST et metaboxes dédiées. 【F:mon-affichage-article/mon-affichage-articles.php†L1480-L1519】【F:mon-affichage-article/includes/class-my-articles-metaboxes.php†L17-L239】
- **Gestion des articles épinglés** : normalisation, requêtes séparées et calcul spécifique pour la pagination. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L891-L1184】
- **Paramètres globaux & instrumentation** : page d’options avec sections dédiées et documentation intégrée. 【F:mon-affichage-article/includes/class-my-articles-settings.php†L162-L420】

## Affichage & interactions
- **Shortcode + bloc Gutenberg** : le bloc convertit ses attributs en overrides passés au shortcode. 【F:mon-affichage-article/includes/class-my-articles-block.php†L29-L123】
- **Trois modes de rendu (grille, liste, diaporama)** : gérés dans le rendu HTML et via les scripts Swiper. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2169-L2407】【F:mon-affichage-article/assets/js/swiper-init.js†L220-L360】
- **Pagination (none / load more / numbered) et auto-load** : boutons et calcul du nombre de pages implémentés, avec IntersectionObserver et repli scroll côté JS. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2219-L2247】【F:mon-affichage-article/assets/js/load-more.js†L1041-L1134】
- **Filtres frontaux & recherche** : formulaire accessible avec suggestions, tri et filtres taxonomiques persistés. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2030-L2164】【F:mon-affichage-article/assets/js/filter.js†L1-L420】
- **Lazy-load & états squelettes** : enregistrement conditionnel de `lazysizes` et placeholders dédiés. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1898-L2364】【F:mon-affichage-article/assets/css/styles.css†L92-L232】

## Personnalisation visuelle
- **Préréglages de design** : chargés depuis `config/design-presets.json`, exportés vers l’éditeur et verrouillage pour certains modèles. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L45-L223】【F:mon-affichage-article/config/design-presets.json†L1-L363】【F:mon-affichage-article/includes/class-my-articles-enqueue.php†L83-L150】
- **Contrôles fins (espacements, couleurs, typographies)** : disponibles dans les options normalisées et le bloc. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1237-L1726】【F:mon-affichage-article/blocks/mon-affichage-articles/block.json†L19-L210】
- **Mode debug front** : active un panneau d’informations techniques et un script helper dédié. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2252-L2286】

## Accessibilité & UX
- **Région dynamique ARIA + étiquettes personnalisables** : attributs `role`, `aria-live`, `aria-label` et libellés configurables dans la metabox/shortcode. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1996-L2130】【F:mon-affichage-article/includes/class-my-articles-shortcode-data-preparer.php†L284-L313】
- **Carrousel conforme ARIA et navigation clavier** : configuration Swiper (keyboard, messages) et respect de `prefers-reduced-motion`. 【F:mon-affichage-article/assets/js/swiper-init.js†L220-L360】

## Instrumentation & supervision
- **Instrumentation front (console/dataLayer/fetch) + événements personnalisés** : runtime partagé, événements `my-articles:*` et gestion du nonce. 【F:mon-affichage-article/assets/js/shared-runtime.js†L20-L214】【F:mon-affichage-article/assets/js/filter.js†L1-L212】【F:mon-affichage-article/assets/js/load-more.js†L1233-L1418】
- **Action serveur `my_articles_track_interaction` et dashboard admin** : REST `/track`, agrégation des métriques et tableau de bord dédié. 【F:mon-affichage-article/includes/rest/class-my-articles-controller.php†L411-L452】【F:mon-affichage-article/includes/class-my-articles-telemetry.php†L18-L236】
- **Constante `MY_ARTICLES_DEBUG_CACHE` & cache combiné** : logs détaillés, transients + object cache, namespace régénéré. 【F:mon-affichage-article/mon-affichage-articles.php†L1104-L1326】

## Intégrations développeurs
- **API REST complète (`/filter`, `/load-more`, `/search`, `/render-preview`, `/nonce`, `/track`)** : routes enregistrées avec validation et normalisation partagée. 【F:mon-affichage-article/includes/rest/class-my-articles-controller.php†L28-L452】
- **Hooks et filtres** : préréglages (`my_articles_design_presets`), pagination (`my_articles_calculate_total_pages`), clés de cache (`my_articles_cache_fragments`) et statut d’instance. 【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L52-L136】【F:mon-affichage-article/includes/helpers.php†L328-L534】【F:mon-affichage-article/mon-affichage-articles.php†L474-L835】

## Conclusion
La revue n’a pas identifié de fonctionnalité manquante par rapport au périmètre décrit dans le README : chaque bloc du programme dispose de son implémentation dans le plugin.
