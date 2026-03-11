/**
 * Royal Links TinyMCE Plugin
 */

(function() {
    'use strict';

    tinymce.PluginManager.add('royal_links', function(editor) {
        // Add button to toolbar
        editor.addButton('royal_links_button', {
            title: 'Insert Royal Link',
            icon: 'link',
            onclick: function() {
                if (typeof RoyalLinksClassicEditor !== 'undefined') {
                    RoyalLinksClassicEditor.openModal(editor);
                }
            }
        });

        // Add menu item
        editor.addMenuItem('royal_links_menu', {
            text: 'Insert Royal Link',
            icon: 'link',
            context: 'insert',
            onclick: function() {
                if (typeof RoyalLinksClassicEditor !== 'undefined') {
                    RoyalLinksClassicEditor.openModal(editor);
                }
            }
        });
    });
})();
