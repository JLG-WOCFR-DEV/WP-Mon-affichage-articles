// Fichier: assets/js/shared-runtime.js
(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], function () {
            return factory(root);
        });
        return;
    }

    if (typeof module === 'object' && module.exports) {
        module.exports = factory(root);
        return;
    }

    root.myArticlesShared = factory(root);
}(typeof globalThis !== 'undefined' ? globalThis : (typeof self !== 'undefined' ? self : this), function (root) {
    'use strict';

    var existing = (root && root.myArticlesShared && typeof root.myArticlesShared === 'object')
        ? root.myArticlesShared
        : {};

    var INSTRUMENTATION_DEFAULTS = {
        enabled: false,
        channel: 'console',
        fetchUrl: ''
    };

    function logError(error) {
        if (root && root.console && typeof root.console.error === 'function') {
            root.console.error(error);
        }
    }

    function dispatchCustomEvent(eventName, detail) {
        var target = root && root.document ? root : null;

        if (!target) {
            return;
        }

        var eventDetail = detail || {};
        var customEvent;

        try {
            if (typeof target.CustomEvent === 'function') {
                customEvent = new target.CustomEvent(eventName, { detail: eventDetail });
            } else if (target.document && typeof target.document.createEvent === 'function') {
                customEvent = target.document.createEvent('CustomEvent');
                customEvent.initCustomEvent(eventName, false, false, eventDetail);
            }

            if (customEvent && typeof target.dispatchEvent === 'function') {
                target.dispatchEvent(customEvent);
            }
        } catch (error) {
            logError(error);
        }
    }

    function resolveInstrumentationConfig(settings) {
        var config = settings && typeof settings.instrumentation === 'object'
            ? settings.instrumentation
            : null;

        if (!config) {
            return {
                enabled: INSTRUMENTATION_DEFAULTS.enabled,
                channel: INSTRUMENTATION_DEFAULTS.channel,
                fetchUrl: INSTRUMENTATION_DEFAULTS.fetchUrl,
                callback: null
            };
        }

        var channel = typeof config.channel === 'string' ? config.channel : INSTRUMENTATION_DEFAULTS.channel;
        var enabled = !!config.enabled;
        var fetchUrl = typeof config.fetchUrl === 'string' ? config.fetchUrl : '';

        if (!fetchUrl && settings && typeof settings.restRoot === 'string') {
            fetchUrl = settings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/track';
        }

        return {
            enabled: enabled,
            channel: channel,
            fetchUrl: fetchUrl,
            callback: typeof config.callback === 'function' ? config.callback : null
        };
    }

    function createEventEmitter(getSettings) {
        if (typeof getSettings !== 'function') {
            var staticSettings = getSettings;
            getSettings = function () {
                return staticSettings;
            };
        }

        function getInstrumentationSettings() {
            return resolveInstrumentationConfig(getSettings());
        }

        function runEventCallbacks(eventName, detail) {
            var settings = getSettings();

            if (settings && typeof settings.onEvent === 'function') {
                try {
                    settings.onEvent(eventName, detail);
                } catch (error) {
                    logError(error);
                }
            }

            var instrumentation = getInstrumentationSettings();
            if (instrumentation.callback) {
                try {
                    instrumentation.callback(eventName, detail);
                } catch (error) {
                    logError(error);
                }
            }
        }

        function routeInstrumentation(eventName, detail) {
            var instrumentation = getInstrumentationSettings();

            if (!instrumentation.enabled) {
                return;
            }

            var payload = {
                event: eventName,
                detail: detail
            };

            if (instrumentation.channel === 'dataLayer') {
                if (root) {
                    if (!root.dataLayer || !Array.isArray(root.dataLayer)) {
                        root.dataLayer = [];
                    }

                    try {
                        root.dataLayer.push(payload);
                    } catch (error) {
                        logError(error);
                    }
                }

                return;
            }

            if (instrumentation.channel === 'fetch') {
                if (root && typeof root.fetch === 'function' && instrumentation.fetchUrl) {
                    var headers = { 'Content-Type': 'application/json' };
                    var settings = getSettings();
                    var nonceHeader = settings && typeof settings.restNonce === 'string' ? settings.restNonce : '';

                    if (nonceHeader) {
                        headers['X-WP-Nonce'] = nonceHeader;
                    }

                    try {
                        root.fetch(instrumentation.fetchUrl, {
                            method: 'POST',
                            headers: headers,
                            credentials: 'same-origin',
                            body: JSON.stringify(payload)
                        }).catch(function () {
                            return null;
                        });
                    } catch (error) {
                        logError(error);
                    }
                }

                return;
            }

            if (root && root.console && typeof root.console.log === 'function') {
                root.console.log('[my-articles]', eventName, detail);
            }
        }

        function emit(eventName, detail) {
            dispatchCustomEvent(eventName, detail);
            runEventCallbacks(eventName, detail);
            routeInstrumentation(eventName, detail);
        }

        return {
            dispatchCustomEvent: dispatchCustomEvent,
            getInstrumentationSettings: getInstrumentationSettings,
            runEventCallbacks: runEventCallbacks,
            routeInstrumentation: routeInstrumentation,
            emit: emit
        };
    }

    function getNonceEndpoint(settings) {
        if (settings && typeof settings.nonceEndpoint === 'string' && settings.nonceEndpoint.length > 0) {
            return settings.nonceEndpoint;
        }

        if (settings && typeof settings.restRoot === 'string' && settings.restRoot.length > 0) {
            return settings.restRoot.replace(/\/+$/, '') + '/my-articles/v1/nonce';
        }

        return '';
    }

    function extractNonceFromResponse(response) {
        if (!response || typeof response !== 'object') {
            return '';
        }

        if (typeof response.nonce === 'string' && response.nonce.length > 0) {
            return response.nonce;
        }

        if (response.data && typeof response.data.nonce === 'string' && response.data.nonce.length > 0) {
            return response.data.nonce;
        }

        return '';
    }

    function isInvalidNonceResponse(jqXHR, response) {
        var payload = response || null;

        if (!payload && jqXHR && jqXHR.responseJSON && typeof jqXHR.responseJSON === 'object') {
            payload = jqXHR.responseJSON;
        }

        if (!payload && jqXHR && typeof jqXHR.responseText === 'string') {
            try {
                payload = JSON.parse(jqXHR.responseText);
            } catch (error) {
                payload = null;
            }
        }

        if (!payload || typeof payload !== 'object') {
            return false;
        }

        if (typeof payload.code === 'string' && payload.code === 'my_articles_invalid_nonce') {
            return true;
        }

        if (payload.data && typeof payload.data.code === 'string' && payload.data.code === 'my_articles_invalid_nonce') {
            return true;
        }

        return false;
    }

    function createNonceManager($, options) {
        options = options || {};

        var getSettings = typeof options.getSettings === 'function'
            ? options.getSettings
            : function () {
                return options.settings || null;
            };

        var mirrorAccessors = Array.isArray(options.mirrors) ? options.mirrors : [];
        var pendingNonceDeferred = null;

        function resolveMirrors(explicitSettings) {
            var targets = [];
            var primary = explicitSettings || getSettings();

            if (primary && typeof primary === 'object') {
                targets.push(primary);
            }

            for (var i = 0; i < mirrorAccessors.length; i += 1) {
                var candidate = typeof mirrorAccessors[i] === 'function'
                    ? mirrorAccessors[i]()
                    : mirrorAccessors[i];

                if (candidate && typeof candidate === 'object' && targets.indexOf(candidate) === -1) {
                    targets.push(candidate);
                }
            }

            return targets;
        }

        function applyNonce(nonce, explicitSettings) {
            if (!nonce) {
                return;
            }

            var targets = resolveMirrors(explicitSettings);

            for (var i = 0; i < targets.length; i += 1) {
                targets[i].restNonce = nonce;
            }
        }

        function refreshNonce(explicitSettings) {
            var settings = explicitSettings || getSettings();

            if (pendingNonceDeferred) {
                return pendingNonceDeferred.promise();
            }

            if (!$ || typeof $.Deferred !== 'function') {
                var rejected = { promise: function () { return { then: function () { return this; } }; } };
                return rejected.promise();
            }

            var deferred = $.Deferred();
            pendingNonceDeferred = deferred;

            var endpoint = getNonceEndpoint(settings);
            var nonceHeader = settings && typeof settings.restNonce === 'string' ? settings.restNonce : '';
            var reloadTriggered = false;

            function reloadOnRejection() {
                if (reloadTriggered) {
                    return;
                }

                reloadTriggered = true;

                if (root && root.location && typeof root.location.reload === 'function') {
                    try {
                        root.location.reload();
                    } catch (error) {
                        logError(error);
                    }
                }
            }

            if (!endpoint) {
                deferred.reject(new Error('Missing nonce endpoint'));
                pendingNonceDeferred = null;

                return deferred.promise();
            }

            var ajaxOptions = {
                url: endpoint,
                type: 'GET',
                success: function (response) {
                    var nonce = extractNonceFromResponse(response);

                    if (nonce) {
                        applyNonce(nonce, settings);
                        deferred.resolve(nonce);

                        return;
                    }

                    reloadOnRejection();
                    deferred.reject(new Error('Invalid nonce payload'));
                },
                error: function () {
                    reloadOnRejection();
                    deferred.reject(new Error('Nonce request failed'));
                },
                complete: function () {
                    pendingNonceDeferred = null;
                }
            };

            if (nonceHeader) {
                ajaxOptions.headers = {
                    'X-WP-Nonce': nonceHeader
                };
            }

            $.ajax(ajaxOptions);

            return deferred.promise();
        }

        return {
            getNonceEndpoint: getNonceEndpoint,
            extractNonceFromResponse: extractNonceFromResponse,
            applyNonce: applyNonce,
            refreshNonce: refreshNonce,
            isInvalidNonceResponse: isInvalidNonceResponse
        };
    }

    var api = Object.assign({}, existing, {
        dispatchCustomEvent: dispatchCustomEvent,
        createEventEmitter: createEventEmitter,
        createNonceManager: createNonceManager,
        getNonceEndpoint: getNonceEndpoint,
        extractNonceFromResponse: extractNonceFromResponse,
        isInvalidNonceResponse: isInvalidNonceResponse
    });

    if (root) {
        root.myArticlesShared = api;
    }

    return api;
}));
