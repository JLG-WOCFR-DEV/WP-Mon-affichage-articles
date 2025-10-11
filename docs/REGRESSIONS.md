# Registres de régressions et comportements subtils

## Normalisation des fragments de cache associatifs

Lorsque le filtre `my_articles_cache_fragments` fournit des fragments supplémentaires, les tableaux associatifs sont désormais sérialisés en tenant compte de leurs clés. Chaque paire clé/valeur est normalisée, triée puis intégrée au hash pour éviter les collisions entre fragments partageant les mêmes valeurs mais des clés différentes. Les intégrateurs doivent donc veiller à choisir des clés explicites : elles influencent directement l'identifiant de cache généré.
