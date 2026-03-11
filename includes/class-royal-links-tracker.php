<?php
/**
 * Royal Links Click Tracker
 *
 * Tracks clicks on short links.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Tracker {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Constructor is intentionally empty
    }

    /**
     * Track a click on a link
     */
    public function track_click($link_id) {
        global $wpdb;

        // Don't track admin/logged-in users if setting is enabled
        if (is_user_logged_in() && !get_option('royal_links_track_admin_clicks', false)) {
            // Still track for now, but this can be made configurable
        }

        // Don't track bots
        if ($this->is_bot()) {
            return false;
        }

        $table_name = $wpdb->prefix . 'royal_links_clicks';

        // Get visitor data
        $ip_address = $this->get_ip_address();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

        // Parse user agent
        $browser_info = $this->parse_user_agent($user_agent);

        // Check if this is a unique click (same IP hasn't clicked this link today)
        $is_unique = $this->is_unique_click($link_id, $ip_address);

        // Prepare data
        $data = array(
            'link_id'         => $link_id,
            'click_date'      => current_time('mysql'),
            'ip_address'      => get_option('royal_links_track_ip', false) ? $ip_address : null,
            'user_agent'      => substr($user_agent, 0, 500),
            'referer'         => substr($referer, 0, 2000),
            'browser'         => $browser_info['browser'],
            'browser_version' => $browser_info['version'],
            'os'              => $browser_info['os'],
            'os_version'      => $browser_info['os_version'],
            'device_type'     => $browser_info['device_type'],
            'is_unique'       => $is_unique ? 1 : 0,
        );

        $format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d');

        $result = $wpdb->insert($table_name, $data, $format);

        // Update click count meta for quick access
        if ($result) {
            $total_clicks = (int) get_post_meta($link_id, '_royal_links_total_clicks', true);
            update_post_meta($link_id, '_royal_links_total_clicks', $total_clicks + 1);

            if ($is_unique) {
                $unique_clicks = (int) get_post_meta($link_id, '_royal_links_unique_clicks', true);
                update_post_meta($link_id, '_royal_links_unique_clicks', $unique_clicks + 1);
            }

            update_post_meta($link_id, '_royal_links_last_click', current_time('mysql'));
        }

        return $result !== false;
    }

    /**
     * Check if this is a unique click
     */
    private function is_unique_click($link_id, $ip_address) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'royal_links_clicks';

        // Check if this IP has clicked this link in the last 24 hours
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
            WHERE link_id = %d
            AND ip_address = %s
            AND click_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $link_id,
            $ip_address
        ));

        return intval($count) === 0;
    }

    /**
     * Get visitor IP address
     */
    private function get_ip_address() {
        $ip = '';

        // Check for proxy headers (in order of reliability)
        $headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // General proxy
            'REMOTE_ADDR',               // Direct connection
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                break;
            }
        }

        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '';
    }

    /**
     * Check if request is from a bot
     */
    private function is_bot() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return true;
        }

        $user_agent = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])));

        $bots = array(
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'sogou', 'exabot', 'facebot', 'facebookexternalhit',
            'ia_archiver', 'crawler', 'spider', 'robot', 'bot/', 'bot;',
            'wget', 'curl', 'python', 'java/', 'libwww', 'httpunit',
            'nutch', 'phpcrawl', 'msnbot', 'jyxobot', 'fast-webcrawler',
            'auditbot', 'dotbot', 'semrushbot', 'ahrefsbot', 'majestic',
            'blexbot', 'petalbot', 'linkdexbot', 'megaindex', 'semantic',
        );

        foreach ($bots as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse user agent string
     */
    private function parse_user_agent($user_agent) {
        $result = array(
            'browser'     => 'Unknown',
            'version'     => '',
            'os'          => 'Unknown',
            'os_version'  => '',
            'device_type' => 'Desktop',
        );

        if (empty($user_agent)) {
            return $result;
        }

        $ua = strtolower($user_agent);

        // Detect device type
        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua)) {
            $result['device_type'] = 'Mobile';
        } elseif (preg_match('/tablet|ipad|kindle|silk|playbook/i', $ua)) {
            $result['device_type'] = 'Tablet';
        }

        // Detect browser
        $browsers = array(
            'Edge'    => '/edg[e\/]?([\d.]+)?/i',
            'Chrome'  => '/chrome\/([\d.]+)/i',
            'Firefox' => '/firefox\/([\d.]+)/i',
            'Safari'  => '/version\/([\d.]+).*safari/i',
            'Opera'   => '/(?:opera|opr)\/([\d.]+)/i',
            'IE'      => '/(?:msie |rv:)([\d.]+)/i',
        );

        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $user_agent, $matches)) {
                $result['browser'] = $browser;
                $result['version'] = isset($matches[1]) ? $matches[1] : '';
                break;
            }
        }

        // Detect OS
        $os_patterns = array(
            'Windows 11'    => '/windows nt 10.*build.*2[2-9]\d{3}/i',
            'Windows 10'    => '/windows nt 10/i',
            'Windows 8.1'   => '/windows nt 6\.3/i',
            'Windows 8'     => '/windows nt 6\.2/i',
            'Windows 7'     => '/windows nt 6\.1/i',
            'macOS'         => '/mac os x ([\d_\.]+)?/i',
            'iOS'           => '/(?:iphone|ipad|ipod).*os ([\d_]+)/i',
            'Android'       => '/android ([\d\.]+)?/i',
            'Linux'         => '/linux/i',
            'Chrome OS'     => '/cros/i',
        );

        foreach ($os_patterns as $os => $pattern) {
            if (preg_match($pattern, $user_agent, $matches)) {
                $result['os'] = preg_replace('/\s*\d.*$/', '', $os);
                if (isset($matches[1])) {
                    $result['os_version'] = str_replace('_', '.', $matches[1]);
                }
                break;
            }
        }

        return $result;
    }

    /**
     * Get click statistics for a link
     */
    public static function get_link_stats($link_id, $period = '30days') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'royal_links_clicks';

        // Determine date range
        switch ($period) {
            case '7days':
                $date_limit = 'DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case '30days':
                $date_limit = 'DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
            case '90days':
                $date_limit = 'DATE_SUB(NOW(), INTERVAL 90 DAY)';
                break;
            case 'year':
                $date_limit = 'DATE_SUB(NOW(), INTERVAL 1 YEAR)';
                break;
            case 'all':
            default:
                $date_limit = "'1970-01-01'";
                break;
        }

        // Total clicks
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE link_id = %d AND click_date > $date_limit",
            $link_id
        ));

        // Unique clicks
        $unique = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE link_id = %d AND is_unique = 1 AND click_date > $date_limit",
            $link_id
        ));

        // Clicks by day
        $by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(click_date) as date, COUNT(*) as clicks
            FROM $table_name
            WHERE link_id = %d AND click_date > $date_limit
            GROUP BY DATE(click_date)
            ORDER BY date DESC",
            $link_id
        ), ARRAY_A);

        // Top referrers
        $referrers = $wpdb->get_results($wpdb->prepare(
            "SELECT
                CASE
                    WHEN referer = '' OR referer IS NULL THEN 'Direct'
                    ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '://', -1)
                END as source,
                COUNT(*) as clicks
            FROM $table_name
            WHERE link_id = %d AND click_date > $date_limit
            GROUP BY source
            ORDER BY clicks DESC
            LIMIT 10",
            $link_id
        ), ARRAY_A);

        // Browser breakdown
        $browsers = $wpdb->get_results($wpdb->prepare(
            "SELECT browser, COUNT(*) as clicks
            FROM $table_name
            WHERE link_id = %d AND click_date > $date_limit
            GROUP BY browser
            ORDER BY clicks DESC",
            $link_id
        ), ARRAY_A);

        // Device breakdown
        $devices = $wpdb->get_results($wpdb->prepare(
            "SELECT device_type, COUNT(*) as clicks
            FROM $table_name
            WHERE link_id = %d AND click_date > $date_limit
            GROUP BY device_type
            ORDER BY clicks DESC",
            $link_id
        ), ARRAY_A);

        // OS breakdown
        $os_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT os, COUNT(*) as clicks
            FROM $table_name
            WHERE link_id = %d AND click_date > $date_limit
            GROUP BY os
            ORDER BY clicks DESC",
            $link_id
        ), ARRAY_A);

        return array(
            'total'     => intval($total),
            'unique'    => intval($unique),
            'by_day'    => $by_day,
            'referrers' => $referrers,
            'browsers'  => $browsers,
            'devices'   => $devices,
            'os'        => $os_stats,
        );
    }
}
