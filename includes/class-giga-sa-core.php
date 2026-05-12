<?php
/**
 * Core bootstrap class for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Declare WooCommerce HPOS compatibility globally before plugins_loaded
// ---------------------------------------------------------------------------
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			GIGA_SA_PLUGIN_FILE,
			true
		);
	}
} );

/**
 * Class Giga_SA_Core
 */
if ( ! class_exists( 'Giga_SA_Core' ) ) {
class Giga_SA_Core {

	private static ?Giga_SA_Core $instance = null;

	public Giga_SA_Subscription $subscription;
	public Giga_SA_Notifier $notifier;
	public Giga_SA_Widget $widget;
	public Giga_SA_My_Account $my_account;
	public ?Giga_SA_Admin $admin = null;

	public static function instance(): Giga_SA_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( ! $this->check_woocommerce() ) {
			return;
		}

		$this->load_textdomain();
		$this->load_dependencies();
		$this->init_classes();
		$this->register_hooks();
	}

	private function check_woocommerce(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>' . wp_kses_post( __( 'Giga Stock Alerts requires <strong>WooCommerce</strong> to be installed and active.', 'giga-stock-alerts' ) ) . '</p></div>';
			} );
			return false;
		}
		return true;
	}

	private function load_textdomain(): void {
		// load_plugin_textdomain() removed since WP 4.6 - WordPress handles this automatically
	}

	private function load_dependencies(): void {
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-db.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-email.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-notifier.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-subscription.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-widget.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-my-account.php';
		
		if ( is_admin() ) {
			require_once GIGA_SA_PLUGIN_DIR . 'admin/class-giga-sa-admin.php';
		}
	}

	private function init_classes(): void {
		$this->subscription = new Giga_SA_Subscription();
		$this->notifier     = new Giga_SA_Notifier();
		$this->widget       = new Giga_SA_Widget();
		$this->my_account   = new Giga_SA_My_Account();

		if ( is_admin() ) {
			$this->admin = new Giga_SA_Admin();
		}
	}

	private function register_hooks(): void {
		add_action( 'giga_sa_process_notifications',   [ $this->notifier, 'process_notifications' ],   10, 2 );
		add_action( 'giga_sa_retry_notification',      [ $this->notifier, 'retry_failed_notifications' ], 10, 2 );
		add_action( 'giga_sa_auto_confirm_cleanup',    [ $this, 'auto_confirm_cleanup' ] );
	}

	/**
	 * Auto-confirm or expire stale pending subscriptions.
	 *
	 * Runs daily via WP-Cron. Marks pending subscriptions as `confirmed` (auto-confirm)
	 * when the `giga_sa_double_optin` option is disabled, or deletes them when double
	 * opt-in is enabled and they are older than `giga_sa_auto_confirm_days` days.
	 *
	 * @return void
	 */
	public function auto_confirm_cleanup(): void {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'giga_stock_alerts' );

		$days         = max( 1, (int) get_option( 'giga_sa_auto_confirm_days', 7 ) );
		$double_optin = filter_var( get_option( 'giga_sa_double_optin', true ), FILTER_VALIDATE_BOOLEAN );
		$cutoff       = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		if ( $double_optin ) {
			// Double opt-in ON: delete stale unconfirmed (pending) subscriptions.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE status = 'pending' AND subscribed_at < %s",
					$cutoff
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// Double opt-in OFF: auto-confirm any pending subscriptions older than cutoff.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET status = 'confirmed' WHERE status = 'pending' AND subscribed_at < %s",
					$cutoff
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		wp_cache_delete( 'giga_sa_subscriber_stats', 'giga_stock_alerts' );
	}

	// -----------------------------------------------------------------------
	// Activation / Deactivation hooks
	// -----------------------------------------------------------------------

	public static function activate(): void {
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-db.php';
		Giga_SA_DB::create_tables();

		// Set default options upon first launch
		add_option( 'giga_sa_button_heading', __( 'Out of Stock — Get Notified!', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_button_text', __( 'Notify Me!', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_success_message', __( "You'll be notified when this product is back!", 'giga-stock-alerts' ) );
		add_option( 'giga_sa_gdpr_text', __( 'I agree to receive stock notifications for this product.', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_button_color', '#2271b1' );
		add_option( 'giga_sa_show_name_field', true );
		add_option( 'giga_sa_double_optin', true );
		add_option( 'giga_sa_email_subject', __( 'Great news! {product_name} is back in stock!', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_email_from_name', '' );
		add_option( 'giga_sa_admin_notify', true );
		add_option( 'giga_sa_batch_size', 50 );
		add_option( 'giga_sa_notification_delay', 1 );
		add_option( 'giga_sa_auto_confirm_days', 7 );
		add_option( 'giga_sa_hide_outofstock', false );
		add_option( 'giga_sa_rate_limit', 3 );
		add_option( 'giga_sa_delete_data', false );
		add_option( 'giga_sa_debug_mode', false );

		// Schedule daily cleanup cron if not already scheduled.
		if ( ! wp_next_scheduled( 'giga_sa_auto_confirm_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'giga_sa_auto_confirm_cleanup' );
		}

		// Legacy routine cleanup just in case.
		wp_clear_scheduled_hook( 'giga_sa_restock_check' );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'giga_sa_process_notifications' );
		wp_clear_scheduled_hook( 'giga_sa_retry_notification' );
		wp_clear_scheduled_hook( 'giga_sa_auto_confirm_cleanup' );
	}

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'giga-stock-alerts' ), '1.0.0' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'giga-stock-alerts' ), '1.0.0' );
	}
}
}
