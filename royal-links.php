<?php
/**
 * Plugin Name: Royal Links
 * Plugin URI: https://royalplugins.com/royal-links
 * Description: A powerful WordPress link management plugin for shortening, tracking, and organizing your links.
 * Version: 1.1.3
 * Author: Royal Plugins
 * Author URI: https://royalplugins.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: royal-links
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ROYAL_LINKS_VERSION', '1.1.3');
define('ROYAL_LINKS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ROYAL_LINKS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ROYAL_LINKS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Royal_Links Class
 */
final class Royal_Links {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-post-type.php';
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-redirect.php';
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-tracker.php';
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-analytics.php';
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-import-export.php';
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-link-checker.php';
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-ajax.php';

        // Admin classes
        if (is_admin()) {
            require_once ROYAL_LINKS_PLUGIN_DIR . 'admin/class-royal-links-admin.php';
            require_once ROYAL_LINKS_PLUGIN_DIR . 'admin/class-royal-links-meta-boxes.php';
            require_once ROYAL_LINKS_PLUGIN_DIR . 'admin/class-royal-links-settings.php';
        }

        // Editor integration
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-gutenberg.php';
        require_once ROYAL_LINKS_PLUGIN_DIR . 'includes/class-royal-links-classic-editor.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Post type must register early on init (priority 0) before other init callbacks
        add_action('init', array($this, 'register_post_type_early'), 0);
        add_action('init', array($this, 'init'));
    }

    /**
     * Register post type early (priority 0)
     */
    public function register_post_type_early() {
        Royal_Links_Post_Type::get_instance();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        // Note: Royal_Links_Post_Type is initialized earlier at priority 0
        Royal_Links_Redirect::get_instance();
        Royal_Links_Tracker::get_instance();
        Royal_Links_Analytics::get_instance();
        Royal_Links_Import_Export::get_instance();
        Royal_Links_Link_Checker::get_instance();
        Royal_Links_Ajax::get_instance();
        Royal_Links_Gutenberg::get_instance();
        Royal_Links_Classic_Editor::get_instance();

        if (is_admin()) {
            Royal_Links_Admin::get_instance();
            Royal_Links_Meta_Boxes::get_instance();
            Royal_Links_Settings::get_instance();
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Register post type and flush rewrite rules
        Royal_Links_Post_Type::get_instance()->register_post_type();
        Royal_Links_Post_Type::get_instance()->register_taxonomy();

        // Add redirect rewrite rules before flushing
        Royal_Links_Redirect::get_instance()->add_rewrite_rules();

        flush_rewrite_rules();

        // Set default options
        $this->set_default_options();

        // Schedule cron events
        if (!wp_next_scheduled('royal_links_check_broken_links')) {
            wp_schedule_event(time(), 'daily', 'royal_links_check_broken_links');
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('royal_links_check_broken_links');
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Click tracking table
        $table_name = $wpdb->prefix . 'royal_links_clicks';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            link_id bigint(20) unsigned NOT NULL,
            click_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referer text DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            browser_version varchar(50) DEFAULT NULL,
            os varchar(100) DEFAULT NULL,
            os_version varchar(50) DEFAULT NULL,
            device_type varchar(50) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            is_unique tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY click_date (click_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Link check results table
        $table_name = $wpdb->prefix . 'royal_links_health';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            link_id bigint(20) unsigned NOT NULL,
            check_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status_code int(11) DEFAULT NULL,
            response_time float DEFAULT NULL,
            is_broken tinyint(1) DEFAULT 0,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY check_date (check_date)
        ) $charset_collate;";

        dbDelta($sql);

        update_option('royal_links_db_version', ROYAL_LINKS_VERSION);
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'default_redirect_type' => '301',
            'link_prefix' => 'go',
            'track_clicks' => true,
            'track_ip' => false,
            'enable_nofollow' => true,
            'enable_sponsored' => false,
            'open_new_tab' => true,
            'enable_link_checker' => true,
            'check_frequency' => 'daily',
            'uninstall_delete_data' => false,
        );

        foreach ($defaults as $key => $value) {
            if (get_option('royal_links_' . $key) === false) {
                update_option('royal_links_' . $key, $value);
            }
        }
    }
}

/**
 * Initialize the plugin
 */
function royal_links() {
    return Royal_Links::get_instance();
}

// Start the plugin
royal_links();
