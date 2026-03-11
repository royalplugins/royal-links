<?php
/**
 * WordPress admin dashboard widget.
 *
 * Shows a quick-glance overview of link performance on the
 * WP Admin Dashboard (index.php). Boxed stat cards with
 * period-over-period change indicators.
 *
 * @package Royal_Links
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Royal_Links_Dashboard_Widget {

	/**
	 * Register the dashboard widget.
	 */
	public static function register() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'royal_links_dashboard_widget',
			__( 'Royal Links — Overview', 'royal-links' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Calculate percentage change between two values.
	 *
	 * @param float $current  Current period value.
	 * @param float $previous Previous period value.
	 * @return array { direction: 'up'|'down'|'neutral', value: float }
	 */
	private static function calc_change( $current, $previous ) {
		if ( $previous == 0 && $current == 0 ) {
			return array( 'direction' => 'neutral', 'value' => 0 );
		}
		if ( $previous == 0 ) {
			return array( 'direction' => 'up', 'value' => 100 );
		}

		$change = round( ( ( $current - $previous ) / $previous ) * 100, 1 );

		if ( $change > 0 ) {
			return array( 'direction' => 'up', 'value' => $change );
		} elseif ( $change < 0 ) {
			return array( 'direction' => 'down', 'value' => abs( $change ) );
		}

		return array( 'direction' => 'neutral', 'value' => 0 );
	}

	/**
	 * Render change badge HTML.
	 *
	 * @param array $change Change data from calc_change().
	 * @param bool  $invert If true, "down" is good (e.g. broken links).
	 */
	private static function render_change_badge( $change, $invert = false ) {
		$dir   = $change['direction'];
		$value = $change['value'];

		if ( 'neutral' === $dir ) {
			echo '<span class="rl-dw-change neutral"><span class="dashicons dashicons-minus"></span>0%</span>';
			return;
		}

		$icon = 'up' === $dir ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt';
		$css  = $invert ? ( 'up' === $dir ? 'down' : 'up' ) : $dir;

		printf(
			'<span class="rl-dw-change %s"><span class="dashicons %s"></span>%s%%</span>',
			esc_attr( $css ),
			esc_attr( $icon ),
			esc_html( $value )
		);
	}

	/**
	 * Render the dashboard widget.
	 */
	public static function render() {
		global $wpdb;

		// --- Periods ---
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$sixty_days_ago  = gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) );

		// Total active links.
		$total_links = (int) wp_count_posts( 'royal_link' )->publish;

		// Clicks: current 30d vs previous 30d.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$month_clicks = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date >= %s",
			$thirty_days_ago
		) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$prev_clicks = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date >= %s AND click_date < %s",
			$sixty_days_ago, $thirty_days_ago
		) );
		$clicks_change = self::calc_change( $month_clicks, $prev_clicks );

		// Unique links clicked: current 30d vs previous 30d.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$month_unique = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT link_id) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date >= %s",
			$thirty_days_ago
		) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$prev_unique = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT link_id) FROM {$wpdb->prefix}royal_links_clicks WHERE click_date >= %s AND click_date < %s",
			$sixty_days_ago, $thirty_days_ago
		) );
		$unique_change = self::calc_change( $month_unique, $prev_unique );

		// New links created: current 30d vs previous 30d.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$month_new_links = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'royal_link' AND post_status = 'publish' AND post_date >= %s",
			$thirty_days_ago
		) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$prev_new_links = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'royal_link' AND post_status = 'publish' AND post_date >= %s AND post_date < %s",
			$sixty_days_ago, $thirty_days_ago
		) );
		$new_links_change = self::calc_change( $month_new_links, $prev_new_links );

		// Broken links count.
		$broken_count = (int) Royal_Links_Link_Checker::get_broken_count();

		// Top performing links (last 30 days, up to 5).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$top_links = $wpdb->get_results( $wpdb->prepare(
			"SELECT link_id, COUNT(*) as clicks
			FROM {$wpdb->prefix}royal_links_clicks
			WHERE click_date >= %s
			GROUP BY link_id
			ORDER BY clicks DESC
			LIMIT 5",
			$thirty_days_ago
		), ARRAY_A );

		?>

		<div class="rl-dw-wrap">

			<!-- Header -->
			<div class="rl-dw-header">
				<span class="rl-dw-header-period"><?php esc_html_e( 'Last 30 Days', 'royal-links' ); ?></span>
				<span class="rl-dw-header-timeframe"><?php esc_html_e( 'vs. previous 30 days', 'royal-links' ); ?></span>
			</div>

			<!-- Stat Boxes -->
			<div class="rl-dw-grid">
				<!-- Clicks -->
				<div class="rl-dw-box">
					<div class="rl-dw-box-label"><?php esc_html_e( 'Clicks', 'royal-links' ); ?></div>
					<div class="rl-dw-box-value">
						<span class="rl-dw-box-num"><?php echo esc_html( number_format( $month_clicks ) ); ?></span>
						<?php self::render_change_badge( $clicks_change ); ?>
					</div>
				</div>

				<!-- Unique Links Clicked -->
				<div class="rl-dw-box">
					<div class="rl-dw-box-label"><?php esc_html_e( 'Links Clicked', 'royal-links' ); ?></div>
					<div class="rl-dw-box-value">
						<span class="rl-dw-box-num"><?php echo esc_html( number_format( $month_unique ) ); ?></span>
						<?php self::render_change_badge( $unique_change ); ?>
					</div>
				</div>

				<!-- New Links -->
				<div class="rl-dw-box">
					<div class="rl-dw-box-label"><?php esc_html_e( 'New Links', 'royal-links' ); ?></div>
					<div class="rl-dw-box-value">
						<span class="rl-dw-box-num"><?php echo esc_html( number_format( $month_new_links ) ); ?></span>
						<?php self::render_change_badge( $new_links_change ); ?>
					</div>
				</div>

				<!-- Total Links -->
				<div class="rl-dw-box">
					<div class="rl-dw-box-label"><?php esc_html_e( 'Total Links', 'royal-links' ); ?></div>
					<div class="rl-dw-box-value">
						<span class="rl-dw-box-num"><?php echo esc_html( number_format( $total_links ) ); ?></span>
					</div>
				</div>

				<!-- Broken Links -->
				<?php if ( $broken_count > 0 ) : ?>
				<div class="rl-dw-box rl-dw-box-highlight">
					<div class="rl-dw-box-label">
						<?php esc_html_e( 'Broken Links', 'royal-links' ); ?>
						<span class="rl-dw-badge rl-dw-badge-warning">
							<?php esc_html_e( 'needs attention', 'royal-links' ); ?>
						</span>
					</div>
					<div class="rl-dw-box-value">
						<span class="rl-dw-box-num"><?php echo esc_html( number_format( $broken_count ) ); ?></span>
					</div>
				</div>
				<?php else : ?>
				<div class="rl-dw-box">
					<div class="rl-dw-box-label"><?php esc_html_e( 'Broken Links', 'royal-links' ); ?></div>
					<div class="rl-dw-box-value">
						<span class="rl-dw-box-num">0</span>
						<span class="rl-dw-change up"><span class="dashicons dashicons-yes"></span><?php esc_html_e( 'healthy', 'royal-links' ); ?></span>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<!-- Broken Links Warning Bar -->
			<?php if ( $broken_count > 0 ) : ?>
			<div class="rl-dw-section" style="background: #fffbeb; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;">
				<span style="font-size: 13px; color: #92400e; font-weight: 500;">
					<?php
					printf(
						/* translators: %d: number of broken links */
						esc_html__( '%d broken link(s) need fixing', 'royal-links' ),
						intval( $broken_count )
					);
					?>
				</span>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=royal_link&page=royal-links-health' ) ); ?>" style="font-size: 13px; font-weight: 500; color: #92400e; text-decoration: none;">
					<?php esc_html_e( 'View &rarr;', 'royal-links' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<!-- Top Performing Links -->
			<div class="rl-dw-section">
				<h4><?php esc_html_e( 'Top Performing Links', 'royal-links' ); ?></h4>
				<?php if ( empty( $top_links ) ) : ?>
					<p class="rl-dw-empty"><?php esc_html_e( 'No clicks recorded yet.', 'royal-links' ); ?></p>
				<?php else : ?>
					<?php foreach ( $top_links as $link ) :
						$post = get_post( $link['link_id'] );
						if ( ! $post ) {
							continue;
						}
					?>
					<div class="rl-dw-row">
						<div>
							<a href="<?php echo esc_url( get_edit_post_link( $link['link_id'] ) ); ?>">
								<?php echo esc_html( $post->post_title ); ?>
							</a>
						</div>
						<span class="rl-dw-clicks-count">
							<?php
							printf(
								/* translators: %s: number of clicks */
								esc_html__( '%s clicks', 'royal-links' ),
								esc_html( number_format( $link['clicks'] ) )
							);
							?>
						</span>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Footer -->
			<div class="rl-dw-footer">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=royal_link' ) ); ?>">
					<?php esc_html_e( 'Manage Links', 'royal-links' ); ?> &rarr;
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=royal_link&page=royal-links-analytics' ) ); ?>">
					<?php esc_html_e( 'View Analytics', 'royal-links' ); ?> &rarr;
				</a>
			</div>

		</div>
		<?php
	}
}
