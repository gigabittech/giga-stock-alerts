<?php
/**
 * Per-Product Override Settings for Giga Stock Alerts.
 *
 * Adds a "Giga Stock Alerts" section to the WooCommerce product General tab,
 * allowing per-product control over:
 *   - Disabling the notify widget entirely
 *   - A custom email subject for restock notifications
 *   - A maximum subscriber cap for that product
 *
 * @package GigaStockAlerts
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_Product_Meta' ) ) {
class Giga_SA_Product_Meta {

	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		// Render fields in the General tab.
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_fields' ] );
		// Save when the product is saved.
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_fields' ], 10, 1 );
	}

	// -----------------------------------------------------------------------
	// Admin UI
	// -----------------------------------------------------------------------

	/**
	 * Render the Giga Stock Alerts option group in the product General tab.
	 */
	public function render_fields(): void {
		global $post;
		$product_id = (int) $post->ID;
		?>
		<div class="options_group giga-sa-product-options" style="border-top:1px solid #eee;padding-top:12px;">
			<p class="form-field" style="padding: 0 12px;">
				<strong style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#555;">
					🔔 <?php esc_html_e( 'Giga Stock Alerts', 'giga-stock-alerts' ); ?>
				</strong>
			</p>

			<?php
			woocommerce_wp_checkbox( [
				'id'          => '_giga_sa_disable_widget',
				'label'       => __( 'Disable "Notify Me" widget', 'giga-stock-alerts' ),
				'description' => __( 'Hides the stock alert form on this product page.', 'giga-stock-alerts' ),
				'value'       => get_post_meta( $product_id, '_giga_sa_disable_widget', true ),
			] );

			woocommerce_wp_text_input( [
				'id'          => '_giga_sa_email_subject',
				'label'       => __( 'Custom Restock Email Subject', 'giga-stock-alerts' ),
				'description' => __( 'Overrides the global subject. Use {product_name} and {store_name}.', 'giga-stock-alerts' ),
				'placeholder' => __( 'Great news! {product_name} is back in stock!', 'giga-stock-alerts' ),
				'value'       => (string) get_post_meta( $product_id, '_giga_sa_email_subject', true ),
				'desc_tip'    => true,
			] );

			woocommerce_wp_text_input( [
				'id'                => '_giga_sa_max_subscribers',
				'label'             => __( 'Max Subscriber Cap', 'giga-stock-alerts' ),
				'description'       => __( 'Stop accepting new subscriptions after this many. 0 = unlimited.', 'giga-stock-alerts' ),
				'type'              => 'number',
				'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
				'value'             => (string) ( (int) get_post_meta( $product_id, '_giga_sa_max_subscribers', true ) ),
				'desc_tip'          => true,
			] );
			?>
		</div>
		<?php
	}

	/**
	 * Save the product meta fields when the product is saved.
	 *
	 * Nonce verification is handled upstream by WooCommerce's own save process
	 * before `woocommerce_process_product_meta` fires.
	 *
	 * @param int $product_id
	 */
	public function save_fields( int $product_id ): void {
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$disable_widget = isset( $_POST['_giga_sa_disable_widget'] ) ? 'yes' : 'no';
		$email_subject  = isset( $_POST['_giga_sa_email_subject'] )
			? sanitize_text_field( wp_unslash( $_POST['_giga_sa_email_subject'] ) )
			: '';
		$max_subs       = isset( $_POST['_giga_sa_max_subscribers'] )
			? absint( $_POST['_giga_sa_max_subscribers'] )
			: 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $product_id, '_giga_sa_disable_widget',   $disable_widget );
		update_post_meta( $product_id, '_giga_sa_email_subject',    $email_subject );
		update_post_meta( $product_id, '_giga_sa_max_subscribers',  $max_subs );
	}

	// -----------------------------------------------------------------------
	// Static helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns true if the stock alert widget is disabled for this product.
	 *
	 * @param int $product_id
	 * @return bool
	 */
	public static function is_widget_disabled( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, '_giga_sa_disable_widget', true );
	}

	/**
	 * Get the per-product custom email subject (empty = use global setting).
	 *
	 * @param int $product_id
	 * @return string
	 */
	public static function get_email_subject( int $product_id ): string {
		return (string) get_post_meta( $product_id, '_giga_sa_email_subject', true );
	}

	/**
	 * Get the max subscriber cap for this product (0 = unlimited).
	 *
	 * @param int $product_id
	 * @return int
	 */
	public static function get_max_subscribers( int $product_id ): int {
		return (int) get_post_meta( $product_id, '_giga_sa_max_subscribers', true );
	}
}
}
