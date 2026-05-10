<?php
/**
 * My Account Stock Alerts Handler.
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Giga_SA_My_Account' ) ) {
class Giga_SA_My_Account {

	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'init',                           [ $this, 'add_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items',  [ $this, 'add_menu_item' ] );
		add_action( 'woocommerce_account_stock-alerts_endpoint', [ $this, 'render_endpoint' ] );
	}

	/**
	 * Register the stock-alerts endpoint.
	 */
	public function add_endpoint(): void {
		add_rewrite_endpoint( 'stock-alerts', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add "Stock Alerts" to the My Account navigation.
	 */
	public function add_menu_item( array $items ): array {
		$new_items = [];
		foreach ( $items as $key => $value ) {
			$new_items[$key] = $value;
			if ( 'dashboard' === $key ) {
				$new_items['stock-alerts'] = __( 'Stock Alerts', 'giga-stock-alerts' );
			}
		}
		return $new_items;
	}

	/**
	 * Render the Stock Alerts list in My Account.
	 */
	public function render_endpoint(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user = wp_get_current_user();
		$subscriptions = Giga_SA_DB::get_subscriptions_by_email( $current_user->user_email );

		?>
		<!-- Page Header -->
		<div class="giga-sa-account-header">
			<div class="giga-sa-account-header-icon">🔔</div>
			<div>
				<h2><?php esc_html_e( 'Your Stock Alerts', 'giga-stock-alerts' ); ?></h2>
				<p><?php esc_html_e( 'Manage your product notifications', 'giga-stock-alerts' ); ?></p>
			</div>
		</div>

		<?php if ( empty( $subscriptions ) ) : ?>
			<!-- Empty State -->
			<div class="giga-sa-empty-state">
				<div class="giga-sa-empty-state-icon">🔔</div>
				<h3><?php esc_html_e( 'No active stock alerts', 'giga-stock-alerts' ); ?></h3>
				<p><?php esc_html_e( "Browse products and click 'Notify Me' on out-of-stock items.", 'giga-stock-alerts' ); ?></p>
			</div>
		<?php else : ?>
			<!-- Subscription Cards -->
			<div class="giga-sa-subscription-list">
				<?php foreach ( $subscriptions as $sub ) :
					$target_id = $sub->variation_id ?: $sub->product_id;
					$product   = wc_get_product( $target_id );
					if ( ! $product ) continue;
					$product_image = $product->get_image( 'thumbnail' );
					?>
					<div class="giga-sa-subscription-card">
						<div class="giga-sa-subscription-card-left">
							<div class="giga-sa-product-thumb-placeholder">
								<?php echo wp_kses_post( $product_image ); ?>
							</div>
							<div class="giga-sa-product-info">
								<h4><?php echo esc_html( $product->get_name() ); ?></h4>
								<span><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $sub->subscribed_at ) ) ); ?></span>
							</div>
						</div>
						<div class="giga-sa-subscription-card-right">
							<span class="giga-sa-badge giga-sa-badge-<?php echo esc_attr( $sub->status ); ?>">
								<?php echo esc_html( ucfirst( $sub->status ) ); ?>
							</span>
							<button class="giga-sa-unsub-btn"
									data-id="<?php echo absint( $sub->id ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'giga_sa_unsubscribe_' . $sub->id ) ); ?>">
								<?php esc_html_e( 'Unsubscribe', 'giga-stock-alerts' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}
}
}
