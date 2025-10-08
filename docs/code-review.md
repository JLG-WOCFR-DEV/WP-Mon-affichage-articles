# Code Review

## Points principaux

- **Clé de cache ambiguë pour les réponses filtrées** : dans `prepare_filter_articles_response()`, les fragments ajoutés à `$cache_extra_parts` ne sont pas nommés. Une recherche telle que `"sort:date"` produira exactement la même chaîne qu'un tri `sort => date`. Résultat, la clé générée avec `generate_response_cache_key()` peut renvoyer à tort une réponse mise en cache d'un contexte différent (recherche vs tri). Il suffirait de préfixer explicitement la valeur de recherche (par ex. `search:`) pour éviter la collision. 【F:mon-affichage-article/mon-affichage-articles.php†L474-L492】
- **Même problème côté chargement progressif, aggravé par les identifiants épinglés** : `prepare_load_more_articles_response()` ajoute aussi la recherche sans préfixe et concatène les identifiants épinglés sans espace de nom. Une recherche `"123"` peut donc partager sa clé de cache avec un lot d'IDs `123`, renvoyant des résultats incohérents au « load more ». Là aussi, préfixer les fragments (ex. `search:` et `pinned:`) éliminerait le risque. 【F:mon-affichage-article/mon-affichage-articles.php†L794-L820】

## Recommandations

1. Préfixer systématiquement chaque morceau ajouté à `$cache_extra_parts` (`search:`, `sort:`, `filters:`, `pinned:`) avant de construire la clé. Cela garantit l'unicité des combinaisons de contexte et évite les collisions difficiles à diagnostiquer.
2. Ajouter un test de non-régression qui prépare deux réponses avec des combinaisons ambiguës (`search = 'sort:foo'` vs `sort = 'foo'`, ou `search = '123'` vs `pinned_ids = '123'`) pour s'assurer que chaque cas produit une clé de cache distincte.

## Plan d'action proposé

- **Étape 1 — Refactor** : introduire une classe `My_Articles_Response_Cache_Key` responsable de la normalisation des fragments et du hash final. Cette classe doit être couverte par des tests unitaires (`tests/ResponseCacheKeyTest.php`).
- **Étape 2 — Documentation** : compléter `tests/REGRESSIONS.md` avec un scénario manuel décrivant la purge des caches existants après déploiement et les commandes WP-CLI associées.【F:tests/REGRESSIONS.md†L1-L26】
- **Étape 3 — Observabilité** : journaliser les hits/miss dans `WP_DEBUG_LOG` (ou un canal dédié) lorsque la constante `MY_ARTICLES_DEBUG_CACHE` est active afin d'alimenter un futur tableau de bord instrumentation.【F:docs/roadmap-technique.md†L8-L84】

