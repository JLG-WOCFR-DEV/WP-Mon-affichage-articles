(function (wp) {
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
    var useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : function () {
        return {};
    };
    var PanelColorSettings = wp.blockEditor
        ? wp.blockEditor.PanelColorSettings
        : wp.editor && wp.editor.PanelColorSettings;
    var __experimentalColorGradientSettings = wp.blockEditor
        ? wp.blockEditor.__experimentalColorGradientSettings
        : wp.editor && wp.editor.__experimentalColorGradientSettings;
    var useSetting = wp.blockEditor
        ? wp.blockEditor.useSetting || wp.blockEditor.__experimentalUseSetting
        : wp.editor && (wp.editor.useSetting || wp.editor.__experimentalUseSetting);
    var PanelBody = wp.components.PanelBody;
    var ComboboxControl = wp.components.ComboboxControl;
    var Button = wp.components.Button;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var RangeControl = wp.components.RangeControl;
    var TextControl = wp.components.TextControl;
    var BaseControl = wp.components.BaseControl;
    var ColorIndicator = wp.components.ColorIndicator;
    var Placeholder = wp.components.Placeholder;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var Fragment = wp.element.Fragment;
    var el = wp.element.createElement;
    var useSelect = wp.data.useSelect;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var useCallback = wp.element.useCallback;
    var ServerSideRender = wp.serverSideRender;

    var designPresets = window.myArticlesDesignPresets || {};
    var DESIGN_PRESET_FALLBACK = 'custom';

    var SSRContentWrapper = function (props) {
        var onChange = props.onChange;
        var children = props.children;
        var forwardedRef = props.forwardRef;
        var containerRef = useRef(null);
        var wrapperProps = {};

        Object.keys(props).forEach(function (key) {
            if (key !== 'onChange' && key !== 'children' && key !== 'forwardRef') {
                wrapperProps[key] = props[key];
            }
        });

        var setContainerRef = useCallback(
            function (node) {
                containerRef.current = node;

                if (typeof forwardedRef === 'function') {
                    forwardedRef(node);
                } else if (forwardedRef && typeof forwardedRef === 'object') {
                    forwardedRef.current = node;
                }
            },
            [forwardedRef]
        );

        useEffect(
            function () {
                if (!containerRef.current || typeof onChange !== 'function' || typeof MutationObserver === 'undefined') {
                    return undefined;
                }

                var observer = new MutationObserver(function () {
                    onChange();
                });

                observer.observe(containerRef.current, { childList: true, subtree: true });

                return function () {
                    observer.disconnect();
                };
            },
            [onChange]
        );

        useEffect(
            function () {
                if (typeof onChange === 'function') {
                    onChange();
                }
            },
            [onChange]
        );

        wrapperProps.ref = setContainerRef;

        return el('div', wrapperProps, children);
    };

    var MODULE_QUERY_DEFAULTS = {
        orderby: 'title',
        order: 'asc',
        status: 'publish',
        context: 'view',
    };
    var MODULES_PER_PAGE = 20;

    registerBlockType('mon-affichage/articles', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps({ className: 'my-articles-block' });

            var _useState = useState('');
            var searchValue = _useState[0];
            var setSearchValue = _useState[1];

            var _useState2 = useState(1);
            var currentPage = _useState2[0];
            var setCurrentPage = _useState2[1];

            var _useState3 = useState([]);
            var fetchedInstances = _useState3[0];
            var setFetchedInstances = _useState3[1];

            var _useState4 = useState(true);
            var hasMoreResults = _useState4[0];
            var setHasMoreResults = _useState4[1];

            var _useState5 = useState(0);
            var previewRenderCount = _useState5[0];
            var setPreviewRenderCount = _useState5[1];
            var ssrAttributesKey = JSON.stringify(attributes || {});

            var isDesignPresetLocked = false;

            var listData = useSelect(function (select) {
                var core = select('core');
                var dataStore = select('core/data');
                var query = Object.assign({}, MODULE_QUERY_DEFAULTS, {
                    per_page: MODULES_PER_PAGE,
                    page: currentPage,
                });

                if (searchValue) {
                    query.search = searchValue;
                }

                return {
                    instances: core.getEntityRecords('postType', 'mon_affichage', query),
                    isResolving: dataStore.isResolving('core', 'getEntityRecords', ['postType', 'mon_affichage', query]),
                };
            }, [searchValue, currentPage]);

            var selectedData = useSelect(function (select) {
                var core = select('core');
                var dataStore = select('core/data');

                return {
                    selectedInstance: attributes.instanceId ? core.getEntityRecord('postType', 'mon_affichage', attributes.instanceId) : null,
                    isResolvingSelected: attributes.instanceId
                        ? dataStore.isResolving('core', 'getEntityRecord', ['postType', 'mon_affichage', attributes.instanceId])
                        : false,
                };
            }, [attributes.instanceId]);

            var canEditPosts = useSelect(function (select) {
                var core = select('core');

                if (!core || typeof core.canUser !== 'function') {
                    return false;
                }

                var capabilityResult = core.canUser('edit', 'posts');

                if (typeof capabilityResult === 'boolean') {
                    return capabilityResult;
                }

                if (capabilityResult && typeof capabilityResult === 'object' && Object.prototype.hasOwnProperty.call(capabilityResult, 'resolved')) {
                    return !!capabilityResult.resolved;
                }

                return !!capabilityResult;
            }, []);

            useEffect(
                function () {
                    if (!listData) {
                        return;
                    }

                    if (!Array.isArray(listData.instances)) {
                        if (!listData.isResolving && currentPage === 1) {
                            setFetchedInstances([]);
                            setHasMoreResults(false);
                        }
                        return;
                    }

                    setFetchedInstances(function (prevInstances) {
                        if (currentPage === 1) {
                            return listData.instances.slice();
                        }

                        var existingIds = {};
                        prevInstances.forEach(function (item) {
                            existingIds[item.id] = true;
                        });

                        var merged = prevInstances.slice();

                        listData.instances.forEach(function (item) {
                            if (!existingIds[item.id]) {
                                merged.push(item);
                            }
                        });

                        return merged;
                    });

                    setHasMoreResults(listData.instances.length === MODULES_PER_PAGE);
                },
                [listData && listData.instances, listData && listData.isResolving, currentPage]
            );

            useEffect(
                function () {
                    if (!attributes.instanceId) {
                        return;
                    }

                    if (typeof window === 'undefined') {
                        return;
                    }

                    if (typeof window.myArticlesInitWrappers === 'function') {
                        window.myArticlesInitWrappers();
                    }

                    if (typeof window.myArticlesInitSwipers === 'function') {
                        window.myArticlesInitSwipers();
                    }
                },
                [previewRenderCount, ssrAttributesKey]
            );

            var handlePreviewChange = useCallback(
                function () {
                    setPreviewRenderCount(function (count) {
                        return count + 1;
                    });
                },
                [setPreviewRenderCount]
            );

            var instances = Array.isArray(fetchedInstances) ? fetchedInstances : [];
            var instanceOptions = instances.map(function (post) {
                var title = post && post.title && post.title.rendered ? post.title.rendered : __('(Sans titre)', 'mon-articles');
                return { label: title, value: String(post.id) };
            });

            if (attributes.instanceId && selectedData && selectedData.selectedInstance) {
                var selectedId = String(attributes.instanceId);
                var found = instanceOptions.some(function (option) {
                    return option.value === selectedId;
                });

                if (!found) {
                    var selectedTitle = selectedData.selectedInstance.title && selectedData.selectedInstance.title.rendered
                        ? selectedData.selectedInstance.title.rendered
                        : __('(Sans titre)', 'mon-articles');
                    instanceOptions.unshift({ label: selectedTitle, value: selectedId });
                }
            }

            var showLoadMoreButton = instances.length > 0 || (listData && listData.isResolving) || currentPage > 1;

            var currentDesignPresetId = attributes.design_preset || DESIGN_PRESET_FALLBACK;
            if (!designPresets[currentDesignPresetId]) {
                currentDesignPresetId = DESIGN_PRESET_FALLBACK;
            }
            var selectedPreset = designPresets[currentDesignPresetId] || null;
            var designPresetValues = selectedPreset && selectedPreset.values && typeof selectedPreset.values === 'object' ? selectedPreset.values : {};
            var designPresetValuesString = JSON.stringify(designPresetValues);
            isDesignPresetLocked = !!(selectedPreset && selectedPreset.locked);
            var isAttributeLocked = function (key) {
                if (!isDesignPresetLocked || !key) {
                    return false;
                }
                return Object.prototype.hasOwnProperty.call(designPresetValues, key);
            };
            var withLockedGuard = function (key, callback) {
                return function () {
                    if (isAttributeLocked(key)) {
                        return;
                    }
                    return callback.apply(null, arguments);
                };
            };

            var designPresetOptions = Object.keys(designPresets).map(function (presetId) {
                var preset = designPresets[presetId] || {};
                return { label: preset.label || presetId, value: presetId };
            });
            if (designPresetOptions.length === 0) {
                designPresetOptions = [{ label: __('Personnalisé', 'mon-articles'), value: DESIGN_PRESET_FALLBACK }];
            } else {
                designPresetOptions.sort(function (a, b) {
                    if (a.value === DESIGN_PRESET_FALLBACK) {
                        return -1;
                    }
                    if (b.value === DESIGN_PRESET_FALLBACK) {
                        return 1;
                    }
                    if (typeof a.label === 'string' && typeof b.label === 'string') {
                        return a.label.localeCompare(b.label);
                    }
                    return 0;
                });
            }

            useEffect(
                function () {
                    if (!isDesignPresetLocked || !designPresetValues) {
                        return;
                    }
                    var updates = {};
                    var hasUpdates = false;
                    Object.keys(designPresetValues).forEach(function (key) {
                        if (attributes[key] !== designPresetValues[key]) {
                            updates[key] = designPresetValues[key];
                            hasUpdates = true;
                        }
                    });
                    if (hasUpdates) {
                        setAttributes(updates);
                    }
                },
                [attributes.design_preset, designPresetValuesString]
            );

            var handleDesignPresetChange = useCallback(
                function (nextPresetId) {
                    var resolvedId = nextPresetId && designPresets[nextPresetId] ? nextPresetId : DESIGN_PRESET_FALLBACK;
                    var preset = designPresets[resolvedId] || {};
                    var updates = { design_preset: resolvedId };
                    if (preset.values && typeof preset.values === 'object') {
                        Object.keys(preset.values).forEach(function (key) {
                            updates[key] = preset.values[key];
                        });
                    }
                    setAttributes(updates);
                },
                [setAttributes]
            );

            var displayMode = attributes.display_mode || 'grid';
            var isListMode = displayMode === 'list';
            var isSlideshowMode = displayMode === 'slideshow';

            var ensureNumber = function (value, fallback) {
                return typeof value === 'number' ? value : fallback;
            };

            var getAttributeValue = function (key, fallback) {
                var value = attributes[key];
                return value === undefined || value === null ? fallback : value;
            };

            var resolvePaletteSetting = function (setting) {
                if (!setting) {
                    return [];
                }

                if (Array.isArray(setting)) {
                    return setting;
                }

                var resolved = [];
                if (typeof setting === 'object') {
                    Object.keys(setting).forEach(function (key) {
                        var value = setting[key];
                        if (Array.isArray(value)) {
                            resolved = resolved.concat(value);
                        }
                    });
                }

                return resolved;
            };

            var flattenPalette = function (palette) {
                if (!Array.isArray(palette)) {
                    return [];
                }

                var deduped = [];
                var seen = {};

                palette.forEach(function (item) {
                    if (!item || typeof item !== 'object') {
                        return;
                    }

                    var identifier = item.slug || item.name || item.color;
                    if (!identifier) {
                        return;
                    }

                    if (!seen[identifier]) {
                        seen[identifier] = true;
                        deduped.push(item);
                    }
                });

                return deduped;
            };

            var paletteSetting = typeof useSetting === 'function' ? useSetting('color.palette') : [];
            var availableColors = flattenPalette(resolvePaletteSetting(paletteSetting));

            var colorControlsConfig = [
                { label: __('Fond du module', 'mon-articles'), key: 'module_bg_color', defaultValue: 'rgba(255,255,255,0)' },
                { label: __('Fond de la vignette', 'mon-articles'), key: 'vignette_bg_color', defaultValue: '#ffffff', disableAlpha: true },
                { label: __('Fond du bloc titre', 'mon-articles'), key: 'title_wrapper_bg_color', defaultValue: '#ffffff', disableAlpha: true },
                { label: __('Couleur du titre', 'mon-articles'), key: 'title_color', defaultValue: '#333333', disableAlpha: true },
                { label: __('Couleur du texte (méta)', 'mon-articles'), key: 'meta_color', defaultValue: '#6b7280', disableAlpha: true },
                { label: __('Couleur (méta, survol)', 'mon-articles'), key: 'meta_color_hover', defaultValue: '#000000', disableAlpha: true },
                { label: __('Couleur de pagination', 'mon-articles'), key: 'pagination_color', defaultValue: '#333333', disableAlpha: true },
                { label: __('Couleur de l’extrait', 'mon-articles'), key: 'excerpt_color', defaultValue: '#4b5563', disableAlpha: true },
                { label: __('Ombre', 'mon-articles'), key: 'shadow_color', defaultValue: 'rgba(0,0,0,0.07)' },
                { label: __('Ombre (survol)', 'mon-articles'), key: 'shadow_color_hover', defaultValue: 'rgba(0,0,0,0.12)' },
                { label: __('Bordure (épinglés)', 'mon-articles'), key: 'pinned_border_color', defaultValue: '#eab308', disableAlpha: true },
                { label: __('Fond du badge épinglé', 'mon-articles'), key: 'pinned_badge_bg_color', defaultValue: '#eab308', disableAlpha: true },
                { label: __('Texte du badge épinglé', 'mon-articles'), key: 'pinned_badge_text_color', defaultValue: '#ffffff', disableAlpha: true },
            ];

            var colorSettingsComponent = PanelColorSettings || __experimentalColorGradientSettings || null;
            var colorPanelSettings = [];
            var gradientPanelSettings = [];
            var lockedColorNotices = [];

            colorControlsConfig.forEach(function (control) {
                var key = control.key;
                var value = getAttributeValue(key, control.defaultValue || '');
                var isLocked = isAttributeLocked(key);

                if (isLocked) {
                    lockedColorNotices.push(
                        el(
                            BaseControl,
                            {
                                key: 'locked-' + key,
                                label: control.label,
                                className: 'my-articles-color-control is-locked',
                            },
                            el(
                                'div',
                                { className: 'my-articles-color-control__locked' },
                                value
                                    ? el(ColorIndicator, {
                                          key: 'indicator-' + key,
                                          colorValue: value,
                                      })
                                    : null,
                                el(
                                    'p',
                                    { className: 'components-base-control__help' },
                                    __('Réglage verrouillé par le modèle.', 'mon-articles')
                                )
                            )
                        )
                    );
                    return;
                }

                var changeHandler = withLockedGuard(key, function (nextValue) {
                    var colorValue = typeof nextValue === 'string' ? nextValue : '';

                    var update = {};
                    update[key] = colorValue;
                    setAttributes(update);
                });

                colorPanelSettings.push({
                    label: control.label,
                    value: value,
                    onChange: changeHandler,
                    className: 'my-articles-color-control',
                    clearable: true,
                    enableAlpha: !(control.disableAlpha || false),
                    key: key,
                });

                gradientPanelSettings.push({
                    label: control.label,
                    colorValue: value,
                    onColorChange: changeHandler,
                    className: 'my-articles-color-control',
                    clearable: true,
                    enableAlpha: !(control.disableAlpha || false),
                    key: key,
                });
            });

            var colorSettingsPanel = null;

            if (colorSettingsComponent === PanelColorSettings && PanelColorSettings) {
                colorSettingsPanel = el(
                    PanelColorSettings,
                    {
                        title: __('Couleurs', 'mon-articles'),
                        initialOpen: false,
                        colorSettings: colorPanelSettings,
                        colors: availableColors,
                        disableCustomColors: false,
                        disableCustomGradients: true,
                        __experimentalIsRenderedInSidebar: true,
                    },
                    lockedColorNotices.length > 0 ? el(Fragment, {}, lockedColorNotices) : null
                );
            } else if (colorSettingsComponent === __experimentalColorGradientSettings && __experimentalColorGradientSettings) {
                colorSettingsPanel = el(
                    __experimentalColorGradientSettings,
                    {
                        title: __('Couleurs', 'mon-articles'),
                        initialOpen: false,
                        settings: gradientPanelSettings,
                        colors: availableColors,
                        gradients: [],
                        disableCustomColors: false,
                        disableCustomGradients: true,
                        __experimentalIsRenderedInSidebar: true,
                    },
                    lockedColorNotices.length > 0 ? el(Fragment, {}, lockedColorNotices) : null
                );
            } else {
                colorSettingsPanel = el(
                    PanelBody,
                    { title: __('Couleurs', 'mon-articles'), initialOpen: false },
                    lockedColorNotices.length > 0
                        ? el(Fragment, {}, lockedColorNotices)
                        : el(
                              Notice,
                              { status: 'warning', isDismissible: false },
                              __('La sélection de couleur n’est pas disponible dans cet environnement.', 'mon-articles')
                          )
                );
            }

            var inspectorControls = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Module', 'mon-articles'), initialOpen: true },
                    el(
                        Fragment,
                        {},
                        el(ComboboxControl, {
                            label: __('Instance', 'mon-articles'),
                            value: attributes.instanceId ? String(attributes.instanceId) : '',
                            options: instanceOptions,
                            onChange: function (value) {
                                var parsed = parseInt(value, 10);
                                setAttributes({ instanceId: parsed > 0 ? parsed : 0 });
                            },
                            onFilterValueChange: function (value) {
                                setSearchValue(value || '');
                                setCurrentPage(1);
                                setFetchedInstances([]);
                                setHasMoreResults(true);
                            },
                            help: __('Utilisez la recherche pour trouver un contenu « mon_affichage ». Les résultats se chargent au fur et à mesure.', 'mon-articles'),
                        }),
                        listData && listData.isResolving
                            ? el('div', { className: 'my-articles-block__module-loading' }, el(Spinner, { key: 'module-spinner' }))
                            : null,
                        showLoadMoreButton
                            ? el(
                                  Button,
                                  {
                                      variant: 'secondary',
                                      onClick: function () {
                                          setCurrentPage(function (prevPage) {
                                              return prevPage + 1;
                                          });
                                      },
                                      disabled: !hasMoreResults || (listData && listData.isResolving),
                                      className: 'my-articles-block__module-load-more',
                                  },
                                  hasMoreResults
                                      ? __('Charger plus de résultats', 'mon-articles')
                                      : __('Tous les contenus sont chargés', 'mon-articles')
                              )
                            : null,
                        el(SelectControl, {
                            label: __('Modèle', 'mon-articles'),
                            value: currentDesignPresetId,
                            options: designPresetOptions,
                            onChange: handleDesignPresetChange,
                        }),
                        isDesignPresetLocked
                            ? el(Notice, { status: 'info', isDismissible: false }, __('Ce modèle verrouille certains réglages de design.', 'mon-articles'))
                            : null
                    )
                ),
                el(
                    PanelBody,
                    { title: __('Affichage', 'mon-articles'), initialOpen: false },
                    el(SelectControl, {
                        label: __('Mode d’affichage', 'mon-articles'),
                        value: attributes.display_mode || 'grid',
                        options: [
                            { label: __('Grille', 'mon-articles'), value: 'grid' },
                            { label: __('Liste', 'mon-articles'), value: 'list' },
                            { label: __('Diaporama', 'mon-articles'), value: 'slideshow' },
                        ],
                        onChange: withLockedGuard('display_mode', function (value) {
                            setAttributes({ display_mode: value });
                        }),
                        disabled: isAttributeLocked('display_mode'),
                    }),
                    el(RangeControl, {
                        label: __('Articles par page', 'mon-articles'),
                        value: attributes.posts_per_page,
                        min: 0,
                        max: 24,
                        allowReset: true,
                        onChange: withLockedGuard('posts_per_page', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ posts_per_page: value });
                        }),
                        help: __('Définissez 0 pour désactiver la limite.', 'mon-articles'),
                        disabled: isAttributeLocked('posts_per_page'),
                    }),
                    el(SelectControl, {
                        label: __('Pagination', 'mon-articles'),
                        value: attributes.pagination_mode || 'none',
                        options: [
                            { label: __('Aucune', 'mon-articles'), value: 'none' },
                            { label: __('Bouton « Charger plus »', 'mon-articles'), value: 'load_more' },
                            { label: __('Pagination numérotée', 'mon-articles'), value: 'numbered' },
                        ],
                        onChange: withLockedGuard('pagination_mode', function (value) {
                            setAttributes({ pagination_mode: value });
                        }),
                        disabled: isAttributeLocked('pagination_mode'),
                    }),
                    isSlideshowMode
                        ? el(Fragment, {},
                              el(ToggleControl, {
                                  label: __('Boucle infinie', 'mon-articles'),
                                  checked: !!attributes.slideshow_loop,
                                  onChange: withLockedGuard('slideshow_loop', function (value) {
                                      setAttributes({ slideshow_loop: !!value });
                                  }),
                                  disabled: isAttributeLocked('slideshow_loop'),
                              }),
                              el(ToggleControl, {
                                  label: __('Lecture automatique', 'mon-articles'),
                                  checked: !!attributes.slideshow_autoplay,
                                  onChange: withLockedGuard('slideshow_autoplay', function (value) {
                                      setAttributes({ slideshow_autoplay: !!value });
                                  }),
                                  disabled: isAttributeLocked('slideshow_autoplay'),
                              }),
                              el(RangeControl, {
                                  label: __('Délai entre les diapositives (ms)', 'mon-articles'),
                                  value: ensureNumber(attributes.slideshow_delay, 5000),
                                  min: 1000,
                                  max: 20000,
                                  step: 100,
                                  allowReset: true,
                                  onChange: withLockedGuard('slideshow_delay', function (value) {
                                      if (typeof value !== 'number') {
                                          value = 5000;
                                      }
                                      setAttributes({ slideshow_delay: value });
                                  }),
                                  disabled: !attributes.slideshow_autoplay || isAttributeLocked('slideshow_delay'),
                                  help: __('Actif uniquement si la lecture automatique est activée.', 'mon-articles'),
                              }),
                              el(ToggleControl, {
                                  label: __('Mettre en pause lors des interactions', 'mon-articles'),
                                  checked: !!attributes.slideshow_pause_on_interaction,
                                  onChange: withLockedGuard('slideshow_pause_on_interaction', function (value) {
                                      setAttributes({ slideshow_pause_on_interaction: !!value });
                                  }),
                                  disabled: !attributes.slideshow_autoplay || isAttributeLocked('slideshow_pause_on_interaction'),
                              }),
                              el(ToggleControl, {
                                  label: __('Mettre en pause au survol', 'mon-articles'),
                                  checked: !!attributes.slideshow_pause_on_mouse_enter,
                                  onChange: withLockedGuard('slideshow_pause_on_mouse_enter', function (value) {
                                      setAttributes({ slideshow_pause_on_mouse_enter: !!value });
                                  }),
                                  disabled: !attributes.slideshow_autoplay || isAttributeLocked('slideshow_pause_on_mouse_enter'),
                              }),
                              el(ToggleControl, {
                                  label: __('Afficher les flèches de navigation', 'mon-articles'),
                                  checked: !!attributes.slideshow_show_navigation,
                                  onChange: withLockedGuard('slideshow_show_navigation', function (value) {
                                      setAttributes({ slideshow_show_navigation: !!value });
                                  }),
                                  disabled: isAttributeLocked('slideshow_show_navigation'),
                              }),
                              el(ToggleControl, {
                                  label: __('Afficher la pagination', 'mon-articles'),
                                  checked: !!attributes.slideshow_show_pagination,
                                  onChange: withLockedGuard('slideshow_show_pagination', function (value) {
                                      setAttributes({ slideshow_show_pagination: !!value });
                                  }),
                                  disabled: isAttributeLocked('slideshow_show_pagination'),
                              })
                          )
                        : null
                ),
                el(
                    PanelBody,
                    { title: __('Disposition', 'mon-articles'), initialOpen: false },
                    el(RangeControl, {
                        label: __('Colonnes (mobile)', 'mon-articles'),
                        value: ensureNumber(attributes.columns_mobile, 1),
                        min: 1,
                        max: 3,
                        allowReset: true,
                        onChange: withLockedGuard('columns_mobile', function (value) {
                            if (typeof value !== 'number') {
                                value = 1;
                            }
                            setAttributes({ columns_mobile: value });
                        }),
                        disabled: isListMode || isAttributeLocked('columns_mobile'),
                        help: isListMode ? __('Disponible pour Grille et Diaporama.', 'mon-articles') : null,
                    }),
                    el(RangeControl, {
                        label: __('Colonnes (tablette)', 'mon-articles'),
                        value: ensureNumber(attributes.columns_tablet, 2),
                        min: 1,
                        max: 4,
                        allowReset: true,
                        onChange: withLockedGuard('columns_tablet', function (value) {
                            if (typeof value !== 'number') {
                                value = 2;
                            }
                            setAttributes({ columns_tablet: value });
                        }),
                        disabled: isListMode || isAttributeLocked('columns_tablet'),
                        help: isListMode ? __('Disponible pour Grille et Diaporama.', 'mon-articles') : null,
                    }),
                    el(RangeControl, {
                        label: __('Colonnes (desktop)', 'mon-articles'),
                        value: ensureNumber(attributes.columns_desktop, 3),
                        min: 1,
                        max: 6,
                        allowReset: true,
                        onChange: withLockedGuard('columns_desktop', function (value) {
                            if (typeof value !== 'number') {
                                value = 3;
                            }
                            setAttributes({ columns_desktop: value });
                        }),
                        disabled: isListMode || isAttributeLocked('columns_desktop'),
                        help: isListMode ? __('Disponible pour Grille et Diaporama.', 'mon-articles') : null,
                    }),
                    el(RangeControl, {
                        label: __('Colonnes (ultra-large)', 'mon-articles'),
                        value: ensureNumber(attributes.columns_ultrawide, 4),
                        min: 1,
                        max: 8,
                        allowReset: true,
                        onChange: withLockedGuard('columns_ultrawide', function (value) {
                            if (typeof value !== 'number') {
                                value = 4;
                            }
                            setAttributes({ columns_ultrawide: value });
                        }),
                        disabled: isListMode || isAttributeLocked('columns_ultrawide'),
                        help: isListMode ? __('Disponible pour Grille et Diaporama.', 'mon-articles') : null,
                    }),
                    el(RangeControl, {
                        label: __('Espacement des vignettes (px)', 'mon-articles'),
                        value: ensureNumber(attributes.gap_size, 25),
                        min: 0,
                        max: 50,
                        allowReset: true,
                        onChange: withLockedGuard('gap_size', function (value) {
                            if (typeof value !== 'number') {
                                value = 25;
                            }
                            setAttributes({ gap_size: value });
                        }),
                        disabled: isListMode || isAttributeLocked('gap_size'),
                    }),
                    el(RangeControl, {
                        label: __('Espacement vertical (liste)', 'mon-articles'),
                        value: ensureNumber(attributes.list_item_gap, 25),
                        min: 0,
                        max: 50,
                        allowReset: true,
                        onChange: withLockedGuard('list_item_gap', function (value) {
                            if (typeof value !== 'number') {
                                value = 25;
                            }
                            setAttributes({ list_item_gap: value });
                        }),
                        disabled: !isListMode || isAttributeLocked('list_item_gap'),
                    })
                ),
                el(
                    PanelBody,
                    { title: __('Espacements & typographie', 'mon-articles'), initialOpen: false },
                    el(RangeControl, {
                        label: __('Marge intérieure gauche (px)', 'mon-articles'),
                        value: ensureNumber(attributes.module_padding_left, 0),
                        min: 0,
                        max: 200,
                        allowReset: true,
                        onChange: withLockedGuard('module_padding_left', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ module_padding_left: value });
                        }),
                        disabled: isAttributeLocked('module_padding_left'),
                    }),
                    el(RangeControl, {
                        label: __('Marge intérieure droite (px)', 'mon-articles'),
                        value: ensureNumber(attributes.module_padding_right, 0),
                        min: 0,
                        max: 200,
                        allowReset: true,
                        onChange: withLockedGuard('module_padding_right', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ module_padding_right: value });
                        }),
                        disabled: isAttributeLocked('module_padding_right'),
                    }),
                    el(RangeControl, {
                        label: __('Arrondi des bordures (px)', 'mon-articles'),
                        value: ensureNumber(attributes.border_radius, 12),
                        min: 0,
                        max: 50,
                        allowReset: true,
                        onChange: withLockedGuard('border_radius', function (value) {
                            if (typeof value !== 'number') {
                                value = 12;
                            }
                            setAttributes({ border_radius: value });
                        }),
                        disabled: isAttributeLocked('border_radius'),
                    }),
                    el(RangeControl, {
                        label: __('Taille du titre (px)', 'mon-articles'),
                        value: ensureNumber(attributes.title_font_size, 16),
                        min: 10,
                        max: 40,
                        allowReset: true,
                        onChange: withLockedGuard('title_font_size', function (value) {
                            if (typeof value !== 'number') {
                                value = 16;
                            }
                            setAttributes({ title_font_size: value });
                        }),
                        disabled: isAttributeLocked('title_font_size'),
                    }),
                    el(RangeControl, {
                        label: __('Taille des métadonnées (px)', 'mon-articles'),
                        value: ensureNumber(attributes.meta_font_size, 14),
                        min: 8,
                        max: 24,
                        allowReset: true,
                        onChange: withLockedGuard('meta_font_size', function (value) {
                            if (typeof value !== 'number') {
                                value = 14;
                            }
                            setAttributes({ meta_font_size: value });
                        }),
                        disabled: isAttributeLocked('meta_font_size'),
                    }),
                    el(RangeControl, {
                        label: __('Taille de l’extrait (px)', 'mon-articles'),
                        value: ensureNumber(attributes.excerpt_font_size, 14),
                        min: 8,
                        max: 28,
                        allowReset: true,
                        onChange: withLockedGuard('excerpt_font_size', function (value) {
                            if (typeof value !== 'number') {
                                value = 14;
                            }
                            setAttributes({ excerpt_font_size: value });
                        }),
                        disabled: !attributes.show_excerpt || isAttributeLocked('excerpt_font_size'),
                    }),
                    el(RangeControl, {
                        label: __('Padding contenu liste – haut (px)', 'mon-articles'),
                        value: ensureNumber(attributes.list_content_padding_top, 0),
                        min: 0,
                        max: 100,
                        allowReset: true,
                        onChange: withLockedGuard('list_content_padding_top', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ list_content_padding_top: value });
                        }),
                        disabled: !isListMode || isAttributeLocked('list_content_padding_top'),
                    }),
                    el(RangeControl, {
                        label: __('Padding contenu liste – droite (px)', 'mon-articles'),
                        value: ensureNumber(attributes.list_content_padding_right, 0),
                        min: 0,
                        max: 100,
                        allowReset: true,
                        onChange: withLockedGuard('list_content_padding_right', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ list_content_padding_right: value });
                        }),
                        disabled: !isListMode || isAttributeLocked('list_content_padding_right'),
                    }),
                    el(RangeControl, {
                        label: __('Padding contenu liste – bas (px)', 'mon-articles'),
                        value: ensureNumber(attributes.list_content_padding_bottom, 0),
                        min: 0,
                        max: 100,
                        allowReset: true,
                        onChange: withLockedGuard('list_content_padding_bottom', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ list_content_padding_bottom: value });
                        }),
                        disabled: !isListMode || isAttributeLocked('list_content_padding_bottom'),
                    }),
                    el(RangeControl, {
                        label: __('Padding contenu liste – gauche (px)', 'mon-articles'),
                        value: ensureNumber(attributes.list_content_padding_left, 0),
                        min: 0,
                        max: 100,
                        allowReset: true,
                        onChange: withLockedGuard('list_content_padding_left', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ list_content_padding_left: value });
                        }),
                        disabled: !isListMode || isAttributeLocked('list_content_padding_left'),
                    })
                ),
                colorSettingsPanel,
                el(
                    PanelBody,
                    { title: __('Tri & ordre', 'mon-articles'), initialOpen: false },
                    el(SelectControl, {
                        label: __('Ordre de tri', 'mon-articles'),
                        value: attributes.orderby || 'date',
                        options: [
                            { label: __('Date de publication', 'mon-articles'), value: 'date' },
                            { label: __('Titre', 'mon-articles'), value: 'title' },
                            { label: __('Ordre du menu', 'mon-articles'), value: 'menu_order' },
                            { label: __('Méta personnalisée', 'mon-articles'), value: 'meta_value' },
                        ],
                        onChange: function (value) {
                            setAttributes({ orderby: value });
                        },
                    }),
                    el(SelectControl, {
                        label: __('Sens du tri', 'mon-articles'),
                        value: attributes.order || 'DESC',
                        options: [
                            { label: __('Décroissant (Z → A)', 'mon-articles'), value: 'DESC' },
                            { label: __('Croissant (A → Z)', 'mon-articles'), value: 'ASC' },
                        ],
                        onChange: function (value) {
                            setAttributes({ order: value });
                        },
                    }),
                    'meta_value' === (attributes.orderby || 'date')
                        ? el(TextControl, {
                              label: __('Clé de méta personnalisée', 'mon-articles'),
                              value: attributes.meta_key || '',
                              onChange: function (value) {
                                  setAttributes({ meta_key: value || '' });
                              },
                              help: __('Renseignez la clé utilisée pour le tri par méta.', 'mon-articles'),
                          })
                        : null
                ),
                el(
                    PanelBody,
                    { title: __('Méta-données', 'mon-articles'), initialOpen: false },
                    el(ToggleControl, {
                        label: __('Afficher le filtre de catégories', 'mon-articles'),
                        checked: !!attributes.show_category_filter,
                        onChange: withLockedGuard('show_category_filter', function (value) {
                            setAttributes({ show_category_filter: !!value });
                        }),
                        disabled: isAttributeLocked('show_category_filter'),
                    }),
                    el(SelectControl, {
                        label: __('Alignement du filtre', 'mon-articles'),
                        value: attributes.filter_alignment || 'right',
                        options: [
                            { label: __('Gauche', 'mon-articles'), value: 'left' },
                            { label: __('Centre', 'mon-articles'), value: 'center' },
                            { label: __('Droite', 'mon-articles'), value: 'right' },
                        ],
                        onChange: withLockedGuard('filter_alignment', function (value) {
                            setAttributes({ filter_alignment: value });
                        }),
                        disabled: !attributes.show_category_filter || isAttributeLocked('filter_alignment'),
                    }),
                    el(TextControl, {
                        label: __('Libellé ARIA du filtre de catégories', 'mon-articles'),
                        value: attributes.category_filter_aria_label || '',
                        onChange: function (value) {
                            setAttributes({ category_filter_aria_label: value || '' });
                        },
                        help: __('Laissez vide pour générer automatiquement une étiquette basée sur le titre du module.', 'mon-articles'),
                        disabled: !attributes.show_category_filter,
                    }),
                    el(ToggleControl, {
                        label: __('Afficher la catégorie', 'mon-articles'),
                        checked: !!attributes.show_category,
                        onChange: withLockedGuard('show_category', function (value) {
                            setAttributes({ show_category: !!value });
                        }),
                        disabled: isAttributeLocked('show_category'),
                    }),
                    el(ToggleControl, {
                        label: __('Afficher l’auteur', 'mon-articles'),
                        checked: !!attributes.show_author,
                        onChange: withLockedGuard('show_author', function (value) {
                            setAttributes({ show_author: !!value });
                        }),
                        disabled: isAttributeLocked('show_author'),
                    }),
                    el(ToggleControl, {
                        label: __('Afficher la date', 'mon-articles'),
                        checked: !!attributes.show_date,
                        onChange: withLockedGuard('show_date', function (value) {
                            setAttributes({ show_date: !!value });
                        }),
                        disabled: isAttributeLocked('show_date'),
                    }),
                    el(ToggleControl, {
                        label: __('Afficher l’extrait', 'mon-articles'),
                        checked: !!attributes.show_excerpt,
                        onChange: withLockedGuard('show_excerpt', function (value) {
                            setAttributes({ show_excerpt: !!value });
                        }),
                        disabled: isAttributeLocked('show_excerpt'),
                    }),
                    el(RangeControl, {
                        label: __('Longueur de l’extrait', 'mon-articles'),
                        value: ensureNumber(attributes.excerpt_length, 25),
                        min: 0,
                        max: 100,
                        onChange: withLockedGuard('excerpt_length', function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ excerpt_length: value });
                        }),
                        disabled: !attributes.show_excerpt || isAttributeLocked('excerpt_length'),
                    })
                )
            );

            var previewContent;

            if (!attributes.instanceId) {
                var placeholderChildren = [];

                if (listData && listData.isResolving && instances.length === 0) {
                    placeholderChildren.push(el(Spinner, { key: 'spinner' }));
                } else if (instances.length === 0) {
                    placeholderChildren.push(
                        el('p', { key: 'no-instances' }, __('Aucune instance « mon_affichage » n’a été trouvée.', 'mon-articles'))
                    );
                } else {
                    placeholderChildren.push(
                        el('p', { key: 'instructions' }, __('Sélectionnez un module dans la barre latérale.', 'mon-articles'))
                    );
                }

                if (canEditPosts) {
                    placeholderChildren.push(
                        el(
                            'div',
                            { key: 'create-action', className: 'my-articles-block-placeholder__actions' },
                            el(
                                Button,
                                {
                                    variant: 'primary',
                                    href: 'post-new.php?post_type=mon_affichage',
                                    target: '_blank',
                                    rel: 'noreferrer',
                                },
                                __('Créer un nouveau module', 'mon-articles')
                            )
                        )
                    );
                }

                previewContent = el(
                    Placeholder,
                    {
                        icon: 'screenoptions',
                        label: __('Tuiles – LCV', 'mon-articles'),
                        className: 'my-articles-block-placeholder',
                    },
                    placeholderChildren
                );
            } else if (selectedData && selectedData.isResolvingSelected && !selectedData.selectedInstance) {
                previewContent = el(Spinner, { key: 'spinner-loading' });
            } else if (!selectedData || !selectedData.selectedInstance) {
                previewContent = el(
                    Notice,
                    { status: 'warning', isDismissible: false },
                    __('Le module sélectionné est introuvable.', 'mon-articles')
                );
            } else if (ServerSideRender) {
                var title = selectedData.selectedInstance.title && selectedData.selectedInstance.title.rendered
                    ? selectedData.selectedInstance.title.rendered
                    : __('(Sans titre)', 'mon-articles');
                previewContent = el(
                    'div',
                    { className: 'my-articles-block-preview' },
                    el('p', { className: 'my-articles-block-preview__title' }, title),
                    el(
                        SSRContentWrapper,
                        { onChange: handlePreviewChange },
                        el(ServerSideRender, { block: 'mon-affichage/articles', attributes: attributes })
                    )
                );
            } else {
                previewContent = el(
                    Notice,
                    { status: 'warning', isDismissible: false },
                    __('Le composant ServerSideRender est indisponible sur ce site.', 'mon-articles')
                );
            }

            return el(
                Fragment,
                null,
                inspectorControls,
                el('div', blockProps, previewContent)
            );
        },
        save: function () {
            return null;
        },
    });
})(window.wp);
