<?php
/**
 * Royal Links Settings
 *
 * Handles plugin settings page.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
    }

    /**
     * Add settings submenu page
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=royal_link',
            __('Settings', 'royal-links'),
            __('Settings', 'royal-links'),
            'manage_options',
            'royal-links-settings',
            array($this, 'render_settings_page')
        );

        // Upgrade to Pro page (last item)
        add_submenu_page(
            'edit.php?post_type=royal_link',
            __('Upgrade to Pro', 'royal-links'),
            '<span style="color:#C9A227;">' . __('Upgrade to Pro', 'royal-links') . '</span>',
            'manage_options',
            'royal-links-upgrade',
            array($this, 'render_upgrade_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        add_settings_section(
            'royal_links_general',
            __('General Settings', 'royal-links'),
            array($this, 'render_general_section'),
            'royal-links-settings'
        );

        add_settings_field(
            'royal_links_link_prefix',
            __('Link Prefix', 'royal-links'),
            array($this, 'render_prefix_field'),
            'royal-links-settings',
            'royal_links_general'
        );

        add_settings_field(
            'royal_links_default_redirect_type',
            __('Default Redirect Type', 'royal-links'),
            array($this, 'render_redirect_type_field'),
            'royal-links-settings',
            'royal_links_general'
        );

        // Default Link Options
        add_settings_section(
            'royal_links_defaults',
            __('Default Link Options', 'royal-links'),
            array($this, 'render_defaults_section'),
            'royal-links-settings'
        );

        add_settings_field(
            'royal_links_enable_nofollow',
            __('Nofollow', 'royal-links'),
            array($this, 'render_nofollow_field'),
            'royal-links-settings',
            'royal_links_defaults'
        );

        add_settings_field(
            'royal_links_enable_sponsored',
            __('Sponsored', 'royal-links'),
            array($this, 'render_sponsored_field'),
            'royal-links-settings',
            'royal_links_defaults'
        );

        add_settings_field(
            'royal_links_open_new_tab',
            __('Open in New Tab', 'royal-links'),
            array($this, 'render_new_tab_field'),
            'royal-links-settings',
            'royal_links_defaults'
        );

        // Tracking Settings
        add_settings_section(
            'royal_links_tracking',
            __('Tracking Settings', 'royal-links'),
            array($this, 'render_tracking_section'),
            'royal-links-settings'
        );

        add_settings_field(
            'royal_links_track_clicks',
            __('Enable Click Tracking', 'royal-links'),
            array($this, 'render_track_clicks_field'),
            'royal-links-settings',
            'royal_links_tracking'
        );

        add_settings_field(
            'royal_links_track_ip',
            __('Store IP Addresses', 'royal-links'),
            array($this, 'render_track_ip_field'),
            'royal-links-settings',
            'royal_links_tracking'
        );

        // Link Health Settings
        add_settings_section(
            'royal_links_health',
            __('Link Health Settings', 'royal-links'),
            array($this, 'render_health_section'),
            'royal-links-settings'
        );

        add_settings_field(
            'royal_links_enable_link_checker',
            __('Enable Link Checker', 'royal-links'),
            array($this, 'render_link_checker_field'),
            'royal-links-settings',
            'royal_links_health'
        );

        // Register settings
        register_setting('royal_links_settings', 'royal_links_link_prefix', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_title',
            'default'           => 'go',
        ));

        register_setting('royal_links_settings', 'royal_links_default_redirect_type', array(
            'type'              => 'string',
            'sanitize_callback' => array($this, 'sanitize_redirect_type'),
            'default'           => '301',
        ));

        register_setting('royal_links_settings', 'royal_links_enable_nofollow', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ));

        register_setting('royal_links_settings', 'royal_links_enable_sponsored', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ));

        register_setting('royal_links_settings', 'royal_links_open_new_tab', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ));

        register_setting('royal_links_settings', 'royal_links_track_clicks', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ));

        register_setting('royal_links_settings', 'royal_links_track_ip', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ));

        register_setting('royal_links_settings', 'royal_links_enable_link_checker', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ));
    }

    /**
     * Sanitize redirect type
     */
    public function sanitize_redirect_type($value) {
        $allowed = array('301', '302', '307');
        return in_array($value, $allowed) ? $value : '301';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_GET['settings-updated']) && sanitize_text_field(wp_unslash($_GET['settings-updated']))) {
            // Flush rewrite rules when prefix changes
            update_option('royal_links_flush_rewrite_rules', true);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Royal Links Settings', 'royal-links'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('royal_links_settings');
                do_settings_sections('royal-links-settings');
                submit_button();
                ?>
            </form>

            <!-- Premium Upsell -->
            <div class="royal-links-upsell-box">
                <h3><?php esc_html_e('Upgrade to Royal Links Pro', 'royal-links'); ?></h3>
                <p><?php esc_html_e('Get advanced link management features:', 'royal-links'); ?></p>
                <ul>
                    <li><?php esc_html_e('Auto-Link Keywords', 'royal-links'); ?></li>
                    <li><?php esc_html_e('Link Scheduling & Expiration', 'royal-links'); ?></li>
                    <li><?php esc_html_e('Advanced Analytics & Reports', 'royal-links'); ?></li>
                    <li><?php esc_html_e('Geo-Targeting & Device Redirects', 'royal-links'); ?></li>
                    <li><?php esc_html_e('A/B Split Testing', 'royal-links'); ?></li>
                    <li><?php esc_html_e('Priority Support', 'royal-links'); ?></li>
                </ul>
                <a href="https://royalplugins.com/royal-links" target="_blank" class="button button-primary">
                    <?php esc_html_e('Upgrade Now', 'royal-links'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Section callbacks
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general plugin settings.', 'royal-links') . '</p>';
    }

    public function render_defaults_section() {
        echo '<p>' . esc_html__('Set default options for new links.', 'royal-links') . '</p>';
    }

    public function render_tracking_section() {
        echo '<p>' . esc_html__('Configure how click tracking works.', 'royal-links') . '</p>';
    }

    public function render_health_section() {
        echo '<p>' . esc_html__('Configure link health monitoring.', 'royal-links') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function render_prefix_field() {
        $value = get_option('royal_links_link_prefix', 'go');
        ?>
        <input type="text" name="royal_links_link_prefix" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('The prefix used in short URLs. Example:', 'royal-links'); ?>
            <code><?php echo esc_html(home_url('/' . $value . '/my-link')); ?></code>
        </p>
        <?php
    }

    public function render_redirect_type_field() {
        $value = get_option('royal_links_default_redirect_type', '301');
        ?>
        <select name="royal_links_default_redirect_type">
            <?php foreach (Royal_Links_Redirect::get_redirect_types() as $code => $info) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                    <?php echo esc_html($info['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Default redirect type for new links.', 'royal-links'); ?></p>
        <?php
    }

    public function render_nofollow_field() {
        $value = get_option('royal_links_enable_nofollow', true);
        ?>
        <label>
            <input type="checkbox" name="royal_links_enable_nofollow" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Add nofollow by default', 'royal-links'); ?>
        </label>
        <p class="description"><?php esc_html_e('Adds rel="nofollow" to new links by default.', 'royal-links'); ?></p>
        <?php
    }

    public function render_sponsored_field() {
        $value = get_option('royal_links_enable_sponsored', false);
        ?>
        <label>
            <input type="checkbox" name="royal_links_enable_sponsored" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Add sponsored by default', 'royal-links'); ?>
        </label>
        <p class="description"><?php esc_html_e('Adds rel="sponsored" to new links by default.', 'royal-links'); ?></p>
        <?php
    }

    public function render_new_tab_field() {
        $value = get_option('royal_links_open_new_tab', true);
        ?>
        <label>
            <input type="checkbox" name="royal_links_open_new_tab" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Open in new tab by default', 'royal-links'); ?>
        </label>
        <p class="description"><?php esc_html_e('Opens links in a new tab by default.', 'royal-links'); ?></p>
        <?php
    }

    public function render_track_clicks_field() {
        $value = get_option('royal_links_track_clicks', true);
        ?>
        <label>
            <input type="checkbox" name="royal_links_track_clicks" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Enable click tracking', 'royal-links'); ?>
        </label>
        <p class="description"><?php esc_html_e('Track clicks on all links for analytics.', 'royal-links'); ?></p>
        <?php
    }

    public function render_track_ip_field() {
        $value = get_option('royal_links_track_ip', false);
        ?>
        <label>
            <input type="checkbox" name="royal_links_track_ip" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Store IP addresses', 'royal-links'); ?>
        </label>
        <p class="description"><?php esc_html_e('Store visitor IP addresses for advanced analytics. Consider privacy implications.', 'royal-links'); ?></p>
        <?php
    }

    public function render_link_checker_field() {
        $value = get_option('royal_links_enable_link_checker', true);
        ?>
        <label>
            <input type="checkbox" name="royal_links_enable_link_checker" value="1" <?php checked($value); ?>>
            <?php esc_html_e('Enable automatic link checking', 'royal-links'); ?>
        </label>
        <p class="description"><?php esc_html_e('Automatically check for broken links daily.', 'royal-links'); ?></p>
        <?php
    }

    /**
     * Render Upgrade to Pro page
     */
    public function render_upgrade_page() {
        ?>
        <div class="wrap royal-links-upgrade-wrap">
            <h1><?php esc_html_e('Upgrade to Royal Links Pro', 'royal-links'); ?></h1>

            <div class="royal-links-upgrade-header">
                <p class="royal-links-upgrade-tagline">
                    <?php esc_html_e('Unlock powerful link management features to boost your affiliate revenue and optimize your marketing campaigns.', 'royal-links'); ?>
                </p>
                <a href="https://royalplugins.com/royal-links/" target="_blank" class="button button-primary button-hero">
                    <?php esc_html_e('Get Royal Links Pro', 'royal-links'); ?>
                </a>
            </div>

            <div class="royal-links-features-grid">
                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-location"></span>
                    <h3><?php esc_html_e('Geo-Targeting', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Redirect visitors to different URLs based on their country. Perfect for international affiliate programs.', 'royal-links'); ?></p>
                </div>

                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <h3><?php esc_html_e('A/B Split Testing', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Test multiple destination URLs to find which converts best. Data-driven optimization for your links.', 'royal-links'); ?></p>
                </div>

                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-smartphone"></span>
                    <h3><?php esc_html_e('Device Targeting', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Send mobile users to app stores and desktop users to websites. Maximize conversions on every device.', 'royal-links'); ?></p>
                </div>

                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-qrcode"></span>
                    <h3><?php esc_html_e('QR Code Generation', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Generate QR codes for any link instantly. Perfect for print materials and offline marketing.', 'royal-links'); ?></p>
                </div>

                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-admin-links"></span>
                    <h3><?php esc_html_e('Auto Keyword Linking', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Automatically convert keywords in your content to affiliate links. Set it once and earn passively.', 'royal-links'); ?></p>
                </div>

                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-tag"></span>
                    <h3><?php esc_html_e('UTM Parameter Builder', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Add UTM tracking parameters to links automatically. Track campaigns in Google Analytics with ease.', 'royal-links'); ?></p>
                </div>

                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-products"></span>
                    <h3><?php esc_html_e('Product Displays', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Create beautiful product boxes with images, prices, and buy buttons using simple shortcodes.', 'royal-links'); ?></p>
                </div>

                <div class="royal-links-feature-card">
                    <span class="dashicons dashicons-megaphone"></span>
                    <h3><?php esc_html_e('Affiliate Disclosure', 'royal-links'); ?></h3>
                    <p><?php esc_html_e('Automatically add FTC-compliant affiliate disclosures to posts containing affiliate links.', 'royal-links'); ?></p>
                </div>
            </div>

            <div class="royal-links-upgrade-cta">
                <h2><?php esc_html_e('Ready to supercharge your links?', 'royal-links'); ?></h2>
                <a href="https://royalplugins.com/royal-links/" target="_blank" class="button button-primary button-hero">
                    <?php esc_html_e('Upgrade to Pro Now', 'royal-links'); ?>
                </a>
                <p class="royal-links-guarantee"><?php esc_html_e('30-day money-back guarantee. No questions asked.', 'royal-links'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Custom admin footer text for Royal Links pages
     */
    public function admin_footer_text($text) {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== 'royal_link') {
            return $text;
        }

        $footer_text = sprintf(
            /* translators: %s: Royal Plugins link */
            __('Built By %s', 'royal-links'),
            '<a href="https://royalplugins.com" target="_blank" rel="noopener noreferrer">Royal Plugins</a>'
        );

        return $footer_text . ' | ' . $text;
    }
}
