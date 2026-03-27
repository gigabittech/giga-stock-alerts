<?php
/**
 * Uninstall routine for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Ensure database commands are accessible natively through the db handler fallback.
require_once dirname( __FILE__ ) . '/includes/class-giga-sa-db.php';
Giga_SA_DB::drop_tables();

global $wpdb;

// Delete all standard exact configurations natively tracked explicitly inside options array.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'giga_sa_%'" );

// Purge all transients related directly to our limits and locking mechanics
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_giga_sa_%' OR option_name LIKE '_transient_timeout_giga_sa_%'" );

// Clear all potential queued CRON processes cleanly natively inside WordPress architecture natively.
wp_clear_scheduled_hook( 'giga_sa_process_notifications' );
wp_clear_scheduled_hook( 'giga_sa_retry_notification' );
