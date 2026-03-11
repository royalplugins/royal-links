<?php
/**
 * Royal Links Ajax Handler
 *
 * Handles all AJAX requests.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Ajax {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin AJAX actions
        add_action('wp_ajax_royal_links_quick_add', array($this, 'quick_add_link'));
        add_action('wp_ajax_royal_links_search', array($this, 'search_links'));
        add_action('wp_ajax_royal_links_get_stats', array($this, 'get_link_stats'));
        add_action('wp_ajax_royal_links_check_slug', array($this, 'check_slug_availability'));
        add_action('wp_ajax_royal_links_generate_slug', array($this, 'generate_slug'));
    }

    /**
     * Quick add a link via AJAX
     */
    public function quick_add_link() {
        check_ajax_referer('royal_links_quick_add', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'royal-links')));
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';

        if (empty($url)) {
            wp_send_json_error(array('message' => __('Destination URL is required.', 'royal-links')));
        }

        $result = Royal_Links_Post_Type::create_link(array(
            'title'           => $title ?: $url,
            'destination_url' => $url,
            'slug'            => $slug,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $short_url = Royal_Links_Post_Type::get_short_url($result);

        wp_send_json_success(array(
            'id'        => $result,
            'short_url' => $short_url,
            'title'     => $title ?: $url,
            'message'   => __('Link created successfully!', 'royal-links'),
        ));
    }

    /**
     * Search links
     */
    public function search_links() {
        check_ajax_referer('royal_links_search', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'royal-links')));
        }

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        $args = array(
            'post_type'      => 'royal_link',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            's'              => $search,
        );

        $query = new WP_Query($args);
        $links = array();

        foreach ($query->posts as $post) {
            $links[] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'short_url' => Royal_Links_Post_Type::get_short_url($post->ID),
                'dest_url'  => get_post_meta($post->ID, '_royal_links_destination_url', true),
                'nofollow'  => get_post_meta($post->ID, '_royal_links_nofollow', true),
                'sponsored' => get_post_meta($post->ID, '_royal_links_sponsored', true),
                'new_tab'   => get_post_meta($post->ID, '_royal_links_new_tab', true),
            );
        }

        wp_send_json_success(array('links' => $links));
    }

    /**
     * Get link statistics
     */
    public function get_link_stats() {
        check_ajax_referer('royal_links_stats', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'royal-links')));
        }

        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;
        $period = isset($_POST['period']) ? sanitize_text_field(wp_unslash($_POST['period'])) : '30days';

        if (!$link_id) {
            wp_send_json_error(array('message' => __('Invalid link ID.', 'royal-links')));
        }

        $stats = Royal_Links_Tracker::get_link_stats($link_id, $period);

        wp_send_json_success($stats);
    }

    /**
     * Check slug availability
     */
    public function check_slug_availability() {
        check_ajax_referer('royal_links_check_slug', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'royal-links')));
        }

        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $exclude_id = isset($_POST['exclude_id']) ? intval($_POST['exclude_id']) : 0;

        if (empty($slug)) {
            wp_send_json_error(array('message' => __('Slug is required.', 'royal-links')));
        }

        $exists = Royal_Links_Post_Type::slug_exists($slug, $exclude_id);

        wp_send_json_success(array(
            'available' => !$exists,
            'message'   => $exists ? __('This slug is already in use.', 'royal-links') : __('Slug is available.', 'royal-links'),
        ));
    }

    /**
     * Generate slug from title
     */
    public function generate_slug() {
        check_ajax_referer('royal_links_generate_slug', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'royal-links')));
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

        if (empty($title)) {
            // Generate random slug
            $slug = wp_generate_password(8, false, false);
        } else {
            $slug = sanitize_title($title);
        }

        // Ensure slug is unique
        $original_slug = $slug;
        $counter = 1;

        while (Royal_Links_Post_Type::slug_exists($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        wp_send_json_success(array('slug' => $slug));
    }
}
