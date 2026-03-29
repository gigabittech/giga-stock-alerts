<?php
/**
 * Email sender class for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Email' ) ) {
class Giga_SA_Email {

	/**
	 * Send the double opt-in confirmation email.
	 */
	public static function send_confirmation( int $subscription_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'giga_stock_alerts';
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $subscription_id ) );
		
		if ( ! $sub || empty( $sub->confirm_token ) ) {
			return false;
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product ) {
			return false;
		}

		$confirm_url = add_query_arg( 'giga_sa_confirm', $sub->confirm_token, site_url( '/' ) );
		$store_name  = get_bloginfo( 'name' );

		$subject = sprintf( __( 'Confirm your stock alert for %s', 'giga-stock-alerts' ), $product->get_name() );
		$message = sprintf(
			__( "Hi %s,\n\nPlease confirm your request to be notified when %s is back in stock at %s.\n\nClick here to confirm: %s\n\nIf you did not request this, you can ignore this email.", 'giga-stock-alerts' ),
			$sub->customer_name ?: __( 'there', 'giga-stock-alerts' ),
			$product->get_name(),
			$store_name,
			$confirm_url
		);

		return self::dispatch( $subscription_id, $sub->email, $subject, nl2br( $message ), 'confirmation' );
	}

	/**
	 * Send the restock notification email.
	 */
	public static function send_restock_notification( int $subscription_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'giga_stock_alerts';
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $subscription_id ) );
		
		if ( ! $sub ) {
			return false;
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product ) {
			return false;
		}

		$store_name      = get_bloginfo( 'name' );
		$product_name    = $product->get_name();
		$product_price   = wc_price( wc_get_price_to_display( $product ) );
		$product_url     = $product->get_permalink();
		$customer_name   = $sub->customer_name ?: __( 'Customer', 'giga-stock-alerts' );
		
		$image_id    = $product->get_image_id();
		$product_img = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : wc_placeholder_img_src();

		$hmac            = hash_hmac( 'sha256', (string) $sub->id, wp_salt( 'auth' ) );
		$unsubscribe_url = add_query_arg( [
			'giga_sa_unsubscribe' => $sub->id,
			'giga_sa_token'       => $hmac,
		], site_url( '/' ) );

		// Load HTML Template
		$template_path   = GIGA_SA_PLUGIN_DIR . 'templates/email-restock.php';
		$html_content    = '';
		if ( file_exists( $template_path ) ) {
			ob_start();
			include $template_path;
			$html_content = ob_get_clean();
			
			// Replace variables
			$replacements = [
				'{customer_name}'   => esc_html( $customer_name ),
				'{product_name}'    => esc_html( $product_name ),
				'{product_price}'   => wp_kses_post( $product_price ),
				'{product_url}'     => esc_url( $product_url ),
				'{product_image}'   => esc_url( $product_img ),
				'{store_name}'      => esc_html( $store_name ),
				'{unsubscribe_url}' => esc_url( $unsubscribe_url ),
			];

			$html_content = strtr( $html_content, $replacements );
		}

		$subject = sprintf( __( 'Great news! %s is back in stock at %s', 'giga-stock-alerts' ), $product_name, $store_name );

		return self::dispatch( $subscription_id, $sub->email, $subject, $html_content, 'restock' );
	}

	/**
	 * Wrapper for wp_mail with WooCommerce settings.
	 */
	private static function dispatch( int $subscription_id, string $to, string $subject, string $message, string $channel ): bool {
		$from_name  = get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];

		$sent = wp_mail( $to, $subject, $message, $headers );

		Giga_SA_DB::log_notification( [
			'subscription_id' => $subscription_id,
			'channel'         => $channel,
			'status'          => $sent ? 'sent' : 'failed',
			'error_message'   => $sent ? null : 'wp_mail returned false',
		] );

		return $sent;
	}
}
}
