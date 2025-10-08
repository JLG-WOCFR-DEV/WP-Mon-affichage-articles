# Propositions de préréglages graphiques

Ce document rassemble plusieurs pistes de préréglages inspirés de bibliothèques d'interface populaires. Chaque preset est pensé pour être transposable dans le bloc « Mon affichage d'articles » via des variables de thème (`theme.json`) et des classes utilitaires afin de faciliter les tests et l'industrialisation.

## 1. Preset « Headless Air » (inspiré de Headless UI, slug : `headless-air`)
- **Palette** : dominante neutre (gris bleu clair), accent bleu #3B82F6, mode sombre en inversion douce.
- **Typographie** : police sans serif modulaire (Inter, Source Sans), interlignage généreux et capitalisation minimale.
- **Composants** : cartes à coins arrondis (`--wp--custom--radius--lg`), ombres douces, séparateurs fins.
- **Interactions** : transitions `ease-in-out` 200 ms sur les hover/focus, focus ring apparente en bleu.
- **Usage** : idéal pour un rendu sobre mais contemporain dans une logique « design system » minimal.

## 2. Preset « Shadcn Contrast » (inspiré de Shadcn UI, slug : `shadcn-contrast`)
- **Palette** : contraste marqué (fond quasi noir, textes ivoire), accent chartreuse pour les tags.
- **Typographie** : `font-family: "Satoshi", "Manrope", sans-serif`, titres en `font-weight: 700`, corps `500`.
- **Composants** : cartes borderless avec `backdrop-filter: blur(12px)` et `border` translucide (`rgba(255,255,255,0.08)`).
- **Interactions** : micro-animations `transform: translateY(-2px)` au survol, focus state en double contour.
- **Usage** : met l'accent sur les articles premium ou contenus spéciaux grâce au fort contraste.

## 3. Preset « Radix Modular » (inspiré de Radix UI, slug : `radix-modular`)
- **Palette** : gammes programmatiques (bleu, violet, vert) définies en paliers (`50 → 900`).
- **Typographie** : `font-size` responsive via clamp (`clamp(1rem, 1vw + 0.9rem, 1.15rem)`), titres en `Space Grotesk`.
- **Composants** : tokens de `border-radius` (`--wp--custom--radius--md`) et `spacing` stricts (4/8/12/16/24).
- **Interactions** : états de `aria-pressed` et `aria-expanded` explicités par des variations de couleur/saturation.
- **Usage** : parfait pour un bloc riche en filtres ou vues tabulaires nécessitant une hiérarchie d'état claire.

## 4. Preset « Bootstrap Classic » (slug : `bootstrap-classic`)
- **Palette** : déclinaisons du bleu primaire (`#0D6EFD`) avec success, warning, danger alignés sur la charte Bootstrap.
- **Typographie** : `font-family: "IBM Plex Sans", system-ui`, accent mis sur les `lead` et badges.
- **Composants** : grilles 12 colonnes (3 cartes par ligne sur desktop), boutons `btn` arrondis légers.
- **Interactions** : effets hover simples (légère augmentation de la luminosité) et états `active` nets.
- **Usage** : utile pour des sites institutionnels ou portails d'information cherchant une lecture familière.

## 5. Preset « Semantic Soft » (inspiré de Semantic UI, slug : `semantic-soft`)
- **Palette** : couleurs pastels modulaires (`teal`, `orange`, `violet`) avec nuances de `grey` équilibrées.
- **Typographie** : `Lato` ou `Open Sans`, `letter-spacing` légèrement augmenté pour les titres.
- **Composants** : segments avec `box-shadow` faible, `divider` colorés, `label` arrondis pour les métadonnées.
- **Interactions** : transitions `ease` 150 ms, `hover` sur les tags qui change la teinte (+10 de saturation).
- **Usage** : adapté aux rubriques magazine, lifestyle ou culture.

## 6. Preset « Anime Motion » (inspiré de Anime.js, slug : `anime-motion`)
- **Palette** : fond sombre (`#111827`) et accents néon (cyan, magenta) pour souligner les animations.
- **Typographie** : `JetBrains Mono` pour les badges, `Poppins` pour les titres afin de contraster.
- **Composants** : cartes minimalistes avec `border` lumineuse animée (gradient animé via `@keyframes`).
- **Interactions** : animations séquencées (fade-in + scale) à l'apparition des articles, transitions dynamiques sur les filtres.
- **Usage** : pour des pages événementielles, lancements produit ou storytelling immersif.

## Implémentation suggérée
1. Déclarer chaque preset dans un fichier JSON (par ex. `presets/{slug}.json`) contenant couleurs, typos et tokens CSS.
2. Générer des classes utilitaires (`.is-style-{slug}`) via build CSS pour activer un preset sur le bloc.
3. Prévoir des aperçus (captures WebP) et métadonnées (mode clair/sombre, tonalité, accessibilité) pour l'interface d'administration.
4. Exposer une commande npm (`npm run generate-presets`) qui synchronise les JSON avec `theme.json` et le CSS compilé.

Ces suggestions servent de base pour étendre la bibliothèque de préréglages et offrir un spectre esthétique aligné sur des écosystèmes UI éprouvés.
