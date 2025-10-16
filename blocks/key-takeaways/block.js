(function (blocks, i18n, element, blockEditor, components) {
    const { __ } = i18n;
    const { createElement: el, Fragment } = element;
    const { useBlockProps, InspectorControls, RichText } = blockEditor;
    const { PanelBody, TextControl, Button } = components;

    blocks.registerBlockType('viteseo-ttf-child/key-takeaways', {
        apiVersion: 3,
        title: __('Key Takeaways', 'viteseo-ttf-child'),
        description: __('A styled list of key takeaways with checkmarks.', 'viteseo-ttf-child'),
        icon: 'list-view',
        category: 'text',

        edit: function (props) {
            const { attributes: { heading, items = [] }, setAttributes } = props;

            const setHeading = (value) => setAttributes({ heading: value });

            const updateItem = (index, value) => {
                const next = [...items];
                next[index] = value;
                setAttributes({ items: next });
            };

            const addItem = () => setAttributes({ items: [...items, '' ] });

            const removeItem = (index) => {
                const next = items.filter((_, i) => i !== index);
                setAttributes({ items: next });
            };

            const moveItem = (index, delta) => {
                const newIndex = index + delta;
                if (newIndex < 0 || newIndex >= items.length) return;
                const next = [...items];
                const temp = next[index];
                next[index] = next[newIndex];
                next[newIndex] = temp;
                setAttributes({ items: next });
            };

            const blockProps = useBlockProps({ className: 'key-takeaways-container' });

            // Canvas preview (non-editable UI; we edit in the sidebar)
            const Preview = el(
                'div',
                { className: 'key-takeaways-box' },
                el(RichText, {
                    tagName: 'h4',
                    value: heading,
                    onChange: setHeading,
                    placeholder: __('Key Takeaways', 'viteseo-ttf-child')
                }),
                el(
                    'ul',
                    null,
                    (items && items.length ? items : ['']).map((text, i) =>
                        el('li', { key: i }, text || __('(Empty item)', 'viteseo-ttf-child'))
                    )
                )
            );

            // Sidebar controls
            const Sidebar = el(
                InspectorControls,
                null,
                el(
                    PanelBody,
                    { title: __('Heading', 'viteseo-ttf-child'), initialOpen: true },
                    el(TextControl, {
                        label: __('Title', 'viteseo-ttf-child'),
                        value: heading,
                        onChange: setHeading,
                        placeholder: __('Key Takeaways', 'viteseo-ttf-child')
                    })
                ),
                el(
                    PanelBody,
                    { title: __('Takeaways', 'viteseo-ttf-child'), initialOpen: true },
                    el(
                        Fragment,
                        null,
                        (items || []).map((text, index) =>
                            el(
                                'div',
                                { key: index, className: 'kt-item-row' },
                                el(TextControl, {
                                    label: sprintf(__('Item %d', 'viteseo-ttf-child'), index + 1),
                                    value: text,
                                    onChange: (val) => updateItem(index, val),
                                    placeholder: __('Type takeaway…', 'viteseo-ttf-child')
                                }),
                                el(
                                    'div',
                                    { className: 'kt-item-actions' },
                                    el(Button, {
                                        isSmall: true,
                                        icon: 'arrow-up-alt2',
                                        label: __('Move up', 'viteseo-ttf-child'),
                                        onClick: () => moveItem(index, -1),
                                        disabled: index === 0
                                    }),
                                    el(Button, {
                                        isSmall: true,
                                        icon: 'arrow-down-alt2',
                                        label: __('Move down', 'viteseo-ttf-child'),
                                        onClick: () => moveItem(index, +1),
                                        disabled: index === items.length - 1
                                    }),
                                    el(Button, {
                                        isDestructive: true,
                                        isSmall: true,
                                        icon: 'trash',
                                        label: __('Remove', 'viteseo-ttf-child'),
                                        onClick: () => removeItem(index)
                                    })
                                )
                            )
                        ),
                        el(Button, {
                            variant: 'primary',
                            onClick: addItem
                        }, __('Add takeaway', 'viteseo-ttf-child'))
                    )
                )
            );

            return el(Fragment, null, Sidebar, el('div', blockProps, Preview));
        },

        save: function (props) {
            const { attributes: { heading, items = [] } } = props;
            const blockProps = blockEditor.useBlockProps.save({ className: 'key-takeaways-container' });

            return el(
                'div',
                blockProps,
                el(
                    'div',
                    { className: 'key-takeaways-box' },
                    el('h4', null, heading || 'Key Takeaways'),
                    el(
                        'ul',
                        null,
                        (items || []).filter((t) => (t || '').trim().length).map((t, i) => el('li', { key: i }, t))
                    )
                )
            );
        }
    });

})(window.wp.blocks, window.wp.i18n, window.wp.element, window.wp.blockEditor, window.wp.components);
