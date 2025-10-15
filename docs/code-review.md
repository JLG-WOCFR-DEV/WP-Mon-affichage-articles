# Revue du plugin « Tuiles – LCV »

## Résumé exécutif
- Le cœur du plugin est bien structuré (services singleton, séparation helpers / REST / bloc) mais certains comportements JavaScript sont ré-implémentés « maison » et n’offrent pas les garanties attendues (gestion du carrousel notamment).
- L’implémentation actuelle du diaporama enfreint plusieurs critères RGAA (clavier, lecture d’écran) : les puces de pagination ne sont pas accessibles et les diapositives hors champ restent exposées aux technologies d’assistance.
- Les liens dupliqués dans chaque carte peuvent compliquer la navigation clavier et génèrent une répétition inutile pour les lecteurs d’écran.

## Points de vigilance fonctionnels & debugging
1. **Pagination Swiper minimaliste** – Le fichier `assets/vendor/swiper/swiper-bundle.min.js` ré-implémente Swiper en version simplifiée. Aucune gestion du clavier ou des attributs ARIA n’est fournie pour les puces de pagination, qui restent de simples `<span>` cliquables à la souris.【F:mon-affichage-article/assets/vendor/swiper/swiper-bundle.min.js†L96-L155】  
   → Pour debug : vérifier `window.Swiper` dans la console, puis forcer `slideNext()` / `slidePrev()` pour constater l’absence d’état `aria-hidden` mis à jour.
2. **Navigation du carrousel** – Le gabarit PHP ne fait que créer le conteneur `swiper-container`/`swiper-slide`, sans logique côté serveur pour l’accessibilité (ni `aria-hidden`, ni `tabindex`). Toute correction devra passer par le JS d’initialisation ou par un hook PHP ajoutant ces attributs après rendu.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2566-L2644】
3. **Liens redondants** – Chaque carte possède deux liens vers le même article (thumbnail + titre). Pour déboguer les annonces répétées, tester avec NVDA/VoiceOver : l’utilisateur entend deux fois le même intitulé. Une refactorisation pour envelopper la carte d’un lien unique simplifierait la navigation.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2676-L2770】

## Analyse RGAA
| Critère | État | Détails |
| --- | --- | --- |
| **RGAA 7.1 / 7.3 (Navigation clavier)** | ❌ Non conforme | Les puces de pagination sont rendues en `<span>` sans `tabindex` ni gestion `keydown`. Impossible d’y accéder au clavier.【F:mon-affichage-article/assets/vendor/swiper/swiper-bundle.min.js†L112-L143】 |
| **RGAA 4.1 (Structuration de l’information)** | ⚠️ À surveiller | Les diapositives masquées restent exposées dans le DOM, faute d’attributs `aria-hidden` / `inert`. Les lecteurs d’écran lisent toutes les cartes d’un coup, ce qui va à l’encontre des recommandations ARIA pour les carrousels.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2566-L2644】【F:mon-affichage-article/assets/vendor/swiper/swiper-bundle.min.js†L130-L155】 |
| **RGAA 6.1 (Compréhension des liens)** | ⚠️ À surveiller | Deux liens successifs avec le même intitulé (« Lire plus » + titre) alourdissent la navigation. Fusionner les liens ou ajouter du contexte éviterait la redondance.【F:mon-affichage-article/includes/class-my-articles-shortcode.php†L2676-L2806】 |

## Recommandations
1. **Refondre les puces de pagination en boutons** avec un `role="tab"`, `tabindex` géré, et des étiquettes explicites (« Aller à la diapositive n »). Le JS devra écouter `keydown` (Entrée/Espace) en plus de `click`.
2. **Masquer les diapositives inactives** : ajouter `aria-hidden="true"` et éventuellement `tabindex="-1"` sur les slides hors champ, puis inverser ces valeurs sur la diapositive active. L’API JS peut gérer cette bascule après chaque `slideTo`.
3. **Limiter la duplication de liens** : transformer l’ensemble de la carte en lien unique (ou transformer le lien visuel en `div` non interactif) pour alléger la lecture.
4. **Tests d’accessibilité** : prévoir une recette avec un lecteur d’écran (NVDA ou VoiceOver) + audit axe DevTools pour valider la conformité RGAA, en particulier sur la zone carrousel.

