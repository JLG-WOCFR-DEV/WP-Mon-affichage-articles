=== Tuiles - JLG ===
Contributors: jeromelegousse, jlg
Tags: articles, tuiles, shortcode, gutenberg, grille
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Affiche les articles d’une catégorie via shortcode et bloc Gutenberg, avec une grille, une liste ou un diaporama.

== Description ==

Tuiles - JLG affiche une sélection d’articles avec `[mon_affichage_articles id="123"]` ou le bloc « Tuiles - JLG ».

* Réglages sous le menu wp-admin **Tuiles - JLG** (`h1`, `nav-tab`, Settings API, `form-table`, `button-primary`, `notice-*`)
* Bloc Gutenberg en `apiVersion` 3, prévu pour l’éditeur d’articles iframé de WordPress 7.1
* Préréglages de design (le slug `lcv-classique` reste inchangé, libellé « Classique JLG »)
* Shortcode, options et classes CSS historiques conservés (`mon_affichage_articles`, `my_articles_options`, `my-articles-*`)

Auteur : Jérôme Le Gousse.

== Installation ==

1. Copier le dossier `mon-affichage-article` dans `wp-content/plugins/`.
2. Activer l’extension « Tuiles - JLG ».
3. Ouvrir **Tuiles - JLG** dans le menu d’administration, créer un contenu `mon_affichage`, puis coller le shortcode ou insérer le bloc.

== Changelog ==

= 2.4.1 =
* IMPROVEMENT: nom visible **Tuiles - JLG** et auteur Jérôme Le Gousse.
* IMPROVEMENT: menu admin, titres, bloc, onboarding, i18n, REST et WP-CLI alignés sur le nouveau nom.
* FIX: onglet Instrumentation enregistre enfin les options via la Settings API.
* FIX: notice native dans la metabox ; l’adaptateur de prévisualisation charge son interface (plus de fatal hors bootstrap).

= 2.4.0 =
* IMPROVEMENT: chrome wp-admin natif (wrap, h1, nav-tab, Settings API).
* IMPROVEMENT: en-têtes Requires / PHP / Tested up to 7.1.
* FIX: canvas Gutenberg iframé (CSS tuiles + garde JS).
* FIX: chargement de `My_Articles_Display_State_Builder` et extract `$args` pour `load_template()` sous WordPress 7.1.
