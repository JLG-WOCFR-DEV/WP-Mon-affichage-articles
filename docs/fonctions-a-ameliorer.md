# Fonctions prioritaires à faire évoluer

Cette note recense les fonctions qui méritent une refonte pour se rapprocher des standards observés dans les extensions WordPress professionnelles. Chaque entrée indique la localisation de la fonction, les limites constatées et des pistes concrètes d'amélioration.

## 1. `My_Articles_Shortcode::render_shortcode()`
- **Localisation** : `mon-affichage-article/includes/class-my-articles-shortcode.php`
- **Problèmes constatés** :
  - Le rendu mélange collecte de données, calculs de pagination, génération HTML et enregistrement des scripts, ce qui rend les optimisations (cache fragment, SSR côté serveur) très difficiles.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1486-L1776】
  - Les assets sont systématiquement enregistrés à chaque exécution du shortcode, sans mise en commun entre plusieurs instances sur une page ni préchargement conditionnel.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1526-L1566】
  - Le payload JavaScript est directement sérialisé dans `wp_localize_script`, ce qui complique la mutualisation avec d'autres modules et la mise en cache HTTP côté CDN.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L1526-L1566】
- **Pistes d'amélioration** :
  - Extraire la préparation des requêtes et la configuration front dans des services dédiés afin de pouvoir mettre en place un cache (transients, object cache) par combinaison d'options.
  - Enregistrer les scripts via un gestionnaire central (`My_Articles_Enqueue`) qui applique un contrôle de doublon et permet d'injecter des préchargements ou des versions asynchrones.
  - Remplacer `wp_localize_script` par un registre de données (via `wp_add_inline_script` ou le Data API de WordPress) pour favoriser le cache HTTP et la composition avec d'autres blocs.

## 2. `My_Articles_Shortcode::build_display_state()`
- **Localisation** : `mon-affichage-article/includes/class-my-articles-shortcode.php`
- **Problèmes constatés** :
  - La fonction recompose manuellement plusieurs requêtes `WP_Query`, avec beaucoup de branches spécifiques (pagination séquentielle, slideshow, lazy load), ce qui la rend difficile à tester et à optimiser.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L530-L704】
  - Aucune mise en cache des IDs « pinned » ou des résultats réguliers n'est prévue alors que la méthode peut être appelée plusieurs fois pour la même instance (prévisualisation, REST, SSR).
  - La logique métier (calcul des offsets, fusion des listes exclus) n'est pas externalisée, empêchant l'introduction de stratégies avancées (pré-chargement via `pre_get_posts`, index Elasticsearch, etc.).
- **Pistes d'amélioration** :
  - Isoler le calcul des limites/pagination dans une classe dédiée avec une interface testable.
  - Ajouter un système de cache transitoire sur les IDs épinglés et sur le comptage total.
  - Offrir des points d'extension (hooks/filters) pour déléguer les requêtes à des services de recherche professionnels.

## 3. `Mon_Affichage_Articles::render_articles_for_response()`
- **Localisation** : `mon-affichage-article/mon-affichage-articles.php`
- **Problèmes constatés** :
  - Le rendu HTML est construit via `echo` successifs et `ob_start`, sans templating ni tamponnage différé, ce qui complique l'accessibilité, l'i18n et l'injection de tests end-to-end.【F:mon-affichage-article/mon-affichage-articles.php†L96-L193】
  - Le compteur `displayed_posts_count` est maintenu manuellement et dépend du flux de sortie, rendant la fonction fragile face à l'ajout de nouvelles sections ou d'un mode skeleton plus riche.【F:mon-affichage-article/mon-affichage-articles.php†L120-L170】
  - Aucune instrumentation (logs de performance, hook d'analyse) n'est exposée pour les outils de supervision courants.
- **Pistes d'amélioration** :
  - Introduire un moteur de templates (par exemple `wp_template_part` ou un système Twig) pour séparer la présentation et la logique.
  - Centraliser le comptage et l'état dans un objet retour structuré (DTO) afin de faciliter les tests et l'analytics.
  - Ajouter des hooks pour brancher une télémétrie (New Relic, Datadog) ou des tests A/B sur les composants rendus.

## 4. `My_Articles_Settings::sanitize()`
- **Localisation** : `mon-affichage-article/includes/class-my-articles-settings.php`
- **Problèmes constatés** :
  - La validation repose sur une succession de `min/max` et de `isset`, sans schéma centralisé ; l'ajout d'un nouveau champ nécessite de modifier plusieurs blocs et augmente le risque d'oubli de contraintes.【F:mon-affichage-article/includes/class-my-articles-settings.php†L86-L167】
  - Certains couples de valeurs dépendantes (ex. `pagination_mode`, `load_more_auto`) ne sont pas validés ici mais dans la normalisation, entraînant des incohérences possibles entre la sauvegarde et le rendu.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L909-L918】
  - Aucune normalisation des champs destinés à l'instrumentation (URL cible, ID de canal) n'est prévue, ce qui limiterait l'intégration avec des outils enterprise (Segment, Adobe Analytics).
- **Pistes d'amélioration** :
  - Mettre en place un schéma déclaratif (tableau de définitions ou librairie type `JustValidate`) pour centraliser contraintes et valeurs par défaut.
  - Déplacer la logique de dépendance (`load_more_auto`, `pagination_mode`) dans la phase de sauvegarde afin d'éviter des états invalides en base.
  - Introduire des validateurs dédiés pour les champs d'instrumentation (URL, identifiants), avec messages d'erreur traduisibles.

## 5. `My_Articles_Shortcode::get_design_presets()`
- **Localisation** : `mon-affichage-article/includes/class-my-articles-shortcode.php`
- **Problèmes constatés** :
  - Les presets sont codés en dur dans la classe ; impossible de les versionner, d'appliquer des mises à jour distantes ou de proposer un catalogue dynamique comme le font les solutions premium.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L32-L134】
  - Aucune métadonnée (aperçu, palette accessible, compatibilité mode sombre) n'est associée, ce qui limite les intégrations UI dans l'éditeur bloc.
- **Pistes d'amélioration** :
  - Charger les presets depuis des fichiers JSON versionnés ou un endpoint distant, avec mise en cache et signature.
  - Ajouter des métadonnées pour générer des aperçus visuels et filtrer les presets selon le contexte (mode sombre, accessibilité).

## 6. `my_articles_calculate_total_pages()`
- **Localisation** : `mon-affichage-article/includes/helpers.php`
- **Problèmes constatés** :
  - La fonction considère qu'un `posts_per_page` à `0` équivaut à « illimité » mais retourne malgré tout un `total_pages` de `1`, empêchant tout suivi analytique précis du nombre réel de lots chargés côté client.【F:mon-affichage-article/includes/helpers.php†L281-L323】
  - Aucun hook ne permet d'ajuster la stratégie de pagination (par exemple prendre en compte un stock pré-calculé ou un service externe).
- **Pistes d'amélioration** :
  - Exposer un filtre `my_articles_calculate_total_pages` pour surcharger le calcul.
  - Fournir en sortie des métadonnées supplémentaires (total potentiels, taille restante) pour alimenter des dashboards ou un tracking front avancé.

## Tests de diagnostic ajoutés

- **`tests/CalculateTotalPagesTest.php`** : nouvelle série de scénarios couvrant les combinaisons d'articles épinglés et réguliers, y compris le cas « illimité ». Ces tests servent de filet de sécurité avant refonte et facilitent le repérage d'effets de bord sur la pagination.【F:tests/CalculateTotalPagesTest.php†L1-L54】

Pour exécuter l'ensemble des tests : `composer test`.
