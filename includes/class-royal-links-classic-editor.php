<?php
/**
 * Royal Links Classic Editor Integration
 *
 * Adds TinyMCE button for inserting links in classic editor.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Classic_Editor {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'add_link_modal'));
    }

    /**
     * Initialize TinyMCE integration
     */
    public function init() {
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            return;
        }

        if (get_user_option('rich_editing') !== 'true') {
            return;
        }

        add_filter('mce_external_plugins', array($this, 'add_tinymce_plugin'));
        add_filter('mce_buttons', array($this, 'register_tinymce_button'));
    }

    /**
     * Add TinyMCE plugin
     */
    public function add_tinymce_plugin($plugins) {
        $plugins['royal_links'] = ROYAL_LINKS_PLUGIN_URL . 'admin/js/tinymce-plugin.js';
        return $plugins;
    }

    /**
     * Register TinyMCE button
     */
    public function register_tinymce_button($buttons) {
        array_push($buttons, 'royal_links_button');
        return $buttons;
    }

    /**
     * Enqueue scripts for classic editor
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        wp_enqueue_style(
            'royal-links-classic-editor',
            ROYAL_LINKS_PLUGIN_URL . 'admin/css/classic-editor.css',
            array(),
            ROYAL_LINKS_VERSION
        );

        wp_enqueue_script(
            'royal-links-classic-editor',
            ROYAL_LINKS_PLUGIN_URL . 'admin/js/classic-editor.js',
            array('jquery'),
            ROYAL_LINKS_VERSION,
            true
        );

        wp_localize_script('royal-links-classic-editor', 'royalLinksClassic', array(
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'searchNonce'   => wp_create_nonce('royal_links_search'),
            'quickAddNonce' => wp_create_nonce('royal_links_quick_add'),
            'i18n'          => array(
                'modalTitle'      => __('Insert Royal Link', 'royal-links'),
                'searchLabel'     => __('Search Links', 'royal-links'),
                'searchPlaceholder' => __('Search for a link...', 'royal-links'),
                'orCreateNew'     => __('Or create a new link:', 'royal-links'),
                'titleLabel'      => __('Link Title', 'royal-links'),
                'urlLabel'        => __('Destination URL', 'royal-links'),
                'slugLabel'       => __('Custom Slug (optional)', 'royal-links'),
                'insertLink'      => __('Insert Link', 'royal-links'),
                'createAndInsert' => __('Create & Insert', 'royal-links'),
                'cancel'          => __('Cancel', 'royal-links'),
                'noResults'       => __('No links found.', 'royal-links'),
                'loading'         => __('Loading...', 'royal-links'),
            ),
        ));
    }

    /**
     * Add link modal HTML
     */
    public function add_link_modal() {
        $screen = get_current_screen();

        if (!$screen || !in_array($screen->base, array('post', 'page'))) {
            return;
        }

        ?>
        <div id="royal-links-modal" class="royal-links-modal" style="display: none;">
            <div class="royal-links-modal-overlay"></div>
            <div class="royal-links-modal-container">
                <div class="royal-links-modal-header">
                    <h2><?php esc_html_e('Insert Royal Link', 'royal-links'); ?></h2>
                    <button type="button" class="royal-links-modal-close">&times;</button>
                </div>
                <div class="royal-links-modal-body">
                    <div class="royal-links-search-section">
                        <label for="royal-links-search-input"><?php esc_html_e('Search Links', 'royal-links'); ?></label>
                        <input type="text" id="royal-links-search-input" placeholder="<?php esc_attr_e('Search for a link...', 'royal-links'); ?>">
                        <div id="royal-links-search-results" class="royal-links-search-results"></div>
                    </div>

                    <div class="royal-links-divider">
                        <span><?php esc_html_e('Or create a new link', 'royal-links'); ?></span>
                    </div>

                    <div class="royal-links-create-section">
                        <div class="royal-links-field">
                            <label for="royal-links-new-title"><?php esc_html_e('Link Title', 'royal-links'); ?></label>
                            <input type="text" id="royal-links-new-title" placeholder="<?php esc_attr_e('My Awesome Link', 'royal-links'); ?>">
                        </div>
                        <div class="royal-links-field">
                            <label for="royal-links-new-url"><?php esc_html_e('Destination URL', 'royal-links'); ?> <span class="required">*</span></label>
                            <input type="url" id="royal-links-new-url" placeholder="https://example.com" required>
                        </div>
                        <div class="royal-links-field">
                            <label for="royal-links-new-slug"><?php esc_html_e('Custom Slug', 'royal-links'); ?> <span class="optional">(<?php esc_html_e('optional', 'royal-links'); ?>)</span></label>
                            <input type="text" id="royal-links-new-slug" placeholder="my-link">
                        </div>
                    </div>
                </div>
                <div class="royal-links-modal-footer">
                    <button type="button" class="button royal-links-modal-cancel"><?php esc_html_e('Cancel', 'royal-links'); ?></button>
                    <button type="button" class="button button-primary" id="royal-links-create-insert"><?php esc_html_e('Create & Insert', 'royal-links'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}
