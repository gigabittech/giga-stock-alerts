<?php
/**
 * Low Stock Alert Handler for Giga Stock Alerts.
 *
 * When a product's stock quantity drops below the admin-configured threshold,
 * sends an urgency notification to all confirmed restock subscribers for
 * that product — giving them first pick before it sells out.
 *
 * @package GigaStockAlerts
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Low_Stock' ) ) {
class Giga_SA_Low_Stock {

	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'woocommerce_product_set_stock',        [ $this, 'on_product_stock_change' ],   10, 1 );
		add_action( 'woocommerce_variation_set_stock',      [ $this, 'on_variation_stock_change' ], 10, 1 );
		add_action( 'giga_sa_send_low_stock_notifications', [ $this, 'process_notifications' ],     10, 2 );
	}

	/**
	 * Fires when a simple/grouped product's stock is updated.
	 *
	 * @param \WC_Product $product
	 */
	public function on_product_stock_change( \WC_Product $product ): void {
		$this->maybe_schedule( $product->get_id(), 0, (int) $product->get_stock_quantity() );
	}

	/**
	 * Fires when a variation's stock is updated.
	 *
	 * @param \WC_Product_Variation $variation
	 */
	public function on_variation_stock_change( \WC_Product_Variation $variation ): void {
		$this->maybe_schedule( $variation->get_parent_id(), $variation->get_id(), (int) $variation->get_stock_quantity() );
	}

	/**
	 * Check if stock just crossed below threshold and schedule notification.
	 *
	 * @param int $product_id
	 * @param int $variation_id
	 * @param int $quantity     Current stock count.
	 */
	private function maybe_schedule( int $product_id, int $variation_id, int $quantity ): void {
		$threshold = (int) get_option( 'giga_sa_low_stock_threshold', 5 );

		// Disabled or stock not yet below threshold.
		if ( $threshold <= 0 || $quantity < 0 || $quantity > $threshold ) {
			return;
		}

		// Debounce: only notify once per hour per product/variation.
		$transient_key = "giga_sa_low_stock_{$product_id}_{$variation_id}";
		if ( get_transient( $transient_key ) ) {
			return;
		}

		set_transient( $transient_key, true, HOUR_IN_SECONDS );
		wp_schedule_single_event(
			time() + 30,
			'giga_sa_send_low_stock_notifications',
			[ $product_id, $variation_id ]
		);
	}

	/**
	 * CRON CALLBACK: Send low-stock urgency notifications.
	 *
	 * @param int $product_id
	 * @param int $variation_id
	 */
	public function process_notifications( int $product_id, int $variation_id ): void {
		$product = wc_get_product( $variation_id ?: $product_id );
		if ( ! $product ) {
			return;
		}

		$threshold = (int) get_option( 'giga_sa_low_stock_threshold', 5 );
		$stock     = (int) $product->get_stock_quantity();

		// Abort if stock recovered or went completely out.
		if ( $stock <= 0 || $stock > $threshold ) {
			return;
		}

		// Only notify confirmed restock subscribers.
		$subscribers = Giga_SA_DB::get_subscribers_for_product( $product_id, $variation_id, 'confirmed' );
		if ( empty( $subscribers ) ) {
			return;
		}

		foreach ( $subscribers as $sub ) {
			Giga_SA_Email::send_low_stock_notification( (int) $sub->id, $stock );
		}
	}
}
}
