# Regression Notes

## Reliable front-end initializers
- **Context:** AJAX filters and load-more requests can inject new markup after the initial `DOMContentLoaded` event.
- **Guardrail:** `window.myArticlesInitWrappers()` and `window.myArticlesInitSwipers()` can be re-run after content updates to restore responsive layouts and Swiper sliders.
- **Verification:** Trigger the relevant AJAX action (filter or load-more) and call the exposed initializers in the success callback to confirm that grid breakpoints and sliders refresh correctly.
