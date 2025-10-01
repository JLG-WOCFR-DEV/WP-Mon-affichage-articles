(function (wp) {
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
    var useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : function () {
        return {};
    };
    var PanelBody = wp.components.PanelBody;
    var ComboboxControl = wp.components.ComboboxControl;
    var Button = wp.components.Button;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var RangeControl = wp.components.RangeControl;
    var TextControl = wp.components.TextControl;
    var Placeholder = wp.components.Placeholder;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var Fragment = wp.element.Fragment;
    var el = wp.element.createElement;
    var useSelect = wp.data.useSelect;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var ServerSideRender = wp.serverSideRender;

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
                        onChange: function (value) {
                            setAttributes({ display_mode: value });
                        },
                    }),
                    el(RangeControl, {
                        label: __('Articles par page', 'mon-articles'),
                        value: attributes.posts_per_page,
                        min: 0,
                        max: 24,
                        allowReset: true,
                        onChange: function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ posts_per_page: value });
                        },
                        help: __('Définissez 0 pour désactiver la limite.', 'mon-articles'),
                    }),
                    el(SelectControl, {
                        label: __('Pagination', 'mon-articles'),
                        value: attributes.pagination_mode || 'none',
                        options: [
                            { label: __('Aucune', 'mon-articles'), value: 'none' },
                            { label: __('Bouton « Charger plus »', 'mon-articles'), value: 'load_more' },
                            { label: __('Pagination numérotée', 'mon-articles'), value: 'numbered' },
                        ],
                        onChange: function (value) {
                            setAttributes({ pagination_mode: value });
                        },
                    })
                ),
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
                        onChange: function (value) {
                            setAttributes({ show_category_filter: !!value });
                        },
                    }),
                    el(SelectControl, {
                        label: __('Alignement du filtre', 'mon-articles'),
                        value: attributes.filter_alignment || 'right',
                        options: [
                            { label: __('Gauche', 'mon-articles'), value: 'left' },
                            { label: __('Centre', 'mon-articles'), value: 'center' },
                            { label: __('Droite', 'mon-articles'), value: 'right' },
                        ],
                        onChange: function (value) {
                            setAttributes({ filter_alignment: value });
                        },
                        disabled: !attributes.show_category_filter,
                    }),
                    el(ToggleControl, {
                        label: __('Afficher la catégorie', 'mon-articles'),
                        checked: !!attributes.show_category,
                        onChange: function (value) {
                            setAttributes({ show_category: !!value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Afficher l’auteur', 'mon-articles'),
                        checked: !!attributes.show_author,
                        onChange: function (value) {
                            setAttributes({ show_author: !!value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Afficher la date', 'mon-articles'),
                        checked: !!attributes.show_date,
                        onChange: function (value) {
                            setAttributes({ show_date: !!value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Afficher l’extrait', 'mon-articles'),
                        checked: !!attributes.show_excerpt,
                        onChange: function (value) {
                            setAttributes({ show_excerpt: !!value });
                        },
                    }),
                    el(RangeControl, {
                        label: __('Longueur de l’extrait', 'mon-articles'),
                        value: attributes.excerpt_length,
                        min: 0,
                        max: 100,
                        onChange: function (value) {
                            if (typeof value !== 'number') {
                                value = 0;
                            }
                            setAttributes({ excerpt_length: value });
                        },
                        disabled: !attributes.show_excerpt,
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
                    el(ServerSideRender, { block: 'mon-affichage/articles', attributes: attributes })
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
