# Roadmap technique Tuiles – LCV

Cette feuille de route découpe les chantiers identifiés dans le README et les notes techniques en lots activables. Chaque objectif renvoie vers les zones de code concernées pour faciliter la planification.

## 0-3 mois — Stabilisation et découplage

### 1. Externaliser la préparation des données du shortcode
- **Portée** : `My_Articles_Shortcode::render_shortcode()` et `My_Articles_Shortcode::build_display_state()`.
- **Cibles** : créer un service de préparation (cache + normalisation) et un gestionnaire d'enqueue partagé.
- **Livrables** :
  - Interface PHP décrivant les états de rendu et le contrat de cache.
  - Gestionnaire d'assets mutualisé (`My_Articles_Enqueue`) et remplacement de `wp_localize_script` par `wp_add_inline_script`.
- **Référence** : voir `docs/fonctions-a-ameliorer.md` sections 1 et 2.

### 2. Normaliser le rendu du bloc Gutenberg
- **Portée** : `My_Articles_Block::render_block()` et conversion des attributs.
- **Cibles** : isoler la préparation des overrides, prévoir un mode « prévisualisation » et réduire la duplication avec le shortcode.
- **Livrables** :
  - Tests unitaires PHP couvrant `prepare_overrides_from_attributes()`.
  - Hook/filter pour étendre les attributs pris en charge par le bloc.
- **Référence** : voir `docs/fonctions-a-ameliorer.md` section 7.

### 3. Mise en place d'un socle de tests
- **Portée** : scénarios PHPUnit existants (`tests/CalculateTotalPagesTest.php`).
- **Cibles** : ajouter des tests autour de la pagination slideshow, des articles épinglés et du bloc.
- **Livrables** :
  - Suite PHPUnit élargie et intégration GitHub Actions.
  - Documentation `composer test` + `npm run test:js` dans le README (déjà présente) et rappels dans les PR templates.
- **Suivi** : préparer un test dédié aux collisions de clés de cache (`search` vs `sort`, IDs épinglés) avant la refonte de l’enqueue.【F:docs/code-review.md†L5-L23】

### 4. Fiabiliser les clés de cache REST
- **Portée** : `prepare_filter_articles_response()`, `prepare_load_more_articles_response()` et helpers associés.
- **Cibles** : préfixer systématiquement les fragments (`search:`, `sort:`, `pinned:`) et documenter la convention pour les nouvelles routes.
- **Livrables** :
  - Refactor du générateur de clé (`generate_response_cache_key()`) avec tests de collision.
  - Notes de migration dans `tests/REGRESSIONS.md` pour accompagner le nettoyage des caches après déploiement.【F:docs/code-review.md†L5-L23】【F:tests/REGRESSIONS.md†L1-L26】
- **Statut** : ✅ `My_Articles_Response_Cache_Key` centralise désormais les fragments nommés, des tests unitaires dédiés existent (`tests/ResponseCacheKeyTest.php`) et la journalisation `MY_ARTICLES_DEBUG_CACHE` facilite l'audit des hits/miss.

## 3-6 mois — Expérience éditeur et design

### 5. Catalogue de préréglages enrichi
- **Portée** : presets du shortcode et panneau Module dans le bloc.
- **Cibles** : charger des préréglages depuis des fichiers JSON, ajouter métadonnées (thumbnails, tags) et synchronisation REST.
- **Livrables** :
  - Galerie visuelle dans l'éditeur (panel React) + API REST pour exporter/importer.
  - Commande WP-CLI pour synchroniser les presets.
- **Référence** : `docs/pistes-amelioration-design.md` section 2.

### 6. Filtres front avancés
- **Portée** : scripts de filtrage (`assets/js`) et panneau de configuration du bloc.
- **Cibles** : introduire recherche multi-critères, chips contextuelles et animation de feedback.
- **Livrables** :
  - Nouvelle couche de configuration front (data registry) + instrumentation des événements.
  - Tests d'accessibilité et de clavier pour les nouveaux composants.
- **Référence** : `docs/pistes-amelioration-design.md` section 4.

### 7. Mode aperçu enrichi
- **Portée** : `preview.js`, `edit.js` et pipeline de synchronisation des presets.
- **Cibles** : remplacer le rendu HTML statique par un canvas React capable de charger les tokens du thème actif et de simuler plusieurs points de rupture.
- **Livrables** :
  - Composant `PreviewCanvas` avec bascule responsive et mode sombre.
  - Hooks de personnalisation permettant d’injecter des champs dynamiques (ACF, taxonomies personnalisées).
- **Référence** : `docs/comparaison-apps-pro.md` section « Focus UX/UI détaillé » et `docs/pistes-amelioration-design.md` section 4.

## 6-12 mois — Observabilité et intégrations

### 8. Tableau de bord instrumentation
- **Portée** : page « Instrumentation » et endpoints REST (`/track`).
- **Cibles** : centraliser les métriques temps réel (latence, erreurs) et proposer des exportations vers Segment/Adobe.
- **Livrables** :
  - Collecte côté serveur (`my_articles_track_interaction`) enrichie et enregistrement de logs structurés.
  - Dashboard React dans l'admin + documentation d'intégration.
- **Référence** : README section « Instrumentation et suivi » et `docs/fonctions-a-ameliorer.md` section 3.

### 9. Connecteurs de contenu avancés
- **Portée** : résolution des sources (`build_display_state`) et normalisation des IDs épinglés.
- **Cibles** : permettre de plugger des services externes (Elastic, API tierces) via des adapters et un système de priorités.
- **Livrables** :
  - Hooks pour injecter des `WP_Query` alternatifs ou des collections d'articles.
  - Tests d'intégration couvrant les fallback WordPress vs. source externe.
- **Référence** : README section « Intégrations développeurs » et `docs/fonctions-a-ameliorer.md` section 2.

## Dépendances transverses

| Sujet | Dépendances | Notes |
| --- | --- | --- |
| Cache & performance | Finalisation du service de rendu avant instrumentation, correctifs de clés REST | Prioriser l'extraction de la couche données pour éviter la dette lors du monitoring. |
| Accessibilité | Requiert les nouvelles interfaces de filtres | Prévoir un audit axe-core à chaque refonte UI. |
| Internationalisation | À synchroniser avec la gestion des presets JSON | Maintenir la procédure de re-encodage `.mo` décrite dans le README. |
| Observabilité | Dépend du durcissement des clés de cache et de la nouvelle stack de tests | Bloquer les déploiements tant que les tableaux de bord n'ont pas de données valides. |

## Suivi opérationnel

- Ajouter ces jalons au board produit (colonne « Discovery » ↔ « Delivery »).
- Créer un gabarit de ticket incluant : objectif, métriques attendues, dépendances, checklist QA.
- Revoir la roadmap trimestriellement avec les équipes produit/design afin d'ajuster les priorités.
