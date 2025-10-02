# Regression Notes

## Reliable front-end initializers
- **Context:** AJAX filters and load-more requests can inject new markup after the initial `DOMContentLoaded` event.
- **Guardrail:** `window.myArticlesInitWrappers()` and `window.myArticlesInitSwipers()` can be re-run after content updates to restore responsive layouts and Swiper sliders.
- **Verification:** Trigger the relevant AJAX action (filter or load-more) and call the exposed initializers in the success callback to confirm that grid breakpoints and sliders refresh correctly.

## REST transition guardrails
- **Context:** Front-end interactions now call the REST endpoints `my-articles/v1/filter` and `my-articles/v1/load-more` instead of legacy `admin-ajax.php` actions.
- **Front non-regression:** Simulate category changes and sequential load-more clicks while intercepting REST responses. Verify that `X-WP-Nonce` headers are sent, that pagination attributes (`data-paged`, `data-total-pages`, `data-pinned-ids`) stay in sync with the responses, and that the accessibility feedback region reflects the server counts.
- **Back non-regression:** Issue REST requests with valid/invalid nonces and ensure that controller methods return structured payloads for success and `WP_Error` objects (status `403` or `400`) for rejected requests. Confirm that pagination metadata (`total_pages`, `next_page`) is respected across cached and uncached responses.

## Progressive decommission of AJAX routes
- **Phase 1:** Keep legacy AJAX callbacks registered but wrap them with deprecation notices and smoke tests to guarantee they proxy to the REST controllers during the rollout.
- **Phase 2:** Gate the AJAX callbacks behind a feature flag (filter or option) so administrators can opt into REST-only behaviour once theme-side scripts are deployed.
- **Phase 3:** Remove the callbacks and enqueue logic tied to `wp_ajax_*` once monitoring confirms REST usage only. Update documentation and configuration to reflect the REST requirement.
