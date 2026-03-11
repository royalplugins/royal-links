<?php
/**
 * Royal Links Meta Boxes
 *
 * Handles meta boxes for link editing.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Meta_Boxes {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_royal_link', array($this, 'save_meta_boxes'), 10, 2);
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        // Link Settings
        add_meta_box(
            'royal_links_settings',
            __('Link Settings', 'royal-links'),
            array($this, 'render_settings_meta_box'),
            'royal_link',
            'normal',
            'high'
        );

        // Link Options
        add_meta_box(
            'royal_links_options',
            __('Link Options', 'royal-links'),
            array($this, 'render_options_meta_box'),
            'royal_link',
            'side',
            'default'
        );

        // Link Statistics
        add_meta_box(
            'royal_links_stats',
            __('Link Statistics', 'royal-links'),
            array($this, 'render_stats_meta_box'),
            'royal_link',
            'side',
            'default'
        );

        // Short URL
        add_meta_box(
            'royal_links_short_url',
            __('Short URL', 'royal-links'),
            array($this, 'render_short_url_meta_box'),
            'royal_link',
            'side',
            'high'
        );
    }

    /**
     * Render settings meta box
     */
    public function render_settings_meta_box($post) {
        wp_nonce_field('royal_links_save_meta', 'royal_links_meta_nonce');

        $destination_url = get_post_meta($post->ID, '_royal_links_destination_url', true);
        $slug = get_post_meta($post->ID, '_royal_links_slug', true);
        $redirect_type = get_post_meta($post->ID, '_royal_links_redirect_type', true) ?: '301';

        $prefix = get_option('royal_links_link_prefix', 'go');
        ?>
        <table class="form-table royal-links-meta-table">
            <tr>
                <th><label for="royal_links_destination_url"><?php esc_html_e('Destination URL', 'royal-links'); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="url" id="royal_links_destination_url" name="royal_links_destination_url"
                           value="<?php echo esc_attr($destination_url); ?>"
                           class="large-text" required
                           placeholder="https://example.com/your-destination-page">
                    <p class="description"><?php esc_html_e('The URL where visitors will be redirected.', 'royal-links'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="royal_links_slug"><?php esc_html_e('Custom Slug', 'royal-links'); ?></label></th>
                <td>
                    <div class="royal-links-slug-input">
                        <span class="royal-links-slug-prefix"><?php echo esc_html(home_url($prefix . '/')); ?></span>
                        <input type="text" id="royal_links_slug" name="royal_links_slug"
                               value="<?php echo esc_attr($slug); ?>"
                               placeholder="<?php esc_attr_e('my-link', 'royal-links'); ?>">
                        <span class="royal-links-slug-status"></span>
                    </div>
                    <p class="description"><?php esc_html_e('Leave empty to auto-generate from title.', 'royal-links'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="royal_links_redirect_type"><?php esc_html_e('Redirect Type', 'royal-links'); ?></label></th>
                <td>
                    <select id="royal_links_redirect_type" name="royal_links_redirect_type">
                        <?php foreach (Royal_Links_Redirect::get_redirect_types() as $code => $info) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($redirect_type, $code); ?>>
                                <?php echo esc_html($info['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description redirect-description">
                        <?php
                        $types = Royal_Links_Redirect::get_redirect_types();
                        echo esc_html($types[$redirect_type]['description']);
                        ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render options meta box
     */
    public function render_options_meta_box($post) {
        $nofollow = get_post_meta($post->ID, '_royal_links_nofollow', true);
        $sponsored = get_post_meta($post->ID, '_royal_links_sponsored', true);
        $new_tab = get_post_meta($post->ID, '_royal_links_new_tab', true);

        // Set defaults for new posts
        if ($post->post_status === 'auto-draft') {
            $nofollow = get_option('royal_links_enable_nofollow', true);
            $sponsored = get_option('royal_links_enable_sponsored', false);
            $new_tab = get_option('royal_links_open_new_tab', true);
        }
        ?>
        <div class="royal-links-options">
            <p>
                <label>
                    <input type="checkbox" name="royal_links_nofollow" value="1" <?php checked($nofollow); ?>>
                    <?php esc_html_e('Add nofollow', 'royal-links'); ?>
                </label>
                <span class="description"><?php esc_html_e('Adds rel="nofollow" to the link', 'royal-links'); ?></span>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="royal_links_sponsored" value="1" <?php checked($sponsored); ?>>
                    <?php esc_html_e('Add sponsored', 'royal-links'); ?>
                </label>
                <span class="description"><?php esc_html_e('Adds rel="sponsored" for affiliate links', 'royal-links'); ?></span>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="royal_links_new_tab" value="1" <?php checked($new_tab); ?>>
                    <?php esc_html_e('Open in new tab', 'royal-links'); ?>
                </label>
                <span class="description"><?php esc_html_e('Opens link in a new browser tab', 'royal-links'); ?></span>
            </p>
        </div>
        <?php
    }

    /**
     * Render statistics meta box
     */
    public function render_stats_meta_box($post) {
        $total_clicks = get_post_meta($post->ID, '_royal_links_total_clicks', true) ?: 0;
        $unique_clicks = get_post_meta($post->ID, '_royal_links_unique_clicks', true) ?: 0;
        $last_click = get_post_meta($post->ID, '_royal_links_last_click', true);
        $is_broken = get_post_meta($post->ID, '_royal_links_is_broken', true);
        $last_check = get_post_meta($post->ID, '_royal_links_last_check', true);
        ?>
        <div class="royal-links-stats">
            <div class="stat-row">
                <span class="stat-label"><?php esc_html_e('Total Clicks:', 'royal-links'); ?></span>
                <span class="stat-value"><?php echo esc_html(number_format($total_clicks)); ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label"><?php esc_html_e('Unique Clicks:', 'royal-links'); ?></span>
                <span class="stat-value"><?php echo esc_html(number_format($unique_clicks)); ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label"><?php esc_html_e('Last Click:', 'royal-links'); ?></span>
                <span class="stat-value">
                    <?php echo $last_click ? esc_html(human_time_diff(strtotime($last_click)) . ' ' . __('ago', 'royal-links')) : esc_html__('Never', 'royal-links'); ?>
                </span>
            </div>
            <div class="stat-row">
                <span class="stat-label"><?php esc_html_e('Link Status:', 'royal-links'); ?></span>
                <span class="stat-value <?php echo $is_broken ? 'broken' : 'healthy'; ?>">
                    <?php echo $is_broken ? esc_html__('Broken', 'royal-links') : esc_html__('Healthy', 'royal-links'); ?>
                </span>
            </div>

            <?php if ($post->post_status !== 'auto-draft') : ?>
                <p>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=royal_link&page=royal-links-analytics&link_id=' . $post->ID)); ?>" class="button">
                        <?php esc_html_e('View Full Analytics', 'royal-links'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render short URL meta box
     */
    public function render_short_url_meta_box($post) {
        if ($post->post_status === 'auto-draft') {
            echo '<p class="description">' . esc_html__('Save the link to generate a short URL.', 'royal-links') . '</p>';
            return;
        }

        $short_url = Royal_Links_Post_Type::get_short_url($post->ID);
        ?>
        <div class="royal-links-short-url">
            <input type="text" value="<?php echo esc_attr($short_url); ?>" readonly class="large-text" id="royal-links-short-url-input">
            <p>
                <button type="button" class="button royal-links-copy-btn" data-clipboard="<?php echo esc_attr($short_url); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e('Copy to Clipboard', 'royal-links'); ?>
                </button>
                <a href="<?php echo esc_url($short_url); ?>" target="_blank" class="button">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('Test Link', 'royal-links'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['royal_links_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['royal_links_meta_nonce'])), 'royal_links_save_meta')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save destination URL
        if (isset($_POST['royal_links_destination_url'])) {
            $url = esc_url_raw(wp_unslash($_POST['royal_links_destination_url']));
            update_post_meta($post_id, '_royal_links_destination_url', $url);
        }

        // Save slug
        if (isset($_POST['royal_links_slug'])) {
            $slug = sanitize_title(wp_unslash($_POST['royal_links_slug']));

            // Generate slug if empty
            if (empty($slug)) {
                $slug = sanitize_title($post->post_title);
                if (empty($slug)) {
                    $slug = wp_generate_password(8, false);
                }
            }

            // Ensure unique
            $original_slug = $slug;
            $counter = 1;
            while (Royal_Links_Post_Type::slug_exists($slug, $post_id)) {
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }

            update_post_meta($post_id, '_royal_links_slug', $slug);
        }

        // Save redirect type
        if (isset($_POST['royal_links_redirect_type'])) {
            $type = sanitize_text_field(wp_unslash($_POST['royal_links_redirect_type']));
            if (in_array($type, array('301', '302', '307'))) {
                update_post_meta($post_id, '_royal_links_redirect_type', $type);
            }
        }

        // Save options
        update_post_meta($post_id, '_royal_links_nofollow', isset($_POST['royal_links_nofollow']));
        update_post_meta($post_id, '_royal_links_sponsored', isset($_POST['royal_links_sponsored']));
        update_post_meta($post_id, '_royal_links_new_tab', isset($_POST['royal_links_new_tab']));
    }
}
