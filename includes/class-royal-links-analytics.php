<?php
/**
 * Royal Links Analytics
 *
 * Provides analytics and reporting functionality.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Analytics {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_analytics_page'));
    }

    /**
     * Add analytics submenu page
     */
    public function add_analytics_page() {
        add_submenu_page(
            'edit.php?post_type=royal_link',
            __('Analytics', 'royal-links'),
            __('Analytics', 'royal-links'),
            'manage_options',
            'royal-links-analytics',
            array($this, 'render_analytics_page')
        );
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30days';
        $link_id = isset($_GET['link_id']) ? intval($_GET['link_id']) : 0;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Link Analytics', 'royal-links'); ?></h1>

            <div class="royal-links-analytics-filters">
                <form method="get">
                    <input type="hidden" name="post_type" value="royal_link">
                    <input type="hidden" name="page" value="royal-links-analytics">

                    <select name="period">
                        <option value="7days" <?php selected($period, '7days'); ?>><?php esc_html_e('Last 7 Days', 'royal-links'); ?></option>
                        <option value="30days" <?php selected($period, '30days'); ?>><?php esc_html_e('Last 30 Days', 'royal-links'); ?></option>
                        <option value="90days" <?php selected($period, '90days'); ?>><?php esc_html_e('Last 90 Days', 'royal-links'); ?></option>
                        <option value="year" <?php selected($period, 'year'); ?>><?php esc_html_e('Last Year', 'royal-links'); ?></option>
                        <option value="all" <?php selected($period, 'all'); ?>><?php esc_html_e('All Time', 'royal-links'); ?></option>
                    </select>

                    <?php
                    $links = get_posts(array(
                        'post_type'      => 'royal_link',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish',
                    ));
                    ?>
                    <select name="link_id">
                        <option value="0"><?php esc_html_e('All Links', 'royal-links'); ?></option>
                        <?php foreach ($links as $link) : ?>
                            <option value="<?php echo esc_attr($link->ID); ?>" <?php selected($link_id, $link->ID); ?>>
                                <?php echo esc_html($link->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="button"><?php esc_html_e('Filter', 'royal-links'); ?></button>
                </form>
            </div>

            <div class="royal-links-analytics-dashboard">
                <?php
                $stats = $this->get_overall_stats($period, $link_id);
                ?>

                <div class="royal-links-stats-cards">
                    <div class="royal-links-stat-card">
                        <h3><?php esc_html_e('Total Clicks', 'royal-links'); ?></h3>
                        <div class="stat-number"><?php echo esc_html(number_format($stats['total_clicks'])); ?></div>
                    </div>
                    <div class="royal-links-stat-card">
                        <h3><?php esc_html_e('Unique Clicks', 'royal-links'); ?></h3>
                        <div class="stat-number"><?php echo esc_html(number_format($stats['unique_clicks'])); ?></div>
                    </div>
                    <div class="royal-links-stat-card">
                        <h3><?php esc_html_e('Active Links', 'royal-links'); ?></h3>
                        <div class="stat-number"><?php echo esc_html(number_format($stats['active_links'])); ?></div>
                    </div>
                    <div class="royal-links-stat-card">
                        <h3><?php esc_html_e('Avg. Clicks/Day', 'royal-links'); ?></h3>
                        <div class="stat-number"><?php echo esc_html(number_format($stats['avg_daily'], 1)); ?></div>
                    </div>
                </div>

                <div class="royal-links-charts-row">
                    <div class="royal-links-chart-container">
                        <h3><?php esc_html_e('Clicks Over Time', 'royal-links'); ?></h3>
                        <canvas id="royal-links-clicks-chart"></canvas>
                    </div>
                </div>

                <div class="royal-links-tables-row">
                    <div class="royal-links-table-container">
                        <h3><?php esc_html_e('Top Performing Links', 'royal-links'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Link', 'royal-links'); ?></th>
                                    <th><?php esc_html_e('Clicks', 'royal-links'); ?></th>
                                    <th><?php esc_html_e('Unique', 'royal-links'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['top_links'] as $link) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($link['id'])); ?>">
                                                <?php echo esc_html($link['title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html(number_format($link['clicks'])); ?></td>
                                        <td><?php echo esc_html(number_format($link['unique'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="royal-links-table-container">
                        <h3><?php esc_html_e('Top Referrers', 'royal-links'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Source', 'royal-links'); ?></th>
                                    <th><?php esc_html_e('Clicks', 'royal-links'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['top_referrers'] as $referrer) : ?>
                                    <tr>
                                        <td><?php echo esc_html($referrer['source']); ?></td>
                                        <td><?php echo esc_html(number_format($referrer['clicks'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="royal-links-tables-row">
                    <div class="royal-links-table-container">
                        <h3><?php esc_html_e('Browsers', 'royal-links'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Browser', 'royal-links'); ?></th>
                                    <th><?php esc_html_e('Clicks', 'royal-links'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['browsers'] as $browser) : ?>
                                    <tr>
                                        <td><?php echo esc_html($browser['browser']); ?></td>
                                        <td><?php echo esc_html(number_format($browser['clicks'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="royal-links-table-container">
                        <h3><?php esc_html_e('Devices', 'royal-links'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Device', 'royal-links'); ?></th>
                                    <th><?php esc_html_e('Clicks', 'royal-links'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['devices'] as $device) : ?>
                                    <tr>
                                        <td><?php echo esc_html($device['device_type']); ?></td>
                                        <td><?php echo esc_html(number_format($device['clicks'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php

        // Add chart initialization script properly via wp_add_inline_script
        $chart_data = wp_json_encode($stats['chart_data']);
        $clicks_label = esc_js(__('Clicks', 'royal-links'));

        $chart_script = "
            jQuery(document).ready(function($) {
                var ctx = document.getElementById('royal-links-clicks-chart');
                if (ctx) {
                    var chartData = {$chart_data};
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: '{$clicks_label}',
                                data: chartData.data,
                                borderColor: '#2271b1',
                                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                                fill: true,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            });
        ";

        wp_add_inline_script('chartjs', $chart_script);
    }

    /**
     * Get overall statistics
     */
    public function get_overall_stats($period = '30days', $link_id = 0) {
        global $wpdb;

        // Determine date range
        $days = 30;
        switch ($period) {
            case '7days':
                $date_limit = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
                $days = 7;
                break;
            case '30days':
                $date_limit = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
                $days = 30;
                break;
            case '90days':
                $date_limit = gmdate('Y-m-d H:i:s', strtotime('-90 days'));
                $days = 90;
                break;
            case 'year':
                $date_limit = gmdate('Y-m-d H:i:s', strtotime('-1 year'));
                $days = 365;
                break;
            case 'all':
            default:
                $date_limit = '1970-01-01 00:00:00';
                $days = 365;
                break;
        }

        // Total clicks
        if ($link_id > 0) {
            $total_clicks = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date > %s AND link_id = %d",
                $date_limit,
                $link_id
            ));
        } else {
            $total_clicks = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date > %s",
                $date_limit
            ));
        }

        // Unique clicks
        if ($link_id > 0) {
            $unique_clicks = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}royal_links_clicks WHERE is_unique = 1 AND click_date > %s AND link_id = %d",
                $date_limit,
                $link_id
            ));
        } else {
            $unique_clicks = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}royal_links_clicks WHERE is_unique = 1 AND click_date > %s",
                $date_limit
            ));
        }

        // Active links count
        if ($link_id > 0) {
            $active_links = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT link_id) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date > %s AND link_id = %d",
                $date_limit,
                $link_id
            ));
        } else {
            $active_links = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT link_id) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date > %s",
                $date_limit
            ));
        }

        // Average daily clicks
        $avg_daily = $days > 0 ? $total_clicks / $days : 0;

        // Top links
        if ($link_id > 0) {
            $top_links_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT link_id, COUNT(*) as clicks, SUM(is_unique) as unique_clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s AND link_id = %d
                GROUP BY link_id
                ORDER BY clicks DESC
                LIMIT 10",
                $date_limit,
                $link_id
            ), ARRAY_A);
        } else {
            $top_links_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT link_id, COUNT(*) as clicks, SUM(is_unique) as unique_clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s
                GROUP BY link_id
                ORDER BY clicks DESC
                LIMIT 10",
                $date_limit
            ), ARRAY_A);
        }
        $top_links = array();

        foreach ($top_links_raw as $row) {
            $post = get_post($row['link_id']);
            if ($post) {
                $top_links[] = array(
                    'id'     => $row['link_id'],
                    'title'  => $post->post_title,
                    'clicks' => intval($row['clicks']),
                    'unique' => intval($row['unique_clicks']),
                );
            }
        }

        // Top referrers
        if ($link_id > 0) {
            $top_referrers = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    CASE
                        WHEN referer = '' OR referer IS NULL THEN 'Direct'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '://', -1)
                    END as source,
                    COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s AND link_id = %d
                GROUP BY source
                ORDER BY clicks DESC
                LIMIT 10",
                $date_limit,
                $link_id
            ), ARRAY_A);
        } else {
            $top_referrers = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    CASE
                        WHEN referer = '' OR referer IS NULL THEN 'Direct'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '://', -1)
                    END as source,
                    COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s
                GROUP BY source
                ORDER BY clicks DESC
                LIMIT 10",
                $date_limit
            ), ARRAY_A);
        }

        // Browsers
        if ($link_id > 0) {
            $browsers = $wpdb->get_results($wpdb->prepare(
                "SELECT browser, COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s AND link_id = %d
                GROUP BY browser
                ORDER BY clicks DESC
                LIMIT 5",
                $date_limit,
                $link_id
            ), ARRAY_A);
        } else {
            $browsers = $wpdb->get_results($wpdb->prepare(
                "SELECT browser, COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s
                GROUP BY browser
                ORDER BY clicks DESC
                LIMIT 5",
                $date_limit
            ), ARRAY_A);
        }

        // Devices
        if ($link_id > 0) {
            $devices = $wpdb->get_results($wpdb->prepare(
                "SELECT device_type, COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s AND link_id = %d
                GROUP BY device_type
                ORDER BY clicks DESC",
                $date_limit,
                $link_id
            ), ARRAY_A);
        } else {
            $devices = $wpdb->get_results($wpdb->prepare(
                "SELECT device_type, COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s
                GROUP BY device_type
                ORDER BY clicks DESC",
                $date_limit
            ), ARRAY_A);
        }

        // Chart data - clicks by day
        if ($link_id > 0) {
            $chart_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(click_date) as date, COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s AND link_id = %d
                GROUP BY DATE(click_date)
                ORDER BY date ASC",
                $date_limit,
                $link_id
            ), ARRAY_A);
        } else {
            $chart_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(click_date) as date, COUNT(*) as clicks
                FROM {$wpdb->prefix}royal_links_clicks
                WHERE click_date > %s
                GROUP BY DATE(click_date)
                ORDER BY date ASC",
                $date_limit
            ), ARRAY_A);
        }
        $chart_data = array(
            'labels' => array(),
            'data'   => array(),
        );

        foreach ($chart_raw as $row) {
            $chart_data['labels'][] = gmdate('M j', strtotime($row['date']));
            $chart_data['data'][] = intval($row['clicks']);
        }

        return array(
            'total_clicks'  => intval($total_clicks),
            'unique_clicks' => intval($unique_clicks),
            'active_links'  => intval($active_links),
            'avg_daily'     => $avg_daily,
            'top_links'     => $top_links,
            'top_referrers' => $top_referrers,
            'browsers'      => $browsers,
            'devices'       => $devices,
            'chart_data'    => $chart_data,
        );
    }
}
