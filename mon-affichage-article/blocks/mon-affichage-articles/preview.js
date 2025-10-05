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

                if (typeof window !== 'undefined') {
                    if (typeof window.myArticlesInitWrappers === 'function') {
                        window.myArticlesInitWrappers();
                    }

                    if (shouldInitSwipers && typeof window.myArticlesInitSwipers === 'function') {
                        window.myArticlesInitSwipers();
                    }
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
