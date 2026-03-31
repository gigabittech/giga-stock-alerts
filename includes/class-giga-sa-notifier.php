<?php
/**
 * Restock notifier system.
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Notifier' ) ) {
class Giga_SA_Notifier {

	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'woocommerce_product_set_stock_status', [ $this, 'on_simple_stock_change' ], 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', [ $this, 'on_variation_stock_change' ], 10, 3 );
	}

	/**
	 * Detect simple product stock change.
	 */
	public function on_simple_stock_change( $product_id, $stock_status, $product ): void {
		// WC only fires this hook if the status actually changed.
		if ( 'instock' === $stock_status ) {
			$this->schedule_processing( $product_id, 0 );
		}
	}

	/**
	 * Detect variation stock change.
	 */
	public function on_variation_stock_change( $variation_id, $stock_status, $variation ): void {
		if ( 'instock' === $stock_status ) {
			$parent_id = $variation->get_parent_id();
			$this->schedule_processing( $parent_id, $variation_id );
		}
	}

	/**
	 * DEBOUNCE and async scheduling.
	 */
	private function schedule_processing( int $product_id, int $variation_id ): void {
		$transient_key = "giga_sa_notified_{$product_id}_{$variation_id}";

		if ( get_transient( $transient_key ) ) {
			return; // Already triggered within the 10-minute window
		}

		set_transient( $transient_key, true, 10 * MINUTE_IN_SECONDS );

		// For small subscriber lists, process immediately instead of relying on WP cron
		$subscribers = Giga_SA_DB::get_subscribers_for_product( $product_id, $variation_id, 'confirmed' );
		$batch_size  = (int) get_option( 'giga_sa_batch_size', 50 );

		if ( count( $subscribers ) <= $batch_size ) {
			$this->process_notifications( $product_id, $variation_id );
		} else {
			wp_schedule_single_event( time() + 60, 'giga_sa_process_notifications', [ $product_id, $variation_id ] );
		}
	}

	/**
	 * CRON CALLBACK: Process notification batches.
	 */
	public function process_notifications( int $product_id, int $variation_id ): void {
		// Fetch target product object
		$product = wc_get_product( $variation_id ?: $product_id );
		
		// Verify product is STILL in stock - abort if went back out of stock
		if ( ! $product || ! $product->is_in_stock() ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[Giga Stock Alerts] Skipping notifications — product %d (variation %d) is not in stock or not found.',
					$product_id,
					$variation_id
				) );
			}
			return;
		}

		$subscribers = Giga_SA_DB::get_subscribers_for_product( $product_id, $variation_id, 'confirmed' );
		if ( empty( $subscribers ) ) {
			return;
		}

		$batch_size = (int) get_option( 'giga_sa_batch_size', 50 );
		$batches    = array_chunk( $subscribers, $batch_size );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $sub ) {
				// Dispatch real email via standard Email framework.
				Giga_SA_Email::send_restock_notification( (int) $sub->id );
				
				// Mark as notified immediately
				Giga_SA_DB::update_subscription_status( (int) $sub->id, 'notified', [ 'notified_at' => current_time( 'mysql' ) ] );
			}
			
			// Rate control padding to prevent host email locks
			sleep( 5 );
		}

		// Schedule the retry run for any failed emails
		wp_schedule_single_event( time() + 1800, 'giga_sa_retry_notification', [ $product_id, $variation_id ] );
	}

	/**
	 * RETRY CRON: Check log table for 'failed' entries and retry once.
	 */
	public function retry_failed_notifications( int $product_id, int $variation_id ): void {
		global $wpdb;
		$log_table = esc_sql( $wpdb->prefix . 'giga_stock_alerts_log' );
		$sub_table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// Identify all previously failed sync routines for this direct batch.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$failed_logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.id as log_id, l.subscription_id 
			 FROM `{$log_table}` l 
			 JOIN `{$sub_table}` s ON l.subscription_id = s.id 
			 WHERE s.product_id = %d AND s.variation_id = %d AND l.status = 'failed' AND l.channel = 'restock'",
			$product_id,
			$variation_id
		) );

		if ( empty( $failed_logs ) ) {
			return;
		}

		foreach ( $failed_logs as $log ) {
			// Retry mapping through the API one last time
			Giga_SA_Email::send_restock_notification( (int) $log->subscription_id );
			
			// Regardless of result, mark as 'retried' so it is never spun up again.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$log_table,
				[ 'status' => 'retried' ],
				[ 'id' => $log->log_id ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}
}
}
