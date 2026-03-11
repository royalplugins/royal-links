<?php
/**
 * Royal Links Post Type
 *
 * Handles the custom post type for links and taxonomy for categories.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Post_Type {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_filter('post_type_link', array($this, 'custom_link_permalink'), 10, 2);
        add_filter('manage_royal_link_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_royal_link_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-royal_link_sortable_columns', array($this, 'sortable_columns'));
    }

    /**
     * Register custom post type for links
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Links', 'Post type general name', 'royal-links'),
            'singular_name'         => _x('Link', 'Post type singular name', 'royal-links'),
            'menu_name'             => _x('Royal Links', 'Admin Menu text', 'royal-links'),
            'add_new'               => __('Add New', 'royal-links'),
            'add_new_item'          => __('Add New Link', 'royal-links'),
            'edit_item'             => __('Edit Link', 'royal-links'),
            'new_item'              => __('New Link', 'royal-links'),
            'view_item'             => __('View Link', 'royal-links'),
            'search_items'          => __('Search Links', 'royal-links'),
            'not_found'             => __('No links found', 'royal-links'),
            'not_found_in_trash'    => __('No links found in Trash', 'royal-links'),
            'all_items'             => __('All Links', 'royal-links'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 30,
            'menu_icon'           => 'dashicons-admin-links',
            'supports'            => array('title'),
            'show_in_rest'        => true,
        );

        register_post_type('royal_link', $args);
    }

    /**
     * Register taxonomy for link categories
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => _x('Link Categories', 'taxonomy general name', 'royal-links'),
            'singular_name'     => _x('Link Category', 'taxonomy singular name', 'royal-links'),
            'search_items'      => __('Search Categories', 'royal-links'),
            'all_items'         => __('All Categories', 'royal-links'),
            'parent_item'       => __('Parent Category', 'royal-links'),
            'parent_item_colon' => __('Parent Category:', 'royal-links'),
            'edit_item'         => __('Edit Category', 'royal-links'),
            'update_item'       => __('Update Category', 'royal-links'),
            'add_new_item'      => __('Add New Category', 'royal-links'),
            'new_item_name'     => __('New Category Name', 'royal-links'),
            'menu_name'         => __('Categories', 'royal-links'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
        );

        register_taxonomy('royal_link_category', array('royal_link'), $args);

        // Register link tags
        $tag_labels = array(
            'name'              => _x('Link Tags', 'taxonomy general name', 'royal-links'),
            'singular_name'     => _x('Link Tag', 'taxonomy singular name', 'royal-links'),
            'search_items'      => __('Search Tags', 'royal-links'),
            'all_items'         => __('All Tags', 'royal-links'),
            'edit_item'         => __('Edit Tag', 'royal-links'),
            'update_item'       => __('Update Tag', 'royal-links'),
            'add_new_item'      => __('Add New Tag', 'royal-links'),
            'new_item_name'     => __('New Tag Name', 'royal-links'),
            'menu_name'         => __('Tags', 'royal-links'),
        );

        $tag_args = array(
            'hierarchical'      => false,
            'labels'            => $tag_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
        );

        register_taxonomy('royal_link_tag', array('royal_link'), $tag_args);
    }

    /**
     * Custom permalink structure
     */
    public function custom_link_permalink($permalink, $post) {
        if ($post->post_type !== 'royal_link') {
            return $permalink;
        }

        $prefix = get_option('royal_links_link_prefix', 'go');
        $slug = get_post_meta($post->ID, '_royal_links_slug', true);

        if (empty($slug)) {
            $slug = $post->post_name;
        }

        return home_url($prefix . '/' . $slug);
    }

    /**
     * Add custom columns to links list
     */
    public function add_custom_columns($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['short_link'] = __('Short Link', 'royal-links');
                $new_columns['destination'] = __('Destination URL', 'royal-links');
                $new_columns['redirect_type'] = __('Redirect', 'royal-links');
                $new_columns['clicks'] = __('Clicks', 'royal-links');
            } elseif ($key !== 'date') {
                $new_columns[$key] = $value;
            }
        }

        $new_columns['date'] = __('Date', 'royal-links');

        return $new_columns;
    }

    /**
     * Custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'short_link':
                $prefix = get_option('royal_links_link_prefix', 'go');
                $slug = get_post_meta($post_id, '_royal_links_slug', true);
                if (empty($slug)) {
                    $slug = get_post_field('post_name', $post_id);
                }
                $short_url = home_url($prefix . '/' . $slug);
                echo '<code style="font-size: 12px;">' . esc_html($short_url) . '</code>';
                echo '<button class="button button-small royal-links-copy" data-clipboard="' . esc_attr($short_url) . '" style="margin-left: 5px;">';
                echo '<span class="dashicons dashicons-clipboard" style="font-size: 14px; vertical-align: middle;"></span>';
                echo '</button>';
                break;

            case 'destination':
                $url = get_post_meta($post_id, '_royal_links_destination_url', true);
                if ($url) {
                    $display_url = strlen($url) > 50 ? substr($url, 0, 50) . '...' : $url;
                    echo '<a href="' . esc_url($url) . '" target="_blank" title="' . esc_attr($url) . '">' . esc_html($display_url) . '</a>';
                }
                break;

            case 'redirect_type':
                $type = get_post_meta($post_id, '_royal_links_redirect_type', true);
                $types = array(
                    '301' => '301 (Permanent)',
                    '302' => '302 (Temporary)',
                    '307' => '307 (Temporary)',
                );
                echo isset($types[$type]) ? esc_html($types[$type]) : esc_html($type);
                break;

            case 'clicks':
                global $wpdb;
                $table = $wpdb->prefix . 'royal_links_clicks';
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE link_id = %d",
                    $post_id
                ));
                $unique = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE link_id = %d AND is_unique = 1",
                    $post_id
                ));
                echo '<strong>' . intval($total) . '</strong> / ' . intval($unique) . ' unique';
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['clicks'] = 'clicks';
        return $columns;
    }

    /**
     * Create a new link
     */
    public static function create_link($args) {
        $defaults = array(
            'title'           => '',
            'destination_url' => '',
            'slug'            => '',
            'redirect_type'   => get_option('royal_links_default_redirect_type', '301'),
            'nofollow'        => get_option('royal_links_enable_nofollow', true),
            'sponsored'       => get_option('royal_links_enable_sponsored', false),
            'new_tab'         => get_option('royal_links_open_new_tab', true),
            'category'        => array(),
            'tags'            => array(),
        );

        $args = wp_parse_args($args, $defaults);

        // Validate destination URL
        if (empty($args['destination_url']) || !filter_var($args['destination_url'], FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Please provide a valid destination URL.', 'royal-links'));
        }

        // Generate slug if not provided
        if (empty($args['slug'])) {
            $args['slug'] = sanitize_title($args['title']);
            if (empty($args['slug'])) {
                $args['slug'] = wp_generate_password(8, false);
            }
        }

        // Check for duplicate slug
        if (self::slug_exists($args['slug'])) {
            $args['slug'] = $args['slug'] . '-' . wp_generate_password(4, false);
        }

        // Create post
        $post_id = wp_insert_post(array(
            'post_title'  => sanitize_text_field($args['title']),
            'post_name'   => sanitize_title($args['slug']),
            'post_type'   => 'royal_link',
            'post_status' => 'publish',
        ));

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save meta
        update_post_meta($post_id, '_royal_links_destination_url', esc_url_raw($args['destination_url']));
        update_post_meta($post_id, '_royal_links_slug', sanitize_title($args['slug']));
        update_post_meta($post_id, '_royal_links_redirect_type', sanitize_text_field($args['redirect_type']));
        update_post_meta($post_id, '_royal_links_nofollow', (bool) $args['nofollow']);
        update_post_meta($post_id, '_royal_links_sponsored', (bool) $args['sponsored']);
        update_post_meta($post_id, '_royal_links_new_tab', (bool) $args['new_tab']);

        // Set categories
        if (!empty($args['category'])) {
            wp_set_object_terms($post_id, $args['category'], 'royal_link_category');
        }

        // Set tags
        if (!empty($args['tags'])) {
            wp_set_object_terms($post_id, $args['tags'], 'royal_link_tag');
        }

        return $post_id;
    }

    /**
     * Check if slug exists
     */
    public static function slug_exists($slug, $exclude_id = 0) {
        global $wpdb;

        if ($exclude_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_royal_links_slug'
                AND pm.meta_value = %s
                AND p.post_type = 'royal_link'
                AND p.post_status != 'trash'
                AND pm.post_id != %d",
                $slug,
                $exclude_id
            ));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_royal_links_slug'
                AND pm.meta_value = %s
                AND p.post_type = 'royal_link'
                AND p.post_status != 'trash'",
                $slug
            ));
        }

        return $result !== null;
    }

    /**
     * Get link by slug
     */
    public static function get_link_by_slug($slug) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_royal_links_slug'
            AND pm.meta_value = %s
            AND p.post_type = 'royal_link'
            AND p.post_status = 'publish'",
            $slug
        ));

        if ($post_id) {
            return get_post($post_id);
        }

        return null;
    }

    /**
     * Get short URL for a link
     */
    public static function get_short_url($post_id) {
        $prefix = get_option('royal_links_link_prefix', 'go');
        $slug = get_post_meta($post_id, '_royal_links_slug', true);

        if (empty($slug)) {
            $slug = get_post_field('post_name', $post_id);
        }

        return home_url($prefix . '/' . $slug);
    }
}
