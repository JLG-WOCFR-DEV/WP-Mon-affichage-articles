// Fichier: assets/js/utils/url-state.js
(function (global) {
    'use strict';

    if (!global) {
        return;
    }

    function updateInstanceQueryParams(instanceId, params) {
        if (typeof global.history === 'undefined') {
            return;
        }

        var historyApi = global.history;
        var historyMethod = null;

        if (typeof historyApi.replaceState === 'function') {
            historyMethod = 'replaceState';
        } else if (typeof historyApi.pushState === 'function') {
            historyMethod = 'pushState';
        }

        if (!historyMethod || !global.location || !global.location.href) {
            return;
        }

        try {
            var url = new URL(global.location.href);

            Object.keys(params || {}).forEach(function (key) {
                var value = params[key];

                if (value === null || typeof value === 'undefined' || value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            });

            historyApi[historyMethod](null, '', url.toString());
        } catch (error) {
            // Silencieusement ignorer les erreurs (navigateurs plus anciens ou URL invalide)
        }
    }

    global.MyArticlesUtils = global.MyArticlesUtils || {};
    global.MyArticlesUtils.updateInstanceQueryParams = updateInstanceQueryParams;

})(typeof window !== 'undefined' ? window : null);
