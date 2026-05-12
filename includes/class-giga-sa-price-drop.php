<?php
/**
 * Price Drop Alert Handler for Giga Stock Alerts.
 *
 * Hooks into WooCommerce price meta updates, detects drops, and queues
 * email notifications to all confirmed price-drop subscribers.
 *
 * @package GigaStockAlerts
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Price_Drop' ) ) {
class Giga_SA_Price_Drop {

	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		// Fires BEFORE post meta is saved — lets us compare old vs new price.
		add_filter( 'update_post_metadata', [ $this, 'detect_price_drop' ], 10, 4 );

		// Cron callback for processing notifications.
		add_action( 'giga_sa_process_price_drop', [ $this, 'process_notifications' ], 10, 3 );

		// AJAX: subscribe to price drop alert.
		add_action( 'wp_ajax_giga_sa_price_drop_subscribe',        [ $this, 'ajax_subscribe' ] );
		add_action( 'wp_ajax_nopriv_giga_sa_price_drop_subscribe', [ $this, 'ajax_subscribe' ] );
	}

	// -----------------------------------------------------------------------
	// Price change detection
	// -----------------------------------------------------------------------

	/**
	 * Fires before post meta is written. Compares old vs new price and
	 * schedules notification if the price actually dropped.
	 *
	 * @param mixed  $check      null to proceed normally.
	 * @param int    $object_id  Post (product/variation) ID.
	 * @param string $meta_key   Meta key being updated.
	 * @param mixed  $meta_value New value.
	 * @return mixed
	 */
	public function detect_price_drop( $check, int $object_id, string $meta_key, $meta_value ) {
		if ( ! in_array( $meta_key, [ '_price', '_sale_price' ], true ) ) {
			return $check;
		}

		$post = get_post( $object_id );
		if ( ! $post || ! in_array( $post->post_type, [ 'product', 'product_variation' ], true ) ) {
			return $check;
		}

		$old_price = (float) get_post_meta( $object_id, '_price', true );
		$new_price = (float) $meta_value;

		// Only act on real price drops (both positive, new is lower).
		if ( $old_price <= 0 || $new_price <= 0 || $new_price >= $old_price ) {
			return $check;
		}

		$is_variation = ( 'product_variation' === $post->post_type );
		$variation_id = $is_variation ? $object_id : 0;
		$product_id   = $is_variation ? (int) $post->post_parent : $object_id;

		// Debounce: schedule only once per 5-minute window per product.
		$transient_key = "giga_sa_price_drop_{$product_id}_{$variation_id}";
		if ( ! get_transient( $transient_key ) ) {
			set_transient( $transient_key, $new_price, 5 * MINUTE_IN_SECONDS );
			wp_schedule_single_event(
				time() + 60,
				'giga_sa_process_price_drop',
				[ $product_id, $variation_id, $new_price ]
			);
		}

		return $check; // null — lets WordPress proceed with the normal meta update.
	}

	// -----------------------------------------------------------------------
	// Cron callback
	// -----------------------------------------------------------------------

	/**
	 * Send price drop notifications to all matching confirmed subscribers.
	 *
	 * @param int   $product_id   Parent product ID.
	 * @param int   $variation_id Variation ID (0 for simple products).
	 * @param float $new_price    The price that triggered the drop.
	 */
	public function process_notifications( int $product_id, int $variation_id, float $new_price ): void {
		$subscribers = Giga_SA_DB::get_price_drop_subscribers( $product_id, $variation_id, $new_price );

		if ( empty( $subscribers ) ) {
			return;
		}

		foreach ( $subscribers as $sub ) {
			$sent = Giga_SA_Email::send_price_drop_notification( (int) $sub->id, $new_price );
			if ( $sent ) {
				Giga_SA_DB::update_subscription_status(
					(int) $sub->id,
					'notified',
					[ 'notified_at' => current_time( 'mysql' ) ]
				);
			}
		}
	}

	// -----------------------------------------------------------------------
	// AJAX subscription handler
	// -----------------------------------------------------------------------

	/**
	 * Handle AJAX price drop subscription form submission.
	 */
	public function ajax_subscribe(): void {
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
			'giga_sa_subscribe_nonce'
		) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'giga-stock-alerts' ) ] );
		}

		$email        = isset( $_POST['email'] )        ? sanitize_email( wp_unslash( $_POST['email'] ) )                 : '';
		$name         = isset( $_POST['name'] )         ? sanitize_text_field( wp_unslash( $_POST['name'] ) )             : '';
		$product_id   = isset( $_POST['product_id'] )   ? absint( $_POST['product_id'] )                                  : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] )                                : 0;
		$gdpr         = isset( $_POST['gdpr'] )         ? sanitize_text_field( wp_unslash( $_POST['gdpr'] ) )             : '0';

		if ( ! is_email( $email ) || ! $product_id || '1' !== $gdpr ) {
			wp_send_json_error( [ 'message' => __( 'Invalid email, product, or missing GDPR consent.', 'giga-stock-alerts' ) ] );
		}

		// Per-product: respect max subscriber cap (shared across alert types).
		$max = Giga_SA_Product_Meta::get_max_subscribers( $product_id );
		if ( $max > 0 ) {
			$current_count = Giga_SA_DB::count_subscriptions_by_product( $product_id );
			if ( $current_count >= $max ) {
				wp_send_json_error( [ 'message' => __( 'This product has reached its maximum number of price alert subscribers.', 'giga-stock-alerts' ) ] );
			}
		}

		$product = wc_get_product( $variation_id ?: $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'giga-stock-alerts' ) ] );
		}

		$current_price = (float) wc_get_price_to_display( $product );

		// Check for existing active price-drop subscription.
		$existing = Giga_SA_DB::get_subscription_by_email_product_type( $email, $product_id, $variation_id, 'price_drop' );
		if ( $existing && in_array( $existing->status, [ 'pending', 'confirmed', 'notified' ], true ) ) {
			wp_send_json_error( [
				'message' => __( "You're already watching this product's price!", 'giga-stock-alerts' ),
				'code'    => 'duplicate',
			] );
		}

		// Rate limiting.
		$ip         = $this->get_client_ip();
		$rate_key   = 'giga_sa_rate_pd_' . md5( $ip );
		$count      = (int) get_transient( $rate_key );
		$rate_limit = (int) get_option( 'giga_sa_rate_limit', 3 );
		if ( $count >= $rate_limit ) {
			wp_send_json_error( [ 'message' => __( 'Rate limit exceeded. Try again later.', 'giga-stock-alerts' ) ] );
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$double_optin  = filter_var( get_option( 'giga_sa_double_optin', true ), FILTER_VALIDATE_BOOLEAN );
		$confirm_token = $double_optin ? bin2hex( random_bytes( 32 ) ) : null;

		$insert_id = Giga_SA_DB::insert_subscription( [
			'product_id'    => $product_id,
			'variation_id'  => $variation_id,
			'email'         => $email,
			'customer_name' => $name,
			'status'        => $double_optin ? 'pending' : 'confirmed',
			'confirm_token' => $confirm_token,
			'alert_type'    => 'price_drop',
			'price_watched' => $current_price,
			'ip_address'    => substr( $ip, 0, 45 ),
		] );

		if ( is_wp_error( $insert_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not save subscription.', 'giga-stock-alerts' ) ] );
		}

		if ( $double_optin ) {
			Giga_SA_Email::send_confirmation( (int) $insert_id );
			$msg = __( 'Please check your email to confirm your price alert subscription.', 'giga-stock-alerts' );
		} else {
			$msg = __( 'Done! We will notify you when the price drops.', 'giga-stock-alerts' );
		}

		wp_send_json_success( [ 'message' => $msg ] );
	}

	private function get_client_ip(): string {
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				return trim( $ip[0] );
			}
		}
		return '0.0.0.0';
	}
}
}
