(function (wp) {
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
    var BlockControls =
        wp.blockEditor && wp.blockEditor.BlockControls
            ? wp.blockEditor.BlockControls
            : wp.editor && wp.editor.BlockControls;
    var useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : function () {
        return {};
    };
    var PanelColorSettings = wp.blockEditor
        ? wp.blockEditor.PanelColorSettings
        : wp.editor && wp.editor.PanelColorSettings;
    var __experimentalColorGradientSettings = wp.blockEditor
        ? wp.blockEditor.__experimentalColorGradientSettings
        : wp.editor && wp.editor.__experimentalColorGradientSettings;
    var useSettings = wp.blockEditor && typeof wp.blockEditor.useSettings === 'function'
        ? wp.blockEditor.useSettings
        : null;
    var useSetting = wp.blockEditor
        ? wp.blockEditor.useSetting || wp.blockEditor.__experimentalUseSetting
        : wp.editor && (wp.editor.useSetting || wp.editor.__experimentalUseSetting);
    var components = wp.components || {};
    var PanelBody = components.PanelBody;
    var Panel = components.Panel
        ? components.Panel
        : function (props) {
              var className = 'components-panel';
              if (props && props.className) {
                  className += ' ' + props.className;
              }
              return el('div', Object.assign({}, props, { className: className }), props && props.children);
          };
    var ToolbarGroup = components.ToolbarGroup;
    var ToolbarButton = components.ToolbarButton;
    var ComboboxControl = components.ComboboxControl;
    var Button = components.Button;
    var ToggleGroupControl =
        components.ToggleGroupControl || components.__experimentalToggleGroupControl || null;
    var ToggleGroupControlOption =
        components.ToggleGroupControlOption || components.__experimentalToggleGroupControlOption || null;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl || null;
    var BaseControl = components.BaseControl;
    var FormTokenField = components.FormTokenField;
    var ColorIndicator = components.ColorIndicator;
    var Placeholder = components.Placeholder || null;
    var Spinner = components.Spinner || null;
    var Notice = components.Notice || null;
    var SearchControl = components.SearchControl || null;
    var Guide = components.Guide || null;
    var GuidePage = components.GuidePage || null;
    var Modal = components.Modal || null;
    var Tooltip = components.Tooltip || null;
    var Fragment = wp.element.Fragment;
    var el = wp.element.createElement;
    var apiFetch = wp.apiFetch ? wp.apiFetch : null;

    if (!Spinner) {
        Spinner = function () {
            return el('span', { className: 'components-spinner' });
        };
    }

    if (!Notice) {
        Notice = function (props) {
            return el(
                'div',
                { className: 'components-notice is-' + (props.status || 'info') },
                props.children
            );
        };
    }

    if (!Placeholder) {
        Placeholder = function (props) {
            return el(
                'div',
                { className: 'components-placeholder' },
                props.label ? el('h2', { className: 'components-placeholder__label' }, props.label) : null,
                props.instructions
                    ? el(
                          'p',
                          { className: 'components-placeholder__instructions' },
                          props.instructions
                      )
                    : null,
                props.children
            );
        };
    }
    var useSelect = wp.data.useSelect;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useCallback = wp.element.useCallback;
    var useMemo = wp.element.useMemo;
    var useViewportMatch =
        wp.compose && wp.compose.useViewportMatch
            ? wp.compose.useViewportMatch
            : function () {
                  return false;
              };
    var useAsyncDebounce =
        wp.compose && (wp.compose.useAsyncDebounce || wp.compose.useDebounce)
            ? wp.compose.useAsyncDebounce || wp.compose.useDebounce
            : function (callback) {
                  var passthrough = function () {
                      return callback.apply(null, arguments);
                  };
                  passthrough.cancel = function () {};
                  return passthrough;
              };
    var decodeEntities =
        wp.htmlEntities && typeof wp.htmlEntities.decodeEntities === 'function'
            ? wp.htmlEntities.decodeEntities
            : function (value) {
                  return value;
              };
    var PreviewCanvas = null;
    if (window.myArticlesBlocks) {
        if (window.myArticlesBlocks.PreviewCanvas) {
            PreviewCanvas = window.myArticlesBlocks.PreviewCanvas;
        } else if (window.myArticlesBlocks.PreviewPane) {
            PreviewCanvas = window.myArticlesBlocks.PreviewPane;
        }
    }
    var availableContentAdapters = Array.isArray(window.myArticlesContentAdapters)
        ? window.myArticlesContentAdapters
        : [];
    var dateI18n = wp.date && typeof wp.date.dateI18n === 'function' ? wp.date.dateI18n : null;
    var getDateSettings =
        wp.date && typeof wp.date.__experimentalGetSettings === 'function'
            ? wp.date.__experimentalGetSettings
            : null;

    function parseDate(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        var date = new Date(value);

        if (!date || isNaN(date.getTime())) {
            return null;
        }

        return date;
    }

    function formatDateTime(value) {
        var date = parseDate(value);

        if (!date) {
            return __('Inconnu', 'mon-articles');
        }

        if (dateI18n && getDateSettings) {
            var settings = getDateSettings();
            var format = settings && settings.formats && settings.formats.datetime ? settings.formats.datetime : 'Y-m-d H:i';

            return dateI18n(format, date.toISOString());
        }

        if (typeof date.toLocaleString === 'function') {
            try {
                return date.toLocaleString();
            } catch (error) {
                // no-op fall back to ISO string below.
            }
        }

        return date.toISOString();
    }

    function getDaysSince(value) {
        var date = parseDate(value);

        if (!date) {
            return null;
        }

        var now = new Date();
        var diff = now.getTime() - date.getTime();

        if (diff < 0) {
            diff = 0;
        }

        return Math.round(diff / (1000 * 60 * 60 * 24));
    }

    function formatFreshness(days) {
        if (null === days) {
            return __('Non disponible', 'mon-articles');
        }

        if (days <= 0) {
            return __('Moins de 24 h', 'mon-articles');
        }

        if (days === 1) {
            return __('1 jour', 'mon-articles');
        }

        return sprintf(__('%d jours', 'mon-articles'), days);
    }

    function buildFreshnessInsights(value) {
        var days = getDaysSince(value);
        var summary = formatFreshness(days);
        var severity = 'ok';
        var description = '';

        if (null === days) {
            severity = 'info';
            description = __('Impossible de déterminer la dernière mise à jour. Effectuez une sauvegarde pour rafraîchir ces informations.', 'mon-articles');
        } else if (days >= 120) {
            severity = 'critical';
            description = sprintf(__('Ce module n’a pas été actualisé depuis %d jours. Vérifiez que sa configuration est toujours pertinente.', 'mon-articles'), days);
        } else if (days >= 60) {
            severity = 'warning';
            description = sprintf(__('Pensez à vérifier ce module : sa dernière mise à jour remonte à %d jours.', 'mon-articles'), days);
        } else {
            description = sprintf(__('Mis à jour il y a %s.', 'mon-articles'), summary.toLowerCase());
        }

        return {
            days: days,
            summary: summary,
            severity: severity,
            description: description,
        };
    }

    function parseColor(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        var color = value.trim().toLowerCase();

        if (!color) {
            return null;
        }

        if (color[0] === '#') {
            var hex = color.slice(1);
            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }
            if (hex.length === 6) {
                var r = parseInt(hex.slice(0, 2), 16);
                var g = parseInt(hex.slice(2, 4), 16);
                var b = parseInt(hex.slice(4, 6), 16);
                if (!isNaN(r) && !isNaN(g) && !isNaN(b)) {
                    return { r: r, g: g, b: b };
                }
            }
            return null;
        }

        var rgbMatch = color.match(/rgba?\(([^)]+)\)/);
        if (rgbMatch && rgbMatch[1]) {
            var parts = rgbMatch[1]
                .split(',')
                .map(function (part) {
                    return parseFloat(part.trim());
                })
                .filter(function (part) {
                    return !isNaN(part);
                });

            if (parts.length >= 3) {
                return { r: parts[0], g: parts[1], b: parts[2] };
            }
        }

        return null;
    }

    function isColorDark(value) {
        var rgb = parseColor(value);
        if (!rgb) {
            return null;
        }

        var luminance = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;
        return luminance < 0.6;
    }

    function buildPresetSwatch(values) {
        var background = values && typeof values.module_bg_color === 'string' ? values.module_bg_color : '#ffffff';
        var accent = values && typeof values.pagination_color === 'string' ? values.pagination_color : '#2563eb';
        var heading = values && typeof values.title_color === 'string' ? values.title_color : '#1f2937';

        return {
            background: background,
            accent: accent,
            heading: heading,
        };
    }

    function usePresetCatalog() {
        var inlineCatalog = window.myArticlesDesignPresetsCatalog || null;
        var _useState = useState(function () {
            if (inlineCatalog && Array.isArray(inlineCatalog.presets)) {
                return {
                    presets: inlineCatalog.presets,
                    version: inlineCatalog.version ? String(inlineCatalog.version) : '0',
                    isLoading: false,
                    error: null,
                    source: 'inline',
                };
            }

            return {
                presets: [],
                version: '0',
                isLoading: true,
                error: null,
                source: null,
            };
        });

        var catalogState = _useState[0];
        var setCatalogState = _useState[1];

        var fetchRemote = useCallback(
            function () {
                if (!apiFetch) {
                    setCatalogState(function (prev) {
                        if (!prev.isLoading) {
                            return prev;
                        }
                        return Object.assign({}, prev, { isLoading: false });
                    });
                    return Promise.resolve();
                }

                setCatalogState(function (prev) {
                    return Object.assign({}, prev, { isLoading: true, error: null });
                });

                var request = apiFetch({ path: '/my-articles/v1/presets' });

                if (!request || typeof request.then !== 'function') {
                    setCatalogState(function (prev) {
                        return Object.assign({}, prev, { isLoading: false });
                    });
                    return Promise.resolve();
                }

                return request
                    .then(function (result) {
                        var presets = result && Array.isArray(result.presets) ? result.presets : [];
                        var version = result && result.version ? String(result.version) : '0';

                        setCatalogState({
                            presets: presets,
                            version: version,
                            isLoading: false,
                            error: null,
                            source: 'remote',
                        });

                        return result;
                    })
                    .catch(function (error) {
                        setCatalogState(function (prev) {
                            return Object.assign({}, prev, { isLoading: false, error: error });
                        });

                        return error;
                    });
            },
            []
        );

        useEffect(
            function () {
                if ('remote' === catalogState.source) {
                    return;
                }
                fetchRemote();
            },
            [fetchRemote]
        );

        return {
            presets: catalogState.presets,
            version: catalogState.version,
            isLoading: catalogState.isLoading,
            error: catalogState.error,
            reload: fetchRemote,
        };
    }

    function derivePresetTags(preset, presetId) {
        var tags = [];
        if (preset && Array.isArray(preset.tags)) {
            tags = preset.tags.filter(function (tag) {
                return tag && typeof tag === 'string';
            });
        }

        if (!tags.length) {
            var values = preset && preset.values ? preset.values : {};
            var displayMode = typeof values.display_mode === 'string' ? values.display_mode : 'grid';
            var displayLabels = {
                grid: __('Grille', 'mon-articles'),
                list: __('Liste', 'mon-articles'),
                slideshow: __('Diaporama', 'mon-articles'),
            };
            if (displayLabels[displayMode]) {
                tags.push(displayLabels[displayMode]);
            }
        }

        var isDark = preset && preset.values ? isColorDark(preset.values.module_bg_color) : null;
        if (null !== isDark) {
            tags.push(isDark ? __('Sombre', 'mon-articles') : __('Clair', 'mon-articles'));
        }

        if (preset && preset.locked) {
            tags.push(__('Guidé', 'mon-articles'));
        }

        if (presetId && tags.length === 0) {
            tags.push(presetId);
        }

        var unique = [];
        var seen = {};
        tags.forEach(function (tag) {
            if (!tag || typeof tag !== 'string') {
                return;
            }
            if (seen[tag]) {
                return;
            }
            seen[tag] = true;
            unique.push(tag);
        });

        return unique;
    }

    function PresetGallery(props) {
        var presets = Array.isArray(props.presets) ? props.presets : [];
        var isLoading = !!props.isLoading;
        var error = props.error || null;
        var onRetry = typeof props.onRetry === 'function' ? props.onRetry : null;
        var activeId = props.value || '';
        var disabled = !!props.disabled;
        var onChange = typeof props.onChange === 'function' ? props.onChange : function () {};
        var catalogVersion = props.version ? String(props.version) : null;

        var _useState7 = useState('');
        var searchValue = _useState7[0];
        var setSearchValue = _useState7[1];

        var _useState8 = useState([]);
        var activeTags = _useState8[0];
        var setActiveTags = _useState8[1];

        var _useState9 = useState(activeId || '');
        var focusedId = _useState9[0];
        var setFocusedId = _useState9[1];

        useEffect(
            function () {
                if (activeId && focusedId !== activeId) {
                    setFocusedId(activeId);
                }
            },
            [activeId]
        );

        var normalizedSearch = searchValue ? searchValue.toLowerCase() : '';

        var availableTags = useMemo(
            function () {
                var tags = [];
                presets.forEach(function (preset) {
                    if (Array.isArray(preset.tags)) {
                        preset.tags.forEach(function (tag) {
                            if (!tag || typeof tag !== 'string') {
                                return;
                            }
                            if (tags.indexOf(tag) === -1) {
                                tags.push(tag);
                            }
                        });
                    }
                });

                tags.sort(function (a, b) {
                    return a.localeCompare(b);
                });

                return tags;
            },
            [presets]
        );

        var filteredPresets = useMemo(
            function () {
                return presets.filter(function (preset) {
                    var matchesSearch = true;
                    if (normalizedSearch) {
                        var haystack = [];
                        if (preset.label) {
                            haystack.push(preset.label);
                        }
                        if (preset.description) {
                            haystack.push(preset.description);
                        }
                        if (Array.isArray(preset.tags)) {
                            haystack = haystack.concat(preset.tags);
                        }

                        matchesSearch = haystack.some(function (value) {
                            return value && typeof value === 'string' && value.toLowerCase().indexOf(normalizedSearch) !== -1;
                        });
                    }

                    var matchesTags = true;
                    if (activeTags.length) {
                        var presetTags = Array.isArray(preset.tags) ? preset.tags : [];
                        matchesTags = activeTags.every(function (tag) {
                            return presetTags.indexOf(tag) !== -1;
                        });
                    }

                    return matchesSearch && matchesTags;
                });
            },
            [presets, normalizedSearch, activeTags]
        );

        var currentPreview = useMemo(
            function () {
                if (!focusedId) {
                    return null;
                }

                return filteredPresets.find(function (preset) {
                    return preset.id === focusedId;
                }) || presets.find(function (preset) {
                    return preset.id === focusedId;
                }) || null;
            },
            [filteredPresets, presets, focusedId]
        );

        var toggleTag = useCallback(
            function (tag) {
                if (!tag || typeof tag !== 'string') {
                    return;
                }
                setActiveTags(function (previous) {
                    if (!Array.isArray(previous) || previous.indexOf(tag) === -1) {
                        return (previous || []).concat([tag]);
                    }
                    return previous.filter(function (item) {
                        return item !== tag;
                    });
                });
            },
            []
        );

        var searchField = SearchControl
            ? el(SearchControl, {
                  value: searchValue,
                  onChange: function (value) {
                      setSearchValue(value || '');
                  },
                  label: __('Rechercher un modèle', 'mon-articles'),
                  placeholder: __('Filtrer par nom, description ou tag…', 'mon-articles'),
                  __nextHasNoMarginBottom: true,
              })
            : el(TextControl, {
                  value: searchValue,
                  onChange: function (value) {
                      setSearchValue(value || '');
                  },
                  label: __('Rechercher un modèle', 'mon-articles'),
                  placeholder: __('Filtrer par nom, description ou tag…', 'mon-articles'),
                  __nextHasNoMarginBottom: true,
                  __next40pxDefaultSize: true,
              });

        var tagFilterBar = null;
        if (availableTags.length) {
            tagFilterBar = el(
                'div',
                { className: 'my-articles-preset-browser__tags' },
                availableTags.map(function (tag) {
                    var isActive = activeTags.indexOf(tag) !== -1;
                    var tagClass = 'my-articles-preset-browser__tag';
                    if (isActive) {
                        tagClass += ' is-active';
                    }
                    return el(
                        'button',
                        {
                            key: tag,
                            type: 'button',
                            className: tagClass,
                            onClick: function () {
                                toggleTag(tag);
                            },
                        },
                        tag
                    );
                })
            );
        }

        var gridContent = null;

        if (isLoading && !presets.length) {
            gridContent = el('div', { className: 'my-articles-preset-browser__loading' }, el(Spinner, null));
        } else if (filteredPresets.length === 0) {
            gridContent = el(
                Notice,
                { status: 'warning', isDismissible: false },
                __('Aucun modèle ne correspond à votre recherche.', 'mon-articles')
            );
        } else {
            gridContent = el(
                'div',
                {
                    className: 'my-articles-preset-browser__grid',
                    role: 'listbox',
                    'aria-label': __('Choisir un modèle de présentation', 'mon-articles'),
                },
                filteredPresets.map(function (preset) {
                    var isActive = preset.id === activeId;
                    var cardClass = 'my-articles-preset-browser__card';

                    if (isActive) {
                        cardClass += ' is-active';
                    }
                    if (disabled) {
                        cardClass += ' is-disabled';
                    }

                    var tags = Array.isArray(preset.tags) ? preset.tags : [];
                    var hasThumbnail = preset.thumbnail && typeof preset.thumbnail === 'string';

                    var previewNode = hasThumbnail
                        ? el('img', {
                              src: preset.thumbnail,
                              alt: preset.label || preset.id,
                              className: 'my-articles-preset-browser__thumbnail',
                          })
                        : renderPresetPreview(preset);

                    return el(
                        'button',
                        {
                            key: preset.id,
                            type: 'button',
                            className: cardClass,
                            role: 'option',
                            'aria-selected': isActive,
                            'aria-pressed': isActive,
                            onClick: function () {
                                if (disabled || isActive) {
                                    return;
                                }
                                onChange(preset.id);
                            },
                            onMouseEnter: function () {
                                setFocusedId(preset.id);
                            },
                            onFocus: function () {
                                setFocusedId(preset.id);
                            },
                            disabled: disabled,
                        },
                        previewNode,
                        el(
                            'div',
                            { className: 'my-articles-preset-browser__card-header' },
                            el('span', { className: 'my-articles-preset-browser__card-title' }, preset.label || preset.id),
                            preset.locked
                                ? el(
                                      'span',
                                      { className: 'my-articles-preset-browser__badge', title: __('Modèle guidé', 'mon-articles') },
                                      '🔒'
                                  )
                                : null
                        ),
                        preset.description
                            ? el('div', { className: 'my-articles-preset-browser__card-description' }, preset.description)
                            : null,
                        tags.length
                            ? el(
                                  'div',
                                  { className: 'my-articles-preset-browser__card-tags' },
                                  tags.map(function (tag) {
                                      return el('span', { key: tag, className: 'my-articles-preset-browser__card-tag' }, tag);
                                  })
                              )
                            : null
                    );
                })
            );
        }

        var errorNotice = null;
        if (error) {
            var retryButton = null;
            if (onRetry) {
                retryButton = el(
                    Button,
                    {
                        onClick: function () {
                            onRetry();
                        },
                        variant: 'secondary',
                    },
                    __('Réessayer', 'mon-articles')
                );
            }

            errorNotice = el(
                Notice,
                { status: 'error', isDismissible: false },
                __('Impossible de charger les préréglages distants.', 'mon-articles'),
                retryButton
            );
        }

        var previewPanel = null;
        if (currentPreview) {
            previewPanel = el(
                'div',
                { className: 'my-articles-preset-browser__preview' },
                el(
                    'div',
                    { className: 'my-articles-preset-browser__preview-header' },
                    el('strong', null, currentPreview.label || currentPreview.id),
                    currentPreview.locked
                        ? el('span', { className: 'my-articles-preset-browser__badge' }, __('Guidé', 'mon-articles'))
                        : null
                ),
                renderPresetResponsivePreview(currentPreview)
            );
        }

        return el(
            'div',
            { className: 'my-articles-preset-browser' },
            el(
                'div',
                { className: 'my-articles-preset-browser__toolbar' },
                searchField,
                tagFilterBar,
                catalogVersion
                    ? el('div', { className: 'my-articles-preset-browser__version' }, __('Version', 'mon-articles'), ': ', catalogVersion)
                    : null
            ),
            errorNotice,
            isLoading && presets.length
                ? el('div', { className: 'my-articles-preset-browser__loading-inline' }, el(Spinner, null))
                : null,
            gridContent,
            previewPanel
        );
    }

    function renderPresetPreview(preset) {
        var values = preset && preset.values ? preset.values : {};
        var swatch = buildPresetSwatch(values);
        var cardBackground = values.vignette_bg_color || '#ffffff';
        var metaColor = values.meta_color || '#6b7280';
        var accent = values.pagination_color || '#2563eb';

        return el(
            'div',
            {
                className: 'my-articles-preset-browser__thumbnail is-generated',
                style: { background: swatch.background },
            },
            [0, 1, 2].map(function (index) {
                return el(
                    'div',
                    { key: index, className: 'my-articles-preset-browser__thumbnail-card', style: { background: cardBackground } },
                    el('span', {
                        className: 'my-articles-preset-browser__thumbnail-title',
                        style: { color: swatch.heading },
                    }),
                    el('span', {
                        className: 'my-articles-preset-browser__thumbnail-meta',
                        style: { background: metaColor },
                    }),
                    el('span', {
                        className: 'my-articles-preset-browser__thumbnail-bar',
                        style: { background: accent },
                    })
                );
            })
        );
    }
    if (!TextareaControl) {
        TextareaControl = function (props) {
            var value = typeof props.value === 'string' ? props.value : '';
            var rows = props.rows && props.rows > 0 ? props.rows : 6;
            var placeholder = props.placeholder || '';
            var onChange = typeof props.onChange === 'function' ? props.onChange : function () {};
            return el(
                BaseControl || 'div',
                {
                    className: 'components-textarea-control',
                    label: props.label,
                    help: props.help,
                },
                el('textarea', {
                    className: 'components-textarea-control__input',
                    value: value,
                    rows: rows,
                    placeholder: placeholder,
                    onChange: function (event) {
                        onChange(event && event.target ? event.target.value : '');
                    },
                })
            );
        };
    }

    function renderPresetResponsivePreview(preset) {
        var values = preset && preset.values ? preset.values : {};
        var background = values.module_bg_color || '#ffffff';
        var cardBackground = values.vignette_bg_color || '#ffffff';
        var titleColor = values.title_color || '#1f2937';
        var metaColor = values.meta_color || '#6b7280';
        var accent = values.pagination_color || '#2563eb';

        var buildScreen = function (key, label) {
            return el(
                'div',
                { key: key, className: 'my-articles-preset-browser__screen my-articles-preset-browser__screen--' + key, style: { background: background } },
                el('div', { className: 'my-articles-preset-browser__screen-title' }, label),
                el(
                    'div',
                    { className: 'my-articles-preset-browser__screen-content' },
                    [0, 1, 2].map(function (index) {
                        return el(
                            'div',
                            { key: key + '-' + index, className: 'my-articles-preset-browser__screen-card', style: { background: cardBackground } },
                            el('span', {
                                className: 'my-articles-preset-browser__screen-card-title',
                                style: { color: titleColor },
                            }),
                            el('span', {
                                className: 'my-articles-preset-browser__screen-card-meta',
                                style: { color: metaColor },
                            }),
                            el('span', {
                                className: 'my-articles-preset-browser__screen-card-bar',
                                style: { background: accent },
                            })
                        );
                    })
                )
            );
        };

        return el(
            'div',
            { className: 'my-articles-preset-browser__responsive' },
            buildScreen('desktop', __('Bureau', 'mon-articles')),
            buildScreen('tablet', __('Tablette', 'mon-articles')),
            buildScreen('mobile', __('Mobile', 'mon-articles'))
        );
    }

    function OnboardingGuide(props) {
        var onFinish = typeof props.onFinish === 'function' ? props.onFinish : function () {};
        var onDismiss = typeof props.onDismiss === 'function' ? props.onDismiss : onFinish;

        if (Guide && GuidePage) {
            return el(
                Guide,
                {
                    className: 'my-articles-onboarding',
                    finishButtonText: __('Terminer', 'mon-articles'),
                    nextButtonText: __('Suivant', 'mon-articles'),
                    backButtonText: __('Précédent', 'mon-articles'),
                    onFinish: onFinish,
                    onDismiss: onDismiss,
                    isVisible: true,
                },
                el(
                    GuidePage,
                    {
                        key: 'welcome',
                        title: __('Bienvenue dans Tuiles – LCV', 'mon-articles'),
                        description: __('Un assistant condensé pour paramétrer rapidement votre module.', 'mon-articles'),
                    },
                    el(
                        'p',
                        {},
                        __('Trouvez un module existant via la recherche et choisissez un modèle visuel adapté à votre usage.', 'mon-articles')
                    ),
                    el(
                        'p',
                        {},
                        __('Vous pouvez filtrer les réglages par mots-clés grâce au champ de recherche du panneau latéral.', 'mon-articles')
                    )
                ),
                el(
                    GuidePage,
                    {
                        key: 'presets',
                        title: __('Explorer les modèles', 'mon-articles'),
                        description: __('La galerie affiche un aperçu, des tags d’usage et le statut de verrouillage de chaque modèle.', 'mon-articles'),
                    },
                    el(
                        'p',
                        {},
                        __('Cliquez sur une vignette pour appliquer instantanément un modèle. Les tags aident à repérer les contextes recommandés.', 'mon-articles')
                    ),
                    el(
                        'p',
                        {},
                        __('Les modèles guidés verrouillent certains réglages pour garantir la cohérence éditoriale.', 'mon-articles')
                    )
                ),
                el(
                    GuidePage,
                    {
                        key: 'pilot',
                        title: __('Activer le mode pilotage', 'mon-articles'),
                        description: __('Superposez des métriques clés directement dans la prévisualisation.', 'mon-articles'),
                    },
                    el(
                        'p',
                        {},
                        __('Le bouton « Pilotage » de la barre d’outils affiche le volume d’articles, les filtres actifs et la configuration de pagination.', 'mon-articles')
                    ),
                    el(
                        'p',
                        {},
                        __('Changez de viewport pour valider vos choix responsive depuis le sélecteur intégré à la prévisualisation.', 'mon-articles')
                    )
                )
            );
        }

        if (Modal) {
            return el(
                Modal,
                {
                    title: __('Bienvenue dans Tuiles – LCV', 'mon-articles'),
                    className: 'my-articles-onboarding-modal',
                    onRequestClose: onDismiss,
                },
                el(
                    'div',
                    { className: 'my-articles-onboarding-modal__content' },
                    el(
                        'p',
                        {},
                        __('Parcourez les modèles visuels, utilisez la recherche de réglages et activez le mode pilotage pour contrôler vos données.', 'mon-articles')
                    )
                ),
                el(
                    'div',
                    { className: 'my-articles-onboarding-modal__actions' },
                    el(
                        Button,
                        {
                            variant: 'primary',
                            onClick: onFinish,
                        },
                        __('Terminer', 'mon-articles')
                    )
                )
            );
        }

        return null;
    }

    function getStatusMetadata(status) {
        if (typeof status !== 'string' || '' === status) {
            return {
                label: __('Inconnu', 'mon-articles'),
                notice: null,
            };
        }

        var normalized = status.toLowerCase();
        var labels = {
            publish: __('Publié', 'mon-articles'),
            draft: __('Brouillon', 'mon-articles'),
            pending: __('En attente', 'mon-articles'),
            future: __('Planifié', 'mon-articles'),
            private: __('Privé', 'mon-articles'),
            trash: __('Corbeille', 'mon-articles'),
        };
        var notices = {
            draft: __('Ce module est un brouillon : il n’apparaîtra pas côté site tant qu’il n’est pas publié.', 'mon-articles'),
            pending: __('Ce module est en attente de validation par un éditeur.', 'mon-articles'),
            future: __('Ce module est planifié : il se publiera automatiquement à la date indiquée.', 'mon-articles'),
            private: __('Ce module est privé : seuls les utilisateurs autorisés peuvent le voir.', 'mon-articles'),
            trash: __('Ce module est dans la corbeille : restaurez-le avant de l’utiliser.', 'mon-articles'),
        };

        var label = labels[normalized] || status;
        var notice = null;

        if ('publish' !== normalized) {
            var message = notices[normalized] || sprintf(__('Statut actuel : %s.', 'mon-articles'), label.toLowerCase());
            var statusType = 'warning';

            if ('trash' === normalized) {
                statusType = 'error';
            }

            notice = {
                type: statusType,
                message: message,
            };
        }

        return {
            label: label,
            notice: notice,
        };
    }

    if (!FormTokenField) {
        FormTokenField = function (props) {
            var tokens = Array.isArray(props.value) ? props.value : [];

            return el(TextControl, {
                label: props.label,
                value: tokens.join(', '),
                onChange: function (value) {
                    if (typeof props.onChange === 'function') {
                        var nextTokens = (value || '')
                            .split(',')
                            .map(function (item) {
                                return item.trim();
                            });
                        props.onChange(nextTokens);
                    }
                },
                placeholder: props.placeholder,
                help: props.help,
                __nextHasNoMarginBottom: true,
                __next40pxDefaultSize: true,
            });
        };
    }

    var DESIGN_PRESET_FALLBACK = 'custom';
    var DEFAULT_THUMBNAIL_ASPECT_RATIO = '16/9';
    var THUMBNAIL_ASPECT_RATIO_OPTIONS = [
        { value: '1', label: __('Carré (1:1)', 'mon-articles') },
        { value: '4/3', label: __('Classique (4:3)', 'mon-articles') },
        { value: '3/2', label: __('Photo (3:2)', 'mon-articles') },
        { value: '16/9', label: __('Panoramique (16:9)', 'mon-articles') },
    ];
    var THUMBNAIL_ASPECT_RATIO_VALUES = THUMBNAIL_ASPECT_RATIO_OPTIONS.map(function (option) {
        return option.value;
    });

    function sanitizeThumbnailAspectRatio(value) {
        if (typeof value !== 'string') {
            value = '';
        }

        if (THUMBNAIL_ASPECT_RATIO_VALUES.indexOf(value) === -1) {
            return DEFAULT_THUMBNAIL_ASPECT_RATIO;
        }

        return value;
    }

    function normalizeFilterTokens(tokens) {
        if (!Array.isArray(tokens)) {
            return [];
        }

        var normalized = [];

        tokens.forEach(function (token) {
            if (typeof token !== 'string') {
                return;
            }

            var parts = token.split(':');

            if (parts.length !== 2) {
                return;
            }

            var taxonomy = parts[0].trim().toLowerCase();
            var slug = parts[1].trim().toLowerCase();

            if (!taxonomy || !slug) {
                return;
            }

            var sanitized = taxonomy + ':' + slug;

            if (normalized.indexOf(sanitized) === -1) {
                normalized.push(sanitized);
            }
        });

        return normalized;
    }

    var MODULE_QUERY_DEFAULTS = {
        orderby: 'title',
        order: 'asc',
        status: 'publish',
        context: 'view',
    };
    var MODULES_PER_PAGE = 20;
    var MODULE_LIST_FIELDS = typeof Object.freeze === 'function' ? Object.freeze(['id', 'title']) : ['id', 'title'];
    var SELECTED_INSTANCE_FIELDS =
        typeof Object.freeze === 'function'
            ? Object.freeze(['id', 'title', 'status', 'link', 'date', 'date_gmt', 'modified', 'modified_gmt'])
            : ['id', 'title', 'status', 'link', 'date', 'date_gmt', 'modified', 'modified_gmt'];
    var SELECTED_INSTANCE_QUERY =
        typeof Object.freeze === 'function'
            ? Object.freeze({ context: 'edit', _fields: SELECTED_INSTANCE_FIELDS })
            : { context: 'edit', _fields: SELECTED_INSTANCE_FIELDS };

    registerBlockType('mon-affichage/articles', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var _useState = useState('');
            var searchValue = _useState[0];
            var setSearchValue = _useState[1];

            var _useState2 = useState(1);
            var currentPage = _useState2[0];
            var setCurrentPage = _useState2[1];

            var _useState3 = useState([]);
            var fetchedInstances = _useState3[0];
            var setFetchedInstances = _useState3[1];

            var modulesQuery = useMemo(
                function () {
                    var query = Object.assign({}, MODULE_QUERY_DEFAULTS, {
                        per_page: MODULES_PER_PAGE,
                        page: currentPage,
                        _fields: MODULE_LIST_FIELDS,
                    });

                    if (searchValue) {
                        query.search = searchValue;
                    }

                    return query;
                },
                [searchValue, currentPage]
            );

            var _useState4 = useState(true);
            var hasMoreResults = _useState4[0];
            var setHasMoreResults = _useState4[1];

            var _useState5 = useState(null);
            var previewViewport = _useState5[0];
            var setPreviewViewport = _useState5[1];

            var _useState6 = useState('');
            var inspectorSearch = _useState6[0];
            var setInspectorSearch = _useState6[1];

            var _useState7 = useState(!attributes.onboarding_complete);
            var isOnboardingVisible = _useState7[0];
            var setOnboardingVisible = _useState7[1];

            var _useState8 = useState(false);
            var isPilotMode = _useState8[0];
            var setPilotMode = _useState8[1];

            var presetCatalog = usePresetCatalog();

            var designPresets = useMemo(
                function () {
                    var map = {};

                    if (Array.isArray(presetCatalog.presets) && presetCatalog.presets.length) {
                        presetCatalog.presets.forEach(function (preset) {
                            if (!preset || typeof preset.id !== 'string' || '' === preset.id) {
                                return;
                            }

                            var values = preset.values && typeof preset.values === 'object' ? preset.values : {};
                            map[preset.id] = {
                                label: preset.label || preset.id,
                                description: preset.description || '',
                                locked: !!preset.locked,
                                values: values,
                                tags: derivePresetTags(preset, preset.id),
                                swatch: buildPresetSwatch(values),
                                thumbnail: preset.thumbnail || '',
                            };
                        });
                    } else if (typeof window !== 'undefined' && window.myArticlesDesignPresets) {
                        Object.keys(window.myArticlesDesignPresets).forEach(function (presetId) {
                            var legacy = window.myArticlesDesignPresets[presetId] || {};
                            if (!presetId || typeof presetId !== 'string') {
                                return;
                            }
                            var legacyValues = legacy.values && typeof legacy.values === 'object' ? legacy.values : {};
                            map[presetId] = {
                                label: legacy.label || presetId,
                                description: legacy.description || '',
                                locked: !!legacy.locked,
                                values: legacyValues,
                                tags: derivePresetTags(legacy, presetId),
                                swatch: buildPresetSwatch(legacyValues),
                                thumbnail: '',
                            };
                        });
                    }

                    if (!map[DESIGN_PRESET_FALLBACK]) {
                        map[DESIGN_PRESET_FALLBACK] = {
                            label: __('Personnalisé', 'mon-articles'),
                            description: __('Conservez vos réglages actuels.', 'mon-articles'),
                            locked: false,
                            values: {},
                            tags: [__('Libre', 'mon-articles')],
                            swatch: buildPresetSwatch({}),
                            thumbnail: '',
                        };
                    }

                    return map;
                },
                [presetCatalog.presets]
            );

            var galleryPresets = useMemo(
                function () {
                    return Object.keys(designPresets).map(function (presetId) {
                        var preset = designPresets[presetId] || {};
                        return {
                            id: presetId,
                            label: preset.label || presetId,
                            description: preset.description || '',
                            locked: !!preset.locked,
                            tags: Array.isArray(preset.tags) ? preset.tags : derivePresetTags(preset, presetId),
                            swatch: preset.swatch || buildPresetSwatch(preset.values || {}),
                            values: preset.values || {},
                            thumbnail: preset.thumbnail || '',
                        };
                    });
                },
                [designPresets]
            );

            var INSPECTOR_MODE_SIMPLE = 'simple';
            var INSPECTOR_MODE_EXPERT = 'expert';
            var INSPECTOR_PREFERENCE_NAMESPACE = 'mon-articles/editor';
            var INSPECTOR_PREFERENCE_KEY = 'inspectorMode';

            var _useState9 = useState(function () {
                var fallbackMode = INSPECTOR_MODE_SIMPLE;
                var storedMode = null;

                if (wp && wp.data && typeof wp.data.select === 'function') {
                    try {
                        var preferencesStore = wp.data.select('core/preferences');
                        if (preferencesStore && typeof preferencesStore.getPreferences === 'function') {
                            var preferences = preferencesStore.getPreferences(INSPECTOR_PREFERENCE_NAMESPACE) || {};
                            if (preferences && typeof preferences[INSPECTOR_PREFERENCE_KEY] === 'string') {
                                storedMode = preferences[INSPECTOR_PREFERENCE_KEY];
                            }
                        }
                    } catch (error) {
                        storedMode = null;
                    }
                }

                if (!storedMode && typeof window !== 'undefined' && window.localStorage) {
                    try {
                        storedMode = window.localStorage.getItem('myArticlesInspectorMode');
                    } catch (error) {
                        storedMode = null;
                    }
                }

                return storedMode === INSPECTOR_MODE_EXPERT ? INSPECTOR_MODE_EXPERT : fallbackMode;
            });
            var inspectorMode = _useState9[0];
            var setInspectorMode = _useState9[1];

            useEffect(
                function () {
                    var normalizedMode = inspectorMode === INSPECTOR_MODE_EXPERT ? INSPECTOR_MODE_EXPERT : INSPECTOR_MODE_SIMPLE;

                    if (wp && wp.data && typeof wp.data.dispatch === 'function') {
                        try {
                            var preferencesDispatch = wp.data.dispatch('core/preferences');
                            if (preferencesDispatch && typeof preferencesDispatch.setPreferences === 'function') {
                                var update = {};
                                update[INSPECTOR_PREFERENCE_KEY] = normalizedMode;
                                preferencesDispatch.setPreferences(INSPECTOR_PREFERENCE_NAMESPACE, update);
                            }
                        } catch (error) {
                            // Ignore persistence errors silently.
                        }
                    }

                    if (typeof window !== 'undefined' && window.localStorage) {
                        try {
                            window.localStorage.setItem('myArticlesInspectorMode', normalizedMode);
                        } catch (error) {
                            // noop when storage is unavailable.
                        }
                    }
                },
                [inspectorMode]
            );

            var viewportLessThanMedium = typeof useViewportMatch === 'function' ? useViewportMatch('medium', '<') : false;
            var viewportLessThanLarge = typeof useViewportMatch === 'function' ? useViewportMatch('large', '<') : false;
            var autoPreviewViewport = 'desktop';
            if (viewportLessThanMedium) {
                autoPreviewViewport = 'mobile';
            } else if (viewportLessThanLarge) {
                autoPreviewViewport = 'tablet';
            }
            var effectivePreviewViewport = previewViewport || autoPreviewViewport;

            var blockClassName = 'my-articles-block';
            if (effectivePreviewViewport) {
                blockClassName += ' is-preview-' + effectivePreviewViewport;
            }
            var blockProps = useBlockProps({ className: blockClassName });

            var thumbnailAspectRatio = sanitizeThumbnailAspectRatio(attributes.thumbnail_aspect_ratio || DEFAULT_THUMBNAIL_ASPECT_RATIO);

            var isDesignPresetLocked = false;

            useEffect(
                function () {
                    if (attributes.onboarding_complete && isOnboardingVisible) {
                        setOnboardingVisible(false);
                    }
                },
                [attributes.onboarding_complete]
            );

            var handleOnboardingFinish = useCallback(
                function () {
                    setAttributes({ onboarding_complete: true });
                    setOnboardingVisible(false);
                },
                [setAttributes]
            );

            var handleOnboardingDismiss = useCallback(
                function () {
                    setAttributes({ onboarding_complete: true });
                    setOnboardingVisible(false);
                },
                [setAttributes]
            );

            var listData = useSelect(
                function (select) {
                    var core = select('core');
                    var dataStore = select('core/data');

                    return {
                        instances: core.getEntityRecords('postType', 'mon_affichage', modulesQuery),
                        isResolving: dataStore.isResolving('core', 'getEntityRecords', ['postType', 'mon_affichage', modulesQuery]),
                    };
                },
                [modulesQuery]
            );

            var selectedData = useSelect(function (select) {
                var core = select('core');
                var dataStore = select('core/data');

                return {
                    selectedInstance: attributes.instanceId
                        ? core.getEntityRecord('postType', 'mon_affichage', attributes.instanceId, SELECTED_INSTANCE_QUERY)
                        : null,
                    isResolvingSelected: attributes.instanceId
                        ? dataStore.isResolving('core', 'getEntityRecord', [
                              'postType',
                              'mon_affichage',
                              attributes.instanceId,
                              SELECTED_INSTANCE_QUERY,
                          ])
                        : false,
                };
            }, [attributes.instanceId]);

            useEffect(
                function () {
                    var sanitized = sanitizeThumbnailAspectRatio(attributes.thumbnail_aspect_ratio || DEFAULT_THUMBNAIL_ASPECT_RATIO);

                    if (sanitized !== attributes.thumbnail_aspect_ratio) {
                        setAttributes({ thumbnail_aspect_ratio: sanitized });
                    }
                },
                [attributes.thumbnail_aspect_ratio]
            );

            useEffect(
                function () {
                    var normalized = normalizeFilterTokens(attributes.filters || []);

                    if (normalized.length !== (attributes.filters || []).length) {
                        setAttributes({ filters: normalized });
                        return;
                    }

                    var differs = normalized.some(function (token, index) {
                        return token !== (attributes.filters || [])[index];
                    });

                    if (differs) {
                        setAttributes({ filters: normalized });
                    }
                },
                [attributes.filters]
            );

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

            var filterValueChangeHandler = useCallback(
                function (value) {
                    var nextValue = value || '';
                    setSearchValue(nextValue);
                    setCurrentPage(1);
                    setFetchedInstances([]);
                    setHasMoreResults(true);
                },
                [setSearchValue, setCurrentPage, setFetchedInstances, setHasMoreResults]
            );

            var debouncedFilterUpdate = useAsyncDebounce(filterValueChangeHandler, 250);

            useEffect(
                function () {
                    return function () {
                        if (debouncedFilterUpdate && typeof debouncedFilterUpdate.cancel === 'function') {
                            debouncedFilterUpdate.cancel();
                        }
                    };
                },
                [debouncedFilterUpdate]
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

            var designPresetList = useMemo(
                function () {
                    var list = Array.isArray(galleryPresets) ? galleryPresets.slice() : [];

                    if (list.length === 0) {
                        list = [
                            {
                                id: DESIGN_PRESET_FALLBACK,
                                label: __('Personnalisé', 'mon-articles'),
                                description: __('Conservez vos réglages actuels.', 'mon-articles'),
                                locked: false,
                                tags: [__('Libre', 'mon-articles')],
                                swatch: buildPresetSwatch({}),
                                values: {},
                                thumbnail: '',
                            },
                        ];
                    } else {
                        list.sort(function (a, b) {
                            if (a.id === DESIGN_PRESET_FALLBACK) {
                                return -1;
                            }
                            if (b.id === DESIGN_PRESET_FALLBACK) {
                                return 1;
                            }
                            if (typeof a.label === 'string' && typeof b.label === 'string') {
                                return a.label.localeCompare(b.label);
                            }
                            return 0;
                        });
                    }

                    return list;
                },
                [galleryPresets]
            );

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

            var toolbarControls = null;
            if (BlockControls && ToolbarGroup && ToolbarButton) {
                var toolbarChildren = [];

                var displayModeOptions = [
                    { value: 'grid', label: __('Grille', 'mon-articles'), icon: 'grid-view' },
                    { value: 'list', label: __('Liste', 'mon-articles'), icon: 'list-view' },
                    { value: 'slideshow', label: __('Diaporama', 'mon-articles'), icon: 'images-alt2' },
                ];

                toolbarChildren.push(
                    el(
                        ToolbarGroup,
                        { key: 'display-mode', label: __('Mode d’affichage', 'mon-articles') },
                        displayModeOptions.map(function (option) {
                            return el(ToolbarButton, {
                                key: option.value,
                                icon: option.icon,
                                label: option.label,
                                showTooltip: true,
                                isPressed: displayMode === option.value,
                                onClick: function () {
                                    if (displayMode !== option.value && !isAttributeLocked('display_mode')) {
                                        setAttributes({ display_mode: option.value });
                                    }
                                },
                                disabled: isAttributeLocked('display_mode'),
                            });
                        })
                    )
                );

                var previewOptions = [
                    { key: 'auto', stateValue: null, label: __('Auto', 'mon-articles'), icon: 'controls-repeat' },
                    { key: 'mobile', stateValue: 'mobile', label: __('Mobile', 'mon-articles'), icon: 'smartphone' },
                    { key: 'tablet', stateValue: 'tablet', label: __('Tablette', 'mon-articles'), icon: 'tablet' },
                    { key: 'desktop', stateValue: 'desktop', label: __('Ordinateur', 'mon-articles'), icon: 'desktop' },
                ];

                toolbarChildren.push(
                    el(
                        ToolbarGroup,
                        { key: 'preview-mode', label: __('Prévisualisation', 'mon-articles') },
                        previewOptions.map(function (option) {
                            return el(ToolbarButton, {
                                key: option.key,
                                icon: option.icon,
                                label: option.label,
                                showTooltip: true,
                                isPressed:
                                    previewViewport === option.stateValue ||
                                    (!previewViewport && option.stateValue === null),
                                onClick: function () {
                                    if (previewViewport !== option.stateValue) {
                                        setPreviewViewport(option.stateValue);
                                    }
                                },
                            });
                        })
                    )
                );

                toolbarChildren.push(
                    el(
                        ToolbarGroup,
                        { key: 'pilot-mode', label: __('Pilotage', 'mon-articles') },
                        el(ToolbarButton, {
                            key: 'pilot-toggle',
                            icon: 'chart-area',
                            label: __('Basculer le mode pilotage', 'mon-articles'),
                            showTooltip: true,
                            isPressed: isPilotMode,
                            onClick: function () {
                                setPilotMode(function (previous) {
                                    return !previous;
                                });
                            },
                        })
                    )
                );

                toolbarControls = el(BlockControls, {}, toolbarChildren);
            }

            var ensureNumber = function (value, fallback) {
                return typeof value === 'number' ? value : fallback;
            };

            var getAttributeValue = function (key, fallback) {
                var value = attributes[key];
                return value === undefined || value === null ? fallback : value;
            };

            var metaQueryEditorValue = typeof attributes.meta_query_raw === 'string' ? attributes.meta_query_raw : '';
            if (!metaQueryEditorValue) {
                if (Array.isArray(attributes.meta_query) && attributes.meta_query.length) {
                    try {
                        metaQueryEditorValue = JSON.stringify(attributes.meta_query, null, 2);
                    } catch (error) {
                        try {
                            metaQueryEditorValue = JSON.stringify(attributes.meta_query);
                        } catch (error2) {
                            metaQueryEditorValue = '';
                        }
                    }
                }
            }

            var contentAdaptersEditorValue = typeof attributes.content_adapters_raw === 'string' ? attributes.content_adapters_raw : '';
            if (!contentAdaptersEditorValue) {
                if (Array.isArray(attributes.content_adapters) && attributes.content_adapters.length) {
                    try {
                        contentAdaptersEditorValue = JSON.stringify(attributes.content_adapters, null, 2);
                    } catch (error) {
                        try {
                            contentAdaptersEditorValue = JSON.stringify(attributes.content_adapters);
                        } catch (error2) {
                            contentAdaptersEditorValue = '';
                        }
                    }
                }
            }

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

            var paletteSetting = [];
            var paletteResult = null;

            if (typeof useSettings === 'function') {
                paletteResult = useSettings('color.palette');

                if (Array.isArray(paletteResult)) {
                    paletteSetting = paletteResult[0];
                } else if (paletteResult) {
                    paletteSetting = paletteResult;
                }
            }

            if (
                (!paletteSetting || (Array.isArray(paletteSetting) && !paletteSetting.length)) &&
                typeof useSetting === 'function'
            ) {
                paletteSetting = useSetting('color.palette');
            }
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
                var changeHandler = withLockedGuard(key, function (nextValue) {
                    var colorValue = typeof nextValue === 'string' ? nextValue : '';

                    var update = {};
                    update[key] = colorValue;
                    setAttributes(update);
                });

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
                }

                colorPanelSettings.push({
                    label: control.label,
                    value: value,
                    onChange: isLocked
                        ? function () {}
                        : changeHandler,
                    className: isLocked
                        ? 'my-articles-color-control is-locked'
                        : 'my-articles-color-control',
                    clearable: !isLocked,
                    enableAlpha: !(control.disableAlpha || false),
                    key: key,
                    help: isLocked ? __('Réglage verrouillé par le modèle.', 'mon-articles') : undefined,
                });

                gradientPanelSettings.push({
                    label: control.label,
                    colorValue: value,
                    onColorChange: isLocked
                        ? function () {}
                        : changeHandler,
                    className: isLocked
                        ? 'my-articles-color-control is-locked'
                        : 'my-articles-color-control',
                    clearable: !isLocked,
                    enableAlpha: !(control.disableAlpha || false),
                    key: key,
                    help: isLocked ? __('Réglage verrouillé par le modèle.', 'mon-articles') : undefined,
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
                    }
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
                    }
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

            var selectedInstanceTitle = '';
            if (selectedData && selectedData.selectedInstance) {
                var rawSelectedTitle =
                    selectedData.selectedInstance.title && selectedData.selectedInstance.title.rendered
                        ? selectedData.selectedInstance.title.rendered
                        : '';
                if (rawSelectedTitle) {
                    rawSelectedTitle = decodeEntities(rawSelectedTitle);
                    rawSelectedTitle = rawSelectedTitle.replace(/<[^>]*>/g, '');
                    selectedInstanceTitle = rawSelectedTitle.trim();
                }
            }
            if (!selectedInstanceTitle && attributes.instanceId) {
                selectedInstanceTitle = sprintf(__('Module d’articles %d', 'mon-articles'), attributes.instanceId);
            }
            if (!selectedInstanceTitle) {
                selectedInstanceTitle = __('Module d’articles', 'mon-articles');
            }
            var ariaLabelPlaceholder = selectedInstanceTitle;

            var selectedInstance = selectedData && selectedData.selectedInstance ? selectedData.selectedInstance : null;
            var inspectorModeNormalized =
                inspectorMode === INSPECTOR_MODE_EXPERT ? INSPECTOR_MODE_EXPERT : INSPECTOR_MODE_SIMPLE;
            var accessibilitySuggestions = useMemo(
                function () {
                    var suggestions = [];
                    var trimmedLabel = typeof attributes.aria_label === 'string' ? attributes.aria_label.trim() : '';

                    if (selectedInstanceTitle) {
                        suggestions.push(selectedInstanceTitle);
                        suggestions.push(
                            sprintf(__('%s – sélection éditoriale', 'mon-articles'), selectedInstanceTitle)
                        );
                    }

                    if (selectedInstanceTitle && displayMode === 'slideshow') {
                        suggestions.push(
                            sprintf(__('%s – carrousel d’articles', 'mon-articles'), selectedInstanceTitle)
                        );
                    }

                    if (selectedInstanceTitle && attributes.pagination_mode === 'load_more') {
                        suggestions.push(
                            sprintf(__('%s – flux infini', 'mon-articles'), selectedInstanceTitle)
                        );
                    }

                    if (selectedInstance && (selectedInstance.modified || selectedInstance.modified_gmt)) {
                        var updatedAt = formatDateTime(selectedInstance.modified || selectedInstance.modified_gmt);
                        if (updatedAt) {
                            suggestions.push(
                                sprintf(
                                    __('%s – mise à jour le %s', 'mon-articles'),
                                    selectedInstanceTitle || __('Module d’articles', 'mon-articles'),
                                    updatedAt
                                )
                            );
                        }
                    }

                    suggestions.push(__('Articles mis en avant', 'mon-articles'));

                    var unique = [];
                    suggestions.forEach(function (candidate) {
                        if (!candidate || 'string' !== typeof candidate) {
                            return;
                        }

                        var normalized = candidate.trim();
                        if (!normalized || normalized === trimmedLabel) {
                            return;
                        }

                        if (unique.indexOf(normalized) === -1) {
                            unique.push(normalized);
                        }
                    });

                    return unique.slice(0, 4);
                },
                [
                    attributes.aria_label,
                    attributes.pagination_mode,
                    displayMode,
                    selectedInstance,
                    selectedInstanceTitle,
                ]
            );
            var moduleStatusPanel = null;

            if (selectedInstance) {
                var lastModified = selectedInstance.modified || selectedInstance.modified_gmt || '';
                var createdAt = selectedInstance.date || selectedInstance.date_gmt || '';
                var freshness = buildFreshnessInsights(lastModified);
                var statusMetadata = getStatusMetadata(selectedInstance.status || '');
                var insightsRows = [
                    {
                        key: 'id',
                        label: __('Identifiant', 'mon-articles'),
                        value: '#' + selectedInstance.id,
                    },
                    {
                        key: 'status',
                        label: __('Statut', 'mon-articles'),
                        value: statusMetadata.label,
                    },
                    {
                        key: 'updated',
                        label: __('Dernière mise à jour', 'mon-articles'),
                        value: formatDateTime(lastModified),
                    },
                    {
                        key: 'freshness',
                        label: __('Fraîcheur', 'mon-articles'),
                        value: freshness.summary,
                    },
                    {
                        key: 'created',
                        label: __('Créé le', 'mon-articles'),
                        value: formatDateTime(createdAt),
                    },
                ];

                var insightsList = el(
                    'div',
                    { className: 'my-articles-block__module-insights' },
                    insightsRows.map(function (row) {
                        return el(
                            'div',
                            { className: 'my-articles-block__module-insights-row', key: row.key },
                            el('span', { className: 'my-articles-block__module-insights-label' }, row.label),
                            el('span', { className: 'my-articles-block__module-insights-value' }, row.value)
                        );
                    })
                );

                var statusNotice = null;
                if (statusMetadata.notice) {
                    statusNotice = el(
                        Notice,
                        { status: statusMetadata.notice.type, isDismissible: false },
                        statusMetadata.notice.message
                    );
                }

                var freshnessNotice = null;
                if ('warning' === freshness.severity || 'critical' === freshness.severity || 'info' === freshness.severity) {
                    var noticeStatus = 'info';
                    if ('warning' === freshness.severity) {
                        noticeStatus = 'warning';
                    } else if ('critical' === freshness.severity) {
                        noticeStatus = 'error';
                    }
                    freshnessNotice = el(Notice, { status: noticeStatus, isDismissible: false }, freshness.description);
                }

                var actions = [];
                var viewLink = selectedInstance.link || '';
                if (viewLink) {
                    actions.push(
                        el(
                            Button,
                            {
                                key: 'view',
                                href: viewLink,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                variant: 'secondary',
                            },
                            __('Voir sur le site', 'mon-articles')
                        )
                    );
                }

                if (canEditPosts && selectedInstance.id) {
                    var editUrl = 'post.php?post=' + selectedInstance.id + '&action=edit';
                    actions.push(
                        el(
                            Button,
                            {
                                key: 'edit',
                                href: editUrl,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                variant: 'primary',
                            },
                            __('Ouvrir dans l’éditeur', 'mon-articles')
                        )
                    );
                }

                var actionsList = null;
                if (actions.length > 0) {
                    actionsList = el('div', { className: 'my-articles-block__module-insights-actions' }, actions);
                }

                moduleStatusPanel = el(
                    PanelBody,
                    { title: __('État du module', 'mon-articles'), initialOpen: false },
                    statusNotice,
                    freshnessNotice,
                    insightsList,
                    actionsList
                );
            }


            var normalizedInspectorSearch = typeof inspectorSearch === 'string' ? inspectorSearch.trim().toLowerCase() : '';
            var shouldForceInspectorOpen = normalizedInspectorSearch.length > 0;

            var onboardingCallout = null;
            if (!attributes.onboarding_complete) {
                onboardingCallout = el(
                    Notice,
                    {
                        status: 'info',
                        isDismissible: false,
                        className: 'my-articles-module__onboarding-notice',
                    },
                    __('Besoin d’un repérage rapide ? Lancez la visite guidée.', 'mon-articles'),
                    ' ',
                    el(
                        Button,
                        {
                            variant: 'link',
                            onClick: function () {
                                setOnboardingVisible(true);
                            },
                        },
                        __('Commencer', 'mon-articles')
                    )
                );
            } else {
                onboardingCallout = el(
                    Button,
                    {
                        variant: 'link',
                        className: 'my-articles-module-actions__onboarding',
                        onClick: function () {
                            setOnboardingVisible(true);
                        },
                    },
                    __('Revoir la visite guidée', 'mon-articles')
                );
            }

            var moduleSearchTokens = designPresetList.reduce(function (tokens, preset) {
                if (preset.label) {
                    tokens.push(preset.label);
                }
                if (preset.description) {
                    tokens.push(preset.description);
                }
                if (Array.isArray(preset.tags)) {
                    preset.tags.forEach(function (tag) {
                        tokens.push(tag);
                    });
                }
                return tokens;
            }, []);
            if (selectedInstanceTitle) {
                moduleSearchTokens.push(selectedInstanceTitle);
            }

            var modulePanel = el(
                PanelBody,
                {
                    key: 'module-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Module', 'mon-articles'),
                    initialOpen: true,
                    className: shouldForceInspectorOpen ? 'my-articles-panel is-search-active' : 'my-articles-panel',
                },
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
                            debouncedFilterUpdate(value);
                        },
                        help: __('Utilisez la recherche pour trouver un contenu « mon_affichage ». Les résultats se chargent au fur et à mesure.', 'mon-articles'),
                        __nextHasNoMarginBottom: true,
                        __next40pxDefaultSize: true,
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
                    el(
                        'div',
                        { className: 'my-articles-preset-gallery__container' },
                        el(PresetGallery, {
                            presets: designPresetList,
                            value: currentDesignPresetId,
                            onChange: handleDesignPresetChange,
                            disabled: isAttributeLocked('design_preset'),
                            isLoading: presetCatalog.isLoading,
                            error: presetCatalog.error,
                            onRetry: presetCatalog.reload,
                            version: presetCatalog.version,
                        })
                    ),
                    isDesignPresetLocked
                        ? el(Notice, { status: 'info', isDismissible: false }, __('Ce modèle verrouille certains réglages de design.', 'mon-articles'))
                        : null,
                    onboardingCallout,
                    el(
                        'p',
                        { className: 'components-base-control__help my-articles-block__toolbar-help' },
                        __('Astuce : la barre d’outils du bloc permet de changer le mode (Grille/Liste/Diaporama) et de simuler les colonnes mobile/tablette/desktop.', 'mon-articles')
                    )
                )
            );

            var updateAriaLabel = withLockedGuard('aria_label', function (value) {
                setAttributes({ aria_label: value || '' });
            });

            var accessibilityPanel = el(
                PanelBody,
                {
                    key: 'accessibility-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Accessibilité', 'mon-articles'),
                    initialOpen: shouldForceInspectorOpen ? true : false,
                },
                el(TextControl, {
                    label: __('Libellé ARIA du module', 'mon-articles'),
                    value: attributes.aria_label || '',
                    onChange: updateAriaLabel,
                    placeholder: ariaLabelPlaceholder,
                    help: __('Laissez vide pour utiliser automatiquement le titre du module.', 'mon-articles'),
                    disabled: isAttributeLocked('aria_label'),
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true,
                }),
                accessibilitySuggestions.length
                    ? el(
                          'div',
                          { className: 'my-articles-accessibility-suggestions' },
                          el(
                              'p',
                              { className: 'my-articles-accessibility-suggestions__label' },
                              __('Suggestions', 'mon-articles')
                          ),
                          el(
                              'div',
                              { className: 'my-articles-accessibility-suggestions__actions' },
                              accessibilitySuggestions.map(function (suggestion) {
                                  return el(
                                      Button,
                                      {
                                          key: suggestion,
                                          variant: 'tertiary',
                                          onClick: function () {
                                              updateAriaLabel(suggestion);
                                          },
                                          disabled: isAttributeLocked('aria_label'),
                                      },
                                      suggestion
                                  );
                              })
                          )
                      )
                    : null
            );

            var appearancePanel = el(
                PanelBody,
                {
                    key: 'appearance-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Affichage', 'mon-articles'),
                    initialOpen: shouldForceInspectorOpen ? true : false,
                },
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
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true,
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
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true,
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
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true,
                }),
                attributes.pagination_mode === 'load_more'
                    ? el(
                          ToggleControl,
                          {
                              label: __('Activer le déclenchement automatique', 'mon-articles'),
                              help: __('Déclenche le bouton « Charger plus » dès qu’il devient visible à l’écran.', 'mon-articles'),
                              checked: !!attributes.load_more_auto,
                              onChange: withLockedGuard('load_more_auto', function (value) {
                                  setAttributes({ load_more_auto: !!value });
                              }),
                              disabled: isAttributeLocked('load_more_auto'),
                              __nextHasNoMarginBottom: true,
                          }
                      )
                    : null,
                el(SelectControl, {
                    label: __('Ratio des vignettes', 'mon-articles'),
                    value: thumbnailAspectRatio,
                    options: THUMBNAIL_ASPECT_RATIO_OPTIONS,
                    onChange: withLockedGuard('thumbnail_aspect_ratio', function (value) {
                        setAttributes({ thumbnail_aspect_ratio: sanitizeThumbnailAspectRatio(value) });
                    }),
                    help: __('Seuls les ratios 1, 4/3, 3/2 et 16/9 sont acceptés.', 'mon-articles'),
                    disabled: isAttributeLocked('thumbnail_aspect_ratio'),
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true,
                }),
                isSlideshowMode
                    ? el(
                          Fragment,
                          {},
                          el(ToggleControl, {
                              label: __('Boucle infinie', 'mon-articles'),
                              checked: !!attributes.slideshow_loop,
                              onChange: withLockedGuard('slideshow_loop', function (value) {
                                  setAttributes({ slideshow_loop: !!value });
                              }),
                              disabled: isAttributeLocked('slideshow_loop'),
                              __nextHasNoMarginBottom: true,
                          }),
                          el(ToggleControl, {
                              label: __('Lecture automatique', 'mon-articles'),
                              checked: !!attributes.slideshow_autoplay,
                              onChange: withLockedGuard('slideshow_autoplay', function (value) {
                                  setAttributes({ slideshow_autoplay: !!value });
                              }),
                              disabled: isAttributeLocked('slideshow_autoplay'),
                              __nextHasNoMarginBottom: true,
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
                              __nextHasNoMarginBottom: true,
                              __next40pxDefaultSize: true,
                          }),
                          el(ToggleControl, {
                              label: __('Mettre en pause lors des interactions', 'mon-articles'),
                              checked: !!attributes.slideshow_pause_on_interaction,
                              onChange: withLockedGuard('slideshow_pause_on_interaction', function (value) {
                                  setAttributes({ slideshow_pause_on_interaction: !!value });
                              }),
                              disabled: !attributes.slideshow_autoplay || isAttributeLocked('slideshow_pause_on_interaction'),
                              __nextHasNoMarginBottom: true,
                          }),
                          el(ToggleControl, {
                              label: __('Mettre en pause au survol', 'mon-articles'),
                              checked: !!attributes.slideshow_pause_on_mouse_enter,
                              onChange: withLockedGuard('slideshow_pause_on_mouse_enter', function (value) {
                                  setAttributes({ slideshow_pause_on_mouse_enter: !!value });
                              }),
                              disabled: !attributes.slideshow_autoplay || isAttributeLocked('slideshow_pause_on_mouse_enter'),
                              __nextHasNoMarginBottom: true,
                          }),
                          el(ToggleControl, {
                              label: __('Respecter « Réduire les animations »', 'mon-articles'),
                              checked: attributes.slideshow_respect_reduced_motion !== false,
                              onChange: withLockedGuard('slideshow_respect_reduced_motion', function (value) {
                                  setAttributes({ slideshow_respect_reduced_motion: !!value });
                              }),
                              help: __('Neutralise l’autoplay pour les utilisateurs qui limitent les animations.', 'mon-articles'),
                              disabled: isAttributeLocked('slideshow_respect_reduced_motion'),
                              __nextHasNoMarginBottom: true,
                          }),
                          attributes.slideshow_autoplay && attributes.slideshow_respect_reduced_motion === false
                              ? el(
                                    Notice,
                                    {
                                        status: 'warning',
                                        isDismissible: false,
                                        className: 'my-articles-autoplay-warning',
                                    },
                                    __('Autoplay actif sans respecter « Réduire les animations » peut poser problème : activez la protection pour rester conforme aux bonnes pratiques d’accessibilité.', 'mon-articles')
                                )
                              : null,
                          el(ToggleControl, {
                              label: __('Afficher les flèches de navigation', 'mon-articles'),
                              checked: !!attributes.slideshow_show_navigation,
                              onChange: withLockedGuard('slideshow_show_navigation', function (value) {
                                  setAttributes({ slideshow_show_navigation: !!value });
                              }),
                              disabled: isAttributeLocked('slideshow_show_navigation'),
                              __nextHasNoMarginBottom: true,
                          }),
                          el(ToggleControl, {
                              label: __('Afficher la pagination', 'mon-articles'),
                              checked: !!attributes.slideshow_show_pagination,
                              onChange: withLockedGuard('slideshow_show_pagination', function (value) {
                                  setAttributes({ slideshow_show_pagination: !!value });
                              }),
                              disabled: isAttributeLocked('slideshow_show_pagination'),
                              __nextHasNoMarginBottom: true,
                          })
                      )
                    : null
            );

            var layoutPanel = el(
                PanelBody,
                {
                    key: 'layout-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Disposition', 'mon-articles'),
                    initialOpen: shouldForceInspectorOpen ? true : false,
                },
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
            );

            var interactionsPanel = el(
                PanelBody,
                {
                    key: 'interactions-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Effets d’interaction', 'mon-articles'),
                    initialOpen: false,
                },
                el(ToggleControl, {
                    label: __('Élévation au survol (desktop)', 'mon-articles'),
                    checked: !!attributes.hover_lift_desktop,
                    onChange: withLockedGuard('hover_lift_desktop', function (value) {
                        setAttributes({ hover_lift_desktop: !!value });
                    }),
                    help: __('Fait flotter légèrement les cartes sur les écrans à pointeur précis.', 'mon-articles'),
                    disabled: isAttributeLocked('hover_lift_desktop'),
                }),
                el(ToggleControl, {
                    label: __('Halo néon pulsé', 'mon-articles'),
                    checked: !!attributes.hover_neon_pulse,
                    onChange: withLockedGuard('hover_neon_pulse', function (value) {
                        setAttributes({ hover_neon_pulse: !!value });
                    }),
                    help: __('Ajoute un halo animé et lumineux autour des cartes au survol.', 'mon-articles'),
                    disabled: isAttributeLocked('hover_neon_pulse'),
                })
            );

            var spacingPanel = el(
                PanelBody,
                {
                    key: 'spacing-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Espacements & typographie', 'mon-articles'),
                    initialOpen: shouldForceInspectorOpen ? true : false,
                },
                el(RangeControl, {
                    label: __('Marge intérieure haute (px)', 'mon-articles'),
                    value: ensureNumber(attributes.module_padding_top, 0),
                    min: 0,
                    max: 200,
                    allowReset: true,
                    onChange: withLockedGuard('module_padding_top', function (value) {
                        if (typeof value !== 'number') {
                            value = 0;
                        }
                        setAttributes({ module_padding_top: value });
                    }),
                    disabled: isAttributeLocked('module_padding_top'),
                }),
                el(RangeControl, {
                    label: __('Marge intérieure basse (px)', 'mon-articles'),
                    value: ensureNumber(attributes.module_padding_bottom, 0),
                    min: 0,
                    max: 200,
                    allowReset: true,
                    onChange: withLockedGuard('module_padding_bottom', function (value) {
                        if (typeof value !== 'number') {
                            value = 0;
                        }
                        setAttributes({ module_padding_bottom: value });
                    }),
                    disabled: isAttributeLocked('module_padding_bottom'),
                }),
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
                    min: 12,
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
                    min: 10,
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
                    min: 10,
                    max: 24,
                    allowReset: true,
                    onChange: withLockedGuard('excerpt_font_size', function (value) {
                        if (typeof value !== 'number') {
                            value = 14;
                        }
                        setAttributes({ excerpt_font_size: value });
                    }),
                    disabled: isAttributeLocked('excerpt_font_size'),
                })
            );

            var filtersPanel = el(
                PanelBody,
                {
                    key: 'filters-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Filtres additionnels', 'mon-articles'),
                    initialOpen: shouldForceInspectorOpen ? true : false,
                },
                el(ToggleControl, {
                    label: __('Afficher le filtre de catégories', 'mon-articles'),
                    checked: !!attributes.show_category_filter,
                    onChange: withLockedGuard('show_category_filter', function (value) {
                        setAttributes({ show_category_filter: !!value });
                    }),
                    disabled: isAttributeLocked('show_category_filter'),
                    __nextHasNoMarginBottom: true,
                }),
                el(ToggleControl, {
                    label: __('Activer la recherche par mots-clés', 'mon-articles'),
                    checked: !!attributes.enable_keyword_search,
                    onChange: withLockedGuard('enable_keyword_search', function (value) {
                        setAttributes({ enable_keyword_search: !!value });
                    }),
                    disabled: isAttributeLocked('enable_keyword_search'),
                    __nextHasNoMarginBottom: true,
                }),
                el(TextControl, {
                    label: __('Taxonomies filtrables (slug:terme)', 'mon-articles'),
                    value: (attributes.filters || []).join(', '),
                    onChange: function (value) {
                        var tokens = (value || '')
                            .split(',')
                            .map(function (item) {
                                return item.trim();
                            });
                        setAttributes({ filters: normalizeFilterTokens(tokens) });
                    },
                    help: __('Utilisez le format `taxonomy:slug` séparé par des virgules.', 'mon-articles'),
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true,
                })
            );

            var adapterHelpText = __('Définissez des adaptateurs externes (`id`) et leur configuration JSON. Chaque entrée doit correspondre à un adaptateur enregistré.', 'mon-articles');
            if (Array.isArray(availableContentAdapters) && availableContentAdapters.length) {
                var adapterLabels = availableContentAdapters
                    .map(function (adapter) {
                        if (!adapter || typeof adapter.id !== 'string') {
                            return '';
                        }
                        var label = adapter.label && typeof adapter.label === 'string' ? adapter.label : adapter.id;
                        return label + ' (' + adapter.id + ')';
                    })
                    .filter(function (entry) {
                        return entry && entry.length;
                    });

                if (adapterLabels.length) {
                    adapterHelpText +=
                        ' ' +
                        __('Adaptateurs disponibles :', 'mon-articles') +
                        ' ' +
                        adapterLabels.join(', ');
                }
            }

            var advancedQueriesPanel = el(
                PanelBody,
                {
                    key: 'advanced-queries-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Requêtes avancées', 'mon-articles'),
                    initialOpen: false,
                },
                el(SelectControl, {
                    label: __('Relation des conditions méta', 'mon-articles'),
                    value: (attributes.meta_query_relation || 'AND').toUpperCase() === 'OR' ? 'OR' : 'AND',
                    options: [
                        { label: __('ET – toutes les conditions doivent être vraies', 'mon-articles'), value: 'AND' },
                        { label: __('OU – au moins une condition doit être vraie', 'mon-articles'), value: 'OR' },
                    ],
                    onChange: function (value) {
                        var normalized = (value || 'AND').toUpperCase();
                        if (normalized !== 'AND' && normalized !== 'OR') {
                            normalized = 'AND';
                        }
                        setAttributes({ meta_query_relation: normalized });
                    },
                }),
                TextareaControl
                    ? el(TextareaControl, {
                          label: __('Conditions méta (JSON)', 'mon-articles'),
                          value: metaQueryEditorValue,
                          rows: 6,
                          placeholder: '[{"key":"featured","value":"1","compare":"="}]',
                          help: __('Liste JSON de clauses WP_Query. Chaque entrée doit préciser une clé `key` et éventuellement `value`, `compare`, `type`.', 'mon-articles'),
                          onChange: function (value) {
                              var parsed = [];
                              if (value && typeof value === 'string') {
                                  try {
                                      var decoded = JSON.parse(value);
                                      if (Array.isArray(decoded)) {
                                          parsed = decoded;
                                      } else if (decoded && typeof decoded === 'object' && Array.isArray(decoded.clauses)) {
                                          parsed = decoded.clauses;
                                      }
                                  } catch (error) {
                                      parsed = [];
                                  }
                              }

                              setAttributes({
                                  meta_query_raw: value,
                                  meta_query: Array.isArray(parsed) ? parsed : [],
                              });
                          },
                      })
                    : null,
                TextareaControl
                    ? el(TextareaControl, {
                          label: __('Adaptateurs de contenu (JSON)', 'mon-articles'),
                          value: contentAdaptersEditorValue,
                          rows: 4,
                          placeholder: '[{"id":"external","config":{"limit":3}}]',
                          help: adapterHelpText,
                          onChange: function (value) {
                              var parsed = [];
                              if (value && typeof value === 'string') {
                                  try {
                                      var decoded = JSON.parse(value);
                                      if (Array.isArray(decoded)) {
                                          parsed = decoded;
                                      }
                                  } catch (error) {
                                      parsed = [];
                                  }
                              }

                              setAttributes({
                                  content_adapters_raw: value,
                                  content_adapters: Array.isArray(parsed) ? parsed : [],
                              });
                          },
                      })
                    : null
            );

            var sortingPanel = el(
                PanelBody,
                {
                    key: 'sorting-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Tri & ordre', 'mon-articles'),
                    initialOpen: shouldForceInspectorOpen ? true : false,
                },
                el(SelectControl, {
                    label: __('Critère de tri', 'mon-articles'),
                    value: attributes.sort || 'date',
                    options: [
                        { label: __('Date', 'mon-articles'), value: 'date' },
                        { label: __('Titre', 'mon-articles'), value: 'title' },
                        { label: __('Menu', 'mon-articles'), value: 'menu_order' },
                        { label: __('Valeur méta', 'mon-articles'), value: 'meta_value' },
                        { label: __('Popularité (commentaires)', 'mon-articles'), value: 'comment_count' },
                        { label: __('Articles épinglés', 'mon-articles'), value: 'post__in' },
                    ],
                    onChange: function (value) {
                        setAttributes({ sort: value });
                    },
                }),
                el(SelectControl, {
                    label: __('Ordre', 'mon-articles'),
                    value: attributes.order || 'DESC',
                    options: [
                        { label: __('Décroissant', 'mon-articles'), value: 'DESC' },
                        { label: __('Croissant', 'mon-articles'), value: 'ASC' },
                    ],
                    onChange: function (value) {
                        setAttributes({ order: value });
                    },
                })
            );

            var metadataPanel = el(
                PanelBody,
                {
                    key: 'metadata-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                    title: __('Méta-données', 'mon-articles'),
                    initialOpen: shouldForceInspectorOpen ? true : false,
                },
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
                    label: __('Afficher la catégorie', 'mon-articles'),
                    checked: !!attributes.show_category,
                    onChange: withLockedGuard('show_category', function (value) {
                        setAttributes({ show_category: !!value });
                    }),
                    disabled: isAttributeLocked('show_category'),
                })
            );

            var colorPanelSection = null;
            if (colorSettingsPanel) {
                colorPanelSection = el(
                    'div',
                    {
                        key: 'colors-panel-' + (shouldForceInspectorOpen ? 'search' : 'default'),
                        className: 'my-articles-inspector-panel__color' + (shouldForceInspectorOpen ? ' is-search-active' : ''),
                    },
                    colorSettingsPanel
                );
            }

            var inspectorSections = [];
            inspectorSections.push({
                id: 'module',
                title: __('Module', 'mon-articles'),
                keywords: [__('module', 'mon-articles'), __('instance', 'mon-articles'), __('modèle', 'mon-articles'), __('preset', 'mon-articles')],
                component: modulePanel,
                getSearchTokens: function () {
                    return moduleSearchTokens;
                },
            });

            if (moduleStatusPanel) {
                inspectorSections.push({
                    id: 'status',
                    title: __('État du module', 'mon-articles'),
                    keywords: [__('statut', 'mon-articles'), __('fraîcheur', 'mon-articles'), __('insights', 'mon-articles')],
                    component: el(
                        Fragment,
                        { key: 'status-panel-' + (shouldForceInspectorOpen ? 'search' : 'default') },
                        moduleStatusPanel
                    ),
                });
            }

            inspectorSections.push({
                id: 'accessibility',
                title: __('Accessibilité', 'mon-articles'),
                keywords: [__('aria', 'mon-articles'), __('accessibilité', 'mon-articles')],
                component: accessibilityPanel,
            });

            inspectorSections.push({
                id: 'appearance',
                title: __('Affichage', 'mon-articles'),
                keywords: [__('affichage', 'mon-articles'), __('diaporama', 'mon-articles'), __('pagination', 'mon-articles')],
                component: appearancePanel,
            });

            if (colorPanelSection) {
                inspectorSections.push({
                    id: 'colors',
                    title: __('Couleurs', 'mon-articles'),
                    keywords: [__('couleurs', 'mon-articles'), __('palette', 'mon-articles'), __('style', 'mon-articles')],
                    component: colorPanelSection,
                });
            }

            inspectorSections.push({
                id: 'layout',
                title: __('Disposition', 'mon-articles'),
                keywords: [__('colonnes', 'mon-articles'), __('disposition', 'mon-articles'), __('grille', 'mon-articles')],
                component: layoutPanel,
            });

            inspectorSections.push({
                id: 'interactions',
                title: __('Effets d’interaction', 'mon-articles'),
                keywords: [__('survol', 'mon-articles'), __('animation', 'mon-articles'), __('hover', 'mon-articles')],
                component: interactionsPanel,
            });

            inspectorSections.push({
                id: 'spacing',
                title: __('Espacements & typographie', 'mon-articles'),
                keywords: [__('espacements', 'mon-articles'), __('typographie', 'mon-articles'), __('marges', 'mon-articles')],
                component: spacingPanel,
            });

            inspectorSections.push({
                id: 'filters',
                title: __('Filtres additionnels', 'mon-articles'),
                keywords: [__('filtres', 'mon-articles'), __('taxonomie', 'mon-articles'), __('recherche', 'mon-articles')],
                component: filtersPanel,
            });
            inspectorSections.push({
                id: 'advanced-queries',
                title: __('Requêtes avancées', 'mon-articles'),
                keywords: [__('meta', 'mon-articles'), __('adaptateurs', 'mon-articles'), __('requêtes', 'mon-articles')],
                component: advancedQueriesPanel,
            });

            inspectorSections.push({
                id: 'sorting',
                title: __('Tri & ordre', 'mon-articles'),
                keywords: [__('tri', 'mon-articles'), __('ordre', 'mon-articles')],
                component: sortingPanel,
            });

            inspectorSections.push({
                id: 'metadata',
                title: __('Méta-données', 'mon-articles'),
                keywords: [__('auteur', 'mon-articles'), __('date', 'mon-articles'), __('catégorie', 'mon-articles')],
                component: metadataPanel,
            });

            var visibleSections = inspectorSections.filter(function (section) {
                if (!section || !section.component) {
                    return false;
                }

                if (!normalizedInspectorSearch) {
                    return true;
                }

                var tokens = [];
                if (section.title) {
                    tokens.push(section.title);
                }
                if (Array.isArray(section.keywords)) {
                    tokens = tokens.concat(section.keywords);
                }
                if (typeof section.getSearchTokens === 'function') {
                    var extra = section.getSearchTokens();
                    if (Array.isArray(extra)) {
                        tokens = tokens.concat(extra);
                    }
                }

                return tokens.some(function (token) {
                    return token && typeof token === 'string' && token.toLowerCase().indexOf(normalizedInspectorSearch) !== -1;
                });
            });

            if (!normalizedInspectorSearch && inspectorModeNormalized === INSPECTOR_MODE_SIMPLE) {
                var SIMPLE_MODE_SECTION_IDS = ['module', 'appearance', 'filters', 'sorting', 'metadata', 'accessibility'];
                var simpleOrder = {};
                SIMPLE_MODE_SECTION_IDS.forEach(function (id, index) {
                    simpleOrder[id] = index;
                });
                visibleSections = visibleSections
                    .filter(function (section) {
                        return SIMPLE_MODE_SECTION_IDS.indexOf(section.id) !== -1;
                    })
                    .sort(function (a, b) {
                        var aIndex = simpleOrder.hasOwnProperty(a.id) ? simpleOrder[a.id] : SIMPLE_MODE_SECTION_IDS.length;
                        var bIndex = simpleOrder.hasOwnProperty(b.id) ? simpleOrder[b.id] : SIMPLE_MODE_SECTION_IDS.length;
                        return aIndex - bIndex;
                    });
            }

            var searchField = null;
            if (SearchControl) {
                searchField = el(SearchControl, {
                    label: __('Rechercher un réglage', 'mon-articles'),
                    value: inspectorSearch,
                    onChange: function (value) {
                        setInspectorSearch(value);
                    },
                    placeholder: __('Filtrer les sections…', 'mon-articles'),
                    __nextHasNoMarginBottom: true,
                });
            } else {
                searchField = el(TextControl, {
                    label: __('Rechercher un réglage', 'mon-articles'),
                    value: inspectorSearch,
                    onChange: function (value) {
                        setInspectorSearch(value);
                    },
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true,
                });
            }

            var inspectorModeDescriptions = {};
            inspectorModeDescriptions[INSPECTOR_MODE_SIMPLE] = __(
                'Mode Simple : focus sur l’affichage, les filtres et le tri indispensables.',
                'mon-articles'
            );
            inspectorModeDescriptions[INSPECTOR_MODE_EXPERT] = __(
                'Mode Expert : affiche toutes les sections (couleurs, espacements, diagnostics).',
                'mon-articles'
            );

            var modeButtonOptions = [
                { key: INSPECTOR_MODE_SIMPLE, label: __('Simple', 'mon-articles') },
                { key: INSPECTOR_MODE_EXPERT, label: __('Expert', 'mon-articles') },
            ];

            var modeButtons = null;
            var buildModeButton = function (option) {
                var isActive = inspectorModeNormalized === option.key;
                return el(
                    Button,
                    {
                        key: option.key,
                        variant: isActive ? 'primary' : 'secondary',
                        isPressed: isActive,
                        'aria-pressed': isActive,
                        onClick: function () {
                            if (!isActive) {
                                setInspectorMode(option.key);
                            }
                        },
                    },
                    option.label
                );
            };

            if (ToggleGroupControl && ToggleGroupControlOption) {
                modeButtons = el(
                    ToggleGroupControl,
                    {
                        className: 'my-articles-inspector-panel__mode-buttons',
                        label: __('Choisir un mode d’édition', 'mon-articles'),
                        hideLabelFromVision: true,
                        value: inspectorModeNormalized,
                        onChange: function (nextValue) {
                            if (nextValue && nextValue !== inspectorModeNormalized) {
                                setInspectorMode(nextValue);
                            }
                        },
                    },
                    modeButtonOptions.map(function (option) {
                        return el(ToggleGroupControlOption, {
                            key: option.key,
                            value: option.key,
                            label: option.label,
                        });
                    })
                );
            } else {
                modeButtons = el(
                    'div',
                    { className: 'my-articles-inspector-panel__mode-buttons is-fallback' },
                    modeButtonOptions.map(buildModeButton)
                );
            }

            var modeDescription = inspectorModeDescriptions[inspectorModeNormalized] || '';

            var modeSwitcher = el(
                'div',
                { className: 'my-articles-inspector-panel__mode' },
                el(
                    'div',
                    { className: 'my-articles-inspector-panel__mode-header' },
                    el('span', { className: 'my-articles-inspector-panel__mode-title' }, __('Mode d’édition', 'mon-articles')),
                    modeButtons
                ),
                modeDescription
                    ? el('p', { className: 'my-articles-inspector-panel__mode-description' }, modeDescription)
                    : null
            );

            var inspectorBody = visibleSections.length
                ? visibleSections.map(function (section) {
                      return el(
                          'div',
                          {
                              key: section.id,
                              className: 'my-articles-inspector-panel__section' + (shouldForceInspectorOpen ? ' is-search-mode' : ''),
                              'data-section': section.id,
                          },
                          section.component
                      );
                  })
                : [
                      el(
                          Notice,
                          { key: 'empty-search', status: 'info', isDismissible: false },
                          __('Aucun réglage ne correspond à votre recherche.', 'mon-articles')
                      ),
                  ];

            var inspectorControls = el(
                InspectorControls,
                {},
                el(
                    Panel,
                    { className: 'my-articles-inspector-panel' },
                    el('div', { className: 'my-articles-inspector-panel__search' }, searchField),
                    modeSwitcher,
                    inspectorBody
                )
            );

            var previewContent = null;
            var placeholderChildren = [];

            if (!attributes.instanceId) {
                placeholderChildren.push(
                    el('p', { key: 'intro' }, __('Sélectionnez un module dans la barre latérale.', 'mon-articles'))
                );

                if (!attributes.onboarding_complete) {
                    placeholderChildren.push(
                        el(
                            'p',
                            { key: 'onboarding-hint' },
                            __('Besoin d’aide ? Lancez la visite guidée depuis la section « Module ».', 'mon-articles')
                        )
                    );
                }
            } else if (!instances.length && listData && listData.isResolving) {
                placeholderChildren.push(
                    el('p', { key: 'loading' }, __('Chargement des modules…', 'mon-articles'))
                );
            }

            if (!attributes.instanceId || !selectedData || !selectedData.selectedInstance) {
                if (placeholderChildren.length === 0) {
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
            } else if (PreviewCanvas) {
                var title = selectedData.selectedInstance.title && selectedData.selectedInstance.title.rendered
                    ? selectedData.selectedInstance.title.rendered
                    : __('(Sans titre)', 'mon-articles');
                previewContent = el(
                    'div',
                    { className: 'my-articles-block-preview' },
                    el('p', { className: 'my-articles-block-preview__title' }, title),
                    el(PreviewCanvas, {
                        key: 'preview-canvas',
                        className: 'my-articles-block-preview__pane',
                        instanceId: attributes.instanceId,
                        attributes: attributes,
                        displayMode: displayMode,
                        viewport: effectivePreviewViewport,
                        onViewportChange: function (nextViewport) {
                            setPreviewViewport(nextViewport);
                        },
                        pilotMode: isPilotMode,
                    })
                );
            } else {
                previewContent = el(
                    Notice,
                    { status: 'warning', isDismissible: false },
                    __('Le composant de prévisualisation est indisponible sur ce site.', 'mon-articles')
                );
            }

            return el(
                Fragment,
                null,
                toolbarControls,
                inspectorControls,
                el('div', blockProps, previewContent)
            );
        },
        save: function () {
            return null;
        },
    });
})(window.wp);
