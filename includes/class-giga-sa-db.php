<?php
/**
 * Database handler for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_DB' ) ) {
class Giga_SA_DB {

	// -----------------------------------------------------------------------
	// Schema
	// -----------------------------------------------------------------------

	/**
	 * Create or upgrade the custom database tables via dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate   = $wpdb->get_charset_collate();
		$alerts_table_name = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$log_table_name    = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );

		// v1.1 schema — alert_type + price_watched added, unique key updated.
		$sql_alerts = "CREATE TABLE `{$alerts_table_name}` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			variation_id BIGINT(20) UNSIGNED DEFAULT 0,
			email VARCHAR(255) NOT NULL,
			customer_name VARCHAR(255) DEFAULT '',
			status ENUM('pending','confirmed','notified','purchased','unsubscribed') DEFAULT 'pending',
			alert_type VARCHAR(20) NOT NULL DEFAULT 'restock',
			price_watched DECIMAL(10,2) DEFAULT NULL,
			subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			notified_at DATETIME DEFAULT NULL,
			confirm_token VARCHAR(64) DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email_product_variation_type (email, product_id, variation_id, alert_type),
			KEY product_status (product_id, variation_id, status),
			KEY alert_type (alert_type)
		) {$charset_collate};";

		$sql_log = "CREATE TABLE `{$log_table_name}` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			subscription_id BIGINT(20) UNSIGNED NOT NULL,
			channel VARCHAR(50) NOT NULL,
			status ENUM('sent','failed','retried') NOT NULL,
			error_message TEXT DEFAULT NULL,
			sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY subscription_id (subscription_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_alerts );
		dbDelta( $sql_log );
	}

	/**
	 * Run any pending schema migrations for existing installs.
	 * Called on plugins_loaded so it runs on every request until all
	 * migrations are applied.
	 */
	public static function maybe_run_migrations(): void {
		$db_version = get_option( 'giga_sa_db_version', '1.0.0' );

		if ( version_compare( $db_version, '1.1.0', '<' ) ) {
			self::migrate_v1_1();
			update_option( 'giga_sa_db_version', '1.1.0' );
		}
	}

	/**
	 * v1.1 migration: add alert_type + price_watched columns and update the
	 * unique key from (email, product_id, variation_id) to include alert_type.
	 */
	private static function migrate_v1_1(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'giga_stock_alerts'; // intentionally un-escaped for SHOW / ALTER.

		// Add alert_type column if missing.
		$columns = $wpdb->get_col( "DESCRIBE `{$table}`", 0 ); // phpcs:ignore
		if ( ! in_array( 'alert_type', (array) $columns, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `alert_type` VARCHAR(20) NOT NULL DEFAULT 'restock' AFTER `ip_address`" );
		}
		if ( ! in_array( 'price_watched', (array) $columns, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `price_watched` DECIMAL(10,2) DEFAULT NULL AFTER `alert_type`" );
		}

		// Swap old unique key for the new composite one that includes alert_type.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$old_key = $wpdb->get_var(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME   = '{$table}'
			   AND INDEX_NAME   = 'email_product_variation'"
		);

		if ( $old_key ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `email_product_variation`" );
			$wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `email_product_variation_type` (email, product_id, variation_id, alert_type)" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Drop the custom tables (called from uninstall.php).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$alerts_table_name = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$log_table_name    = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$alerts_table_name}`" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$log_table_name}`" );
	}

	// -----------------------------------------------------------------------
	// Subscription CRUD
	// -----------------------------------------------------------------------

	/**
	 * Insert a new subscription row.
	 *
	 * @param array<string, mixed> $data
	 * @return int|\WP_Error
	 */
	public static function insert_subscription( array $data ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		$product_id   = isset( $data['product_id'] )   ? absint( $data['product_id'] )                                            : 0;
		$variation_id = isset( $data['variation_id'] )  ? absint( $data['variation_id'] )                                          : 0;
		$email        = isset( $data['email'] )         ? sanitize_email( wp_unslash( $data['email'] ) )                           : '';

		if ( empty( $product_id ) || ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid product ID or email.', 'giga-stock-alerts' ) );
		}

		$customer_name = isset( $data['customer_name'] ) ? sanitize_text_field( wp_unslash( $data['customer_name'] ) ) : '';
		$status        = isset( $data['status'] )        ? sanitize_text_field( wp_unslash( $data['status'] ) )        : 'pending';
		$alert_type    = isset( $data['alert_type'] )    ? sanitize_text_field( wp_unslash( $data['alert_type'] ) )    : 'restock';
		$price_watched = isset( $data['price_watched'] ) ? (float) $data['price_watched']                              : null;
		$confirm_token = isset( $data['confirm_token'] ) ? sanitize_text_field( wp_unslash( $data['confirm_token'] ) ) : null;
		$ip_address    = isset( $data['ip_address'] )    ? sanitize_text_field( wp_unslash( $data['ip_address'] ) )    : null;
		$subscribed_at = isset( $data['subscribed_at'] ) ? sanitize_text_field( wp_unslash( $data['subscribed_at'] ) ) : current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			[
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'email'         => $email,
				'customer_name' => $customer_name,
				'status'        => $status,
				'alert_type'    => $alert_type,
				'price_watched' => $price_watched,
				'subscribed_at' => $subscribed_at,
				'confirm_token' => $confirm_token,
				'ip_address'    => $ip_address,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get subscription by email + product + variation (restock alert type, default).
	 */
	public static function get_subscription_by_email_product( string $email, int $product_id, int $variation_id ) {
		return self::get_subscription_by_email_product_type( $email, $product_id, $variation_id, 'restock' );
	}

	/**
	 * Get subscription by email + product + variation + alert_type.
	 *
	 * @param string $email
	 * @param int    $product_id
	 * @param int    $variation_id
	 * @param string $alert_type
	 * @return object|null
	 */
	public static function get_subscription_by_email_product_type(
		string $email,
		int $product_id,
		int $variation_id,
		string $alert_type = 'restock'
	) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE email = %s AND product_id = %d AND variation_id = %d AND alert_type = %s LIMIT 1",
				$email,
				$product_id,
				$variation_id,
				$alert_type
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get all confirmed restock subscribers for a product/variation.
	 *
	 * @param int    $product_id
	 * @param int    $variation_id
	 * @param string $status
	 * @return array<int, object>
	 */
	public static function get_subscribers_for_product( int $product_id, int $variation_id, string $status ): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE product_id = %d AND variation_id = %d AND status = %s AND alert_type = 'restock' ORDER BY subscribed_at ASC",
				$product_id,
				$variation_id,
				$status
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get confirmed price-drop subscribers whose watched price is above new_price.
	 *
	 * @param int   $product_id
	 * @param int   $variation_id
	 * @param float $new_price
	 * @return array<int, object>
	 */
	public static function get_price_drop_subscribers( int $product_id, int $variation_id, float $new_price ): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE product_id = %d AND variation_id = %d AND alert_type = 'price_drop' AND status = 'confirmed' AND price_watched > %f ORDER BY subscribed_at ASC",
				$product_id,
				$variation_id,
				$new_price
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Update the status (and optional extra fields) of a subscription.
	 *
	 * @param int                  $id
	 * @param string               $status
	 * @param array<string, mixed> $extra  Optional extra columns (e.g. notified_at).
	 * @return int
	 */
	public static function update_subscription_status( int $id, string $status, array $extra = [] ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		$data   = [ 'status' => $status ];
		$format = [ '%s' ];

		if ( isset( $extra['notified_at'] ) ) {
			$data['notified_at'] = $extra['notified_at'];
			$format[]            = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return absint( $wpdb->update( $table, $data, [ 'id' => $id ], $format, [ '%d' ] ) );
	}

	/**
	 * Update editable subscription fields.
	 *
	 * @param int                  $id
	 * @param array<string, mixed> $data
	 * @return bool
	 */
	public static function update_subscription( int $id, array $data ): bool {
		global $wpdb;
		$table   = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$allowed = [ 'customer_name', 'email', 'status' ];
		$update  = [];
		$format  = [];

		foreach ( $allowed as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = '%s';
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update( $table, $update, [ 'id' => $id ], $format, [ '%d' ] );
	}

	/**
	 * Get subscription by confirmation token.
	 *
	 * @param string $token
	 * @return object|null
	 */
	public static function get_subscription_by_token( string $token ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE confirm_token = %s LIMIT 1",
				$token
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Count confirmed subscriptions for a product (used for cap checks).
	 *
	 * @param int $product_id
	 * @return int
	 */
	public static function count_subscriptions_by_product( int $product_id ): int {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE product_id = %d AND status = 'confirmed'",
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get all active subscriptions for a subscriber email (for My Account page).
	 * Includes purchased so the user can re-subscribe.
	 *
	 * @param string $email
	 * @return array<int, object>
	 */
	public static function get_subscriptions_by_email( string $email ): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE email = %s AND status IN ('pending','confirmed','notified','purchased') ORDER BY subscribed_at DESC",
				$email
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// -----------------------------------------------------------------------
	// Analytics Queries
	// -----------------------------------------------------------------------

	/**
	 * Get status counts + total subscriber count (for the dashboard KPI cards).
	 * Results are cached in the WP object cache for 5 minutes.
	 *
	 * @return array<string, int>
	 */
	public static function get_subscriber_stats_cached(): array {
		$cache_key = 'giga_sa_subscriber_stats';
		$stats     = wp_cache_get( $cache_key, 'giga_stock_alerts' );

		if ( false === $stats ) {
			global $wpdb;
			$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts = $wpdb->get_results( "SELECT status, COUNT(*) as count FROM `{$table}` GROUP BY status", OBJECT_K );
			$total  = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$stats = [
				'total'     => (int) $total,
				'confirmed' => isset( $counts['confirmed'] ) ? (int) $counts['confirmed']->count : 0,
				'notified'  => isset( $counts['notified'] )  ? (int) $counts['notified']->count  : 0,
				'purchased' => isset( $counts['purchased'] ) ? (int) $counts['purchased']->count : 0,
			];

			wp_cache_set( $cache_key, $stats, 'giga_stock_alerts', 300 );
		}

		return $stats;
	}

	/**
	 * Get top N products by confirmed/notified/purchased subscriber count.
	 * Static version for use outside the admin list-table class.
	 *
	 * @param int $limit
	 * @return array<int, object>
	 */
	public static function get_top_wanted_products_static( int $limit = 5 ): array {
		global $wpdb;
		$table     = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$cache_key = 'giga_sa_top_products_' . $limit;
		$results   = wp_cache_get( $cache_key, 'giga_stock_alerts' );

		if ( false === $results ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT product_id, COUNT(*) AS request_count
					 FROM `{$table}`
					 WHERE status IN ('confirmed','notified','purchased')
					 GROUP BY product_id
					 ORDER BY request_count DESC
					 LIMIT %d",
					$limit
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			wp_cache_set( $cache_key, $results, 'giga_stock_alerts', 300 );
		}

		return $results ?: [];
	}

	/**
	 * Get statistics for the past 7 days (for the weekly digest).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_weekly_stats(): array {
		global $wpdb;
		$alerts_table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$log_table    = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );
		$week_ago     = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_subs = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$alerts_table}` WHERE subscribed_at >= %s",
				$week_ago
			)
		);

		$total_subs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$alerts_table}`" );

		$notified_this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$log_table}` WHERE status = 'sent' AND sent_at >= %s",
				$week_ago
			)
		);

		$purchased_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$alerts_table}` WHERE status = 'purchased'"
		);

		$notified_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$alerts_table}` WHERE status IN ('notified','purchased')"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$conversion_rate = $notified_total > 0
			? round( ( $purchased_total / $notified_total ) * 100, 1 )
			: 0.0;

		return [
			'new_subscribers'     => $new_subs,
			'total_subscribers'   => $total_subs,
			'notifications_sent'  => $notified_this_week,
			'conversion_rate'     => $conversion_rate,
		];
	}

	// -----------------------------------------------------------------------
	// Logging
	// -----------------------------------------------------------------------

	/**
	 * Log a notification send attempt.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false
	 */
	public static function log_notification( array $data ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );

		$subscription_id = isset( $data['subscription_id'] ) ? absint( $data['subscription_id'] )                                        : 0;
		$channel         = isset( $data['channel'] )         ? sanitize_text_field( wp_unslash( $data['channel'] ) )                     : '';
		$status          = isset( $data['status'] )          ? sanitize_text_field( wp_unslash( $data['status'] ) )                      : '';
		$error_message   = isset( $data['error_message'] )   ? wp_unslash( $data['error_message'] )                                      : null;
		$sent_at         = isset( $data['sent_at'] )         ? sanitize_text_field( wp_unslash( $data['sent_at'] ) )                     : current_time( 'mysql' );

		if ( empty( $subscription_id ) || empty( $channel ) || empty( $status ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			[
				'subscription_id' => $subscription_id,
				'channel'         => $channel,
				'status'          => $status,
				'error_message'   => $error_message,
				'sent_at'         => $sent_at,
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		return false !== $result ? (int) $wpdb->insert_id : false;
	}
}
}
