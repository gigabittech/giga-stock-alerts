<?php
/**
 * Subscription Form Handler.
 *
 * Handles restock subscription, confirmation, unsubscribe, and re-subscribe flows.
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Subscription' ) ) {
class Giga_SA_Subscription {

	public function __construct( Giga_SA_DB $db = null ) {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'wp_ajax_giga_sa_subscribe',           [ $this, 'ajax_subscribe' ] );
		add_action( 'wp_ajax_nopriv_giga_sa_subscribe',    [ $this, 'ajax_subscribe' ] );
		add_action( 'init',                                 [ $this, 'handle_confirmation' ] );
		add_action( 'init',                                 [ $this, 'handle_unsubscribe' ] );
		add_action( 'woocommerce_order_status_completed',   [ $this, 'track_purchases' ] );
		add_action( 'woocommerce_order_status_processing',  [ $this, 'track_purchases' ] );
		add_action( 'wp_ajax_giga_sa_my_account_unsubscribe', [ $this, 'ajax_my_account_unsubscribe' ] );
		add_action( 'wp_ajax_giga_sa_resubscribe',          [ $this, 'ajax_resubscribe' ] );
	}

	// -----------------------------------------------------------------------
	// Subscribe
	// -----------------------------------------------------------------------

	public function ajax_subscribe(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'giga_sa_subscribe_nonce' ) ) {
			wp_send_json_error( [ 'code' => 'invalid_nonce', 'message' => __( 'Security check failed.', 'giga-stock-alerts' ) ] );
		}

		$email        = isset( $_POST['email'] )        ? sanitize_email( wp_unslash( $_POST['email'] ) )       : '';
		$name         = isset( $_POST['name'] )         ? sanitize_text_field( wp_unslash( $_POST['name'] ) )   : '';
		$product_id   = isset( $_POST['product_id'] )   ? absint( $_POST['product_id'] )                        : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] )                      : 0;
		$gdpr         = isset( $_POST['gdpr'] )         ? sanitize_text_field( wp_unslash( $_POST['gdpr'] ) )   : '0';
		$alert_type   = isset( $_POST['alert_type'] )   ? sanitize_text_field( wp_unslash( $_POST['alert_type'] ) ) : 'restock';

		// Validate alert_type.
		if ( ! in_array( $alert_type, [ 'restock', 'price_drop' ], true ) ) {
			$alert_type = 'restock';
		}

		// Price drop subscriptions are handled by Giga_SA_Price_Drop::ajax_subscribe().
		if ( 'price_drop' === $alert_type ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'giga-stock-alerts' ) ] );
		}

		if ( ! is_email( $email ) || ! $product_id || '1' !== $gdpr ) {
			wp_send_json_error( [ 'message' => __( 'Invalid email, product, or missing GDPR consent.', 'giga-stock-alerts' ) ] );
		}

		// Per-product max subscriber cap.
		if ( class_exists( 'Giga_SA_Product_Meta' ) ) {
			$max = Giga_SA_Product_Meta::get_max_subscribers( $product_id );
			if ( $max > 0 ) {
				$current_count = Giga_SA_DB::count_subscriptions_by_product( $product_id );
				if ( $current_count >= $max ) {
					wp_send_json_error( [ 'message' => __( 'This product has reached its maximum number of subscribers.', 'giga-stock-alerts' ) ] );
				}
			}
		}

		// Rate limiting.
		$ip        = $this->get_client_ip();
		$rate_key  = 'giga_sa_rate_' . md5( $ip );
		$count     = (int) get_transient( $rate_key );
		$rate_limit = (int) get_option( 'giga_sa_rate_limit', 3 );
		if ( $count >= $rate_limit ) {
			wp_send_json_error( [ 'message' => __( 'Rate limit exceeded. Try again later.', 'giga-stock-alerts' ) ] );
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$double_optin = filter_var( get_option( 'giga_sa_double_optin', false ), FILTER_VALIDATE_BOOLEAN );
		$existing     = Giga_SA_DB::get_subscription_by_email_product( $email, $product_id, $variation_id );

		if ( $existing ) {
			if ( 'pending' === $existing->status ) {
				if ( $double_optin ) {
					Giga_SA_Email::send_confirmation( (int) $existing->id );
				} else {
					Giga_SA_DB::update_subscription_status( (int) $existing->id, 'confirmed' );
					Giga_SA_Email::send_admin_alert( (int) $existing->id );
				}
				wp_send_json_success( [ 'message' => __( 'Subscription processed successfully.', 'giga-stock-alerts' ) ] );
			} else {
				wp_send_json_error( [
					'message' => __( "You're already subscribed!", 'giga-stock-alerts' ),
					'code'    => 'duplicate',
				] );
			}
		}

		$confirm_token = bin2hex( random_bytes( 32 ) );

		$insert_id = Giga_SA_DB::insert_subscription( [
			'product_id'    => $product_id,
			'variation_id'  => $variation_id,
			'email'         => $email,
			'customer_name' => $name,
			'status'        => $double_optin ? 'pending' : 'confirmed',
			'confirm_token' => $double_optin ? $confirm_token : null,
			'alert_type'    => 'restock',
			'ip_address'    => ltrim( substr( $ip, 0, 45 ) ),
		] );

		if ( is_wp_error( $insert_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Database error. Could not save.', 'giga-stock-alerts' ) ] );
		}

		if ( $double_optin ) {
			Giga_SA_Email::send_confirmation( (int) $insert_id );
			$msg = __( 'Please check your email to confirm your subscription.', 'giga-stock-alerts' );
		} else {
			Giga_SA_Email::send_admin_alert( (int) $insert_id );
			$msg = __( 'Great! We will notify you when this is back in stock.', 'giga-stock-alerts' );
		}

		wp_send_json_success( [ 'message' => $msg ] );
	}

	// -----------------------------------------------------------------------
	// Re-subscribe (My Account)
	// -----------------------------------------------------------------------

	/**
	 * AJAX handler: allows a logged-in user to re-subscribe for a product they
	 * previously purchased or unsubscribed from.
	 */
	public function ajax_resubscribe(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'giga-stock-alerts' ) ] );
		}

		$sub_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
		$nonce  = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'giga_sa_resubscribe_' . $sub_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'giga-stock-alerts' ) ] );
		}

		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $sub_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub ) {
			wp_send_json_error( [ 'message' => __( 'Subscription not found.', 'giga-stock-alerts' ) ] );
		}

		$current_user = wp_get_current_user();
		if ( $current_user->user_email !== $sub->email ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'giga-stock-alerts' ) ] );
		}

		// Only allow re-subscribe from purchased or unsubscribed state.
		if ( ! in_array( $sub->status, [ 'purchased', 'unsubscribed', 'notified' ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Cannot re-subscribe from this state.', 'giga-stock-alerts' ) ] );
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'giga-stock-alerts' ) ] );
		}

		// If the product is currently in stock, nothing to subscribe to.
		if ( $product->is_in_stock() ) {
			wp_send_json_error( [ 'message' => __( 'This product is currently in stock!', 'giga-stock-alerts' ) ] );
		}

		// Reset to confirmed status.
		Giga_SA_DB::update_subscription_status( $sub_id, 'confirmed', [ 'notified_at' => null ] );
		wp_cache_delete( 'giga_sa_subscriber_stats', 'giga_stock_alerts' );

		wp_send_json_success( [ 'message' => __( "You're back on the list! We'll notify you when it's in stock.", 'giga-stock-alerts' ) ] );
	}

	// -----------------------------------------------------------------------
	// Confirmation / Unsubscribe
	// -----------------------------------------------------------------------

	public function handle_confirmation(): void {
		if ( ! isset( $_GET['giga_sa_confirm'] ) ) {
			return;
		}

		if ( ! isset( $_GET['giga_sa_confirm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['giga_sa_confirm_nonce'] ) ), 'giga_sa_confirm' ) ) {
			wp_die( esc_html__( 'Security check failed. Please use the link from your confirmation email.', 'giga-stock-alerts' ) );
		}

		$token        = sanitize_text_field( wp_unslash( $_GET['giga_sa_confirm'] ) );
		$subscription = Giga_SA_DB::get_subscription_by_token( $token );

		if ( $subscription ) {
			Giga_SA_DB::update_subscription_status( (int) $subscription->id, 'confirmed' );
			Giga_SA_Email::send_admin_alert( (int) $subscription->id );

			global $wpdb;
			$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				[ 'confirm_token' => null ],
				[ 'id' => $subscription->id ],
				[ '%s' ],
				[ '%d' ]
			);

			wc_add_notice( __( 'Your stock alert subscription is confirmed. We will notify you when the product is back in stock!', 'giga-stock-alerts' ), 'success' );
			$url      = get_permalink( $subscription->product_id );
			$redirect = $url ?: ( wc_get_page_permalink( 'shop' ) ?: home_url( '/' ) );
			wp_safe_redirect( $redirect );
			exit;
		} else {
			wc_add_notice( __( 'Invalid or expired confirmation link.', 'giga-stock-alerts' ), 'error' );
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) ?: home_url( '/' ) );
			exit;
		}
	}

	public function handle_unsubscribe(): void {
		if ( isset( $_GET['giga_sa_unsubscribe'], $_GET['giga_sa_token'] ) ) {
			$sub_id = absint( wp_unslash( $_GET['giga_sa_unsubscribe'] ) );
			$hmac   = sanitize_text_field( wp_unslash( $_GET['giga_sa_token'] ) );

			$expected_hmac = hash_hmac( 'sha256', (string) $sub_id, wp_salt( 'auth' ) );

			if ( hash_equals( $expected_hmac, $hmac ) ) {
				Giga_SA_DB::update_subscription_status( $sub_id, 'unsubscribed' );
				wc_add_notice( __( 'You have been successfully unsubscribed from this stock alert.', 'giga-stock-alerts' ), 'success' );
				wp_safe_redirect( home_url( '/' ) );
				exit;
			} else {
				wc_add_notice( __( 'Invalid unsubscribe link.', 'giga-stock-alerts' ), 'error' );
			}
		}
	}

	// -----------------------------------------------------------------------
	// Purchase tracking
	// -----------------------------------------------------------------------

	public function track_purchases( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$email = $order->get_billing_email();
		if ( ! is_email( $email ) ) return;

		global $wpdb;
		$table          = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );
		$seven_days_ago = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );

		foreach ( $order->get_items() as $item ) {
			$product_id   = $item->get_product_id();
			$variation_id = $item->get_variation_id();

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$subs = $wpdb->get_results( $wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE email = %s AND product_id = %d AND variation_id = %d AND status = 'notified' AND notified_at >= %s",
				$email,
				$product_id,
				$variation_id,
				$seven_days_ago
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( $subs as $sub ) {
				Giga_SA_DB::update_subscription_status( (int) $sub->id, 'purchased' );
			}
		}
	}

	// -----------------------------------------------------------------------
	// My Account AJAX handlers
	// -----------------------------------------------------------------------

	public function ajax_my_account_unsubscribe(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'giga-stock-alerts' ) ] );
		}

		$sub_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
		$nonce  = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'giga_sa_unsubscribe_' . $sub_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'giga-stock-alerts' ) ] );
		}

		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT email FROM `{$table}` WHERE id = %d", $sub_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub ) {
			wp_send_json_error( [ 'message' => __( 'Subscription not found.', 'giga-stock-alerts' ) ] );
		}

		$current_user = wp_get_current_user();
		if ( $current_user->user_email !== $sub->email ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'giga-stock-alerts' ) ] );
		}

		Giga_SA_DB::update_subscription_status( $sub_id, 'unsubscribed' );
		wp_send_json_success( [ 'message' => __( 'Successfully unsubscribed.', 'giga-stock-alerts' ) ] );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function get_client_ip(): string {
		$headers = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				return trim( $ip[0] );
			}
		}
		return '0.0.0.0';
	}
}
}
