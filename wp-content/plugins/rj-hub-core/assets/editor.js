(function (wp, config) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps } = wp.blockEditor;
    const { SelectControl, TextControl, TextareaControl } = wp.components;
    const { createElement: h, Fragment } = wp.element;
    const { useDispatch, useSelect } = wp.data;
    const { PluginDocumentSettingPanel } = wp.editor;

    function staticEditor(text) {
        return function Preview() {
            return h('p', useBlockProps({ className: 'rjh-editor-preview' }), text);
        };
    }

    function register(name, settings) {
        registerBlockType('rj-hub/' + name, {
            apiVersion: 3,
            category: 'widgets',
            save: () => null,
            ...settings,
        });
    }

    register('archive', {
        title: 'RJ Hub Collection',
        icon: 'list-view',
        attributes: { collection: { type: 'string', default: 'blog' } },
        edit: function Archive({ attributes, setAttributes }) {
            const options = Object.entries(config.collections).map(([value, label]) => ({ value, label }));
            return h('div', useBlockProps({ className: 'rjh-editor-preview' }),
                h(SelectControl, {
                    label: 'Collection to display',
                    value: attributes.collection,
                    options,
                    onChange: (collection) => setAttributes({ collection }),
                }),
                h('p', null, 'Published items, filters, and page links appear on the site. Edit individual entries from the dashboard.')
            );
        },
    });

    register('map', {
        title: 'RJ Hub Historical Locations',
        icon: 'location-alt',
        edit: staticEditor('The schematic map and searchable list display published, verified locations. Manage records under Historical Map Entries.'),
    });

    const intakeAttributes = Object.fromEntries(Object.entries(config.intake).map(([key, value]) => [key, { type: 'string', default: value }]));
    register('intake', {
        title: 'RJ Hub Intake Preview',
        icon: 'info-outline',
        attributes: intakeAttributes,
        edit: function Intake({ attributes, setAttributes }) {
            return h('div', useBlockProps({ className: 'rjh-editor-preview' }),
                h('p', null, 'Preview text. This block has no intake fields or submission.'),
                ...['learning', 'project', 'other'].map((topic, index) => h('div', { key: topic },
                    h(TextControl, {
                        label: `Topic ${index + 1} title`,
                        value: attributes[topic + 'Title'],
                        onChange: (value) => setAttributes({ [topic + 'Title']: value }),
                    }),
                    h(TextareaControl, {
                        label: `Topic ${index + 1} description`,
                        value: attributes[topic + 'Text'],
                        onChange: (value) => setAttributes({ [topic + 'Text']: value }),
                    })
                ))
            );
        },
    });

    register('byline', {
        title: 'RJ Hub Byline',
        icon: 'admin-users',
        edit: staticEditor('Article byline'),
    });

    function DocumentDetails() {
        const { postType, metadata } = useSelect((select) => {
            const editor = select('core/editor');
            return editor ? {
                postType: editor.getCurrentPostType(),
                metadata: editor.getEditedPostAttribute('meta') || {},
            } : { postType: '', metadata: {} };
        }, []);
        const { editPost } = useDispatch('core/editor');
        const fields = config.fields[postType];
        if (!fields) {
            return null;
        }

        return h(PluginDocumentSettingPanel, { name: 'rjh-details', title: 'RJ Hub details' },
            h(Fragment, null, ...Object.entries(fields).map(([key, [label, type, help]]) => {
                const control = {
                    key,
                    label,
                    help,
                    value: metadata[key] || '',
                    onChange: (value) => editPost({ meta: { ...metadata, [key]: value } }),
                };
                if (type === 'review') {
                    return h(SelectControl, {
                        ...control,
                        value: metadata[key] || 'unreviewed',
                        options: [
                            { label: 'Unreviewed', value: 'unreviewed' },
                            { label: 'Verified', value: 'verified' },
                        ],
                    });
                }
                return h(TextControl, { ...control, type: type === 'coordinate' ? 'text' : type, inputMode: type === 'coordinate' ? 'decimal' : undefined });
            }))
        );
    }

    if (PluginDocumentSettingPanel) {
        wp.plugins.registerPlugin('rjh-document-details', { render: DocumentDetails });
    }
})(window.wp, window.rjhEditor);
