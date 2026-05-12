<?php
/**
 * Uninstall routine for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Always clear scheduled cron events on uninstall.
wp_clear_scheduled_hook( 'giga_sa_process_notifications' );
wp_clear_scheduled_hook( 'giga_sa_retry_notification' );
wp_clear_scheduled_hook( 'giga_sa_auto_confirm_cleanup' );

// Only drop tables and delete options when the admin has opted in to "Delete data on uninstall".
$delete_data = get_option( 'giga_sa_delete_data', false );

if ( filter_var( $delete_data, FILTER_VALIDATE_BOOLEAN ) ) {
	// Drop custom tables.
	require_once dirname( __FILE__ ) . '/includes/class-giga-sa-db.php';
	Giga_SA_DB::drop_tables();

	global $wpdb;

	// Delete all plugin options.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'giga_sa_%' ) );

	// Purge plugin transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_giga_sa_%', '_transient_timeout_giga_sa_%' ) );
}
