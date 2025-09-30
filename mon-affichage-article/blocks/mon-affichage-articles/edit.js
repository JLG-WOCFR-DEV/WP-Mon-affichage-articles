(function (wp) {
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
    var useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : function () {
        return {};
    };
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var RangeControl = wp.components.RangeControl;
    var Placeholder = wp.components.Placeholder;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var Fragment = wp.element.Fragment;
    var el = wp.element.createElement;
    var useSelect = wp.data.useSelect;
    var ServerSideRender = wp.serverSideRender;

    var MODULE_QUERY = {
        per_page: 100,
        orderby: 'title',
        order: 'asc',
        status: 'publish',
        context: 'view',
    };

    registerBlockType('mon-affichage/articles', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps({ className: 'my-articles-block' });

            var data = useSelect(function (select) {
                var core = select('core');
                var dataStore = select('core/data');

                return {
                    instances: core.getEntityRecords('postType', 'mon_affichage', MODULE_QUERY),
                    isResolving: dataStore.isResolving('core', 'getEntityRecords', ['postType', 'mon_affichage', MODULE_QUERY]),
                    selectedInstance: attributes.instanceId ? core.getEntityRecord('postType', 'mon_affichage', attributes.instanceId) : null,
                };
            }, [attributes.instanceId]);

            var instances = data && Array.isArray(data.instances) ? data.instances : [];
            var instanceOptions = instances.map(function (post) {
                var title = post && post.title && post.title.rendered ? post.title.rendered : __('(Sans titre)', 'mon-articles');
                return { label: title, value: String(post.id) };
            });
            instanceOptions.unshift({ label: __('Sélectionner un module', 'mon-articles'), value: '0' });

            var inspectorControls = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Module', 'mon-articles'), initialOpen: true },
                    el(SelectControl, {
                        label: __('Instance', 'mon-articles'),
                        value: String(attributes.instanceId || 0),
                        options: instanceOptions,
                        onChange: function (value) {
                            var parsed = parseInt(value, 10);
                            setAttributes({ instanceId: parsed > 0 ? parsed : 0 });
                        },
                        help: __('Sélectionnez le contenu « mon_affichage » à afficher.', 'mon-articles'),
                    })
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

                if (data && data.isResolving) {
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
            } else if (data && data.isResolving && !data.selectedInstance) {
                previewContent = el(Spinner, { key: 'spinner-loading' });
            } else if (!data || !data.selectedInstance) {
                previewContent = el(
                    Notice,
                    { status: 'warning', isDismissible: false },
                    __('Le module sélectionné est introuvable.', 'mon-articles')
                );
            } else if (ServerSideRender) {
                var title = data.selectedInstance.title && data.selectedInstance.title.rendered
                    ? data.selectedInstance.title.rendered
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
