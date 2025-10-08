# Regression Notes

## Reliable front-end initializers
- **Context:** REST-powered filters, load-more, and search requests inject markup after the initial `DOMContentLoaded` event.
- **Guardrail:** `window.myArticlesInitWrappers()` and `window.myArticlesInitSwipers()` can be re-run after content updates to restore responsive layouts and Swiper sliders.
- **Verification:** Trigger the relevant REST action (filter, load-more, search) and call the exposed initializers in the success callback to confirm that grid breakpoints and sliders refresh correctly.

## REST transition checklist
- **Filter endpoint:** Ensure `POST /my-articles/v1/filter` responds with updated markup, pagination metadata, and pinned IDs. Confirm that invalid nonces return a `403` error and forbidden categories raise `WP_Error` codes.
- **Load-more endpoint:** Validate pagination counters (`total_pages`, `next_page`) and button state updates after `POST /my-articles/v1/load-more`. Confirm that disabled pagination returns the `my_articles_load_more_disabled` error payload.
- **Search endpoint:** Verify `GET /my-articles/v1/search` requires a valid nonce, enforces the `post_type` capability check, and returns `{ results: [] }` structures. Invalid post types should raise the `my_articles_invalid_post_type` error.

## Gradual deprecation of legacy AJAX routes
- **Phase 1:** Mirror responses between legacy AJAX callbacks and REST controllers using the new `prepare_*_response` helpers. Monitor logs for unexpected JSON payload differences.
- **Phase 2:** Update front-end assets to prefer REST endpoints (with nonce injection) while keeping AJAX fallbacks for older caches.
- **Phase 3:** Remove unused `wp_ajax_*` hooks once REST coverage reaches 100% and analytics confirm no AJAX traffic for 30 days.

## REST cache key hardening
- **Collision test:** Prepare two payloads (`search = "sort:date"` & `sort = "date"`) and confirm `generate_response_cache_key()` produces distinct hashes after the prefix refactor. Repeat with `pinned_ids = [123]` vs `search = "123"`.
- **Manual QA:** Purge any persisted cache (`wp transient delete --all`, flush object cache) before déployer les nouvelles clés pour éviter de servir des réponses mélangées.
- **Debug instrumentation:** Activer `define( 'MY_ARTICLES_DEBUG_CACHE', true );` dans l'environnement de staging pour suivre les hits/miss dans les logs et vérifier qu'aucune collision n'est enregistrée sous forte charge.【F:docs/code-review.md†L5-L23】
- **Extensions tierces:** Brancher un filtre de test sur `my_articles_cache_fragments` pour ajouter un fragment personnalisé et vérifier qu'il apparaît bien dans les logs `MY_ARTICLES_DEBUG_CACHE` (hash distinct attendu).
