<?php
/**
 * My Account Stock Alerts Handler.
 *
 * Shows the customer's subscriptions in My Account with status badges,
 * unsubscribe buttons, and a re-subscribe option for purchased/unsubscribed alerts.
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
			$new_items[ $key ] = $value;
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

		$current_user  = wp_get_current_user();
		$subscriptions = Giga_SA_DB::get_subscriptions_by_email( $current_user->user_email );

		// Separate active vs. purchased/historical.
		$active     = [];
		$historical = [];
		foreach ( $subscriptions as $sub ) {
			if ( in_array( $sub->status, [ 'purchased', 'unsubscribed' ], true ) ) {
				$historical[] = $sub;
			} else {
				$active[] = $sub;
			}
		}
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
				<h3><?php esc_html_e( 'No stock alerts yet', 'giga-stock-alerts' ); ?></h3>
				<p><?php esc_html_e( "Browse products and click 'Notify Me' on out-of-stock items.", 'giga-stock-alerts' ); ?></p>
			</div>

		<?php else : ?>

			<?php if ( ! empty( $active ) ) : ?>
				<!-- Active Subscriptions -->
				<h3 class="giga-sa-section-heading"><?php esc_html_e( 'Active Alerts', 'giga-stock-alerts' ); ?></h3>
				<div class="giga-sa-subscription-list">
					<?php foreach ( $active as $sub ) : ?>
						<?php
						$target_id = $sub->variation_id ?: $sub->product_id;
						$product   = wc_get_product( $target_id );
						if ( ! $product ) continue;
						$product_image = $product->get_image( 'thumbnail' );
						$alert_label   = ( isset( $sub->alert_type ) && 'price_drop' === $sub->alert_type )
							? __( 'Price Drop', 'giga-stock-alerts' )
							: __( 'Restock', 'giga-stock-alerts' );
						?>
						<div class="giga-sa-subscription-card">
							<div class="giga-sa-subscription-card-left">
								<div class="giga-sa-product-thumb-placeholder">
									<?php echo wp_kses_post( $product_image ); ?>
								</div>
								<div class="giga-sa-product-info">
									<h4><?php echo esc_html( $product->get_name() ); ?></h4>
									<span class="giga-sa-alert-type-badge"><?php echo esc_html( $alert_label ); ?></span>
									<span><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $sub->subscribed_at ) ) ); ?></span>
								</div>
							</div>
							<div class="giga-sa-subscription-card-right">
								<span class="giga-sa-badge giga-sa-badge-<?php echo esc_attr( $sub->status ); ?>">
									<?php echo esc_html( ucfirst( $sub->status ) ); ?>
								</span>
								<button class="giga-sa-unsubscribe-btn"
										data-id="<?php echo absint( $sub->id ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'giga_sa_unsubscribe_' . $sub->id ) ); ?>">
									<?php esc_html_e( 'Unsubscribe', 'giga-stock-alerts' ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $historical ) ) : ?>
				<!-- Historical / Re-subscribe -->
				<h3 class="giga-sa-section-heading" style="margin-top:1.5rem;">
					<?php esc_html_e( 'Past Alerts', 'giga-stock-alerts' ); ?>
				</h3>
				<div class="giga-sa-subscription-list">
					<?php foreach ( $historical as $sub ) : ?>
						<?php
						$target_id = $sub->variation_id ?: $sub->product_id;
						$product   = wc_get_product( $target_id );
						if ( ! $product ) continue;
						$product_image = $product->get_image( 'thumbnail' );
						// Only show Re-subscribe button for out-of-stock products.
						$show_resubscribe = ! $product->is_in_stock() && in_array( $sub->status, [ 'purchased', 'unsubscribed', 'notified' ], true );
						?>
						<div class="giga-sa-subscription-card giga-sa-subscription-card--historical">
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
								<?php if ( $show_resubscribe ) : ?>
									<button class="giga-sa-resubscribe-btn"
											data-id="<?php echo absint( $sub->id ); ?>"
											data-nonce="<?php echo esc_attr( wp_create_nonce( 'giga_sa_resubscribe_' . $sub->id ) ); ?>">
										<?php esc_html_e( 'Re-subscribe', 'giga-stock-alerts' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php endif; ?>
		<?php
	}
}
}
