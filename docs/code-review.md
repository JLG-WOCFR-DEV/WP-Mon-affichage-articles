# Code Review

## Points principaux

- **Clé de cache ambiguë pour les réponses filtrées** : dans `prepare_filter_articles_response()`, les fragments ajoutés à `$cache_extra_parts` ne sont pas nommés. Une recherche telle que `"sort:date"` produira exactement la même chaîne qu'un tri `sort => date`. Résultat, la clé générée avec `generate_response_cache_key()` peut renvoyer à tort une réponse mise en cache d'un contexte différent (recherche vs tri). Il suffirait de préfixer explicitement la valeur de recherche (par ex. `search:`) pour éviter la collision. 【F:mon-affichage-article/mon-affichage-articles.php†L474-L492】
- **Même problème côté chargement progressif, aggravé par les identifiants épinglés** : `prepare_load_more_articles_response()` ajoute aussi la recherche sans préfixe et concatène les identifiants épinglés sans espace de nom. Une recherche `"123"` peut donc partager sa clé de cache avec un lot d'IDs `123`, renvoyant des résultats incohérents au « load more ». Là aussi, préfixer les fragments (ex. `search:` et `pinned:`) éliminerait le risque. 【F:mon-affichage-article/mon-affichage-articles.php†L794-L820】

## Recommandations

1. Préfixer systématiquement chaque morceau ajouté à `$cache_extra_parts` (`search:`, `sort:`, `filters:`, `pinned:`) avant de construire la clé. Cela garantit l'unicité des combinaisons de contexte et évite les collisions difficiles à diagnostiquer.
2. Ajouter un test de non-régression qui prépare deux réponses avec des combinaisons ambiguës (`search = 'sort:foo'` vs `sort = 'foo'`, ou `search = '123'` vs `pinned_ids = '123'`) pour s'assurer que chaque cas produit une clé de cache distincte.

