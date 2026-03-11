/**
 * Royal Links Classic Editor JavaScript
 */

(function($) {
    'use strict';

    var RoyalLinksClassicEditor = {
        modal: null,
        selectedText: '',
        editor: null,
        searchTimeout: null,

        init: function() {
            this.modal = $('#royal-links-modal');
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Close modal events
            this.modal.on('click', '.royal-links-modal-close, .royal-links-modal-cancel, .royal-links-modal-overlay', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Search input
            this.modal.on('input', '#royal-links-search-input', function() {
                self.handleSearch($(this).val());
            });

            // Search result click
            this.modal.on('click', '.royal-links-search-result', function() {
                var linkData = $(this).data('link');
                self.insertLink(linkData);
            });

            // Create and insert button
            this.modal.on('click', '#royal-links-create-insert', function() {
                self.createAndInsert();
            });

            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.modal.is(':visible')) {
                    self.closeModal();
                }
            });
        },

        openModal: function(editor) {
            this.editor = editor;

            // Get selected text
            if (editor) {
                this.selectedText = editor.selection.getContent({ format: 'text' });
            }

            // Reset form
            this.modal.find('#royal-links-search-input').val('');
            this.modal.find('#royal-links-search-results').empty().removeClass('has-results');
            this.modal.find('#royal-links-new-title').val(this.selectedText);
            this.modal.find('#royal-links-new-url').val('');
            this.modal.find('#royal-links-new-slug').val('');

            this.modal.show();
            this.modal.find('#royal-links-search-input').focus();
        },

        closeModal: function() {
            this.modal.hide();
            this.selectedText = '';
            this.editor = null;
        },

        handleSearch: function(query) {
            var self = this;
            var $results = this.modal.find('#royal-links-search-results');

            clearTimeout(this.searchTimeout);

            if (query.length < 2) {
                $results.empty().removeClass('has-results');
                return;
            }

            $results.html('<div class="royal-links-loading">' + royalLinksClassic.i18n.loading + '</div>').addClass('has-results');

            this.searchTimeout = setTimeout(function() {
                self.searchLinks(query);
            }, 300);
        },

        searchLinks: function(query) {
            var self = this;
            var $results = this.modal.find('#royal-links-search-results');

            $.ajax({
                url: royalLinksClassic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'royal_links_search',
                    nonce: royalLinksClassic.searchNonce,
                    search: query
                },
                success: function(response) {
                    if (response.success && response.data.links.length > 0) {
                        self.renderSearchResults(response.data.links);
                    } else {
                        $results.html('<div class="royal-links-no-results">' + royalLinksClassic.i18n.noResults + '</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="royal-links-no-results">Error searching links</div>');
                }
            });
        },

        renderSearchResults: function(links) {
            var $results = this.modal.find('#royal-links-search-results');
            var html = '';

            links.forEach(function(link) {
                html += '<div class="royal-links-search-result" data-link=\'' + JSON.stringify(link) + '\'>';
                html += '<div class="link-title">' + self.escapeHtml(link.title) + '</div>';
                html += '<div class="link-url">' + self.escapeHtml(link.short_url) + '</div>';
                html += '</div>';
            });

            $results.html(html).addClass('has-results');
        },

        insertLink: function(linkData) {
            if (!this.editor) {
                this.closeModal();
                return;
            }

            var linkText = this.selectedText || linkData.title;
            var rel = [];

            if (linkData.nofollow) {
                rel.push('nofollow');
            }
            if (linkData.sponsored) {
                rel.push('sponsored');
            }
            if (linkData.new_tab) {
                rel.push('noopener');
            }

            var attrs = 'href="' + linkData.short_url + '"';

            if (linkData.new_tab) {
                attrs += ' target="_blank"';
            }

            if (rel.length > 0) {
                attrs += ' rel="' + rel.join(' ') + '"';
            }

            var html = '<a ' + attrs + '>' + this.escapeHtml(linkText) + '</a>';

            this.editor.execCommand('mceInsertContent', false, html);
            this.closeModal();
        },

        createAndInsert: function() {
            var self = this;
            var title = this.modal.find('#royal-links-new-title').val().trim();
            var url = this.modal.find('#royal-links-new-url').val().trim();
            var slug = this.modal.find('#royal-links-new-slug').val().trim();

            if (!url) {
                alert('Please enter a destination URL');
                this.modal.find('#royal-links-new-url').focus();
                return;
            }

            var $button = this.modal.find('#royal-links-create-insert');
            $button.prop('disabled', true).text(royalLinksClassic.i18n.loading);

            $.ajax({
                url: royalLinksClassic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'royal_links_quick_add',
                    nonce: royalLinksClassic.quickAddNonce,
                    title: title,
                    url: url,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        self.insertLink({
                            title: response.data.title,
                            short_url: response.data.short_url,
                            nofollow: true,
                            sponsored: false,
                            new_tab: true
                        });
                    } else {
                        alert(response.data.message || 'Error creating link');
                    }
                },
                error: function() {
                    alert('Error creating link');
                },
                complete: function() {
                    $button.prop('disabled', false).text(royalLinksClassic.i18n.createAndInsert);
                }
            });
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Make accessible globally for TinyMCE plugin
    window.RoyalLinksClassicEditor = RoyalLinksClassicEditor;

    $(document).ready(function() {
        RoyalLinksClassicEditor.init();
    });

})(jQuery);

// Self reference for use in renderSearchResults
var self = window.RoyalLinksClassicEditor;
