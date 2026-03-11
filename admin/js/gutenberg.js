/**
 * Royal Links Gutenberg Block
 */

(function(wp) {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var Fragment = wp.element.Fragment;
    var createElement = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var Placeholder = wp.components.Placeholder;
    var __ = wp.i18n.__;

    // Register the block
    registerBlockType('royal-links/link', {
        title: __('Royal Link', 'royal-links'),
        description: __('Insert a managed link from Royal Links.', 'royal-links'),
        icon: 'admin-links',
        category: 'common',
        keywords: [__('link', 'royal-links'), __('affiliate', 'royal-links'), __('short', 'royal-links')],

        attributes: {
            linkId: {
                type: 'number',
                default: 0
            },
            displayStyle: {
                type: 'string',
                default: 'button'
            },
            buttonText: {
                type: 'string',
                default: ''
            },
            alignment: {
                type: 'string',
                default: 'left'
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var _useState = useState([]);
            var links = _useState[0];
            var setLinks = _useState[1];

            var _useState2 = useState(true);
            var loading = _useState2[0];
            var setLoading = _useState2[1];

            var _useState3 = useState('');
            var searchTerm = _useState3[0];
            var setSearchTerm = _useState3[1];

            var _useState4 = useState(null);
            var selectedLink = _useState4[0];
            var setSelectedLink = _useState4[1];

            // Load links on mount
            useEffect(function() {
                fetchLinks();
            }, []);

            // Update selected link when linkId changes
            useEffect(function() {
                if (attributes.linkId && links.length > 0) {
                    var link = links.find(function(l) {
                        return l.id === attributes.linkId;
                    });
                    setSelectedLink(link || null);
                }
            }, [attributes.linkId, links]);

            function fetchLinks(search) {
                setLoading(true);

                var url = royalLinksGutenberg.restUrl + 'links';
                if (search) {
                    url += '/search?search=' + encodeURIComponent(search);
                }

                wp.apiFetch({ path: url.replace(royalLinksGutenberg.restUrl.replace('/wp-json/', ''), '') })
                    .then(function(response) {
                        setLinks(response);
                        setLoading(false);
                    })
                    .catch(function() {
                        setLoading(false);
                    });
            }

            function handleSearch(value) {
                setSearchTerm(value);
                if (value.length >= 2) {
                    fetchLinks(value);
                } else if (value.length === 0) {
                    fetchLinks();
                }
            }

            function selectLink(link) {
                setSelectedLink(link);
                setAttributes({
                    linkId: link.id,
                    buttonText: attributes.buttonText || link.title
                });
            }

            // If no link selected, show placeholder
            if (!attributes.linkId) {
                return createElement(
                    Placeholder,
                    {
                        icon: 'admin-links',
                        label: __('Royal Link', 'royal-links'),
                        instructions: __('Search and select a link from your Royal Links.', 'royal-links')
                    },
                    createElement(
                        'div',
                        { className: 'royal-links-gutenberg-search' },
                        createElement(TextControl, {
                            placeholder: __('Search links...', 'royal-links'),
                            value: searchTerm,
                            onChange: handleSearch
                        }),
                        loading && createElement(Spinner),
                        !loading && links.length > 0 && createElement(
                            'div',
                            { className: 'royal-links-gutenberg-results' },
                            links.map(function(link) {
                                return createElement(
                                    Button,
                                    {
                                        key: link.id,
                                        className: 'royal-links-gutenberg-result',
                                        onClick: function() { selectLink(link); }
                                    },
                                    createElement('strong', null, link.title),
                                    createElement('span', null, link.shortUrl)
                                );
                            })
                        ),
                        !loading && links.length === 0 && searchTerm.length >= 2 && createElement(
                            'p',
                            null,
                            __('No links found.', 'royal-links')
                        )
                    )
                );
            }

            // Link selected - show preview
            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Link Settings', 'royal-links') },
                        createElement(SelectControl, {
                            label: __('Display Style', 'royal-links'),
                            value: attributes.displayStyle,
                            options: [
                                { label: __('Button', 'royal-links'), value: 'button' },
                                { label: __('Text Link', 'royal-links'), value: 'text' }
                            ],
                            onChange: function(value) {
                                setAttributes({ displayStyle: value });
                            }
                        }),
                        createElement(TextControl, {
                            label: __('Button/Link Text', 'royal-links'),
                            value: attributes.buttonText,
                            onChange: function(value) {
                                setAttributes({ buttonText: value });
                            }
                        }),
                        createElement(SelectControl, {
                            label: __('Alignment', 'royal-links'),
                            value: attributes.alignment,
                            options: [
                                { label: __('Left', 'royal-links'), value: 'left' },
                                { label: __('Center', 'royal-links'), value: 'center' },
                                { label: __('Right', 'royal-links'), value: 'right' }
                            ],
                            onChange: function(value) {
                                setAttributes({ alignment: value });
                            }
                        }),
                        createElement(
                            Button,
                            {
                                isSecondary: true,
                                onClick: function() {
                                    setAttributes({ linkId: 0 });
                                    setSelectedLink(null);
                                }
                            },
                            __('Change Link', 'royal-links')
                        )
                    )
                ),
                createElement(
                    'div',
                    {
                        className: 'wp-block-royal-links-link align' + attributes.alignment,
                        style: { textAlign: attributes.alignment }
                    },
                    attributes.displayStyle === 'button' ? createElement(
                        'span',
                        { className: 'royal-links-button' },
                        attributes.buttonText || (selectedLink ? selectedLink.title : __('Link', 'royal-links'))
                    ) : createElement(
                        'a',
                        { href: '#' },
                        attributes.buttonText || (selectedLink ? selectedLink.title : __('Link', 'royal-links'))
                    )
                )
            );
        },

        save: function() {
            // Dynamic block - rendered on server
            return null;
        }
    });

})(window.wp);
