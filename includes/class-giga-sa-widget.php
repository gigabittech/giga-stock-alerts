<?php
/**
 * Frontend "Notify Me" widget for Giga Stock Alerts.
 *
 * Renders the restock notification form on out-of-stock product pages,
 * and optionally a price-drop alert form on in-stock products.
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

	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'wp_enqueue_scripts',            [ $this, 'enqueue_assets' ] );
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_widget' ], 31 );
		add_shortcode( 'giga_stock_alert', [ $this, 'render_shortcode' ] );
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	public function enqueue_assets(): void {
		$post         = get_post( get_the_ID() );
		$post_content = $post ? ( $post->post_content ?? '' ) : '';
		$on_product   = is_product();
		$on_shortcode = has_shortcode( $post_content, 'giga_stock_alert' );
		$on_account   = is_account_page();

		if ( ! $on_product && ! $on_shortcode && ! $on_account ) {
			return;
		}

		wp_enqueue_style(
			'giga-sa-frontend',
			GIGA_SA_PLUGIN_URL . 'public/css/giga-sa-frontend.css',
			[],
			GIGA_SA_VERSION
		);

		// Apply admin-configured button colour via inline style.
		$btn_color = sanitize_hex_color( get_option( 'giga_sa_button_color', '#2271b1' ) );
		if ( $btn_color ) {
			wp_add_inline_style(
				'giga-sa-frontend',
				'.giga-sa-submit-btn { background-color: ' . $btn_color . ' !important; border-color: ' . $btn_color . ' !important; }'
			);
		}

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
	 * Render the "Notify Me" widget on single-product pages.
	 * - Out-of-stock: shows restock alert form.
	 * - In-stock (and price drop enabled): shows price drop alert form.
	 */
	public function render_widget(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$product_id = $product->get_id();

		// Respect the per-product "Disable Widget" setting.
		if ( class_exists( 'Giga_SA_Product_Meta' ) && Giga_SA_Product_Meta::is_widget_disabled( $product_id ) ) {
			return;
		}

		// Respect the "Hide Widget on Hidden Products" setting.
		$hide_on_hidden = filter_var( get_option( 'giga_sa_hide_outofstock', false ), FILTER_VALIDATE_BOOLEAN );
		if ( $hide_on_hidden && ( ! $product->is_visible() || 'hidden' === $product->get_catalog_visibility() ) ) {
			return;
		}

		if ( $product->is_in_stock() && ! $product->is_type( 'variable' ) ) {
			// In-stock simple product — show price drop form if feature is enabled.
			if ( filter_var( get_option( 'giga_sa_price_drop_enabled', false ), FILTER_VALIDATE_BOOLEAN ) ) {
				$this->display_price_drop_form( $product );
			}
			return;
		}

		$this->display_form( $product );
	}

	/**
	 * Shortcode: [giga_stock_alert product_id="123" show_name="yes" heading="..." button_text="..."]
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts( [
			'product_id'  => 0,
			'show_name'   => '',   // 'yes'/'no' — overrides global setting when set
			'heading'     => '',   // Overrides global heading setting
			'button_text' => '',   // Overrides global button text setting
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

		// Per-product override check.
		if ( class_exists( 'Giga_SA_Product_Meta' ) && Giga_SA_Product_Meta::is_widget_disabled( $product_obj->get_id() ) ) {
			return '';
		}

		ob_start();

		// Override heading / button_text / show_name from shortcode attrs.
		$overrides = [];
		if ( ! empty( $atts['heading'] ) ) {
			$overrides['heading'] = sanitize_text_field( $atts['heading'] );
		}
		if ( ! empty( $atts['button_text'] ) ) {
			$overrides['btn_text'] = sanitize_text_field( $atts['button_text'] );
		}
		if ( '' !== $atts['show_name'] ) {
			$overrides['show_name_field'] = ( 'yes' === strtolower( $atts['show_name'] ) );
		}

		$this->display_form( $product_obj, $overrides );
		return ob_get_clean();
	}

	/**
	 * Output the restock notify-me form.
	 *
	 * @param WC_Product           $product
	 * @param array<string, mixed> $overrides Optional field overrides from shortcode.
	 */
	private function display_form( WC_Product $product, array $overrides = [] ): void {
		$heading         = $overrides['heading']        ?? get_option( 'giga_sa_button_heading', __( 'Out of Stock — Get Notified!', 'giga-stock-alerts' ) );
		$btn_text        = $overrides['btn_text']       ?? get_option( 'giga_sa_button_text', __( 'Notify Me!', 'giga-stock-alerts' ) );
		$show_name_field = $overrides['show_name_field'] ?? filter_var( get_option( 'giga_sa_show_name_field', true ), FILTER_VALIDATE_BOOLEAN );
		$gdpr_text       = get_option( 'giga_sa_gdpr_text', __( 'I agree to receive email notifications regarding this product.', 'giga-stock-alerts' ) );

		$is_hidden = $product->is_type( 'variable' ) && $product->is_in_stock();

		$this->load_template(
			'notify-me-widget.php',
			[
				'product'         => $product,
				'heading'         => $heading,
				'btn_text'        => $btn_text,
				'gdpr_text'       => $gdpr_text,
				'is_hidden'       => $is_hidden,
				'show_name_field' => $show_name_field,
				'alert_type'      => 'restock',
			]
		);
	}

	/**
	 * Output the price drop alert form (shown on in-stock products).
	 *
	 * @param WC_Product $product
	 */
	private function display_price_drop_form( WC_Product $product ): void {
		$gdpr_text       = get_option( 'giga_sa_gdpr_text', __( 'I agree to receive email notifications regarding this product.', 'giga-stock-alerts' ) );
		$show_name_field = filter_var( get_option( 'giga_sa_show_name_field', true ), FILTER_VALIDATE_BOOLEAN );

		$this->load_template(
			'notify-me-widget.php',
			[
				'product'         => $product,
				'heading'         => __( '🔔 Watch for a Price Drop', 'giga-stock-alerts' ),
				'btn_text'        => __( 'Alert Me on Price Drop', 'giga-stock-alerts' ),
				'gdpr_text'       => $gdpr_text,
				'is_hidden'       => false,
				'show_name_field' => $show_name_field,
				'alert_type'      => 'price_drop',
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
	 */
	public function load_template( string $template_name, array $args = [] ): void {
		$theme_template  = get_stylesheet_directory() . '/giga-stock-alerts/' . $template_name;
		$plugin_template = GIGA_SA_PLUGIN_DIR . 'templates/' . $template_name;
		$template_path   = file_exists( $theme_template ) ? $theme_template : $plugin_template;

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
