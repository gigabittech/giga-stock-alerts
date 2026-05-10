<?php
/**
 * Database handler for Giga Stock Alerts.
 *
 * Responsible for creating and managing the custom database tables and
 * executing DB queries.
 *
 * @package GigaStockAlerts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Giga_SA_DB
 *
 * Manages the custom database tables and all CRUD operations.
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'Giga_SA_DB' ) ) {
class Giga_SA_DB {

	// -----------------------------------------------------------------------
	// Schema
	// -----------------------------------------------------------------------

	/**
	 * Create (or upgrade) the custom database tables.
	 *
	 * Uses `dbDelta()` so it is safe to run on upgrades.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate    = $wpdb->get_charset_collate();
		$alerts_table_name  = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$log_table_name     = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );

		$sql_alerts = "CREATE TABLE `{$alerts_table_name}` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			variation_id BIGINT(20) UNSIGNED DEFAULT 0,
			email VARCHAR(255) NOT NULL,
			customer_name VARCHAR(255) DEFAULT '',
			status ENUM('pending','confirmed','notified','purchased','unsubscribed') DEFAULT 'pending',
			subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			notified_at DATETIME DEFAULT NULL,
			confirm_token VARCHAR(64) DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email_product_variation (email, product_id, variation_id),
			KEY product_status (product_id, variation_id, status)
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
	 * Drop the custom database tables.
	 *
	 * Called from uninstall.php — only runs when the plugin is fully deleted.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$alerts_table_name = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$log_table_name    = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );

		// Table names cannot be parameterized — using direct query intentionally.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$alerts_table_name}`" );
		
		// Table names cannot be parameterized — using direct query intentionally.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$log_table_name}`" );
	}

	// -----------------------------------------------------------------------
	// Subscriptions Data Access
	// -----------------------------------------------------------------------

	/**
	 * Insert a new subscription row.
	 *
	 * @param array<string, mixed> $data Subscription data.
	 * @return int|\WP_Error Inserted row ID on success, WP_Error on failure.
	 */
	public static function insert_subscription( array $data ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		$product_id   = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
		$variation_id = isset( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0;
		$email        = isset( $data['email'] ) ? sanitize_email( wp_unslash( $data['email'] ) ) : '';

		if ( empty( $product_id ) || ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid product ID or email.', 'giga-stock-alerts' ) );
		}

		$customer_name = isset( $data['customer_name'] ) ? sanitize_text_field( wp_unslash( $data['customer_name'] ) ) : '';
		$status        = isset( $data['status'] ) ? sanitize_text_field( wp_unslash( $data['status'] ) ) : 'pending';
		$confirm_token = isset( $data['confirm_token'] ) ? sanitize_text_field( wp_unslash( $data['confirm_token'] ) ) : null;
		$ip_address    = isset( $data['ip_address'] ) ? sanitize_text_field( wp_unslash( $data['ip_address'] ) ) : null;
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
				'subscribed_at' => $subscribed_at,
				'confirm_token' => $confirm_token,
				'ip_address'    => $ip_address,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get subscription by email, product, and variation.
	 *
	 * @param string $email        Subscriber email.
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * return object|null Database row object or null.
	 */
	public static function get_subscription_by_email_product( string $email, int $product_id, int $variation_id ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// Table name is from $wpdb->prefix (safe). No user input in SQL structure.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE email = %s AND product_id = %d AND variation_id = %d LIMIT 1",
				$email,
				$product_id,
				$variation_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get all subscribers for a specific product/variation with a given status.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $status       The subscription status.
	 * @return array<int, object> Array of database row objects.
	 */
	public static function get_subscribers_for_product( int $product_id, int $variation_id, string $status ): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// Table name is from $wpdb->prefix (safe). No user input in SQL structure.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE product_id = %d AND variation_id = %d AND status = %s ORDER BY subscribed_at ASC",
				$product_id,
				$variation_id,
				$status
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Update the status (and potentially other data) of a subscription.
	 *
	 * @param int                  $id     Subscription ID.
	 * @param string               $status New status.
	 * @param array<string, mixed> $extra  Extra columns to update (e.g., notified_at).
	 * @return int Number of rows updated or false on error.
	 */
	public static function update_subscription_status( int $id, string $status, array $extra = [] ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		$data   = [ 'status' => $status ];
		$format = [ '%s' ];

		if ( isset( $extra['notified_at'] ) ) {
			$data['notified_at'] = $extra['notified_at'];
			$format[] = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return absint( $wpdb->update(
			$table,
			$data,
			[ 'id' => $id ],
			$format,
			[ '%d' ]
		) );
	}

	/**
	 * Update subscription fields (customer_name, email, status).
	 *
	 * @param int                  $id   Subscription ID.
	 * @param array<string, mixed> $data Associative array of columns to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update_subscription( int $id, array $data ): bool {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

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
		$result = $wpdb->update(
			$table,
			$update,
			[ 'id' => $id ],
			$format,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Get subscription by confirmation token.
	 *
	 * @param string $token Confirmation token.
	 * @return object|null
	 */
	public static function get_subscription_by_token( string $token ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// Table name is from $wpdb->prefix (safe). No user input in SQL structure.
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
	 * Count waiting (confirmed) subscriptions for a given product ID.
	 *
	 * @param int $product_id
	 * @return int
	 */
	public static function count_subscriptions_by_product( int $product_id ): int {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// Table name is from $wpdb->prefix (safe). No user input in SQL structure.
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
	 * Get active subscriptions (confirmed/notified) for a subscriber email.
	 *
	 * @param string $email
	 * @return array<int, object>
	 */
	public static function get_subscriptions_by_email( string $email ): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// Table name is from $wpdb->prefix (safe). No user input in SQL structure.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE email = %s AND status IN ('confirmed', 'notified') ORDER BY subscribed_at DESC",
				$email
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// -----------------------------------------------------------------------
	// Logging Data Access
	// -----------------------------------------------------------------------

	/**
	 * Log a notification attempt.
	 *
	 * @param array<string, mixed> $data Log data.
	 * @return int|false ID of log entry or false.
	 */
	public static function log_notification( array $data ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );

		$subscription_id = isset( $data['subscription_id'] ) ? absint( $data['subscription_id'] ) : 0;
		$channel         = isset( $data['channel'] ) ? sanitize_text_field( wp_unslash( $data['channel'] ) ) : '';
		$status          = isset( $data['status'] ) ? sanitize_text_field( wp_unslash( $data['status'] ) ) : '';
		$error_message   = isset( $data['error_message'] ) ? wp_unslash( $data['error_message'] ) : null;
		$sent_at         = isset( $data['sent_at'] ) ? sanitize_text_field( wp_unslash( $data['sent_at'] ) ) : current_time( 'mysql' );

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
				'error_message'   => $error_message, // TEXT can accept larger strings
				'sent_at'         => $sent_at,
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		return false !== $result ? (int) $wpdb->insert_id : false;
	}
}
}
