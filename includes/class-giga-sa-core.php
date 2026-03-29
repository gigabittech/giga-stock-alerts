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
		load_plugin_textdomain(
			'giga-stock-alerts',
			false,
			dirname( plugin_basename( GIGA_SA_PLUGIN_FILE ) ) . '/languages'
		);
	}

	private function load_dependencies(): void {
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-db.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-email.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-notifier.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-subscription.php';
		require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-widget.php';
		
		if ( is_admin() ) {
			require_once GIGA_SA_PLUGIN_DIR . 'admin/class-giga-sa-admin.php';
		}
	}

	private function init_classes(): void {
		$this->subscription = new Giga_SA_Subscription();
		$this->notifier     = new Giga_SA_Notifier();
		$this->widget       = new Giga_SA_Widget();

		if ( is_admin() ) {
			$this->admin = new Giga_SA_Admin();
		}
	}

	private function register_hooks(): void {
		add_action( 'giga_sa_process_notifications', [ $this->notifier, 'process_notifications' ], 10, 2 );
		add_action( 'giga_sa_retry_notification', [ $this->notifier, 'retry_failed_notifications' ], 10, 2 );
	}

	// -----------------------------------------------------------------------
	// Activation / Deactivation hooks
	// -----------------------------------------------------------------------

	public static function activate(): void {
		require_once plugin_dir_path( __FILE__ ) . 'class-giga-sa-db.php';
		Giga_SA_DB::create_tables();

		// Set default options upon first launch
		add_option( 'giga_sa_button_heading', __( 'Out of Stock — Get Notified!', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_button_text', __( 'Notify Me!', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_success_message', __( 'You\'ll be notified when this product is back!', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_gdpr_text', __( 'I agree to receive email notifications regarding this product.', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_button_color', '#2271b1' );
		add_option( 'giga_sa_double_optin', true );
		add_option( 'giga_sa_email_subject', __( 'Great news! {product_name} is back in stock!', 'giga-stock-alerts' ) );
		add_option( 'giga_sa_batch_size', 50 );

		// Legacy routine cleanup just in case
		wp_clear_scheduled_hook( 'giga_sa_restock_check' );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'giga_sa_process_notifications' );
		wp_clear_scheduled_hook( 'giga_sa_retry_notification' );
	}

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'giga-stock-alerts' ), '1.0.0' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'giga-stock-alerts' ), '1.0.0' );
	}
}
}
