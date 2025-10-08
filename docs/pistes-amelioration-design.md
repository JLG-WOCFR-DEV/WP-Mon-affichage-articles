# Pistes d'amélioration du design

## 1. Formaliser un système de thèmes complet
- Étendre les jetons CSS existants (couleurs, rayons, espacements) pour générer dynamiquement des variations clair/sombre et des paliers de contraste AA/AAA plutôt que des valeurs statiques. Cela permettrait d'offrir des préréglages accessibles tout en restant aligné sur les Global Styles du thème.
- Publier ces jeux de variables sous forme de presets exportables (JSON) afin qu'ils puissent être versionnés, partagés entre sites et liés à des `theme.json` personnalisés.

> **Point de départ actuel :** le plugin expose déjà des variables CSS basées sur les presets WordPress (`--wp--preset--color--*`, `--wp--preset--font-size--*`) que l'on peut enrichir pour générer automatiquement des paliers complémentaires et détecter le mode sombre.【F:mon-affichage-article/assets/css/styles.css†L3-L43】

## 2. Proposer une galerie de préréglages guidée
- Ajouter une vue « catalogue » dans le panneau Module pour présenter les préréglages existants avec captures, tags (clair/sombre, usages éditoriaux) et descriptions synthétiques.
- Permettre d'enregistrer des variantes personnalisées (ex. par rédaction ou section du site) puis de les partager via l'API REST afin de capitaliser sur les configurations plébiscitées.
- Offrir un bouton « Dupliquer ce preset » qui pré-remplit les curseurs avant de passer en mode personnalisé, afin de réduire la friction entre expérimentation et industrialisation.

> **Point de départ actuel :** l'éditeur expose quatre préréglages (« Personnalisé », « Classique LCV », « Projecteur sombre », « Focus éditorial ») mais sans aperçu ni métadonnées, ce qui limite leur appropriation par les équipes éditoriales.【F:README.md†L69-L98】

## 3. Raffiner les états de chargement et vides
- Décliner plusieurs variations de skeleton (carte, liste, diaporama) avec options de densité, de couleur et de motion pour coller aux différents contextes éditoriaux (actualité chaude vs. dossiers long format).
- Prévoir une section « État vide » dans le panneau Couleurs/Contenus permettant de configurer un message, une illustration et éventuellement des CTA pour rediriger les lecteurs.
- Introduire une transition configurable entre skeleton et contenu (fondu, slide, scale) afin d'ancrer la signature visuelle de la marque.

> **Point de départ actuel :** les skeletons sont uniformes, calés sur une animation shimmer et quelques variables de padding ; aucune personnalisation éditoriale n'est exposée côté bloc ou CSS.【F:mon-affichage-article/assets/css/styles.css†L55-L200】

## 4. Enrichir les filtres front-office
- Ajouter un mode « barre de filtres avancés » proposant recherche multi-critères, boutons de tri rapides et favoris afin de guider les lecteurs vers les contenus clés.
- Introduire un sélecteur de mise en page des filtres (pile verticale, grille, navigation horizontale sticky) et la possibilité d'afficher des chips contextuelles (tags, formats, auteurs) en complément de la taxonomie principale.
- Exploiter les interactions existantes (`my-articles:filter`) pour afficher des micro-feedbacks visuels (badge animé sur le filtre actif, compteur de résultats) sans recharger la page.

> **Point de départ actuel :** le bloc fournit des contrôles basiques (alignement, activation/désactivation, libellé ARIA) mais pas de personnalisation avancée des filtres ou du tri multi-niveaux.【F:mon-affichage-article/blocks/mon-affichage-articles/edit.js†L1248-L1378】【F:README.md†L69-L108】

## 5. Consolider accessibilité et personnalisation motion
- Étendre les options d’accessibilité dans l’éditeur (contraste renforcé, bascule `prefers-reduced-motion`, descriptions audio) pour aligner les presets sur les référentiels RGAA/WCAG.
- Documenter des jeux de tokens compatibles avec les modes d’affichage à forte densité (liste compacte, slider auto) tout en conservant des repères de focus visibles.
- Introduire un configurateur d’animations permettant de choisir pour chaque preset une courbe d’accélération, une durée et un comportement en entrée/sortie afin de couvrir les contextes éditoriaux (actualité vs dossier).

> **Point de départ actuel :** les feuilles de style appliquent une animation shimmer unique et une transition d’opacité par défaut ; aucun réglage n’est exposé pour les utilisateurs sensibles au mouvement ou les sites devant respecter des normes d’accessibilité renforcées.【F:mon-affichage-article/assets/css/styles.css†L55-L200】
