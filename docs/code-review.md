# Code Review

## Points principaux

- ✅ **Clé de cache ambiguë pour les réponses filtrées** : les fragments sont désormais normalisés et nommés via la classe `My_Articles_Response_Cache_Key`, garantissant des clés distinctes entre recherche et tri.【F:mon-affichage-article/mon-affichage-articles.php†L470-L552】【F:mon-affichage-article/includes/class-my-articles-response-cache-key.php†L1-L115】
- ✅ **Sécurité du cache côté chargement progressif** : le même traitement s'applique aux routes « load more » avec tri, filtres et identifiants épinglés ordonnés et préfixés pour éviter toute collision.【F:mon-affichage-article/mon-affichage-articles.php†L760-L852】

## Nouvelles capacités QA & observabilité

- `tests/ResponseCacheKeyTest.php` couvre la non-régression sur les collisions connues et la stabilité des fragments.【F:tests/ResponseCacheKeyTest.php†L1-L64】
- Le filtre `my_articles_cache_fragments` permet d'ajouter des fragments customisés tout en respectant la nomenclature officielle.【F:mon-affichage-article/mon-affichage-articles.php†L470-L552】【F:README.md†L37-L44】
- Lorsque `MY_ARTICLES_DEBUG_CACHE` est défini à `true`, chaque hit/miss/promotion est loggé dans `WP_DEBUG_LOG`, fournissant une base pour le futur dashboard instrumentation.【F:mon-affichage-article/mon-affichage-articles.php†L1116-L1179】

## Prochaines étapes

- Continuer à enrichir `tests/REGRESSIONS.md` avec un scénario WP-CLI complet de purge après déploiement (documentation amorcée).【F:tests/REGRESSIONS.md†L1-L26】
- Étendre la télémétrie aux temps de rendu serveur (corrélation avec les événements front) lors de la mise en place du dashboard instrumentation.

