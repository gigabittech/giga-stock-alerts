<?php
/**
 * Email sender class for Giga Stock Alerts.
 *
 * Handles all outgoing emails: confirmation, restock notification, price drop,
 * low stock urgency, admin alerts, test email, and weekly digest.
 *
 * @package GigaStockAlerts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Email' ) ) {
class Giga_SA_Email {

	// -----------------------------------------------------------------------
	// Confirmation email
	// -----------------------------------------------------------------------

	/**
	 * Send the double opt-in confirmation email.
	 *
	 * @param int $subscription_id
	 * @return bool
	 */
	public static function send_confirmation( int $subscription_id ): bool {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $subscription_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub || empty( $sub->confirm_token ) ) {
			return false;
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product && $sub->variation_id ) {
			$product = wc_get_product( $sub->product_id );
		}
		if ( ! $product ) {
			return false;
		}

		$confirm_url = add_query_arg( [
			'giga_sa_confirm'       => $sub->confirm_token,
			'giga_sa_confirm_nonce' => wp_create_nonce( 'giga_sa_confirm' ),
		], site_url( '/' ) );
		$store_name  = get_bloginfo( 'name' );

		/* translators: %1$s: product name */
		$subject = sprintf( __( 'Confirm your stock alert for %1$s', 'giga-stock-alerts' ), $product->get_name() );
		/* translators: %1$s: customer name, %2$s: product name, %3$s: store name, %4$s: confirmation URL */
		$message = sprintf(
			__( "Hi %1\$s,\n\nPlease confirm your request to be notified when %2\$s is back in stock at %3\$s.\n\nClick here to confirm: %4\$s\n\nIf you did not request this, you can ignore this email.", 'giga-stock-alerts' ),
			$sub->customer_name ?: __( 'there', 'giga-stock-alerts' ),
			$product->get_name(),
			$store_name,
			$confirm_url
		);

		return self::dispatch( $subscription_id, $sub->email, $subject, nl2br( $message ), 'confirmation' );
	}

	// -----------------------------------------------------------------------
	// Restock notification
	// -----------------------------------------------------------------------

	/**
	 * Send the restock notification email.
	 *
	 * Supports:
	 * - Custom HTML template from settings (giga_sa_email_template option)
	 * - Per-product email subject override (Giga_SA_Product_Meta)
	 * - WooCommerce email header/footer wrapper (giga_sa_use_wc_template option)
	 *
	 * @param int $subscription_id
	 * @return bool
	 */
	public static function send_restock_notification( int $subscription_id ): bool {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $subscription_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub ) {
			return false;
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product && $sub->variation_id ) {
			$product = wc_get_product( $sub->product_id );
		}
		if ( ! $product ) {
			return false;
		}

		$store_name    = get_bloginfo( 'name' );
		$product_name  = $product->get_name();
		$product_price = wc_price( wc_get_price_to_display( $product ) );
		$product_url   = $product->get_permalink();
		$customer_name = $sub->customer_name ?: __( 'Customer', 'giga-stock-alerts' );
		$image_id      = $product->get_image_id();
		$product_img   = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : wc_placeholder_img_src();

		$hmac            = hash_hmac( 'sha256', (string) $sub->id, wp_salt( 'auth' ) );
		$unsubscribe_url = add_query_arg( [
			'giga_sa_unsubscribe' => $sub->id,
			'giga_sa_token'       => $hmac,
		], site_url( '/' ) );

		// Build the replacement map.
		$replacements = [
			'{customer_name}'   => esc_html( $customer_name ),
			'{product_name}'    => esc_html( $product_name ),
			'{product_price}'   => wp_kses_post( $product_price ),
			'{product_url}'     => esc_url( $product_url ),
			'{product_image}'   => esc_url( $product_img ),
			'{store_name}'      => esc_html( $store_name ),
			'{unsubscribe_url}' => esc_url( $unsubscribe_url ),
		];

		// Option 1: Admin-entered custom HTML template.
		$custom_template = get_option( 'giga_sa_email_template', '' );
		if ( ! empty( trim( $custom_template ) ) ) {
			$html_content = strtr( wp_kses_post( $custom_template ), $replacements );
		} else {
			// Option 2: Load from plugin template file.
			$html_content = self::load_template_html( 'email-restock.php', $replacements );
		}

		// Optionally wrap with WooCommerce email header/footer.
		if ( filter_var( get_option( 'giga_sa_use_wc_template', false ), FILTER_VALIDATE_BOOLEAN ) ) {
			$html_content = self::wrap_in_wc_template( $product_name, $html_content );
		}

		// Subject: per-product override → global setting → fallback.
		$per_product_subject = class_exists( 'Giga_SA_Product_Meta' )
			? Giga_SA_Product_Meta::get_email_subject( (int) $sub->product_id )
			: '';
		$subject_template = $per_product_subject
			?: get_option( 'giga_sa_email_subject', __( 'Great news! {product_name} is back in stock!', 'giga-stock-alerts' ) );
		$subject = strtr( $subject_template, [
			'{product_name}' => $product_name,
			'{store_name}'   => $store_name,
		] );

		return self::dispatch( $subscription_id, $sub->email, $subject, $html_content, 'restock' );
	}

	// -----------------------------------------------------------------------
	// Price drop notification
	// -----------------------------------------------------------------------

	/**
	 * Send a price drop notification to a subscriber.
	 *
	 * @param int   $subscription_id
	 * @param float $new_price  The new (lower) price.
	 * @return bool
	 */
	public static function send_price_drop_notification( int $subscription_id, float $new_price ): bool {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $subscription_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub ) {
			return false;
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product && $sub->variation_id ) {
			$product = wc_get_product( $sub->product_id );
		}
		if ( ! $product ) {
			return false;
		}

		$store_name    = get_bloginfo( 'name' );
		$product_name  = $product->get_name();
		$product_url   = $product->get_permalink();
		$customer_name = $sub->customer_name ?: __( 'Customer', 'giga-stock-alerts' );
		$image_id      = $product->get_image_id();
		$product_img   = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : wc_placeholder_img_src();
		$old_price     = wc_price( (float) $sub->price_watched );
		$new_price_fmt = wc_price( $new_price );

		$hmac            = hash_hmac( 'sha256', (string) $sub->id, wp_salt( 'auth' ) );
		$unsubscribe_url = add_query_arg( [
			'giga_sa_unsubscribe' => $sub->id,
			'giga_sa_token'       => $hmac,
		], site_url( '/' ) );

		$replacements = [
			'{customer_name}'   => esc_html( $customer_name ),
			'{product_name}'    => esc_html( $product_name ),
			'{product_url}'     => esc_url( $product_url ),
			'{product_image}'   => esc_url( $product_img ),
			'{store_name}'      => esc_html( $store_name ),
			'{old_price}'       => wp_kses_post( $old_price ),
			'{new_price}'       => wp_kses_post( $new_price_fmt ),
			'{unsubscribe_url}' => esc_url( $unsubscribe_url ),
		];

		$html_content = self::load_template_html( 'email-price-drop.php', $replacements );

		$subject = strtr(
			/* translators: %s: product name */
			__( '💰 Price Drop Alert: {product_name} is now cheaper!', 'giga-stock-alerts' ),
			[ '{product_name}' => $product_name ]
		);

		return self::dispatch( $subscription_id, $sub->email, $subject, $html_content, 'price_drop' );
	}

	// -----------------------------------------------------------------------
	// Low stock urgency notification
	// -----------------------------------------------------------------------

	/**
	 * Send a low-stock urgency notification to a confirmed restock subscriber.
	 *
	 * @param int $subscription_id
	 * @param int $stock_count  Current remaining stock.
	 * @return bool
	 */
	public static function send_low_stock_notification( int $subscription_id, int $stock_count ): bool {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $subscription_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub ) {
			return false;
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product && $sub->variation_id ) {
			$product = wc_get_product( $sub->product_id );
		}
		if ( ! $product ) {
			return false;
		}

		$store_name    = get_bloginfo( 'name' );
		$product_name  = $product->get_name();
		$product_url   = $product->get_permalink();
		$customer_name = $sub->customer_name ?: __( 'Customer', 'giga-stock-alerts' );
		$image_id      = $product->get_image_id();
		$product_img   = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : wc_placeholder_img_src();
		$product_price = wc_price( wc_get_price_to_display( $product ) );

		$hmac            = hash_hmac( 'sha256', (string) $sub->id, wp_salt( 'auth' ) );
		$unsubscribe_url = add_query_arg( [
			'giga_sa_unsubscribe' => $sub->id,
			'giga_sa_token'       => $hmac,
		], site_url( '/' ) );

		$replacements = [
			'{customer_name}'   => esc_html( $customer_name ),
			'{product_name}'    => esc_html( $product_name ),
			'{product_url}'     => esc_url( $product_url ),
			'{product_image}'   => esc_url( $product_img ),
			'{product_price}'   => wp_kses_post( $product_price ),
			'{store_name}'      => esc_html( $store_name ),
			'{stock_count}'     => absint( $stock_count ),
			'{unsubscribe_url}' => esc_url( $unsubscribe_url ),
		];

		$html_content = self::load_template_html( 'email-low-stock.php', $replacements );

		/* translators: %1$s: stock count, %2$s: product name */
		$subject = sprintf(
			__( '⚡ Only %1$d left! %2$s is almost gone', 'giga-stock-alerts' ),
			$stock_count,
			$product_name
		);

		return self::dispatch( $subscription_id, $sub->email, $subject, $html_content, 'low_stock' );
	}

	// -----------------------------------------------------------------------
	// Test email
	// -----------------------------------------------------------------------

	/**
	 * Send a test restock email using dummy data. No DB log entry is created.
	 *
	 * @param string $to_email Recipient address.
	 * @return bool
	 */
	public static function send_test_email( string $to_email ): bool {
		if ( ! is_email( $to_email ) ) {
			return false;
		}

		$store_name    = get_bloginfo( 'name' );
		$product_name  = __( 'Sample Product', 'giga-stock-alerts' );
		$product_url   = site_url( '/' );
		$product_img   = wc_placeholder_img_src( 'woocommerce_thumbnail' );
		$product_price = wc_price( 29.99 );
		$customer_name = __( 'Valued Customer', 'giga-stock-alerts' );

		$replacements = [
			'{customer_name}'   => esc_html( $customer_name ),
			'{product_name}'    => esc_html( $product_name ),
			'{product_price}'   => wp_kses_post( $product_price ),
			'{product_url}'     => esc_url( $product_url ),
			'{product_image}'   => esc_url( $product_img ),
			'{store_name}'      => esc_html( $store_name ),
			'{unsubscribe_url}' => '#',
		];

		$custom_template = get_option( 'giga_sa_email_template', '' );
		if ( ! empty( trim( $custom_template ) ) ) {
			$html_content = strtr( wp_kses_post( $custom_template ), $replacements );
		} else {
			$html_content = self::load_template_html( 'email-restock.php', $replacements );
		}

		if ( filter_var( get_option( 'giga_sa_use_wc_template', false ), FILTER_VALIDATE_BOOLEAN ) ) {
			$html_content = self::wrap_in_wc_template( $product_name, $html_content );
		}

		$subject_template = get_option( 'giga_sa_email_subject', __( 'Great news! {product_name} is back in stock!', 'giga-stock-alerts' ) );
		$subject = '[TEST] ' . strtr( $subject_template, [
			'{product_name}' => $product_name,
			'{store_name}'   => $store_name,
		] );

		return self::dispatch_direct( $to_email, $subject, $html_content );
	}

	// -----------------------------------------------------------------------
	// Weekly digest
	// -----------------------------------------------------------------------

	/**
	 * Send the weekly digest email to the store admin.
	 *
	 * @param string $to_email        Admin email address.
	 * @param array  $stats           Array from Giga_SA_DB::get_weekly_stats().
	 * @param array  $top_products    Array from Giga_SA_DB::get_top_wanted_products_static().
	 * @return bool
	 */
	public static function send_weekly_digest_email( string $to_email, array $stats, array $top_products ): bool {
		if ( ! is_email( $to_email ) ) {
			return false;
		}

		$store_name   = get_bloginfo( 'name' );
		$period_end   = wp_date( get_option( 'date_format' ) );
		$period_start = wp_date( get_option( 'date_format' ), strtotime( '-7 days' ) );
		$dashboard_url = admin_url( 'admin.php?page=giga-stock-alerts' );

		// Build top products table rows.
		$top_products_rows = '';
		if ( ! empty( $top_products ) ) {
			foreach ( $top_products as $row ) {
				$product      = wc_get_product( (int) $row->product_id );
				$product_name = $product ? esc_html( $product->get_name() ) : sprintf( __( 'Product #%d', 'giga-stock-alerts' ), (int) $row->product_id );
				$top_products_rows .= sprintf(
					'<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;">%s</td><td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;font-weight:bold;">%d</td></tr>',
					$product_name,
					(int) $row->request_count
				);
			}
		} else {
			$top_products_rows = '<tr><td colspan="2" style="padding:8px 12px;text-align:center;color:#888;">' . esc_html__( 'No data for this period.', 'giga-stock-alerts' ) . '</td></tr>';
		}

		$replacements = [
			'{admin_name}'        => esc_html( get_option( 'blogname' ) ),
			'{store_name}'        => esc_html( $store_name ),
			'{period_start}'      => esc_html( $period_start ),
			'{period_end}'        => esc_html( $period_end ),
			'{new_subscribers}'   => absint( $stats['new_subscribers'] ?? 0 ),
			'{total_subscribers}' => absint( $stats['total_subscribers'] ?? 0 ),
			'{notifications_sent}'=> absint( $stats['notifications_sent'] ?? 0 ),
			'{conversion_rate}'   => esc_html( ( $stats['conversion_rate'] ?? 0 ) . '%' ),
			'{top_products_rows}' => $top_products_rows,
			'{dashboard_url}'     => esc_url( $dashboard_url ),
		];

		$html_content = self::load_template_html( 'email-weekly-digest.php', $replacements );

		/* translators: %s: store name */
		$subject = sprintf( __( '📊 Weekly Stock Alerts Report — %s', 'giga-stock-alerts' ), $store_name );

		return self::dispatch_direct( $to_email, $subject, $html_content );
	}

	// -----------------------------------------------------------------------
	// Admin subscription alert
	// -----------------------------------------------------------------------

	/**
	 * Send an alert to the admin about a new confirmed subscription.
	 *
	 * @param int $subscription_id
	 * @return bool
	 */
	public static function send_admin_alert( int $subscription_id ): bool {
		if ( ! filter_var( get_option( 'giga_sa_admin_notify', true ), FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}

		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $subscription_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $sub ) {
			return false;
		}

		$product = wc_get_product( $sub->variation_id ?: $sub->product_id );
		if ( ! $product && $sub->variation_id ) {
			$product = wc_get_product( $sub->product_id );
		}
		if ( ! $product ) {
			return false;
		}

		$admin_email  = get_option( 'admin_email' );
		$product_name = $product->get_name();
		$date         = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sub->subscribed_at ) );
		$count        = Giga_SA_DB::count_subscriptions_by_product( (int) $sub->product_id );
		$admin_url    = admin_url( 'admin.php?page=giga-stock-alerts&s=' . urlencode( $sub->email ) );

		/* translators: %s: product name */
		$subject = sprintf( __( 'New stock alert subscription for %s', 'giga-stock-alerts' ), $product_name );
		$body  = "Product: {$product_name}\n";
		$body .= "Customer Email: {$sub->email}\n";
		$body .= "Subscribed At: {$date}\n";
		$body .= "Total subscribers waiting for this product: {$count}\n";
		$body .= "View all subscribers: {$admin_url}\n";

		return self::dispatch( $subscription_id, $admin_email, $subject, nl2br( $body ), 'admin_alert' );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Load a template file and perform string replacements.
	 *
	 * @param string $template_name Template file name (relative to /templates/).
	 * @param array  $replacements  Key-value replacement map.
	 * @return string HTML string.
	 */
	private static function load_template_html( string $template_name, array $replacements ): string {
		$theme_template  = get_stylesheet_directory() . '/giga-stock-alerts/' . $template_name;
		$plugin_template = GIGA_SA_PLUGIN_DIR . 'templates/' . $template_name;
		$template_path   = file_exists( $theme_template ) ? $theme_template : $plugin_template;

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		ob_start();
		include $template_path;
		$html = ob_get_clean();

		return strtr( $html, $replacements );
	}

	/**
	 * Wrap HTML content with WooCommerce email header and footer templates.
	 *
	 * @param string $heading      The email heading shown in the WC header.
	 * @param string $html_content Inner content to wrap.
	 * @return string Wrapped HTML.
	 */
	private static function wrap_in_wc_template( string $heading, string $html_content ): string {
		if ( ! function_exists( 'wc_get_template' ) ) {
			return $html_content;
		}

		ob_start();
		wc_get_template( 'emails/email-header.php', [ 'email_heading' => $heading ] );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html_content;
		wc_get_template( 'emails/email-footer.php' );
		return ob_get_clean();
	}

	/**
	 * Send email via wp_mail and log the result to the DB.
	 *
	 * @param int    $subscription_id
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 * @param string $channel
	 * @return bool
	 */
	private static function dispatch( int $subscription_id, string $to, string $subject, string $message, string $channel ): bool {
		$result = self::send_mail( $to, $subject, $message );
		$sent   = ( true === $result );

		$error_message = null;
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
		} elseif ( ! $sent ) {
			$error_message = 'wp_mail returned false — check server mail configuration';
		}

		if ( ! $sent ) {
			$debug = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				|| filter_var( get_option( 'giga_sa_debug_mode', false ), FILTER_VALIDATE_BOOLEAN );
			if ( $debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[Giga Stock Alerts] Email FAILED | Channel: %s | To: %s | Subject: %s | Error: %s',
					$channel,
					$to,
					$subject,
					$error_message ?? 'unknown'
				) );
			}
		}

		Giga_SA_DB::log_notification( [
			'subscription_id' => $subscription_id,
			'channel'         => $channel,
			'status'          => $sent ? 'sent' : 'failed',
			'error_message'   => $error_message,
		] );

		return $sent;
	}

	/**
	 * Send email without creating a DB log entry (for test/digest emails).
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 * @return bool
	 */
	private static function dispatch_direct( string $to, string $subject, string $message ): bool {
		$result = self::send_mail( $to, $subject, $message );
		$sent   = ( true === $result );

		if ( ! $sent ) {
			$debug = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
				|| filter_var( get_option( 'giga_sa_debug_mode', false ), FILTER_VALIDATE_BOOLEAN );
			if ( $debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf(
					'[Giga Stock Alerts] Direct email FAILED | To: %s | Subject: %s',
					$to,
					$subject
				) );
			}
		}

		return $sent;
	}

	/**
	 * Build headers and call wp_mail.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 * @return bool|WP_Error
	 */
	private static function send_mail( string $to, string $subject, string $message ) {
		$from_name  = get_option( 'giga_sa_email_from_name' )
			?: get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];

		return wp_mail( $to, $subject, $message, $headers );
	}
}
}
