<?php
/**
 * Admin interface handler for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Giga_SA_Admin
 */
if ( ! class_exists( 'Giga_SA_Admin' ) ) {
class Giga_SA_Admin {

	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'admin_menu',            [ $this, 'add_menu_page' ] );
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_action_giga_sa_export_csv', [ $this, 'export_csv' ] );

		// Feature 2: Product List Badge
		add_filter( 'manage_edit-product_columns',        [ $this, 'add_product_columns' ], 20 );
		add_action( 'manage_product_posts_custom_column', [ $this, 'render_product_column' ], 10, 2 );
	}

	public function enqueue_assets( $hook ): void {
		if ( strpos( $hook, 'giga-stock-alerts' ) === false ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		
		wp_add_inline_script( 'wp-color-picker', "
			jQuery(document).ready(function($){
				$('.giga-sa-color-picker').wpColorPicker();
			});
		" );

		wp_enqueue_style(
			'giga-sa-admin',
			GIGA_SA_PLUGIN_URL . 'admin/css/giga-sa-admin.css',
			[],
			GIGA_SA_VERSION
		);

		wp_enqueue_script(
			'giga-sa-admin',
			GIGA_SA_PLUGIN_URL . 'admin/js/giga-sa-admin.js',
			[ 'jquery' ],
			GIGA_SA_VERSION,
			true
		);
	}

	// -----------------------------------------------------------------------
	// Menus & Routing
	// -----------------------------------------------------------------------

	public function add_menu_page(): void {
		add_menu_page(
			__( 'Stock Alerts', 'giga-stock-alerts' ),
			__( 'Stock Alerts', 'giga-stock-alerts' ),
			'manage_woocommerce',
			'giga-stock-alerts',
			[ $this, 'render_subscribers_page' ],
			'dashicons-bell',
			56
		);

		add_submenu_page(
			'giga-stock-alerts',
			__( 'Subscribers', 'giga-stock-alerts' ),
			__( 'Subscribers', 'giga-stock-alerts' ),
			'manage_woocommerce',
			'giga-stock-alerts',
			[ $this, 'render_subscribers_page' ]
		);

		add_submenu_page(
			'giga-stock-alerts',
			__( 'Settings', 'giga-stock-alerts' ),
			__( 'Settings', 'giga-stock-alerts' ),
			'manage_woocommerce',
			'giga-stock-alerts-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	// -----------------------------------------------------------------------
	// Settings API
	// -----------------------------------------------------------------------

	public function register_settings(): void {
		// Section 1: Widget
		add_settings_section( 'giga_sa_widget_section', __( 'Widget Settings', 'giga-stock-alerts' ), null, 'giga-stock-alerts-settings' );

		register_setting( 'giga_sa_settings_group', 'giga_sa_button_heading', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Out of Stock — Get Notified!' ] );
		register_setting( 'giga_sa_settings_group', 'giga_sa_button_text', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Notify Me!' ] );
		register_setting( 'giga_sa_settings_group', 'giga_sa_success_message', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => "You'll be notified when this product is back!" ] );
		register_setting( 'giga_sa_settings_group', 'giga_sa_gdpr_text', [ 'sanitize_callback' => 'wp_kses_post', 'default' => 'I agree to receive email notifications regarding this product.' ] );
		register_setting( 'giga_sa_settings_group', 'giga_sa_button_color', [ 'sanitize_callback' => 'sanitize_hex_color', 'default' => '#2271b1' ] );

		add_settings_field( 'giga_sa_button_heading', __( 'Widget Heading', 'giga-stock-alerts' ), [ $this, 'render_text_field' ], 'giga-stock-alerts-settings', 'giga_sa_widget_section', [ 'id' => 'giga_sa_button_heading' ] );
		add_settings_field( 'giga_sa_button_text', __( 'Button Text', 'giga-stock-alerts' ), [ $this, 'render_text_field' ], 'giga-stock-alerts-settings', 'giga_sa_widget_section', [ 'id' => 'giga_sa_button_text' ] );
		add_settings_field( 'giga_sa_success_message', __( 'Success Message', 'giga-stock-alerts' ), [ $this, 'render_text_field' ], 'giga-stock-alerts-settings', 'giga_sa_widget_section', [ 'id' => 'giga_sa_success_message' ] );
		add_settings_field( 'giga_sa_gdpr_text', __( 'GDPR Acceptance Text', 'giga-stock-alerts' ), [ $this, 'render_textarea_field' ], 'giga-stock-alerts-settings', 'giga_sa_widget_section', [ 'id' => 'giga_sa_gdpr_text' ] );
		add_settings_field( 'giga_sa_button_color', __( 'Button Color', 'giga-stock-alerts' ), [ $this, 'render_color_picker' ], 'giga-stock-alerts-settings', 'giga_sa_widget_section', [ 'id' => 'giga_sa_button_color' ] );

		// Section 2: Email
		add_settings_section( 'giga_sa_email_section', __( 'Email Settings', 'giga-stock-alerts' ), null, 'giga-stock-alerts-settings' );

		register_setting( 'giga_sa_settings_group', 'giga_sa_double_optin', [ 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ] );
		register_setting( 'giga_sa_settings_group', 'giga_sa_admin_notify', [ 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ] );
		register_setting( 'giga_sa_settings_group', 'giga_sa_email_subject', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Great news! {product_name} is back in stock!' ] );
		register_setting( 'giga_sa_settings_group', 'giga_sa_batch_size', [ 'sanitize_callback' => 'absint', 'default' => 50 ] );

		add_settings_field( 'giga_sa_double_optin', __( 'Require Double Opt-in', 'giga-stock-alerts' ), [ $this, 'render_checkbox_field' ], 'giga-stock-alerts-settings', 'giga_sa_email_section', [ 'id' => 'giga_sa_double_optin' ] );
		add_settings_field( 'giga_sa_admin_notify', __( 'Admin Notification', 'giga-stock-alerts' ), [ $this, 'render_checkbox_field' ], 'giga-stock-alerts-settings', 'giga_sa_email_section', [ 'id' => 'giga_sa_admin_notify' ] );
		add_settings_field( 'giga_sa_email_subject', __( 'Restock Email Subject', 'giga-stock-alerts' ), [ $this, 'render_text_field' ], 'giga-stock-alerts-settings', 'giga_sa_email_section', [ 'id' => 'giga_sa_email_subject', 'class' => 'regular-text' ] );
		add_settings_field( 'giga_sa_batch_size', __( 'Batch Size', 'giga-stock-alerts' ), [ $this, 'render_number_field' ], 'giga-stock-alerts-settings', 'giga_sa_email_section', [ 'id' => 'giga_sa_batch_size', 'min' => 10, 'max' => 500 ] );
	}

	public function render_text_field( $args ): void {
		$value = get_option( $args['id'] );
		echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	public function render_textarea_field( $args ): void {
		$value = get_option( $args['id'] );
		echo '<textarea id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['id'] ) . '" class="large-text" rows="3">' . esc_textarea( $value ) . '</textarea>';
	}

	public function render_checkbox_field( $args ): void {
		$value = get_option( $args['id'] );
		echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['id'] ) . '" value="1" ' . checked( 1, $value, false ) . ' />';
	}

	public function render_number_field( $args ): void {
		$value = get_option( $args['id'] );
		echo '<input type="number" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $args['min'] ) . '" max="' . esc_attr( $args['max'] ) . '" class="small-text" />';
	}

	public function render_color_picker( $args ): void {
		$value = get_option( $args['id'] );
		echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" class="giga-sa-color-picker" data-default-color="' . esc_attr( get_option( $args['id'], '#2271b1' ) ) . '" />';
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Handle successful save notice natively by settings API via settings_errors()
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Giga Stock Alerts Settings', 'giga-stock-alerts' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'giga_sa_settings_group' );
				do_settings_sections( 'giga-stock-alerts-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Subscribers List Table Page
	// -----------------------------------------------------------------------

	public function render_subscribers_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$table = new Giga_SA_List_Table();
		$table->process_bulk_action(); // Process delete logic before querying items
		$table->prepare_items();

		?>
		<div class="wrap giga-sa-admin-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Stock Alert Subscribers', 'giga-stock-alerts' ); ?></h1>
			
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=giga_sa_export_csv' ), 'giga_sa_export' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'giga-stock-alerts' ); ?>
			</a>
			<hr class="wp-header-end">

			<form id="subs-filter" method="get">
				<!-- Keep page info active -->
				<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : 'giga-stock-alerts' ); ?>" />
				<?php
				$table->views();
				$table->search_box( __( 'Search Emails', 'giga-stock-alerts' ), 'search_id' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Export CSV
	// -----------------------------------------------------------------------

	public function export_csv(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'giga-stock-alerts' ) );
		}
		
		check_admin_referer( 'giga_sa_export' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'giga_stock_alerts';
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$subscribers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY subscribed_at DESC" ), ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=giga-stock-alerts-' . gmdate( 'Y-m-d' ) . '.csv' );
		
		$output = fopen( 'php://output', 'w' );
		if ( ! $output ) {
			return;
		}

		fputcsv( $output, [ 
			__( 'ID', 'giga-stock-alerts' ), 
			__( 'Product ID', 'giga-stock-alerts' ), 
			__( 'Variation ID', 'giga-stock-alerts' ), 
			__( 'Email', 'giga-stock-alerts' ), 
			__( 'Name', 'giga-stock-alerts' ), 
			__( 'Status', 'giga-stock-alerts' ), 
			__( 'IP Address', 'giga-stock-alerts' ), 
			__( 'Subscribed At', 'giga-stock-alerts' ), 
			__( 'Notified At', 'giga-stock-alerts' ) 
		] );

		if ( ! empty( $subscribers ) ) {
			foreach ( $subscribers as $row ) {
				fputcsv( $output, [
					$row['id'],
					$row['product_id'],
					$row['variation_id'],
					$row['email'],
					$row['customer_name'],
					$row['status'],
					$row['ip_address'],
					$row['subscribed_at'],
					$row['notified_at'],
				] );
			}
		}

		fclose( $output );
		exit;
	}

	// -----------------------------------------------------------------------
	// Feature 2: Product List Columns
	// -----------------------------------------------------------------------

	/**
	 * Add "Waiting" column to WooCommerce product list.
	 */
	public function add_product_columns( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $label ) {
			$new_columns[$key] = $label;
			if ( 'name' === $key ) {
				$new_columns['giga_sa_waiting'] = __( 'Waiting', 'giga-stock-alerts' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render the "Waiting" column content.
	 */
	public function render_product_column( string $column, int $post_id ): void {
		if ( 'giga_sa_waiting' !== $column ) {
			return;
		}

		$count = Giga_SA_DB::count_subscriptions_by_product( $post_id );

		if ( $count > 0 ) {
			printf( 
				'<span class="giga-sa-badge">%d %s</span>', 
				(int) $count, 
				esc_html__( 'waiting', 'giga-stock-alerts' ) 
			);
		} else {
			echo '<span class="na">&mdash;</span>';
		}
	}
}
}

/**
 * Custom WP_List_Table for Subscribers.
 */
if ( ! class_exists( 'Giga_SA_List_Table' ) ) {
class Giga_SA_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => __( 'Subscriber', 'giga-stock-alerts' ),
			'plural'   => __( 'Subscribers', 'giga-stock-alerts' ),
			'ajax'     => false,
		] );
	}

	protected function get_views(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'giga_stock_alerts';
		
		$base_url = admin_url( 'admin.php?page=giga-stock-alerts' );
		$current  = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : 'all';

		$views = [];
		$statuses = [
			'all'          => __( 'All', 'giga-stock-alerts' ),
			'pending'      => __( 'Pending', 'giga-stock-alerts' ),
			'confirmed'    => __( 'Confirmed', 'giga-stock-alerts' ),
			'notified'     => __( 'Notified', 'giga-stock-alerts' ),
			'purchased'    => __( 'Purchased', 'giga-stock-alerts' ),
			'unsubscribed' => __( 'Unsubscribed', 'giga-stock-alerts' ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$counts = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status" ), OBJECT_K );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table}" ) );

		foreach ( $statuses as $key => $label ) {
			$count = 'all' === $key ? $total : ( isset( $counts[ $key ] ) ? $counts[ $key ]->count : 0 );
			
			$class = ( $current === $key ) ? 'current' : '';
			$url   = 'all' === $key ? $base_url : add_query_arg( 'status_filter', $key, $base_url );
			
			$views[ $key ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$class,
				esc_html( $label ),
				(int) $count
			);
		}

		return $views;
	}

	public function get_columns(): array {
		return [
			'cb'            => '<input type="checkbox" />',
			'email'         => __( 'Email', 'giga-stock-alerts' ),
			'product'       => __( 'Product', 'giga-stock-alerts' ),
			'status'        => __( 'Status', 'giga-stock-alerts' ),
			'subscribed_at' => __( 'Subscribed Date', 'giga-stock-alerts' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'email'         => [ 'email', false ],
			'subscribed_at' => [ 'subscribed_at', true ], // true = default desc
		];
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] );
	}

	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="subscription_ids[]" value="%s" />',
			$item['id']
		);
	}

	protected function column_email( $item ): string {
		$delete_nonce = wp_create_nonce( 'bulk-' . $this->_args['plural'] );
		$actions = [
			'delete' => sprintf(
				'<a href="?page=%s&action=%s&subscription_ids[]=%s&_wpnonce=%s">%s</a>',
				esc_attr( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : 'giga-stock-alerts' ),
				'delete',
				absint( $item['id'] ),
				$delete_nonce,
				__( 'Delete', 'giga-stock-alerts' )
			),
		];
		
		$name = $item['customer_name'] ? '<br><small>' . esc_html( $item['customer_name'] ) . '</small>' : '';

		return sprintf( '<strong>%1$s</strong>%2$s %3$s',
			esc_html( $item['email'] ),
			$name,
			$this->row_actions( $actions )
		);
	}

	protected function column_product( $item ): string {
		$target_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
		$product   = wc_get_product( $target_id );
		
		if ( ! $product ) {
			return sprintf( '<del>%s (ID: %d)</del>', __( 'Deleted Product', 'giga-stock-alerts' ), $target_id );
		}

		$edit_url = admin_url( 'post.php?post=' . ( $item['variation_id'] ? $item['product_id'] : $target_id ) . '&action=edit' );
		
		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $edit_url ),
			esc_html( $product->get_name() )
		);
	}

	protected function column_status( $item ): string {
		// Colors: pending=grey, confirmed=blue, notified=orange, purchased=green, unsubscribed=red
		$colors = [
			'pending'      => 'background: #e2e8f0; color: #475569;',
			'confirmed'    => 'background: #e0f2fe; color: #0284c7;',
			'notified'     => 'background: #ffedd5; color: #c2410c;',
			'purchased'    => 'background: #dcfce3; color: #166534;',
			'unsubscribed' => 'background: #fee2e2; color: #b91c1c;',
		];

		$style  = $colors[ $item['status'] ] ?? 'background: #eee; color: #333;';
		$status = ucfirst( $item['status'] );

		return sprintf(
			'<span class="giga-badge" style="%s">%s</span>',
			esc_attr( $style ),
			esc_html( $status )
		);
	}

	protected function column_subscribed_at( $item ): string {
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['subscribed_at'] ) ) );
	}

	protected function get_bulk_actions(): array {
		return [
			'delete' => __( 'Delete', 'giga-stock-alerts' ),
		];
	}

	public function process_bulk_action(): void {
		if ( 'delete' === $this->current_action() ) {
			check_admin_referer( 'bulk-' . $this->_args['plural'] );

			$ids = isset( $_REQUEST['subscription_ids'] ) ? wp_unslash( $_REQUEST['subscription_ids'] ) : [];
			if ( is_array( $ids ) && ! empty( $ids ) ) {
				$ids = array_map( 'absint', $ids );
				
				global $wpdb;
				$table = $wpdb->prefix . 'giga_stock_alerts';
				
				$ids = array_map( 'intval', $ids );
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($placeholders)", ...$ids ) );
				
				add_settings_error( 'giga_sa_messages', 'giga_sa_deleted', sprintf( _n( '%d subscription deleted.', '%d subscriptions deleted.', count( $ids ), 'giga-stock-alerts' ), count( $ids ) ), 'success' );
			}
		}
	}

	public function prepare_items(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'giga_stock_alerts';

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Columns
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$where  = "1=1";
		$params = [];

		// Search
		if ( ! empty( $_REQUEST['s'] ) ) {
			$where .= " AND email LIKE %s";
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) . '%';
		}

		// Filter
		if ( ! empty( $_REQUEST['status_filter'] ) && 'all' !== $_REQUEST['status_filter'] ) {
			$where .= " AND status = %s";
			$params[] = sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) );
		}

		$sql = "SELECT * FROM {$table} WHERE {$where}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, ...$params );
		}

		// Order
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'subscribed_at';
		$order   = ! empty( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) ? 'ASC' : 'DESC';
		
		$valid_columns = [ 'email', 'subscribed_at', 'id' ];
		if ( ! in_array( $orderby, $valid_columns, true ) ) {
			$orderby = 'subscribed_at';
		}

		$sql .= " ORDER BY {$orderby} {$order}";

		// Meta counts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$sql}) AS count_table" );

		// Pagination
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, ( $current_page - 1 ) * $per_page );

		// Execute
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$this->items = $wpdb->get_results( $sql, ARRAY_A );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );
	}
}
}
