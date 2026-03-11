/**
 * Royal Links Admin JavaScript
 */

(function($) {
    'use strict';

    var RoyalLinksAdmin = {

        init: function() {
            this.bindEvents();
            this.initCopyButtons();
            this.initSlugChecker();
            this.initDismissibleNotices();
        },

        bindEvents: function() {
            // Redirect type description update
            $(document).on('change', '#royal_links_redirect_type', this.updateRedirectDescription);
        },

        /**
         * Initialize copy to clipboard buttons
         */
        initCopyButtons: function() {
            $(document).on('click', '.royal-links-copy, .royal-links-copy-btn', function(e) {
                e.preventDefault();

                var $button = $(this);
                var textToCopy = $button.data('clipboard') || $('#royal-links-short-url-input').val();

                RoyalLinksAdmin.copyToClipboard(textToCopy, $button);
            });
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text, $button) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    RoyalLinksAdmin.showCopySuccess($button);
                }).catch(function() {
                    RoyalLinksAdmin.fallbackCopy(text, $button);
                });
            } else {
                RoyalLinksAdmin.fallbackCopy(text, $button);
            }
        },

        /**
         * Fallback copy method
         */
        fallbackCopy: function(text, $button) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                RoyalLinksAdmin.showCopySuccess($button);
            } catch (err) {
                alert(royalLinksAdmin.i18n.copyFailed);
            }

            $temp.remove();
        },

        /**
         * Show copy success feedback
         */
        showCopySuccess: function($button) {
            var originalHtml = $button.html();

            $button.addClass('copied').html(royalLinksAdmin.i18n.copied);

            setTimeout(function() {
                $button.removeClass('copied').html(originalHtml);
            }, 2000);
        },

        /**
         * Initialize slug availability checker
         */
        initSlugChecker: function() {
            var $slugInput = $('#royal_links_slug');
            var $statusSpan = $('.royal-links-slug-status');
            var timeout;

            if (!$slugInput.length) {
                return;
            }

            $slugInput.on('input', function() {
                clearTimeout(timeout);

                var slug = $(this).val().trim();

                if (!slug) {
                    $statusSpan.removeClass('available taken').text('');
                    return;
                }

                $statusSpan.removeClass('available taken').text(royalLinksAdmin.i18n.checking);

                timeout = setTimeout(function() {
                    RoyalLinksAdmin.checkSlugAvailability(slug, $statusSpan);
                }, 500);
            });
        },

        /**
         * Check if slug is available
         */
        checkSlugAvailability: function(slug, $statusSpan) {
            var postId = $('#post_ID').val() || 0;

            $.ajax({
                url: royalLinksAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'royal_links_check_slug',
                    nonce: royalLinksAdmin.slugNonce,
                    slug: slug,
                    exclude_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            $statusSpan.removeClass('taken').addClass('available')
                                .text(royalLinksAdmin.i18n.slugAvailable);
                        } else {
                            $statusSpan.removeClass('available').addClass('taken')
                                .text(royalLinksAdmin.i18n.slugTaken);
                        }
                    }
                }
            });
        },

        /**
         * Update redirect type description
         */
        updateRedirectDescription: function() {
            var descriptions = {
                '301': 'Best for SEO. Tells search engines the link has permanently moved.',
                '302': 'Temporary redirect. Search engines will keep indexing the original URL.',
                '307': 'Similar to 302 but preserves the request method (POST data).'
            };

            var type = $(this).val();
            $('.redirect-description').text(descriptions[type] || '');
        },

        /**
         * Initialize dismissible notices
         */
        initDismissibleNotices: function() {
            $(document).on('click', '.royal-links-dismissible-notice .notice-dismiss', function() {
                var $notice = $(this).closest('.royal-links-dismissible-notice');
                var noticeType = $notice.data('notice');

                if (noticeType) {
                    $.ajax({
                        url: royalLinksAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'royal_links_dismiss_notice',
                            nonce: royalLinksAdmin.nonce,
                            notice: noticeType
                        }
                    });
                }
            });
        }
    };

    $(document).ready(function() {
        RoyalLinksAdmin.init();
    });

})(jQuery);
