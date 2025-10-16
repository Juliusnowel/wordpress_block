(function (blocks, i18n, element, blockEditor) {
    const { __ } = i18n;
    const { createElement: el } = element;
    const { RichText, useBlockProps } = blockEditor;

    blocks.registerBlockType('viteseo-ttf-child/definition-box', {
        apiVersion: 3,
        title: __('Definition Box', 'viteseo-ttf-child'),
        description: __('A clean callout box for definitions or short explanations.', 'viteseo-ttf-child'),
        icon: 'editor-alignleft',
        category: 'text',

        edit: function (props) {
            const { attributes: { content }, setAttributes } = props;
            const blockProps = useBlockProps({ className: 'definition-box' });

            return el(
                'div',
                blockProps,
                el(RichText, {
                    tagName: 'p',
                    value: content,
                    onChange: (value) => setAttributes({ content: value }),
                    placeholder: __('Type the definition…', 'viteseo-ttf-child'),
                    allowedFormats: ['core/bold', 'core/italic', 'core/link']
                })
            );
        },

        save: function (props) {
            const { attributes: { content } } = props;
            const blockProps = blockEditor.useBlockProps.save({ className: 'definition-box' });

            return el(
                'div',
                blockProps,
                el(RichText.Content, {
                    tagName: 'p',
                    value: content
                })
            );
        }
    });
})(window.wp.blocks, window.wp.i18n, window.wp.element, window.wp.blockEditor);
