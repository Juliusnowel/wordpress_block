(function(blocks, element, editor, components, i18n) {
    const { registerBlockType } = blocks;
    const { createElement: el, Fragment } = element;
    const { InspectorControls, useBlockProps } = editor;
    const { PanelBody, TextControl, TextareaControl, Button, SelectControl } = components;
    const { __ } = i18n;

    registerBlockType('twentytwentyfive-child/vite-faq', {
        title: __('FAQ Block'),
        icon: 'editor-help',
        category: 'widgets',
        description: __('A block for displaying frequently asked questions with collapsible answers and structured data support.'),
        keywords: ['faq', 'questions', 'answers', 'accordion', 'schema'],

        attributes: {
            faqs: {
                type: 'array',
                default: [{
                    id: 1,
                    question: 'What is this FAQ about?',
                    answer: 'This is a sample FAQ item. You can edit this in the sidebar to customize your questions and answers.'
                }]
            },
            titleTag: {
                type: 'string',
                default: 'h2'
            },
            questionTag: {
                type: 'string',
                default: 'h3'
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { faqs, titleTag, questionTag } = attributes;

            const blockProps = useBlockProps({
                className: 'faq-block-editor-preview',
            });

            const headerOptions = [
                { label: __('H1'), value: 'h1' },
                { label: __('H2'), value: 'h2' },
                { label: __('H3'), value: 'h3' },
                { label: __('H4'), value: 'h4' },
                { label: __('H5'), value: 'h5' },
                { label: __('H6'), value: 'h6' },
                { label: __('Div'), value: 'div' },
                { label: __('Span'), value: 'span' }
            ];

            function updateFAQ(index, field, value) {
                const newFAQs = [...faqs];
                newFAQs[index] = { ...newFAQs[index], [field]: value };
                setAttributes({ faqs: newFAQs });
            }

            function addFAQ() {
                const newId = Math.max(...faqs.map(faq => faq.id)) + 1;
                const newFAQs = [...faqs, {
                    id: newId,
                    question: 'New question?',
                    answer: 'New answer here.'
                }];
                setAttributes({ faqs: newFAQs });
            }

            function removeFAQ(index) {
                if (faqs.length > 1) {
                    const newFAQs = faqs.filter((_, i) => i !== index);
                    setAttributes({ faqs: newFAQs });
                }
            }

            return el(Fragment, {},
                el(InspectorControls, {},
                    // Header Settings Panel
                    el(PanelBody, { title: __('Header Settings'), initialOpen: false },
                        el(SelectControl, {
                            label: __('FAQ Title Tag'),
                            value: titleTag,
                            options: headerOptions,
                            onChange: (value) => setAttributes({ titleTag: value }),
                            help: __('Choose the HTML tag for the main FAQ title')
                        }),
                        el(SelectControl, {
                            label: __('Question Tag'),
                            value: questionTag,
                            options: headerOptions,
                            onChange: (value) => setAttributes({ questionTag: value }),
                            help: __('Choose the HTML tag for individual FAQ questions')
                        })
                    ),

                    // FAQ Content Panel
                    el(PanelBody, { title: __('FAQ Content'), initialOpen: true },
                        faqs.map((faq, index) =>
                            el('div', { 
                                key: faq.id, 
                                style: { 
                                    marginBottom: '20px', 
                                    padding: '15px', 
                                    border: '1px solid #ddd', 
                                    borderRadius: '4px' 
                                } 
                            },
                                el('h4', { style: { margin: '0 0 10px 0' } }, __('FAQ Item ') + (index + 1)),

                                el(TextControl, {
                                    label: __('Question'),
                                    value: faq.question,
                                    onChange: (value) => updateFAQ(index, 'question', value)
                                }),

                                el(TextareaControl, {
                                    label: __('Answer'),
                                    value: faq.answer,
                                    onChange: (value) => updateFAQ(index, 'answer', value),
                                    rows: 4
                                }),

                                faqs.length > 1 && el(Button, {
                                    onClick: () => removeFAQ(index),
                                    isDestructive: true,
                                    isSmall: true,
                                    style: { marginTop: '10px' }
                                }, __('Remove FAQ'))
                            )
                        ),

                        el(Button, {
                            onClick: addFAQ,
                            isPrimary: true,
                            style: { marginTop: '15px' }
                        }, __('Add FAQ'))
                    )
                ),

                // Editor Preview
                el('div', blockProps,
                    el('div', { className: 'faq-container' },
                        el(titleTag, { className: 'faq-title' }, __('FAQs')),
                        faqs.map((faq, index) =>
                            el('div', { 
                                key: faq.id, 
                                className: 'faq-item',
                                style: { borderTop: index === 0 ? 'none' : '1px solid #8FA3FF' }
                            },
                                el(questionTag, { 
                                    className: 'faq-question',
                                    style: {
                                        display: 'block',
                                        padding: '16px 0',
                                        fontWeight: '500',
                                        color: '#0d0d0d',
                                        position: 'relative'
                                    }
                                }, faq.question),
                                el('div', { 
                                    className: 'faq-answer',
                                    style: {
                                        maxHeight: 'none',
                                        overflow: 'visible',
                                        lineHeight: '1.6',
                                        color: '#333',
                                        paddingBottom: '1rem'
                                    }
                                }, faq.answer)
                            )
                        )
                    )
                )
            );
        },

        save: function(props) {
            const { attributes } = props;
            const { faqs, titleTag, questionTag } = attributes;

            // Generate structured data for SEO
            const structuredData = {
                "@context": "https://schema.org",
                "@type": "FAQPage",
                "mainEntity": faqs.map(faq => ({
                    "@type": "Question",
                    "name": faq.question,
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": faq.answer
                    }
                }))
            };

            return el(Fragment, {},
                el('script', {
                    type: "application/ld+json",
                    dangerouslySetInnerHTML: { __html: JSON.stringify(structuredData) }
                }),
                el('div', { className: 'faq-container' },
                    el(titleTag, { className: 'faq-title' }, __('FAQs')),
                    faqs.map((faq, index) =>
                        el('div', { key: faq.id, className: 'faq-item' },
                            el('input', { 
                                type: 'checkbox', 
                                id: `faq${faq.id}`,
                                style: { display: 'none' }
                            }),
                            el(questionTag, { 
                                htmlFor: `faq${faq.id}`, 
                                className: 'faq-question' 
                            }, faq.question),
                            el('div', { className: 'faq-answer' }, faq.answer)
                        )
                    )
                )
            );
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);