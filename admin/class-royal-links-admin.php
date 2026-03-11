<?php
/**
 * Royal Links Admin
 *
 * Main admin class for Royal Links.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        require_once ROYAL_LINKS_PLUGIN_DIR . 'admin/class-dashboard-widget.php';

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_dashboard_setup', array('Royal_Links_Dashboard_Widget', 'register'));
        add_filter('plugin_action_links_' . ROYAL_LINKS_PLUGIN_BASENAME, array($this, 'add_plugin_links'));
        add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_ajax_royal_links_dismiss_notice', array($this, 'ajax_dismiss_notice'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        // Only load on Royal Links pages
        $screen = get_current_screen();

        if (!$screen) {
            return;
        }

        $royal_links_pages = array(
            'royal_link',
            'edit-royal_link',
            'royal_link_page_royal-links-analytics',
            'royal_link_page_royal-links-settings',
            'royal_link_page_royal-links-import-export',
            'royal_link_page_royal-links-health',
            'royal_link_page_royal-links-upgrade',
        );

        // Load CSS on dashboard for dashboard widget.
        if ( $screen->id === 'dashboard' ) {
            wp_enqueue_style(
                'royal-links-admin',
                ROYAL_LINKS_PLUGIN_URL . 'admin/css/admin.css',
                array(),
                ROYAL_LINKS_VERSION
            );
            return;
        }

        if (!in_array($screen->id, $royal_links_pages) && $screen->post_type !== 'royal_link') {
            return;
        }

        // Main admin CSS
        wp_enqueue_style(
            'royal-links-admin',
            ROYAL_LINKS_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            ROYAL_LINKS_VERSION
        );

        // Main admin JS
        wp_enqueue_script(
            'royal-links-admin',
            ROYAL_LINKS_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            ROYAL_LINKS_VERSION,
            true
        );

        wp_localize_script('royal-links-admin', 'royalLinksAdmin', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('royal_links_admin'),
            'slugNonce'   => wp_create_nonce('royal_links_check_slug'),
            'i18n'        => array(
                'copied'       => __('Copied!', 'royal-links'),
                'copyFailed'   => __('Failed to copy', 'royal-links'),
                'slugAvailable' => __('Slug is available', 'royal-links'),
                'slugTaken'    => __('Slug is already in use', 'royal-links'),
                'checking'     => __('Checking...', 'royal-links'),
            ),
        ));

        // Chart.js for analytics (bundled locally for WP.org compliance)
        if ($screen->id === 'royal_link_page_royal-links-analytics') {
            wp_enqueue_script(
                'chartjs',
                ROYAL_LINKS_PLUGIN_URL . 'admin/js/chart.min.js',
                array(),
                '4.5.1',
                true
            );
        }
    }

    /**
     * Add plugin action links
     */
    public function add_plugin_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('edit.php?post_type=royal_link&page=royal-links-settings') . '">' . __('Settings', 'royal-links') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }

    /**
     * Add plugin row meta links
     */
    public function add_plugin_row_meta($links, $file) {
        if (ROYAL_LINKS_PLUGIN_BASENAME === $file) {
            // Remove "Visit plugin site" link (auto-generated from Plugin URI)
            foreach ($links as $key => $link) {
                if (strpos($link, 'royalplugins.com/royal-links') !== false) {
                    unset($links[$key]);
                }
            }

            // Add View details link using WordPress standard thickbox modal
            $links[] = sprintf(
                '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
                esc_url(self_admin_url('plugin-install.php?tab=plugin-information&plugin=royal-links&TB_iframe=true&width=600&height=550')),
                /* translators: %s: Plugin name */
                esc_attr(sprintf(__('More information about %s', 'royal-links'), 'Royal Links')),
                __('View details', 'royal-links')
            );

            // Add Docs link
            $links[] = '<a href="https://royalplugins.com/support/royal-links-lite/" target="_blank">' . __('Docs', 'royal-links') . '</a>';
        }
        return $links;
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== 'royal_link') {
            return;
        }

        // Check for broken links (dismissible for 24 hours)
        $broken_count = Royal_Links_Link_Checker::get_broken_count();
        $dismissed_until = get_option('royal_links_notice_broken_dismissed', 0);

        if ($broken_count > 0 && time() > $dismissed_until) {
            ?>
            <div class="notice notice-warning is-dismissible royal-links-dismissible-notice" data-notice="broken_links">
                <p>
                    <?php
                    printf(
                        /* translators: %1$d: number of broken links, %2$s: link to health page */
                        esc_html__('Royal Links: %1$d broken link(s) detected. %2$s', 'royal-links'),
                        intval($broken_count),
                        '<a href="' . esc_url(admin_url('edit.php?post_type=royal_link&page=royal-links-health')) . '">' . esc_html__('View Details', 'royal-links') . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler to dismiss notices
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer('royal_links_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $notice = isset($_POST['notice']) ? sanitize_key(wp_unslash($_POST['notice'])) : '';

        if ($notice === 'broken_links') {
            // Dismiss for 24 hours
            update_option('royal_links_notice_broken_dismissed', time() + DAY_IN_SECONDS);
            wp_send_json_success();
        }

        wp_send_json_error('Invalid notice');
    }
}
