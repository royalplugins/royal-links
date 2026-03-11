<?php
/**
 * Royal Links Gutenberg Integration
 *
 * Adds Gutenberg block and format support.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Gutenberg {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('royal-links/link', array(
            'editor_script'   => 'royal-links-gutenberg',
            'editor_style'    => 'royal-links-gutenberg-editor',
            'render_callback' => array($this, 'render_link_block'),
            'attributes'      => array(
                'linkId' => array(
                    'type'    => 'number',
                    'default' => 0,
                ),
                'displayStyle' => array(
                    'type'    => 'string',
                    'default' => 'button',
                ),
                'buttonText' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'alignment' => array(
                    'type'    => 'string',
                    'default' => 'left',
                ),
            ),
        ));
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'royal-links-gutenberg',
            ROYAL_LINKS_PLUGIN_URL . 'admin/js/gutenberg.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-data'),
            ROYAL_LINKS_VERSION,
            true
        );

        wp_localize_script('royal-links-gutenberg', 'royalLinksGutenberg', array(
            'restUrl'       => rest_url('royal-links/v1/'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'searchNonce'   => wp_create_nonce('royal_links_search'),
            'quickAddNonce' => wp_create_nonce('royal_links_quick_add'),
        ));

        wp_enqueue_style(
            'royal-links-gutenberg-editor',
            ROYAL_LINKS_PLUGIN_URL . 'admin/css/gutenberg.css',
            array(),
            ROYAL_LINKS_VERSION
        );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('royal-links/v1', '/links', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_links'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        register_rest_route('royal-links/v1', '/links/search', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'search_links'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args'                => array(
                'search' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route('royal-links/v1', '/links', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_link'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    /**
     * Get links for REST API
     */
    public function get_links($request) {
        $links = get_posts(array(
            'post_type'      => 'royal_link',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $result = array();

        foreach ($links as $link) {
            $result[] = $this->format_link_for_api($link);
        }

        return rest_ensure_response($result);
    }

    /**
     * Search links for REST API
     */
    public function search_links($request) {
        $search = $request->get_param('search');

        $args = array(
            'post_type'      => 'royal_link',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $links = get_posts($args);
        $result = array();

        foreach ($links as $link) {
            $result[] = $this->format_link_for_api($link);
        }

        return rest_ensure_response($result);
    }

    /**
     * Create link via REST API
     */
    public function create_link($request) {
        $params = $request->get_json_params();

        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $url = isset($params['url']) ? esc_url_raw($params['url']) : '';
        $slug = isset($params['slug']) ? sanitize_title($params['slug']) : '';

        if (empty($url)) {
            return new WP_Error('missing_url', __('Destination URL is required.', 'royal-links'), array('status' => 400));
        }

        $link_id = Royal_Links_Post_Type::create_link(array(
            'title'           => $title ?: $url,
            'destination_url' => $url,
            'slug'            => $slug,
        ));

        if (is_wp_error($link_id)) {
            return $link_id;
        }

        $link = get_post($link_id);

        return rest_ensure_response($this->format_link_for_api($link));
    }

    /**
     * Format link for API response
     */
    private function format_link_for_api($post) {
        return array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'shortUrl'  => Royal_Links_Post_Type::get_short_url($post->ID),
            'destUrl'   => get_post_meta($post->ID, '_royal_links_destination_url', true),
            'nofollow'  => (bool) get_post_meta($post->ID, '_royal_links_nofollow', true),
            'sponsored' => (bool) get_post_meta($post->ID, '_royal_links_sponsored', true),
            'newTab'    => (bool) get_post_meta($post->ID, '_royal_links_new_tab', true),
        );
    }

    /**
     * Render link block
     */
    public function render_link_block($attributes) {
        $link_id = isset($attributes['linkId']) ? intval($attributes['linkId']) : 0;

        if (!$link_id) {
            return '';
        }

        $link = get_post($link_id);

        if (!$link || $link->post_type !== 'royal_link') {
            return '';
        }

        $short_url = Royal_Links_Post_Type::get_short_url($link_id);
        $nofollow = get_post_meta($link_id, '_royal_links_nofollow', true);
        $sponsored = get_post_meta($link_id, '_royal_links_sponsored', true);
        $new_tab = get_post_meta($link_id, '_royal_links_new_tab', true);
        $display_style = isset($attributes['displayStyle']) ? $attributes['displayStyle'] : 'button';
        $button_text = isset($attributes['buttonText']) ? $attributes['buttonText'] : $link->post_title;
        $alignment = isset($attributes['alignment']) ? $attributes['alignment'] : 'left';

        $rel_parts = array();
        if ($nofollow) {
            $rel_parts[] = 'nofollow';
        }
        if ($sponsored) {
            $rel_parts[] = 'sponsored';
        }
        if ($new_tab) {
            $rel_parts[] = 'noopener';
        }

        $rel = !empty($rel_parts) ? ' rel="' . implode(' ', $rel_parts) . '"' : '';
        $target = $new_tab ? ' target="_blank"' : '';

        if ($display_style === 'button') {
            return sprintf(
                '<div class="wp-block-royal-links-link align%s"><a href="%s" class="royal-links-button"%s%s>%s</a></div>',
                esc_attr($alignment),
                esc_url($short_url),
                $target,
                $rel,
                esc_html($button_text)
            );
        } else {
            return sprintf(
                '<a href="%s"%s%s>%s</a>',
                esc_url($short_url),
                $target,
                $rel,
                esc_html($button_text)
            );
        }
    }
}
