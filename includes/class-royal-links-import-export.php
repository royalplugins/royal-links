<?php
/**
 * Royal Links Import/Export
 *
 * Handles importing and exporting links.
 *
 * @package Royal_Links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Royal_Links_Import_Export {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_import_export_page'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_import'));
    }

    /**
     * Add import/export submenu page
     */
    public function add_import_export_page() {
        add_submenu_page(
            'edit.php?post_type=royal_link',
            __('Import/Export', 'royal-links'),
            __('Import/Export', 'royal-links'),
            'manage_options',
            'royal-links-import-export',
            array($this, 'render_page')
        );
    }

    /**
     * Render import/export page
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import/Export Links', 'royal-links'); ?></h1>

            <?php
            // Display notices
            if (isset($_GET['exported']) && sanitize_text_field(wp_unslash($_GET['exported'])) === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Links exported successfully!', 'royal-links') . '</p></div>';
            }
            if (isset($_GET['imported'])) {
                $count = intval(wp_unslash($_GET['imported']));
                $skipped = isset($_GET['skipped']) ? intval(wp_unslash($_GET['skipped'])) : 0;

                if ($count > 0) {
                    $message = sprintf(
                        /* translators: %d: number of links imported */
                        esc_html__('%d links imported successfully!', 'royal-links'),
                        $count
                    );
                    if ($skipped > 0) {
                        $message .= ' ' . sprintf(
                            /* translators: %d: number of links skipped */
                            esc_html__('(%d skipped - missing URL or duplicate slug)', 'royal-links'),
                            $skipped
                        );
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
                } elseif ($skipped > 0) {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf(
                        /* translators: %d: number of links skipped */
                        esc_html__('No links imported. %d rows skipped (missing destination URL or duplicate slug).', 'royal-links'),
                        intval($skipped)
                    ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('No links found in file. Check that your file has data rows.', 'royal-links') . '</p></div>';
                }
            }
            if (isset($_GET['import_error'])) {
                $error_type = sanitize_key(wp_unslash($_GET['import_error']));
                $error_messages = array(
                    'no_file'        => __('No file uploaded. Please select a file.', 'royal-links'),
                    'invalid_format' => __('Invalid file format. Please upload a CSV or JSON file.', 'royal-links'),
                    'parse_error'    => __('Could not read file. Make sure CSV has a "destination_url" column header, or JSON is a valid array.', 'royal-links'),
                );
                $error_msg = isset($error_messages[$error_type]) ? $error_messages[$error_type] : __('Error importing links.', 'royal-links');
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_msg) . '</p></div>';
            }
            ?>

            <div class="royal-links-import-export-wrapper">
                <!-- Export Section -->
                <div class="royal-links-section">
                    <h2><?php esc_html_e('Export Links', 'royal-links'); ?></h2>
                    <p><?php esc_html_e('Download all your links as a CSV or JSON file.', 'royal-links'); ?></p>

                    <form method="post" action="">
                        <?php wp_nonce_field('royal_links_export', 'royal_links_export_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Format', 'royal-links'); ?></th>
                                <td>
                                    <select name="export_format">
                                        <option value="csv">CSV</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Include', 'royal-links'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="include_stats" value="1" checked>
                                        <?php esc_html_e('Include click statistics', 'royal-links'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="royal_links_export" class="button button-primary" value="<?php esc_attr_e('Export Links', 'royal-links'); ?>">
                        </p>
                    </form>
                </div>

                <!-- Import Section -->
                <div class="royal-links-section">
                    <h2><?php esc_html_e('Import Links', 'royal-links'); ?></h2>
                    <p><?php esc_html_e('Import links from a CSV or JSON file.', 'royal-links'); ?></p>

                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field('royal_links_import', 'royal_links_import_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('File', 'royal-links'); ?></th>
                                <td>
                                    <input type="file" name="import_file" accept=".csv,.json" required>
                                    <p class="description"><?php esc_html_e('Accepted formats: CSV, JSON', 'royal-links'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Options', 'royal-links'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="skip_duplicates" value="1" checked>
                                        <?php esc_html_e('Skip duplicate slugs', 'royal-links'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="royal_links_import" class="button button-primary" value="<?php esc_attr_e('Import Links', 'royal-links'); ?>">
                        </p>
                    </form>
                </div>

                <!-- Migrate from Other Plugins -->
                <div class="royal-links-section">
                    <h2><?php esc_html_e('Migrate from Other Plugins', 'royal-links'); ?></h2>
                    <p><?php esc_html_e('Import links from other WordPress link management plugins.', 'royal-links'); ?></p>

                    <?php
                    $available_migrations = $this->get_available_migrations();

                    if (empty($available_migrations)) {
                        echo '<p class="description">' . esc_html__('No compatible plugins detected.', 'royal-links') . '</p>';
                    } else {
                        foreach ($available_migrations as $plugin => $info) {
                            ?>
                            <div class="royal-links-migration-option">
                                <form method="post" action="">
                                    <?php wp_nonce_field('royal_links_migrate_' . $plugin, 'royal_links_migrate_nonce'); ?>
                                    <input type="hidden" name="migrate_from" value="<?php echo esc_attr($plugin); ?>">

                                    <h4><?php echo esc_html($info['name']); ?></h4>
                                    <p><?php printf(
                                        /* translators: %d: number of links found */
                                        esc_html__('%d links found', 'royal-links'),
                                        intval($info['count'])
                                    ); ?></p>
                                    <button type="submit" name="royal_links_migrate" class="button">
                                        <?php esc_html_e('Migrate Links', 'royal-links'); ?>
                                    </button>
                                </form>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>

                <!-- CSV Format Info -->
                <div class="royal-links-section">
                    <h2><?php esc_html_e('CSV Format', 'royal-links'); ?></h2>
                    <p><?php esc_html_e('Your CSV file should have the following columns:', 'royal-links'); ?></p>
                    <code>title,destination_url,slug,redirect_type,nofollow,sponsored,category</code>
                    <p class="description"><?php esc_html_e('The first row should contain the column headers.', 'royal-links'); ?></p>

                    <h4><?php esc_html_e('Import Limits', 'royal-links'); ?></h4>
                    <p class="description">
                        <?php esc_html_e('Maximum 500 links per import batch. For larger files, import multiple times.', 'royal-links'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle export
     */
    public function handle_export() {
        if (!isset($_POST['royal_links_export']) || !isset($_POST['royal_links_export_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['royal_links_export_nonce'])), 'royal_links_export')) {
            wp_die(esc_html__('Security check failed.', 'royal-links'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export links.', 'royal-links'));
        }

        $format = isset($_POST['export_format']) ? sanitize_text_field(wp_unslash($_POST['export_format'])) : 'csv';
        $include_stats = isset($_POST['include_stats']) && sanitize_text_field(wp_unslash($_POST['include_stats'])) === '1';

        $links = $this->get_all_links($include_stats);

        if ($format === 'json') {
            $this->export_json($links);
        } else {
            $this->export_csv($links);
        }
    }

    /**
     * Get all links for export
     */
    private function get_all_links($include_stats = true) {
        $posts = get_posts(array(
            'post_type'      => 'royal_link',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ));

        $links = array();

        foreach ($posts as $post) {
            $categories = wp_get_object_terms($post->ID, 'royal_link_category', array('fields' => 'names'));
            $tags = wp_get_object_terms($post->ID, 'royal_link_tag', array('fields' => 'names'));

            $link = array(
                'id'              => $post->ID,
                'title'           => $post->post_title,
                'destination_url' => get_post_meta($post->ID, '_royal_links_destination_url', true),
                'slug'            => get_post_meta($post->ID, '_royal_links_slug', true),
                'redirect_type'   => get_post_meta($post->ID, '_royal_links_redirect_type', true),
                'nofollow'        => get_post_meta($post->ID, '_royal_links_nofollow', true) ? 'yes' : 'no',
                'sponsored'       => get_post_meta($post->ID, '_royal_links_sponsored', true) ? 'yes' : 'no',
                'new_tab'         => get_post_meta($post->ID, '_royal_links_new_tab', true) ? 'yes' : 'no',
                'categories'      => implode(',', $categories),
                'tags'            => implode(',', $tags),
                'status'          => $post->post_status,
                'created'         => $post->post_date,
            );

            if ($include_stats) {
                $link['total_clicks'] = get_post_meta($post->ID, '_royal_links_total_clicks', true) ?: 0;
                $link['unique_clicks'] = get_post_meta($post->ID, '_royal_links_unique_clicks', true) ?: 0;
            }

            $links[] = $link;
        }

        return $links;
    }

    /**
     * Export as CSV
     */
    private function export_csv($links) {
        $filename = 'royal-links-export-' . gmdate('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing to php://output stream, not filesystem
        $output = fopen('php://output', 'w');

        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        if (!empty($links)) {
            fputcsv($output, array_keys($links[0]));

            // Data rows
            foreach ($links as $link) {
                fputcsv($output, $link);
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream
        fclose($output);
        exit;
    }

    /**
     * Export as JSON
     */
    private function export_json($links) {
        $filename = 'royal-links-export-' . gmdate('Y-m-d') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($links, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Handle import
     */
    public function handle_import() {
        if (!isset($_POST['royal_links_import']) || !isset($_POST['royal_links_import_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['royal_links_import_nonce'])), 'royal_links_import')) {
            wp_die(esc_html__('Security check failed.', 'royal-links'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to import links.', 'royal-links'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES error code is an integer constant
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_safe_redirect(add_query_arg('import_error', 'no_file', wp_get_referer()));
            exit;
        }

        // Sanitize file upload data
        $file_name = isset($_FILES['import_file']['name']) ? sanitize_file_name(wp_unslash($_FILES['import_file']['name'])) : '';
        $file_tmp = isset($_FILES['import_file']['tmp_name']) ? sanitize_text_field($_FILES['import_file']['tmp_name']) : '';
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $skip_duplicates = isset($_POST['skip_duplicates']) && sanitize_text_field(wp_unslash($_POST['skip_duplicates'])) === '1';

        // Check file extension
        if (!in_array($extension, array('csv', 'json'), true)) {
            wp_safe_redirect(add_query_arg('import_error', 'invalid_format', wp_get_referer()));
            exit;
        }

        $result = array('imported' => 0, 'skipped' => 0, 'errors' => 0);

        if ($extension === 'json') {
            $result = $this->import_json($file_tmp, $skip_duplicates);
        } elseif ($extension === 'csv') {
            $result = $this->import_csv($file_tmp, $skip_duplicates);
        }

        if ($result === false) {
            wp_safe_redirect(add_query_arg('import_error', 'parse_error', wp_get_referer()));
        } else {
            wp_safe_redirect(add_query_arg(array(
                'imported' => $result['imported'],
                'skipped'  => $result['skipped'],
            ), wp_get_referer()));
        }
        exit;
    }

    /**
     * Import from JSON
     */
    private function import_json($file_path, $skip_duplicates = true) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading uploaded temp file
        $content = file_get_contents($file_path);
        $links = json_decode($content, true);

        if (!is_array($links)) {
            return false;
        }

        return $this->import_links($links, $skip_duplicates);
    }

    /**
     * Import from CSV
     */
    private function import_csv($file_path, $skip_duplicates = true) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading uploaded temp file
        $handle = fopen($file_path, 'r');

        if ($handle === false) {
            return false;
        }

        // Get headers
        $headers = fgetcsv($handle);

        if ($headers === false || empty($headers)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temp file handle
            fclose($handle);
            return false;
        }

        // Sanitize headers and remove BOM if present
        $headers = array_map(function($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // Remove UTF-8 BOM
            return sanitize_key(trim($h));
        }, $headers);

        // Check for required column
        if (!in_array('destination_url', $headers, true)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temp file handle
            fclose($handle);
            return false;
        }

        $links = array();

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            // Handle rows with fewer columns than headers
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            $link = array_combine($headers, array_slice($row, 0, count($headers)));
            if ($link !== false) {
                $links[] = $link;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temp file handle
        fclose($handle);

        return $this->import_links($links, $skip_duplicates);
    }

    /**
     * Import links array
     */
    private function import_links($links, $skip_duplicates = true) {
        $result = array(
            'imported' => 0,
            'skipped'  => 0,
        );

        foreach ($links as $link_data) {
            // Skip if missing required fields
            if (empty($link_data['destination_url'])) {
                $result['skipped']++;
                continue;
            }

            $slug = isset($link_data['slug']) ? sanitize_title($link_data['slug']) : '';

            // Check for duplicates
            if ($skip_duplicates && !empty($slug) && Royal_Links_Post_Type::slug_exists($slug)) {
                $result['skipped']++;
                continue;
            }

            $args = array(
                'title'           => isset($link_data['title']) ? sanitize_text_field($link_data['title']) : '',
                'destination_url' => esc_url_raw($link_data['destination_url']),
                'slug'            => $slug,
                'redirect_type'   => isset($link_data['redirect_type']) ? sanitize_text_field($link_data['redirect_type']) : '301',
                'nofollow'        => isset($link_data['nofollow']) && $link_data['nofollow'] === 'yes',
                'sponsored'       => isset($link_data['sponsored']) && $link_data['sponsored'] === 'yes',
                'new_tab'         => isset($link_data['new_tab']) && $link_data['new_tab'] === 'yes',
            );

            // Handle categories
            if (!empty($link_data['categories'])) {
                $categories = array_map('trim', explode(',', $link_data['categories']));
                $args['category'] = $categories;
            }

            // Handle tags
            if (!empty($link_data['tags'])) {
                $tags = array_map('trim', explode(',', $link_data['tags']));
                $args['tags'] = $tags;
            }

            $create_result = Royal_Links_Post_Type::create_link($args);

            if (!is_wp_error($create_result)) {
                $result['imported']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Get available migrations from other plugins
     */
    private function get_available_migrations() {
        global $wpdb;

        $migrations = array();

        // Pretty Links
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}prli_links'") === $wpdb->prefix . 'prli_links') {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}prli_links");
            $migrations['prettylinks'] = array(
                'name'  => 'Pretty Links',
                'count' => intval($count),
            );
        }

        // ThirstyAffiliates (uses custom post type)
        $ta_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'thirstylink' AND post_status = 'publish'"
        );
        if ($ta_count > 0) {
            $migrations['thirstyaffiliates'] = array(
                'name'  => 'ThirstyAffiliates',
                'count' => intval($ta_count),
            );
        }

        // BetterLinks (uses custom post type)
        $bl_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'betterlinks' AND post_status = 'publish'"
        );
        if ($bl_count > 0) {
            $migrations['betterlinks'] = array(
                'name'  => 'BetterLinks',
                'count' => intval($bl_count),
            );
        }

        return $migrations;
    }
}
