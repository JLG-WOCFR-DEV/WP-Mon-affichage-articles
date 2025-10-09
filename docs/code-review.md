# Revue ergonomique et technique de « Tuiles – LCV »

## Ergonomie & présentation des options
- La page d'administration juxtapose plus de vingt champs hétérogènes (catégorie par défaut, colonnes, marges, couleurs, instrumentation, etc.) sur un seul écran sans hiérarchie ni dépendances conditionnelles, ce qui surcharge l'utilisateur par rapport aux configurateurs professionnels qui segmentent par cas d'usage ou proposent des parcours guidés.【F:mon-affichage-article/includes/class-my-articles-settings.php†L212-L248】
- Les contrôles numériques/couleurs sont rendus sous leur forme brute (`<input type="number">`, color picker natif) sans aperçu immédiat du rendu ou description contextuelle, obligeant des allers-retours entre l'écran d'options et l'éditeur/bloc pour valider l'effet réel.【F:mon-affichage-article/includes/class-my-articles-settings.php†L294-L316】

**Pistes d'amélioration**
- Regrouper les options en sous-sections repliables (contenu, disposition, identité visuelle, instrumentation) avec un sommaire latéral ou un panneau à onglets secondaires, et masquer dynamiquement les réglages non pertinents selon le mode choisi (grille, liste, diaporama).
- Ajouter un panneau d'aperçu côté admin (iframe, `wp.element` + `@wordpress/components`) qui reflète en temps réel les ajustements de couleurs/espacements pour réduire la charge cognitive.

## UX / UI côté front
- Le texte d'appel « Lire la suite » est rendu via un simple `<span>` sans lien ni focus clavier, ce qui rompt les attentes utilisateur et empêche un lecteur d'écran de l'annoncer comme action.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2470-L2516】
- L'état vide renvoie un paragraphe inline fortement stylé en dur, sans classes ni tonalité visuelle cohérente avec le reste des cartes, ce qui donne une impression moins aboutie qu'une interface pro.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2328-L2335】

**Pistes d'amélioration**
- Transformer l'appel « Lire la suite » en lien secondaire (`<a>` ou `<button>` accessible) pointant vers l'article ou permettant d'afficher l'intégralité du contenu, et prévoir un style focus cohérent.
- Externaliser l'état vide dans un composant dédié (classe BEM + variables CSS) pour l'aligner sur la charte et permettre la traduction/illustration.

## Accessibilité
- La zone de suggestions de recherche est construite avec un conteneur `role="list"` mais chaque suggestion est un `<button role="listitem">`, combinaison non valide pour les lecteurs d'écran et sans annonce de visibilité (pas d'`aria-expanded` sur le champ).【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2079-L2084】【F:mon-affichage-article/assets/js/filter.js†L447-L465】【F:mon-affichage-article/assets/js/filter.js†L1524-L1581】
- Le bouton « Charger plus » ne communique pas la relation avec la liste ciblée (absence d'`aria-controls` ou d'`aria-describedby`) et repose uniquement sur un changement de texte pour signaler le chargement, ce qui limite la compréhension pour les technologies d'assistance.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2168-L2235】【F:mon-affichage-article/assets/js/load-more.js†L1560-L1639】

**Pistes d'amélioration**
- Recomposer les suggestions en `<ul><li><button></button></li></ul>` ou utiliser `role="listbox"`/`role="option"` avec gestion d'`aria-expanded`, et annoncer l'ouverture/fermeture via `aria-live`.
- Associer le bouton de pagination progressive à la grille avec `aria-controls` et fournir un `aria-label` dynamique (« Charger 6 articles supplémentaires sur 12 ») pour clarifier l'état courant.

## Performance & fiabilité
- Chaque instance injecte un bloc de styles inline complet via `wp_add_inline_style`, ce qui peut dupliquer plusieurs dizaines de lignes CSS quand on place plusieurs modules sur la même page, contrairement aux design systems pro qui factorisent les tokens et n'injectent que les deltas nécessaires.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2847-L2936】
- Les scripts `filter.js` et `load-more.js` dupliquent la même logique d'instrumentation (gestion des canaux console/dataLayer/fetch), ce qui alourdit le bundle et multiplie les risques de divergence lors d'une évolution.【F:mon-affichage-article/assets/js/filter.js†L9-L154】【F:mon-affichage-article/assets/js/load-more.js†L112-L160】
- Les requêtes AJAX ne sont jamais annulées quand l'utilisateur enchaîne rapidement filtres et recherches : si deux réponses arrivent hors ordre, la plus lente peut écraser le dernier état affiché, ce qui nuit à la fiabilité perçue.【F:mon-affichage-article/assets/js/filter.js†L1235-L1319】【F:mon-affichage-article/assets/js/load-more.js†L1560-L1639】

**Pistes d'amélioration**
- Remplacer l'injection CSS full par un système de classes utilitaires (ou CSS custom properties globales) et limiter l'inline aux seules variables personnalisées, ou sérialiser les styles dans un fichier généré et mis en cache.
- Extraire les fonctions communes d'instrumentation et de gestion du nonce dans un module partagé (ESM ou IIFE importé par les deux bundles) pour réduire la taille et garantir la cohérence des canaux de télémétrie.
- Stocker le `jqXHR`/`AbortController` courant pour annuler la requête précédente avant d'en lancer une nouvelle, et ignorer les réponses obsolètes via un identifiant incrémental pour sécuriser l'état de l'UI.

