<?php
/**
 * Royal Links Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package Royal_Links
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user wants to delete all data
$delete_data = get_option('royal_links_uninstall_delete_data', false);

if ($delete_data) {
    global $wpdb;

    // Delete custom post type posts and meta
    $posts = get_posts(array(
        'post_type'      => 'royal_link',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ));

    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
    }

    // Delete custom database tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}royal_links_clicks");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}royal_links_health");

    // Delete all plugin options
    $options = array(
        'royal_links_default_redirect_type',
        'royal_links_link_prefix',
        'royal_links_track_clicks',
        'royal_links_track_ip',
        'royal_links_enable_nofollow',
        'royal_links_enable_sponsored',
        'royal_links_open_new_tab',
        'royal_links_enable_link_checker',
        'royal_links_check_frequency',
        'royal_links_uninstall_delete_data',
        'royal_links_db_version',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Delete taxonomy terms
    $terms = get_terms(array(
        'taxonomy'   => array('royal_link_category', 'royal_link_tag'),
        'hide_empty' => false,
        'fields'     => 'ids',
    ));

    if (!is_wp_error($terms)) {
        foreach ($terms as $term_id) {
            wp_delete_term($term_id, 'royal_link_category');
            wp_delete_term($term_id, 'royal_link_tag');
        }
    }

    // Clear any scheduled cron events
    wp_clear_scheduled_hook('royal_links_check_broken_links');

    // Flush rewrite rules
    flush_rewrite_rules();
}
