(function (wp) {
    if (!wp || !wp.element) {
        return;
    }

    var __ = wp.i18n && typeof wp.i18n.__ === 'function' ? wp.i18n.__ : function (text) {
        return text;
    };
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var apiFetch = wp.apiFetch;
    var components = wp.components || {};
    var Spinner = components.Spinner || function () {
        return el('span', { className: 'components-spinner' });
    };
    var Notice = components.Notice || function (props) {
        return el('div', { className: 'components-notice is-' + (props.status || 'info') }, props.children);
    };
    var Button = components.Button || function (props) {
        return el('button', props, props.children);
    };

    var assetPromises = {
        styles: {},
        scripts: {},
    };

    function getDynamicAssets() {
        if (typeof window === 'undefined' || !window.myArticlesAssets || typeof window.myArticlesAssets.dynamic !== 'object') {
            return {};
        }

        return window.myArticlesAssets.dynamic || {};
    }

    function withVersion(url, ver) {
        if (typeof url !== 'string' || !url) {
            return '';
        }

        if (typeof ver !== 'string' || !ver) {
            return url;
        }

        if (url.indexOf('ver=') !== -1) {
            return url;
        }

        var separator = url.indexOf('?') === -1 ? '?' : '&';
        return url + separator + 'ver=' + encodeURIComponent(ver);
    }

    function getOrCreatePromise(store, handle, factory) {
        if (!handle) {
            return factory();
        }

        if (store[handle]) {
            return store[handle];
        }

        store[handle] = factory().catch(function (error) {
            delete store[handle];
            throw error;
        });

        return store[handle];
    }

    function ensureStyleEntry(entry) {
        if (!entry || typeof entry !== 'object') {
            return Promise.resolve();
        }

        var handle = typeof entry.handle === 'string' ? entry.handle : '';
        var src = typeof entry.src === 'string' ? entry.src : '';
        var ver = typeof entry.ver === 'string' ? entry.ver : '';

        if (!src) {
            return Promise.resolve();
        }

        var href = withVersion(src, ver);

        var loader = function () {
            return new Promise(function (resolve, reject) {
                if (typeof document === 'undefined') {
                    resolve();
                    return;
                }

                var selector = 'link[data-my-articles-handle="' + handle + '"]';
                var existing = handle ? document.querySelector(selector) : null;

                if (!existing && href) {
                    existing = document.querySelector('link[href="' + href + '"]');
                }

                if (existing) {
                    resolve();
                    return;
                }

                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;

                if (handle) {
                    link.setAttribute('data-my-articles-handle', handle);
                }

                link.onload = function () {
                    resolve();
                };

                link.onerror = function () {
                    reject(new Error('style_load_failed:' + handle));
                };

                (document.head || document.documentElement).appendChild(link);
            });
        };

        return getOrCreatePromise(assetPromises.styles, handle, loader);
    }

    function ensureScriptEntry(entry) {
        if (!entry || typeof entry !== 'object') {
            return Promise.resolve();
        }

        var handle = typeof entry.handle === 'string' ? entry.handle : '';
        var src = typeof entry.src === 'string' ? entry.src : '';
        var ver = typeof entry.ver === 'string' ? entry.ver : '';
        var attributes = entry.attributes && typeof entry.attributes === 'object' ? entry.attributes : {};

        if (!src) {
            return Promise.resolve();
        }

        if (handle === 'swiper-js' && typeof window !== 'undefined' && typeof window.Swiper === 'function') {
            return Promise.resolve();
        }

        if (handle === 'my-articles-swiper-init' && typeof window !== 'undefined' && typeof window.myArticlesInitSwipers === 'function') {
            return Promise.resolve();
        }

        if (handle === 'lazysizes' && typeof window !== 'undefined' && window.lazySizes) {
            return Promise.resolve();
        }

        var url = withVersion(src, ver);

        var loader = function () {
            return new Promise(function (resolve, reject) {
                if (typeof document === 'undefined') {
                    resolve();
                    return;
                }

                var selector = 'script[data-my-articles-handle="' + handle + '"]';
                var existing = handle ? document.querySelector(selector) : null;

                if (existing) {
                    if (existing.getAttribute('data-my-articles-loaded') === 'true') {
                        resolve();
                    } else {
                        existing.addEventListener('load', function () {
                            resolve();
                        });
                        existing.addEventListener('error', function () {
                            reject(new Error('script_load_failed:' + handle));
                        });
                    }

                    return;
                }

                var script = document.createElement('script');
                script.src = url;

                if (handle) {
                    script.setAttribute('data-my-articles-handle', handle);
                }

                Object.keys(attributes).forEach(function (attributeName) {
                    var value = attributes[attributeName];

                    if (value === false || value === null || typeof value === 'undefined') {
                        return;
                    }

                    if (value === true) {
                        script.setAttribute(attributeName, '');
                        return;
                    }

                    script.setAttribute(attributeName, String(value));
                });

                script.onload = function () {
                    script.setAttribute('data-my-articles-loaded', 'true');
                    resolve();
                };

                script.onerror = function () {
                    reject(new Error('script_load_failed:' + handle));
                };

                (document.head || document.documentElement).appendChild(script);
            });
        };

        return getOrCreatePromise(assetPromises.scripts, handle, loader);
    }

    function getAssetEntries(bucketKey, type) {
        var assets = getDynamicAssets();
        var bucket = assets && typeof assets === 'object' ? assets[bucketKey] : null;

        if (!bucket || typeof bucket !== 'object') {
            return [];
        }

        var entries = bucket[type];

        if (!Array.isArray(entries)) {
            return [];
        }

        return entries.filter(function (item) {
            return item && typeof item === 'object';
        });
    }

    function loadEntriesSequential(entries, loader) {
        return entries.reduce(function (promise, entry) {
            return promise.then(function () {
                return loader(entry);
            });
        }, Promise.resolve());
    }

    function ensureSwiperAssets() {
        var styles = getAssetEntries('swiper', 'styles');
        var scripts = getAssetEntries('swiper', 'scripts');

        return loadEntriesSequential(styles, ensureStyleEntry).then(function () {
            return loadEntriesSequential(scripts, ensureScriptEntry);
        });
    }

    function ensureLazySizes() {
        var scripts = getAssetEntries('lazysizes', 'scripts');

        if (!scripts.length) {
            return Promise.resolve();
        }

        return loadEntriesSequential(scripts, ensureScriptEntry);
    }

    function Skeleton(props) {
        var displayMode = props.displayMode === 'list' ? 'list' : 'grid';
        var itemCount = displayMode === 'list' ? 3 : 6;
        var items = [];

        for (var i = 0; i < itemCount; i += 1) {
            items.push(
                el(
                    'div',
                    { key: i, className: 'my-articles-skeleton__item' },
                    el('div', { className: 'my-articles-skeleton__thumbnail' }),
                    el(
                        'div',
                        { className: 'my-articles-skeleton__body' },
                        el('span', { className: 'my-articles-skeleton__line my-articles-skeleton__line--title' }),
                        el('span', { className: 'my-articles-skeleton__line my-articles-skeleton__line--meta' }),
                        el('span', { className: 'my-articles-skeleton__line' }),
                        el('span', { className: 'my-articles-skeleton__line my-articles-skeleton__line--short' })
                    )
                )
            );
        }

        return el(
            'div',
            {
                className: 'my-articles-skeleton my-articles-skeleton--' + displayMode,
                'aria-hidden': 'true',
                role: 'presentation',
            },
            items
        );
    }

    function getErrorMessage(error) {
        if (!error || typeof error !== 'object') {
            return '';
        }

        if (error.message && typeof error.message === 'string') {
            return error.message;
        }

        if (error.code && typeof error.code === 'string') {
            return error.code;
        }

        if (error.data && typeof error.data.message === 'string') {
            return error.data.message;
        }

        return '';
    }

    function PreviewPane(props) {
        var instanceId = props.instanceId ? parseInt(props.instanceId, 10) : 0;
        if (isNaN(instanceId)) {
            instanceId = 0;
        }
        var attributes = props.attributes && typeof props.attributes === 'object' ? props.attributes : {};
        var initialStatus = instanceId > 0 ? 'loading' : 'idle';
        var containerRef = useRef(null);
        var requestRef = useRef(0);
        var isMountedRef = useRef(true);
        var displayModeProp = typeof props.displayMode === 'string' ? props.displayMode : 'grid';

        var _useState = useState({
            status: initialStatus,
            html: '',
            metadata: null,
            error: null,
        });
        var previewState = _useState[0];
        var setPreviewState = _useState[1];

        var _useState2 = useState(0);
        var refreshToken = _useState2[0];
        var setRefreshToken = _useState2[1];

        var attributesKey;
        try {
            attributesKey = JSON.stringify(attributes || {});
        } catch (serializationError) {
            attributesKey = String(Date.now());
        }

        useEffect(function () {
            return function () {
                isMountedRef.current = false;
            };
        }, []);

        useEffect(
            function () {
                if (!isMountedRef.current) {
                    return undefined;
                }

                if (!instanceId) {
                    setPreviewState({ status: 'idle', html: '', metadata: null, error: null });
                    return undefined;
                }

                if (typeof apiFetch !== 'function') {
                    setPreviewState({
                        status: 'error',
                        html: '',
                        metadata: null,
                        error: new Error('api_fetch_unavailable'),
                    });
                    return undefined;
                }

                var aborted = false;
                requestRef.current += 1;
                var currentRequest = requestRef.current;

                setPreviewState({ status: 'loading', html: '', metadata: null, error: null });

                apiFetch({
                    path: '/my-articles/v1/render-preview',
                    method: 'POST',
                    data: {
                        instance_id: instanceId,
                        attributes: attributes,
                    },
                })
                    .then(function (response) {
                        if (!isMountedRef.current || aborted || currentRequest !== requestRef.current) {
                            return;
                        }

                        var payload = response && typeof response === 'object' ? response : {};

                        if (payload && payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data)) {
                            payload = payload.data;
                        }

                        if (!payload || typeof payload !== 'object' || typeof payload.html !== 'string') {
                            throw new Error('invalid_preview_payload');
                        }

                        setPreviewState({
                            status: 'success',
                            html: payload.html,
                            metadata: payload.metadata || null,
                            error: null,
                        });
                    })
                    .catch(function (error) {
                        if (!isMountedRef.current || aborted || currentRequest !== requestRef.current) {
                            return;
                        }

                        setPreviewState({ status: 'error', html: '', metadata: null, error: error });
                    });

                return function () {
                    aborted = true;
                };
            },
            [instanceId, attributesKey, refreshToken]
        );

        useEffect(
            function () {
                if (previewState.status !== 'success') {
                    return undefined;
                }

                var node = containerRef.current;
                if (!node) {
                    return undefined;
                }

                var shouldInitSwipers = false;
                if (previewState.metadata && typeof previewState.metadata.has_swiper !== 'undefined') {
                    shouldInitSwipers = !!previewState.metadata.has_swiper;
                } else if (previewState.metadata && typeof previewState.metadata.display_mode === 'string') {
                    shouldInitSwipers = previewState.metadata.display_mode === 'slideshow';
                } else {
                    shouldInitSwipers = displayModeProp === 'slideshow';
                }

                if (typeof window !== 'undefined' && typeof window.myArticlesInitWrappers === 'function') {
                    window.myArticlesInitWrappers();
                }

                if (shouldInitSwipers) {
                    ensureSwiperAssets()
                        .then(function () {
                            if (typeof window !== 'undefined' && typeof window.myArticlesInitSwipers === 'function') {
                                window.myArticlesInitSwipers();
                            }
                        })
                        .catch(function (error) {
                            if (typeof window !== 'undefined' && window.console && typeof window.console.error === 'function') {
                                window.console.error('my-articles: unable to load Swiper assets', error);
                            }
                        });
                }

                if (node && typeof node.querySelector === 'function' && node.querySelector('.lazyload')) {
                    ensureLazySizes().catch(function (error) {
                        if (typeof window !== 'undefined' && window.console && typeof window.console.error === 'function') {
                            window.console.error('my-articles: unable to load lazySizes', error);
                        }
                    });
                }

                return undefined;
            },
            [previewState.status, previewState.html, previewState.metadata, displayModeProp]
        );

        var className = 'my-articles-preview-pane';
        if (props.className) {
            className += ' ' + props.className;
        }

        var resolvedDisplayMode = displayModeProp;
        if (previewState.metadata && typeof previewState.metadata.display_mode === 'string') {
            resolvedDisplayMode = previewState.metadata.display_mode;
        }

        var children = [];

        if (previewState.status === 'loading') {
            children.push(el(Skeleton, { key: 'skeleton', displayMode: resolvedDisplayMode }));
        }

        children.push(
            el('div', {
                key: 'content',
                ref: containerRef,
                className: 'my-articles-preview-pane__content',
                dangerouslySetInnerHTML: { __html: previewState.html || '' },
            })
        );

        if (previewState.status === 'error') {
            var errorMessage = getErrorMessage(previewState.error);
            var translatedMessage = __('La prévisualisation du module a échoué.', 'mon-articles');

            if (errorMessage) {
                translatedMessage += ' ' + errorMessage;
            }

            children.push(
                el(
                    'div',
                    { key: 'error', className: 'my-articles-preview-pane__fallback' },
                    el(Spinner, { key: 'spinner', className: 'my-articles-preview-pane__spinner' }),
                    el(
                        Notice,
                        { key: 'notice', status: 'error', isDismissible: false },
                        translatedMessage,
                        ' ',
                        el(
                            Button,
                            {
                                variant: 'secondary',
                                onClick: function () {
                                    setRefreshToken(function (count) {
                                        return count + 1;
                                    });
                                },
                            },
                            __('Réessayer', 'mon-articles')
                        )
                    )
                )
            );
        }

        return el('div', { className: className }, children);
    }

    window.myArticlesBlocks = window.myArticlesBlocks || {};
    window.myArticlesBlocks.PreviewPane = PreviewPane;
})(window.wp);
