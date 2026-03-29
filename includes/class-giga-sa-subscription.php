<?php
/**
 * Subscription Form Handler.
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
		add_action( 'wp_ajax_giga_sa_subscribe',        [ $this, 'ajax_subscribe' ] );
		add_action( 'wp_ajax_nopriv_giga_sa_subscribe', [ $this, 'ajax_subscribe' ] );
		add_action( 'init',                             [ $this, 'handle_confirmation' ] );
		add_action( 'init',                             [ $this, 'handle_unsubscribe' ] );
		add_action( 'woocommerce_order_status_completed', [ $this, 'track_purchases' ] );
	}

	public function ajax_subscribe(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'giga_sa_subscribe_nonce' ) ) {
			wp_send_json_error( [ 'code' => 'invalid_nonce', 'message' => __( 'Security check failed.', 'giga-stock-alerts' ) ] );
		}

		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name         = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$gdpr         = isset( $_POST['gdpr'] ) ? sanitize_text_field( wp_unslash( $_POST['gdpr'] ) ) : '0';

		if ( ! is_email( $email ) || ! $product_id || '1' !== $gdpr ) {
			wp_send_json_error( [ 'message' => __( 'Invalid email, product, or missing GDPR consent.', 'giga-stock-alerts' ) ] );
		}

		$ip = $this->get_client_ip();
		$rate_key = 'giga_sa_rate_' . md5( $ip );
		$count = (int) get_transient( $rate_key );
		if ( $count >= 3 ) {
			wp_send_json_error( [ 'message' => __( 'Rate limit exceeded. Try again later.', 'giga-stock-alerts' ) ] );
		}
		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		$existing = Giga_SA_DB::get_subscription_by_email_product( $email, $product_id, $variation_id );
		
		$double_optin = filter_var( get_option( 'giga_sa_double_optin', false ), FILTER_VALIDATE_BOOLEAN );

		if ( $existing ) {
			if ( 'pending' === $existing->status ) {
				if ( $double_optin ) {
					Giga_SA_Email::send_confirmation( (int) $existing->id );
				} else {
					Giga_SA_DB::update_subscription_status( (int) $existing->id, 'confirmed' );
				}
				wp_send_json_success( [ 'message' => __( 'Subscription processed successfully.', 'giga-stock-alerts' ) ] );
			} else {
				wp_send_json_error( [ 
					'message' => __( "You're already subscribed!", 'giga-stock-alerts' ),
					'code'    => 'duplicate'
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
			'ip_address'    => ltrim( substr( $ip, 0, 45 ) ),
		] );

		if ( is_wp_error( $insert_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Database error. Could not save.', 'giga-stock-alerts' ) ] );
		}

		if ( $double_optin ) {
			Giga_SA_Email::send_confirmation( (int) $insert_id );
			$msg = __( 'Please check your email to confirm your subscription.', 'giga-stock-alerts' );
		} else {
			$msg = __( 'Great! We will notify you when this is back in stock.', 'giga-stock-alerts' );
		}

		wp_send_json_success( [ 'message' => $msg ] );
	}

	public function handle_confirmation(): void {
		if ( ! isset( $_GET['giga_sa_confirm'] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['giga_sa_confirm'] ) );
		$subscription = Giga_SA_DB::get_subscription_by_token( $token );

		if ( $subscription ) {
			Giga_SA_DB::update_subscription_status( (int) $subscription->id, 'confirmed' );
			global $wpdb;
			// Clear token
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->prefix . 'giga_stock_alerts',
				[ 'confirm_token' => null ],
				[ 'id' => $subscription->id ],
				[ '%s' ],
				[ '%d' ]
			);

			wc_add_notice( __( 'Your stock alert subscription is confirmed.', 'giga-stock-alerts' ), 'success' );
			$url = get_permalink( $subscription->product_id );
			if ( $url ) {
				wp_safe_redirect( $url );
				exit;
			}
		} else {
			wc_add_notice( __( 'Invalid or expired confirmation link.', 'giga-stock-alerts' ), 'error' );
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
				
				// Strip query strings to clean URL.
				$base = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
				wp_safe_redirect( $base );
				exit;
			} else {
				wc_add_notice( __( 'Invalid unsubscribe link.', 'giga-stock-alerts' ), 'error' );
			}
		}
	}

	public function track_purchases( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$email = $order->get_billing_email();
		if ( ! is_email( $email ) ) return;

		global $wpdb;
		$table = $wpdb->prefix . 'giga_stock_alerts';
		$seven_days_ago = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );

		foreach ( $order->get_items() as $item ) {
			$product_id   = $item->get_product_id();
			$variation_id = $item->get_variation_id();

			// Find notified subscriptions for this user + product within 7 days
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$subs = $wpdb->get_results( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE email = %s AND product_id = %d AND variation_id = %d AND status = 'notified' AND notified_at >= %s",
				$email,
				$product_id,
				$variation_id,
				$seven_days_ago
			) );

			foreach ( $subs as $sub ) {
				Giga_SA_DB::update_subscription_status( (int) $sub->id, 'purchased' );
			}
		}
	}

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
