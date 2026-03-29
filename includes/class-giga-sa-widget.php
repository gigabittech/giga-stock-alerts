<?php
/**
 * Frontend "Notify Me" widget for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Giga_SA_Widget
 */
if ( ! class_exists( 'Giga_SA_Widget' ) ) {
class Giga_SA_Widget {

	/**
	 * Constructor.
	 *
	 * Note: Subscription handler is no longer injected here directly
	 * because DB logic is now static.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress / WooCommerce hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_widget' ], 31 );
		add_shortcode( 'giga_stock_alert', [ $this, 'render_shortcode' ] );
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	/**
	 * Enqueue front-end CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Only enqueue on product pages or if the shortcode is used.
		// For simplicity, we enqueue on all WooCommerce pages or singles.
		if ( ! is_product() && ! has_shortcode( get_post( get_the_ID() )->post_content ?? '', 'giga_stock_alert' ) ) {
			return;
		}

		wp_enqueue_style(
			'giga-sa-frontend',
			GIGA_SA_PLUGIN_URL . 'public/css/giga-sa-frontend.css',
			[],
			GIGA_SA_VERSION
		);

		wp_enqueue_script(
			'giga-sa-frontend',
			GIGA_SA_PLUGIN_URL . 'public/js/giga-sa-frontend.js',
			[ 'jquery', 'wc-add-to-cart-variation' ],
			GIGA_SA_VERSION,
			true
		);

		wp_localize_script(
			'giga-sa-frontend',
			'gigaSaParams',
			[
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'giga_sa_subscribe_nonce' ),
				'submitting' => __( 'Please wait...', 'giga-stock-alerts' ),
			]
		);
	}

	// -----------------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------------

	/**
	 * Render the "Notify Me" widget on out-of-stock single-product pages.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// If it's a simple product and is in stock, do nothing.
		if ( $product->is_in_stock() && ! $product->is_type( 'variable' ) ) {
			return;
		}

		$this->display_form( $product );
	}

	/**
	 * Shortcode for custom placement: [giga_stock_alert product_id="123"]
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts( [
			'product_id' => 0,
		], $atts, 'giga_stock_alert' );

		$product_id = absint( $atts['product_id'] );
		
		if ( ! $product_id ) {
			global $product;
			$product_obj = $product;
		} else {
			$product_obj = wc_get_product( $product_id );
		}

		if ( ! $product_obj instanceof WC_Product ) {
			return '';
		}

		ob_start();
		$this->display_form( $product_obj );
		return ob_get_clean();
	}

	/**
	 * Output the actual form HTML using the template.
	 *
	 * @param WC_Product $product
	 * @return void
	 */
	private function display_form( WC_Product $product ): void {
		// Get options with defaults.
		$heading   = get_option( 'giga_sa_button_heading', __( 'Out of Stock — Get Notified!', 'giga-stock-alerts' ) );
		$btn_text  = get_option( 'giga_sa_button_text', __( 'Notify Me!', 'giga-stock-alerts' ) );
		$gdpr_text = get_option( 'giga_sa_gdpr_text', __( 'I agree to receive email notifications regarding this product.', 'giga-stock-alerts' ) );

		// For variable products, if the main product is "in stock" (some variations exist),
		// we initially hide the widget, letting JS show it when an out-of-stock variation is selected.
		$is_hidden = $product->is_type( 'variable' ) && $product->is_in_stock();

		$this->load_template(
			'notify-me-widget.php',
			[
				'product'   => $product,
				'heading'   => $heading,
				'btn_text'  => $btn_text,
				'gdpr_text' => $gdpr_text,
				'is_hidden' => $is_hidden,
			]
		);
	}

	// -----------------------------------------------------------------------
	// Template loader
	// -----------------------------------------------------------------------

	/**
	 * Load a template file, allowing themes to override it.
	 *
	 * @param string               $template_name Template file name.
	 * @param array<string, mixed> $args          Variables to extract.
	 * @return void
	 */
	public function load_template( string $template_name, array $args = [] ): void {
		$theme_template  = get_stylesheet_directory() . '/giga-stock-alerts/' . $template_name;
		$plugin_template = GIGA_SA_PLUGIN_DIR . 'templates/' . $template_name;

		$template_path = file_exists( $theme_template ) ? $theme_template : $plugin_template;

		if ( ! file_exists( $template_path ) ) {
			return;
		}

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template_path;
	}
}
}
