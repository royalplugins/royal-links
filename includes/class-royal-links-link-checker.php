<?php
/**
 * Royal Links Link Checker
 *
 * Checks for broken links and reports health status.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Link_Checker {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('royal_links_check_broken_links', array($this, 'check_all_links'));
        add_action('admin_menu', array($this, 'add_health_page'));
    }

    /**
     * Add link health submenu page
     */
    public function add_health_page() {
        add_submenu_page(
            'edit.php?post_type=royal_link',
            __('Link Health', 'royal-links'),
            __('Link Health', 'royal-links'),
            'manage_options',
            'royal-links-health',
            array($this, 'render_health_page')
        );
    }

    /**
     * Render health page
     */
    public function render_health_page() {
        global $wpdb;

        // Handle manual check
        if (isset($_POST['royal_links_check_now']) && check_admin_referer('royal_links_check_now')) {
            $this->check_all_links();
            echo '<div class="notice notice-success"><p>' . esc_html__('Link check completed!', 'royal-links') . '</p></div>';
        }

        // Handle fix action
        if (isset($_GET['action'], $_GET['link_id'])) {
            $action = sanitize_text_field(wp_unslash($_GET['action']));
            $link_id = intval($_GET['link_id']);
            if ($action === 'recheck') {
                check_admin_referer('royal_links_recheck_' . $link_id);
                $this->check_single_link($link_id);
                echo '<div class="notice notice-success"><p>' . esc_html__('Link rechecked!', 'royal-links') . '</p></div>';
            }
        }

        $table_name = $wpdb->prefix . 'royal_links_health';

        // Get broken links
        $broken_links = $wpdb->get_results(
            "SELECT h.*, p.post_title
            FROM $table_name h
            INNER JOIN {$wpdb->posts} p ON h.link_id = p.ID
            WHERE h.is_broken = 1
            AND h.id IN (
                SELECT MAX(id) FROM $table_name GROUP BY link_id
            )
            ORDER BY h.check_date DESC"
        );

        // Get last check date
        $last_check = $wpdb->get_var("SELECT MAX(check_date) FROM $table_name");

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Link Health', 'royal-links'); ?></h1>

            <div class="royal-links-health-header">
                <div class="royal-links-health-stats">
                    <div class="stat-item">
                        <span class="stat-label"><?php esc_html_e('Last Check:', 'royal-links'); ?></span>
                        <span class="stat-value">
                            <?php echo $last_check ? esc_html(human_time_diff(strtotime($last_check)) . ' ' . __('ago', 'royal-links')) : esc_html__('Never', 'royal-links'); ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php esc_html_e('Broken Links:', 'royal-links'); ?></span>
                        <span class="stat-value <?php echo count($broken_links) > 0 ? 'has-issues' : 'healthy'; ?>">
                            <?php echo esc_html(count($broken_links)); ?>
                        </span>
                    </div>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field('royal_links_check_now'); ?>
                    <button type="submit" name="royal_links_check_now" class="button button-primary">
                        <?php esc_html_e('Check All Links Now', 'royal-links'); ?>
                    </button>
                </form>
            </div>

            <?php if (empty($broken_links)) : ?>
                <div class="royal-links-healthy-notice">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e('All links are healthy!', 'royal-links'); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Link', 'royal-links'); ?></th>
                            <th><?php esc_html_e('Destination URL', 'royal-links'); ?></th>
                            <th><?php esc_html_e('Status Code', 'royal-links'); ?></th>
                            <th><?php esc_html_e('Error', 'royal-links'); ?></th>
                            <th><?php esc_html_e('Last Checked', 'royal-links'); ?></th>
                            <th><?php esc_html_e('Actions', 'royal-links'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($broken_links as $link) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($link->link_id)); ?>">
                                        <?php echo esc_html($link->post_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $url = get_post_meta($link->link_id, '_royal_links_destination_url', true);
                                    $display_url = strlen($url) > 40 ? substr($url, 0, 40) . '...' : $url;
                                    ?>
                                    <a href="<?php echo esc_url($url); ?>" target="_blank" title="<?php echo esc_attr($url); ?>">
                                        <?php echo esc_html($display_url); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-code status-<?php echo esc_attr($link->status_code); ?>">
                                        <?php echo esc_html($link->status_code ?: 'N/A'); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($link->error_message); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($link->check_date)) . ' ' . __('ago', 'royal-links')); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(wp_nonce_url(
                                        add_query_arg(array('action' => 'recheck', 'link_id' => $link->link_id)),
                                        'royal_links_recheck_' . $link->link_id
                                    )); ?>" class="button button-small">
                                        <?php esc_html_e('Recheck', 'royal-links'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(get_edit_post_link($link->link_id)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'royal-links'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Check all links
     */
    public function check_all_links() {
        if (!get_option('royal_links_enable_link_checker', true)) {
            return;
        }

        $links = get_posts(array(
            'post_type'      => 'royal_link',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));

        // Only increase limits when there are links to process
        if (!empty($links)) {
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional limit increase for batch processing
            @set_time_limit(300);
            // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Blacklisted -- Required for large link batches
            @ini_set('memory_limit', '256M');

            foreach ($links as $link) {
                $this->check_single_link($link->ID);

                // Add small delay to avoid overwhelming servers
                usleep(100000); // 0.1 second
            }
        }
    }

    /**
     * Check a single link
     */
    public function check_single_link($link_id) {
        global $wpdb;

        $url = get_post_meta($link_id, '_royal_links_destination_url', true);

        if (empty($url)) {
            return;
        }

        $table_name = $wpdb->prefix . 'royal_links_health';

        $start_time = microtime(true);
        $result = $this->check_url($url);
        $response_time = microtime(true) - $start_time;

        $data = array(
            'link_id'       => $link_id,
            'check_date'    => current_time('mysql'),
            'status_code'   => $result['status_code'],
            'response_time' => round($response_time, 3),
            'is_broken'     => $result['is_broken'] ? 1 : 0,
            'error_message' => $result['error_message'],
        );

        $wpdb->insert($table_name, $data);

        // Update link meta
        update_post_meta($link_id, '_royal_links_last_check', current_time('mysql'));
        update_post_meta($link_id, '_royal_links_is_broken', $result['is_broken']);
        update_post_meta($link_id, '_royal_links_last_status', $result['status_code']);

        return $result;
    }

    /**
     * Check URL and return status
     */
    private function check_url($url) {
        $result = array(
            'status_code'   => null,
            'is_broken'     => false,
            'error_message' => '',
        );

        $response = wp_remote_head($url, array(
            'timeout'     => 10,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'Royal-Links Link Checker/' . ROYAL_LINKS_VERSION,
        ));

        if (is_wp_error($response)) {
            $result['is_broken'] = true;
            $result['error_message'] = $response->get_error_message();
            return $result;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $result['status_code'] = $status_code;

        // Check if status code indicates a problem
        if ($status_code >= 400) {
            $result['is_broken'] = true;
            $result['error_message'] = $this->get_status_message($status_code);
        }

        return $result;
    }

    /**
     * Get human-readable status message
     */
    private function get_status_message($code) {
        $messages = array(
            400 => __('Bad Request', 'royal-links'),
            401 => __('Unauthorized', 'royal-links'),
            403 => __('Forbidden', 'royal-links'),
            404 => __('Not Found', 'royal-links'),
            408 => __('Request Timeout', 'royal-links'),
            410 => __('Gone', 'royal-links'),
            500 => __('Internal Server Error', 'royal-links'),
            502 => __('Bad Gateway', 'royal-links'),
            503 => __('Service Unavailable', 'royal-links'),
            504 => __('Gateway Timeout', 'royal-links'),
        );

        return isset($messages[$code]) ? $messages[$code] : __('Unknown Error', 'royal-links');
    }

    /**
     * Get broken links count
     */
    public static function get_broken_count() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'royal_links_health';

        return $wpdb->get_var(
            "SELECT COUNT(DISTINCT link_id) FROM $table_name
            WHERE is_broken = 1
            AND id IN (SELECT MAX(id) FROM $table_name GROUP BY link_id)"
        );
    }
}
