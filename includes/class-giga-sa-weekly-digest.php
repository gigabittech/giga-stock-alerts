<?php
/**
 * Weekly Digest Email for Giga Stock Alerts.
 *
 * Sends a summary email to the store admin every week with subscriber growth,
 * notifications sent, conversion rate, and top wanted products.
 *
 * @package GigaStockAlerts
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Weekly_Digest' ) ) {
class Giga_SA_Weekly_Digest {

	public function __construct() {
		add_action( 'giga_sa_weekly_digest', [ $this, 'send_digest' ] );
	}

	/**
	 * CRON CALLBACK: Build stats and send the weekly digest to the site admin.
	 */
	public function send_digest(): void {
		// Respect admin toggle.
		if ( ! filter_var( get_option( 'giga_sa_weekly_digest_enabled', true ), FILTER_VALIDATE_BOOLEAN ) ) {
			return;
		}

		$admin_email = get_option( 'giga_sa_weekly_digest_email' ) ?: get_option( 'admin_email' );
		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$stats        = Giga_SA_DB::get_weekly_stats();
		$top_products = Giga_SA_DB::get_top_wanted_products_static( 5 );

		Giga_SA_Email::send_weekly_digest_email( $admin_email, $stats, $top_products );
	}
}
}
