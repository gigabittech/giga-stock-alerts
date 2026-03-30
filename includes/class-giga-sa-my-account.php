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
		<h2><?php esc_html_e( 'Your Stock Alert Subscriptions', 'giga-stock-alerts' ); ?></h2>

		<?php if ( empty( $subscriptions ) ) : ?>
			<p><?php esc_html_e( 'You have no active stock alert subscriptions.', 'giga-stock-alerts' ); ?></p>
		<?php else : ?>
			<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'giga-stock-alerts' ); ?></th>
						<th><?php esc_html_e( 'Status', 'giga-stock-alerts' ); ?></th>
						<th><?php esc_html_e( 'Subscribed On', 'giga-stock-alerts' ); ?></th>
						<th><?php esc_html_e( 'Action', 'giga-stock-alerts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $subscriptions as $sub ) : 
						$target_id = $sub->variation_id ?: $sub->product_id;
						$product   = wc_get_product( $target_id );
						if ( ! $product ) continue;
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</a>
							</td>
							<td>
								<span class="giga-badge status-<?php echo esc_attr( $sub->status ); ?>">
									<?php echo esc_html( ucfirst( $sub->status ) ); ?>
								</span>
							</td>
							<td>
								<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $sub->subscribed_at ) ) ); ?>
							</td>
							<td>
								<button class="button giga-sa-unsubscribe-btn" 
										data-id="<?php echo absint( $sub->id ); ?>" 
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'giga_sa_unsubscribe_' . $sub->id ) ); ?>">
									<?php esc_html_e( 'Unsubscribe', 'giga-stock-alerts' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}
}
}
