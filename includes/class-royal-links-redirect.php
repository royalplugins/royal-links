<?php
/**
 * Royal Links Redirect Handler
 *
 * Handles URL redirects for short links.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Redirect {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'), 1);
        add_action('template_redirect', array($this, 'handle_redirect'), 1);
        add_filter('query_vars', array($this, 'add_query_vars'));
    }

    /**
     * Add rewrite rules for short links
     */
    public function add_rewrite_rules() {
        $prefix = get_option('royal_links_link_prefix', 'go');
        $prefix = sanitize_title($prefix);

        add_rewrite_rule(
            '^' . preg_quote($prefix, '/') . '/([^/]+)/?$',
            'index.php?royal_links_redirect=$matches[1]',
            'top'
        );

        // Check if we need to flush rewrite rules
        if (get_option('royal_links_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('royal_links_flush_rewrite_rules');
        }
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'royal_links_redirect';
        return $vars;
    }

    /**
     * Handle the redirect
     */
    public function handle_redirect() {
        $slug = get_query_var('royal_links_redirect');

        if (empty($slug)) {
            return;
        }

        $slug = sanitize_title($slug);
        $link = Royal_Links_Post_Type::get_link_by_slug($slug);

        if (!$link) {
            // Link not found - return 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // Get destination URL
        $destination_url = get_post_meta($link->ID, '_royal_links_destination_url', true);

        if (empty($destination_url)) {
            return;
        }

        // Track the click
        if (get_option('royal_links_track_clicks', true)) {
            Royal_Links_Tracker::get_instance()->track_click($link->ID);
        }

        // Get redirect type
        $redirect_type = get_post_meta($link->ID, '_royal_links_redirect_type', true);
        $redirect_type = in_array($redirect_type, array('301', '302', '307')) ? intval($redirect_type) : 301;

        // Perform redirect
        $this->redirect($destination_url, $redirect_type);
    }

    /**
     * Perform the actual redirect
     */
    private function redirect($url, $status = 301) {
        // Validate URL
        $url = esc_url_raw($url);

        if (empty($url)) {
            return;
        }

        // Prevent caching for tracking accuracy
        nocache_headers();

        // Set appropriate headers based on redirect type
        switch ($status) {
            case 301:
                header('HTTP/1.1 301 Moved Permanently');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                break;
            case 302:
                header('HTTP/1.1 302 Found');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                break;
            case 307:
                header('HTTP/1.1 307 Temporary Redirect');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                break;
        }

        header('Location: ' . $url, true, $status);
        exit;
    }

    /**
     * Get supported redirect types
     */
    public static function get_redirect_types() {
        return array(
            '301' => array(
                'label'       => __('301 Permanent Redirect', 'royal-links'),
                'description' => __('Best for SEO. Tells search engines the link has permanently moved.', 'royal-links'),
            ),
            '302' => array(
                'label'       => __('302 Temporary Redirect', 'royal-links'),
                'description' => __('Temporary redirect. Search engines will keep indexing the original URL.', 'royal-links'),
            ),
            '307' => array(
                'label'       => __('307 Temporary Redirect', 'royal-links'),
                'description' => __('Similar to 302 but preserves the request method (POST data).', 'royal-links'),
            ),
        );
    }
}
