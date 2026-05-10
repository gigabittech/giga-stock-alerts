<?php
/**
 * Admin interface handler for Giga Stock Alerts.
 *
 * @package GigaStockAlerts
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Giga_SA_Admin
 */
if (!class_exists('Giga_SA_Admin')) {
	class Giga_SA_Admin
	{

		public function __construct()
		{
			$this->register_hooks();
		}

		private function register_hooks(): void
		{
			add_action('admin_menu', [$this, 'add_menu_page']);
			add_action('admin_init', [$this, 'register_settings']);
			add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
			add_action('admin_action_giga_sa_export_csv', [$this, 'export_csv']);
			add_action('wp_ajax_giga_sa_update_subscription', [$this, 'ajax_update_subscription']);

			// Suppress other plugins' admin notices on our pages.
			add_action('admin_head', [$this, 'suppress_admin_notices']);

			// Feature 2: Product List Badge
			add_filter('manage_edit-product_columns', [$this, 'add_product_columns'], 20);
			add_action('manage_product_posts_custom_column', [$this, 'render_product_column'], 10, 2);
		}

		/**
		 * Remove all admin notices on Giga Stock Alerts pages to keep the UI clean.
		 */
		public function suppress_admin_notices(): void
		{
			$screen = get_current_screen();
			if ( $screen && strpos( $screen->id, 'giga-stock-alerts' ) !== false ) {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
			}
		}

		public function enqueue_assets($hook): void
		{
			$screen = get_current_screen();
			$is_product_list = ($screen && 'edit-product' === $screen->id);
			$is_plugin_page = (strpos($hook, 'giga-stock-alerts') !== false);

			if (!$is_plugin_page && !$is_product_list) {
				return;
			}

			if ($is_plugin_page) {
				wp_enqueue_style('wp-color-picker');
				wp_enqueue_script('wp-color-picker');

				wp_add_inline_script('wp-color-picker', "
				jQuery(document).ready(function($){
					$('.giga-sa-color-picker').wpColorPicker();
				});
			");
			}

			wp_enqueue_style(
				'giga-sa-admin',
				GIGA_SA_PLUGIN_URL . 'admin/css/giga-sa-admin.css',
				[],
				GIGA_SA_VERSION
			);

			wp_enqueue_script(
				'giga-sa-admin',
				GIGA_SA_PLUGIN_URL . 'admin/js/giga-sa-admin.js',
				['jquery'],
				GIGA_SA_VERSION,
				true
			);

			wp_localize_script('giga-sa-admin', 'gigaSAAdmin', [
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'updateNonce' => wp_create_nonce('giga_sa_update_subscription'),
			]);
		}

		// -----------------------------------------------------------------------
		// Menus & Routing
		// -----------------------------------------------------------------------

		public function add_menu_page(): void
		{
			add_menu_page(
				__('Stock Alerts', 'giga-stock-alerts'),
				__('Stock Alerts', 'giga-stock-alerts'),
				'manage_woocommerce',
				'giga-stock-alerts',
				[$this, 'render_dashboard_page'],
				'dashicons-bell',
				56
			);

			add_submenu_page(
				'giga-stock-alerts',
				__('Dashboard', 'giga-stock-alerts'),
				__('Dashboard', 'giga-stock-alerts'),
				'manage_woocommerce',
				'giga-stock-alerts',
				[$this, 'render_dashboard_page']
			);

			add_submenu_page(
				'giga-stock-alerts',
				__('Subscribers', 'giga-stock-alerts'),
				__('Subscribers', 'giga-stock-alerts'),
				'manage_woocommerce',
				'giga-stock-alerts-subscribers',
				[$this, 'render_subscribers_page']
			);

			add_submenu_page(
				'giga-stock-alerts',
				__('Settings', 'giga-stock-alerts'),
				__('Settings', 'giga-stock-alerts'),
				'manage_woocommerce',
				'giga-stock-alerts-settings',
				[$this, 'render_settings_page']
			);

			add_submenu_page(
				'giga-stock-alerts',
				__('Plugin Details', 'giga-stock-alerts'),
				__('Plugin Details', 'giga-stock-alerts'),
				'manage_woocommerce',
				'giga-stock-alerts-about',
				[$this, 'render_about_page']
			);
		}

		// -----------------------------------------------------------------------
		// Settings API
		// -----------------------------------------------------------------------

		public function register_settings(): void
		{

			// --- Tab 1: Widget ---
			add_settings_section('giga_sa_widget_section', __('Widget Settings', 'giga-stock-alerts'), null, 'giga-stock-alerts-settings');

			register_setting('giga_sa_settings_group', 'giga_sa_button_heading', ['sanitize_callback' => 'sanitize_text_field', 'default' => __('Out of Stock — Get Notified!', 'giga-stock-alerts')]);
			register_setting('giga_sa_settings_group', 'giga_sa_button_text', ['sanitize_callback' => 'sanitize_text_field', 'default' => __('Notify Me!', 'giga-stock-alerts')]);
			register_setting('giga_sa_settings_group', 'giga_sa_button_color', ['sanitize_callback' => 'sanitize_hex_color', 'default' => '#2271b1']);
			register_setting('giga_sa_settings_group', 'giga_sa_success_message', ['sanitize_callback' => 'sanitize_text_field', 'default' => __("You'll be notified when this product is back in stock!", 'giga-stock-alerts')]);
			register_setting('giga_sa_settings_group', 'giga_sa_gdpr_text', ['sanitize_callback' => 'wp_kses_post', 'default' => __('I agree to receive stock notifications for this product.', 'giga-stock-alerts')]);
			register_setting('giga_sa_settings_group', 'giga_sa_show_name_field', ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true]);

			add_settings_field('giga_sa_button_heading', __('Form Heading', 'giga-stock-alerts'), [$this, 'render_text_field'], 'giga-stock-alerts-settings', 'giga_sa_widget_section', ['id' => 'giga_sa_button_heading']);
			add_settings_field('giga_sa_button_text', __('Button Text', 'giga-stock-alerts'), [$this, 'render_text_field'], 'giga-stock-alerts-settings', 'giga_sa_widget_section', ['id' => 'giga_sa_button_text']);
			add_settings_field('giga_sa_button_color', __('Button Color', 'giga-stock-alerts'), [$this, 'render_color_picker'], 'giga-stock-alerts-settings', 'giga_sa_widget_section', ['id' => 'giga_sa_button_color']);
			add_settings_field('giga_sa_success_message', __('Success Message', 'giga-stock-alerts'), [$this, 'render_text_field'], 'giga-stock-alerts-settings', 'giga_sa_widget_section', ['id' => 'giga_sa_success_message']);
			add_settings_field('giga_sa_gdpr_text', __('GDPR Consent Text', 'giga-stock-alerts'), [$this, 'render_textarea_field'], 'giga-stock-alerts-settings', 'giga_sa_widget_section', ['id' => 'giga_sa_gdpr_text']);
			add_settings_field('giga_sa_show_name_field', __('Show Name Field', 'giga-stock-alerts'), [$this, 'render_checkbox_field'], 'giga-stock-alerts-settings', 'giga_sa_widget_section', ['id' => 'giga_sa_show_name_field']);

			// --- Tab 2: Email ---
			add_settings_section('giga_sa_email_section', __('Email Settings', 'giga-stock-alerts'), null, 'giga-stock-alerts-settings');

			register_setting('giga_sa_settings_group', 'giga_sa_double_optin', ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true]);
			register_setting('giga_sa_settings_group', 'giga_sa_email_subject', ['sanitize_callback' => 'sanitize_text_field', 'default' => __('Great news! {product_name} is back in stock!', 'giga-stock-alerts')]);
			register_setting('giga_sa_settings_group', 'giga_sa_email_from_name', ['sanitize_callback' => 'sanitize_text_field', 'default' => '']);
			register_setting('giga_sa_settings_group', 'giga_sa_admin_notify', ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true]);
			register_setting('giga_sa_settings_group', 'giga_sa_batch_size', ['sanitize_callback' => 'absint', 'default' => 50]);

			add_settings_field('giga_sa_double_optin', __('Enable Double Opt-In', 'giga-stock-alerts'), [$this, 'render_checkbox_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_email_section', ['id' => 'giga_sa_double_optin', 'desc' => __('Subscribers must confirm their email before receiving alerts.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_email_subject', __('Email Subject', 'giga-stock-alerts'), [$this, 'render_text_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_email_section', ['id' => 'giga_sa_email_subject', 'class' => 'regular-text', 'desc' => __('Use {product_name} to insert the product name.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_email_from_name', __('From Name', 'giga-stock-alerts'), [$this, 'render_text_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_email_section', ['id' => 'giga_sa_email_from_name', 'class' => 'regular-text', 'desc' => __('Leave empty to use the WooCommerce store name.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_admin_notify', __('Admin Notification', 'giga-stock-alerts'), [$this, 'render_checkbox_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_email_section', ['id' => 'giga_sa_admin_notify', 'desc' => __('Email me when someone subscribes to a stock alert.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_batch_size', __('Emails Per Batch', 'giga-stock-alerts'), [$this, 'render_number_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_email_section', ['id' => 'giga_sa_batch_size', 'min' => 10, 'max' => 100, 'desc' => __('Max emails sent per batch when notifying subscribers.', 'giga-stock-alerts')]);

			// --- Tab 3: General ---
			add_settings_section('giga_sa_general_section', __('General Settings', 'giga-stock-alerts'), null, 'giga-stock-alerts-settings');

			register_setting('giga_sa_settings_group', 'giga_sa_notification_delay', ['sanitize_callback' => 'absint', 'default' => 1]);
			register_setting('giga_sa_settings_group', 'giga_sa_auto_confirm_days', ['sanitize_callback' => 'absint', 'default' => 7]);
			register_setting('giga_sa_settings_group', 'giga_sa_hide_outofstock', ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => false]);

			add_settings_field('giga_sa_notification_delay', __('Notification Delay (minutes)', 'giga-stock-alerts'), [$this, 'render_number_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_general_section', ['id' => 'giga_sa_notification_delay', 'min' => 1, 'max' => 60, 'desc' => __('How long to wait after restock before sending emails.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_auto_confirm_days', __('Auto-expire Pending Subscriptions (days)', 'giga-stock-alerts'), [$this, 'render_number_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_general_section', ['id' => 'giga_sa_auto_confirm_days', 'min' => 1, 'max' => 30, 'desc' => __('Automatically delete unconfirmed subscriptions after this many days.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_hide_outofstock', __('Hide Widget on Hidden Products', 'giga-stock-alerts'), [$this, 'render_checkbox_field'], 'giga-stock-alerts-settings', 'giga_sa_general_section', ['id' => 'giga_sa_hide_outofstock']);

			// --- Tab 4: Advanced ---
			add_settings_section('giga_sa_advanced_section', __('Advanced Settings', 'giga-stock-alerts'), null, 'giga-stock-alerts-settings');

			register_setting('giga_sa_settings_group', 'giga_sa_rate_limit', ['sanitize_callback' => 'absint', 'default' => 3]);
			register_setting('giga_sa_settings_group', 'giga_sa_delete_data', ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => false]);
			register_setting('giga_sa_settings_group', 'giga_sa_debug_mode', ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => false]);

			add_settings_field('giga_sa_rate_limit', __('Rate Limit (per minute per IP)', 'giga-stock-alerts'), [$this, 'render_number_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_advanced_section', ['id' => 'giga_sa_rate_limit', 'min' => 1, 'max' => 10, 'desc' => __('Prevents spam. Recommended: 3.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_delete_data', __('Delete All Data on Uninstall', 'giga-stock-alerts'), [$this, 'render_checkbox_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_advanced_section', ['id' => 'giga_sa_delete_data', 'desc' => __('Warning: Permanently deletes all subscribers and logs on uninstall.', 'giga-stock-alerts')]);
			add_settings_field('giga_sa_debug_mode', __('Enable Debug Mode', 'giga-stock-alerts'), [$this, 'render_checkbox_with_desc'], 'giga-stock-alerts-settings', 'giga_sa_advanced_section', ['id' => 'giga_sa_debug_mode', 'desc' => __('Logs plugin activity. Disable in production.', 'giga-stock-alerts')]);
		}

		// -----------------------------------------------------------------------
		// Field Renderers
		// -----------------------------------------------------------------------

		public function render_text_field($args): void
		{
			$value = get_option($args['id']);
			echo '<input type="text" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" class="regular-text" />';
		}

		public function render_text_with_desc($args): void
		{
			$this->render_text_field($args);
			if (!empty($args['desc'])) {
				echo '<p class="description">' . esc_html($args['desc']) . '</p>';
			}
		}

		public function render_textarea_field($args): void
		{
			$value = get_option($args['id']);
			echo '<textarea id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" class="large-text" rows="3">' . esc_textarea($value) . '</textarea>';
		}

		public function render_checkbox_field($args): void
		{
			$value = get_option($args['id']);
			echo '<input type="checkbox" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" value="1" ' . checked(1, $value, false) . ' />';
		}

		public function render_checkbox_with_desc($args): void
		{
			$this->render_checkbox_field($args);
			if (!empty($args['desc'])) {
				echo '<p class="description">' . esc_html($args['desc']) . '</p>';
			}
		}

		public function render_number_field($args): void
		{
			$value = get_option($args['id']);
			echo '<input type="number" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" min="' . esc_attr($args['min']) . '" max="' . esc_attr($args['max']) . '" class="small-text" />';
		}

		public function render_number_with_desc($args): void
		{
			$this->render_number_field($args);
			if (!empty($args['desc'])) {
				echo '<p class="description">' . esc_html($args['desc']) . '</p>';
			}
		}

		public function render_color_picker($args): void
		{
			$value = get_option($args['id']);
			echo '<input type="text" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" value="' . esc_attr($value) . '" class="giga-sa-color-picker" data-default-color="' . esc_attr(get_option($args['id'], '#2271b1')) . '" />';
		}

		// -----------------------------------------------------------------------
		// Settings Page Renderer (Tabbed UI)
		// -----------------------------------------------------------------------

		public function render_settings_page(): void
		{
			if (!current_user_can('manage_woocommerce')) {
				return;
			}

			$tabs = [
				'widget' => '🧩 Widget',
				'email' => '📧 Email',
				'general' => '🌐 General',
				'advanced' => '🔧 Advanced',
				'support' => '💬 Support',
			];

			$tab_sections = [
				'widget' => 'giga_sa_widget_section',
				'email' => 'giga_sa_email_section',
				'general' => 'giga_sa_general_section',
				'advanced' => 'giga_sa_advanced_section',
			];

			settings_errors('giga_sa_messages');
			?>
			<div class="wrap giga-sa-settings-wrap">
				<!-- Settings Page Header -->
				<div class="giga-sa-page-header">
					<div class="giga-sa-page-header-left">
						<div class="giga-sa-page-icon">⚙️</div>
						<div>
							<h1>Giga Stock Alerts Settings</h1>
							<p>Configure your stock alert notifications</p>
						</div>
					</div>
				</div>

				<!-- Tab Navigation -->
				<div class="giga-sa-tabs-nav">
					<?php foreach ($tabs as $key => $label): ?>
						<button href="#<?php echo esc_attr($key); ?>"
							class="giga-sa-tab-btn <?php echo ('widget' === $key) ? 'active' : ''; ?>"
							data-tab="<?php echo esc_attr($key); ?>">
							<?php echo esc_html($label); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<form action="options.php" method="post">
					<?php settings_fields('giga_sa_settings_group'); ?>

					<?php foreach ($tab_sections as $key => $section_id): ?>
						<div class="giga-sa-settings-card giga-sa-tab-panel" id="tab-<?php echo esc_attr($key); ?>">
							<?php $this->render_section_fields($section_id); ?>
						</div>
					<?php endforeach; ?>

					<div class="giga-sa-settings-card giga-sa-tab-panel" id="tab-support">
						<?php $this->render_support_tab(); ?>
					</div>

					<div style="text-align: center; margin-top: 24px;">
						<?php submit_button(__('Save Settings', 'giga-stock-alerts'), 'giga-sa-save-btn'); ?>
					</div>
				</form>
			</div>
			<?php
		}

		/**
		 * Render form-table fields for a specific settings section.
		 *
		 * @param string $section_id The settings section ID.
		 */
		private function render_section_fields(string $section_id): void
		{
			global $wp_settings_fields;

			if (!isset($wp_settings_fields['giga-stock-alerts-settings'][$section_id])) {
				return;
			}

			echo '<table class="form-table">';
			foreach ($wp_settings_fields['giga-stock-alerts-settings'][$section_id] as $field) {
				echo '<tr>';
				if (!empty($field['title'])) {
					echo '<th scope="row">' . esc_html($field['title']) . '</th>';
				}
				echo '<td>';
				call_user_func($field['callback'], $field['args'] ?? []);
				echo '</td></tr>';
			}
			echo '</table>';
		}

		/**
		 * Render the Support tab content (static, no form fields).
		 */
		private function render_support_tab(): void
		{
			?>
			<h2><?php esc_html_e('Need Help?', 'giga-stock-alerts'); ?></h2>

			<div class="giga-sa-support-cards">
				<div class="giga-sa-support-card">
					<span class="dashicons dashicons-sos" style="color: #d63638;"></span>
					<h3><?php esc_html_e('Community Support', 'giga-stock-alerts'); ?></h3>
					<p><?php esc_html_e('Get help from the community and our team on the WordPress.org support forum.', 'giga-stock-alerts'); ?>
					</p>
					<a href="https://wordpress.org/support/plugin/giga-stock-alerts/" target="_blank" rel="noopener noreferrer"
						class="button">
						<?php esc_html_e('Open Support Forum', 'giga-stock-alerts'); ?>
					</a>
				</div>

				<div class="giga-sa-support-card">
					<span class="dashicons dashicons-book" style="color: #2271b1;"></span>
					<h3><?php esc_html_e('Documentation', 'giga-stock-alerts'); ?></h3>
					<p><?php esc_html_e('Read the full plugin documentation, setup guides, and FAQs.', 'giga-stock-alerts'); ?>
					</p>
					<a href="https://gigabit.agency/docs/giga-stock-alerts/" target="_blank" rel="noopener noreferrer"
						class="button">
						<?php esc_html_e('View Documentation', 'giga-stock-alerts'); ?>
					</a>
				</div>
			</div>
			<?php
		}

		// -----------------------------------------------------------------------
		// Subscribers List Table Page
		// -----------------------------------------------------------------------

		/**
		 * Render the Dashboard page (KPI Cards).
		 */
		public function render_dashboard_page(): void
		{
			if (!current_user_can('manage_woocommerce')) {
				return;
			}

			$stats = $this->get_subscriber_stats();
			?>
			<div class="wrap giga-sa-admin-wrap">
				<div class="giga-sa-page-header">
					<div class="giga-sa-page-header-left">
						<div class="giga-sa-page-icon">📊</div>
						<div>
							<h1><?php esc_html_e('Stock Alerts Dashboard', 'giga-stock-alerts'); ?></h1>
							<p><?php esc_html_e('Real-time overview of restock requests', 'giga-stock-alerts'); ?></p>
						</div>
					</div>
				</div>

				<div class="giga-sa-stats-row">
					<div class="giga-sa-stat-card">
						<div class="giga-sa-stat-icon">👥</div>
						<div class="giga-sa-stat-value"><?php echo esc_html($stats['total']); ?></div>
						<div class="giga-sa-stat-label">Total Subscribers</div>
					</div>
					<div class="giga-sa-stat-card">
						<div class="giga-sa-stat-icon">✅</div>
						<div class="giga-sa-stat-value"><?php echo esc_html($stats['confirmed']); ?></div>
						<div class="giga-sa-stat-label">Confirmed</div>
					</div>
					<div class="giga-sa-stat-card">
						<div class="giga-sa-stat-icon">📧</div>
						<div class="giga-sa-stat-value"><?php echo esc_html($stats['notified']); ?></div>
						<div class="giga-sa-stat-label">Notified</div>
					</div>
					<div class="giga-sa-stat-card">
						<div class="giga-sa-stat-icon">🛒</div>
						<div class="giga-sa-stat-value"><?php echo esc_html($stats['purchased']); ?></div>
						<div class="giga-sa-stat-label">Purchased</div>
					</div>
				</div>

				<div class="giga-sa-dashboard-grid">
					<!-- Top Wanted Products -->
					<div class="giga-sa-settings-card">
						<h2><span class="dashicons dashicons-chart-line"></span>
							<?php esc_html_e('Top Wanted Products', 'giga-stock-alerts'); ?></h2>
						<p class="section-desc">
							<?php esc_html_e('Products your customers are waiting for most.', 'giga-stock-alerts'); ?>
						</p>

						<div class="giga-sa-placeholder-table">
							<div class="placeholder-row header">
								<span>Product</span>
								<span>Requests</span>
							</div>
							<div class="placeholder-row">
								<span>Example Product #1</span>
								<span class="count">--</span>
							</div>
							<div class="placeholder-row">
								<span>Example Product #2</span>
								<span class="count">--</span>
							</div>
							<div class="giga-sa-pro-overlay">
								<span class="dashicons dashicons-lock"></span>
								<p><?php esc_html_e('Analyze demand trends in Giga Stock Alerts Pro', 'giga-stock-alerts'); ?></p>
							</div>
						</div>
					</div>

					<!-- Intelligence Preview -->
					<div class="giga-sa-settings-card">
						<h2><span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e('Demand Intelligence', 'giga-stock-alerts'); ?></h2>
						<p class="section-desc"><?php esc_html_e('Revenue and conversion insights.', 'giga-stock-alerts'); ?></p>

						<div class="giga-sa-insight-cards">
							<div class="insight-pill">
								<span class="label"><?php esc_html_e('Conversion Rate', 'giga-stock-alerts'); ?></span>
								<span class="value">--%</span>
							</div>
							<div class="insight-pill">
								<span class="label"><?php esc_html_e('Revenue Recovered', 'giga-stock-alerts'); ?></span>
								<span class="value">$0.00</span>
							</div>
							<div class="giga-sa-pro-overlay">
								<span class="dashicons dashicons-lock"></span>
								<p><?php esc_html_e('Unlock revenue tracking in Pro version', 'giga-stock-alerts'); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Render the Subscribers page (Table Only).
		 */
		public function render_subscribers_page(): void
		{
			if (!current_user_can('manage_woocommerce')) {
				return;
			}

			$table = new Giga_SA_List_Table();
			$table->process_bulk_action();
			$table->prepare_items();
			?>
			<div class="wrap giga-sa-admin-wrap">
				<div class="giga-sa-page-header">
					<div class="giga-sa-page-header-left">
						<div class="giga-sa-page-icon">🔔</div>
						<div>
							<h1><?php esc_html_e('Stock Alert Subscribers', 'giga-stock-alerts'); ?></h1>
							<p><?php esc_html_e('Manage customers waiting for restocked products', 'giga-stock-alerts'); ?></p>
						</div>
					</div>
					<div class="giga-sa-page-header-right">
						<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=giga_sa_export_csv'), 'giga_sa_export')); ?>"
							class="page-title-action">
							<?php esc_html_e('Export CSV', 'giga-stock-alerts'); ?>
						</a>
					</div>
				</div>

				<form id="subs-filter" method="get">
					<input type="hidden" name="page"
						value="<?php echo esc_attr(isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : 'giga-stock-alerts-subscribers'); ?>" />
					<?php wp_nonce_field('giga_sa_subscribers_filter', 'giga_sa_filter_nonce'); ?>
					<?php
					$table->views();
					$table->search_box(__('Search Emails', 'giga-stock-alerts'), 'search_id');
					$table->display();
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Render the About page with plugin details and a Quick Start guide.
		 */
		public function render_about_page(): void
		{
			if (!current_user_can('manage_woocommerce')) {
				return;
			}
			?>
			<div class="wrap giga-sa-admin-wrap">
				<div class="giga-sa-page-header">
					<div class="giga-sa-page-header-left">
						<div class="giga-sa-page-icon">🚀</div>
						<div>
							<h1><?php esc_html_e('How to use Giga Stock Alerts', 'giga-stock-alerts'); ?></h1>
							<p><?php esc_html_e('Master your back-in-stock strategy in 5 minutes', 'giga-stock-alerts'); ?></p>
							<small style="opacity: 0.6;">Developed with ❤️ by <a href="https://gigabit.agency/" target="_blank"
									style="color: inherit; text-decoration: underline;">Gigabit Agency</a></small>
						</div>
					</div>
				</div>

				<div class="giga-sa-about-grid">
					<!-- Quick Start Guide -->
					<div class="giga-sa-settings-card span-2-cols">
						<h2><span class="dashicons dashicons-welcome-learn-more"></span>
							<?php esc_html_e('Quick Start Guide', 'giga-stock-alerts'); ?></h2>
						<div class="giga-sa-steps-row">
							<div class="step-card">
								<div class="step-number">01</div>
								<h3><?php esc_html_e('Auto-Detection', 'giga-stock-alerts'); ?></h3>
								<p><?php esc_html_e('No configuration needed! The "Notify Me" form automatically appears on any product that goes "Out of Stock".', 'giga-stock-alerts'); ?>
								</p>
							</div>
							<div class="step-card">
								<div class="step-number">02</div>
								<h3><?php esc_html_e('Monitor Requests', 'giga-stock-alerts'); ?></h3>
								<p><?php esc_html_e('Head over to the Subscribers tab to see real-time restock requests from your customers.', 'giga-stock-alerts'); ?>
								</p>
							</div>
							<div class="step-card">
								<div class="step-number">03</div>
								<h3><?php esc_html_e('Restock Product', 'giga-stock-alerts'); ?></h3>
								<p><?php esc_html_e('Simply update your WooCommerce inventory. The plugin detects the change and adds customers to the notify queue.', 'giga-stock-alerts'); ?>
								</p>
							</div>
							<div class="step-card">
								<div class="step-number">04</div>
								<h3><?php esc_html_e('Auto-Notify', 'giga-stock-alerts'); ?></h3>
								<p><?php esc_html_e('Emails are sent automatically ', 'giga-stock-alerts'); ?><strong><?php esc_html_e('based on your SMTP/Mail settings', 'giga-stock-alerts'); ?></strong>.
									<?php esc_html_e('No manual intervention required!', 'giga-stock-alerts'); ?>
								</p>
							</div>
						</div>
					</div>

					<!-- Key Features List -->
					<div class="giga-sa-settings-card">
						<h2><span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e('Key Capabilities', 'giga-stock-alerts'); ?></h2>
						<ul class="giga-sa-feature-list">
							<li>
								<span class="dashicons dashicons-email-alt"></span>
								<strong><?php esc_html_e('Automated Restock Notifications', 'giga-stock-alerts'); ?></strong>
							</li>
							<li>
								<span class="dashicons dashicons-admin-settings"></span>
								<strong><?php esc_html_e('Variable Product Support', 'giga-stock-alerts'); ?></strong>
							</li>
							<li>
								<span class="dashicons dashicons-welcome-view-site"></span>
								<strong><?php esc_html_e('My Account Dashboard', 'giga-stock-alerts'); ?></strong>
							</li>
							<li>
								<span class="dashicons dashicons-chart-bar"></span>
								<strong><?php esc_html_e('Advanced Conversion Analytics', 'giga-stock-alerts'); ?></strong>
							</li>
							<li>
								<span class="dashicons dashicons-download"></span>
								<strong><?php esc_html_e('Export Power Engine', 'giga-stock-alerts'); ?></strong>
							</li>
						</ul>
					</div>

					<!-- Coming Soon Roadmap -->
					<div class="giga-sa-settings-card">
						<h2><span class="dashicons dashicons-calendar-alt"></span>
							<?php esc_html_e('Coming Soon - Roadmap', 'giga-stock-alerts'); ?></h2>
						<div class="giga-sa-roadmap-list">
							<div class="roadmap-item">
								<span class="roadmap-icon">📱</span>
								<div>
									<strong><?php esc_html_e('WhatsApp Alerts', 'giga-stock-alerts'); ?></strong>
									<span class="giga-sa-badge mini"><?php esc_html_e('PRO', 'giga-stock-alerts'); ?></span>
									<p><?php esc_html_e('Notify customers via 98% open-rate WhatsApp messages.', 'giga-stock-alerts'); ?>
									</p>
								</div>
							</div>
							<div class="roadmap-item">
								<span class="roadmap-icon">💬</span>
								<div>
									<strong><?php esc_html_e('Twilio SMS notifications', 'giga-stock-alerts'); ?></strong>
									<span class="giga-sa-badge mini"><?php esc_html_e('PRO', 'giga-stock-alerts'); ?></span>
									<p><?php esc_html_e('Reach customers instantly on their phones.', 'giga-stock-alerts'); ?></p>
								</div>
							</div>
							<div class="roadmap-item">
								<span class="roadmap-icon">📉</span>
								<div>
									<strong><?php esc_html_e('Price Drop Alerts', 'giga-stock-alerts'); ?></strong>
									<span class="giga-sa-badge mini"><?php esc_html_e('PRO', 'giga-stock-alerts'); ?></span>
									<p><?php esc_html_e('Alert watchers when a price-sensitive drop occurs.', 'giga-stock-alerts'); ?>
									</p>
								</div>
							</div>
							<div class="roadmap-item">
								<span class="roadmap-icon">🔌</span>
								<div>
									<strong><?php esc_html_e('Klaviyo/Mailchimp Sync', 'giga-stock-alerts'); ?></strong>
									<span class="giga-sa-badge mini"><?php esc_html_e('PRO', 'giga-stock-alerts'); ?></span>
									<p><?php esc_html_e('Connect your demand data to your ESP.', 'giga-stock-alerts'); ?></p>
								</div>
							</div>
						</div>
					</div>

					<!-- Plugin Info -->
					<div class="giga-sa-settings-card">
						<h2><span class="dashicons dashicons-id-alt"></span>
							<?php esc_html_e('Plugin Information', 'giga-stock-alerts'); ?></h2>
						<div class="giga-sa-info-pills">
							<div class="info-pill"><?php esc_html_e('Version', 'giga-stock-alerts'); ?>: <span>1.0.0</span></div>
							<div class="info-pill"><?php esc_html_e('Developer', 'giga-stock-alerts'); ?>: <span>Gigabit
									Agency</span></div>
							<div class="info-pill"><?php esc_html_e('Framework', 'giga-stock-alerts'); ?>: <span>WooCommerce
									Ready</span></div>
						</div>
						<div class="giga-sa-about-footer">
							<p><?php esc_html_e('Recover lost revenue and keep your customers engaged with the ultimate inventory notification solution for WordPress.', 'giga-stock-alerts'); ?>
							</p>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Get subscriber statistics for the KPI cards.
		 *
		 * @return array
		 */
		private function get_subscriber_stats(): array
		{
			global $wpdb;
			$table_name = esc_sql($wpdb->prefix . 'giga_stock_alerts');

			$cache_key = 'giga_sa_subscriber_stats';
			$stats     = wp_cache_get($cache_key, 'giga_stock_alerts');

			if (false === $stats) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$counts = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) as count FROM `{$table_name}` GROUP BY status"), OBJECT_K);

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_name}`"));

				$stats = [
					'total'     => (int) $total,
					'confirmed' => isset($counts['confirmed']) ? (int) $counts['confirmed']->count : 0,
					'notified'  => isset($counts['notified']) ? (int) $counts['notified']->count : 0,
					'purchased' => isset($counts['purchased']) ? (int) $counts['purchased']->count : 0,
				];

				wp_cache_set($cache_key, $stats, 'giga_stock_alerts', 300);
			}

			return $stats;
		}

		// -----------------------------------------------------------------------
		// Export CSV
		// -----------------------------------------------------------------------

		public function export_csv(): void
		{
			if (!current_user_can('manage_woocommerce')) {
				wp_die(esc_html__('Unauthorized', 'giga-stock-alerts'));
			}

			check_admin_referer('giga_sa_export');

			global $wpdb;
			$table_name = esc_sql($wpdb->prefix . 'giga_stock_alerts');

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$subscribers = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table_name}` ORDER BY subscribed_at DESC"), ARRAY_A);

			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename=giga-stock-alerts-' . gmdate('Y-m-d') . '.csv');

			$output = fopen('php://output', 'w');
			if (!$output) {
				return;
			}

			fputcsv($output, [
				__('ID', 'giga-stock-alerts'),
				__('Product ID', 'giga-stock-alerts'),
				__('Variation ID', 'giga-stock-alerts'),
				__('Email', 'giga-stock-alerts'),
				__('Name', 'giga-stock-alerts'),
				__('Status', 'giga-stock-alerts'),
				__('IP Address', 'giga-stock-alerts'),
				__('Subscribed At', 'giga-stock-alerts'),
				__('Notified At', 'giga-stock-alerts')
			]);

			if (!empty($subscribers)) {
				foreach ($subscribers as $row) {
					fputcsv($output, [
						$row['id'],
						$row['product_id'],
						$row['variation_id'],
						$row['email'],
						$row['customer_name'],
						$row['status'],
						$row['ip_address'],
						$row['subscribed_at'],
						$row['notified_at'],
					]);
				}
			}

			global $wp_filesystem;
			if (is_resource($output)) {
				$wp_filesystem->fclose($output);
			}
			exit;
		}

		// -----------------------------------------------------------------------
		// AJAX: Inline Edit Subscription
		// -----------------------------------------------------------------------

		/**
		 * Handle AJAX request to update a subscription inline.
		 */
		public function ajax_update_subscription(): void
		{
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'giga_sa_update_subscription')) {
				wp_send_json_error(['message' => __('Security check failed.', 'giga-stock-alerts')]);
			}

			if (!current_user_can('manage_woocommerce')) {
				wp_send_json_error(['message' => __('Unauthorized.', 'giga-stock-alerts')]);
			}

			$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
			$customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
			$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
			$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

			if (!$id) {
				wp_send_json_error(['message' => __('Invalid subscription ID.', 'giga-stock-alerts')]);
			}

			if (!is_email($email)) {
				wp_send_json_error(['message' => __('Invalid email address.', 'giga-stock-alerts')]);
			}

			$allowed_statuses = ['pending', 'confirmed', 'notified', 'purchased', 'unsubscribed'];
			if (!in_array($status, $allowed_statuses, true)) {
				wp_send_json_error(['message' => __('Invalid status value.', 'giga-stock-alerts')]);
			}

			$updated = Giga_SA_DB::update_subscription($id, [
				'customer_name' => $customer_name,
				'email' => $email,
				'status' => $status,
			]);

			if (!$updated) {
				wp_send_json_error(['message' => __('Failed to update subscription.', 'giga-stock-alerts')]);
			}

			$status_labels = [
				'pending' => __('Pending', 'giga-stock-alerts'),
				'confirmed' => __('Confirmed', 'giga-stock-alerts'),
				'notified' => __('Notified', 'giga-stock-alerts'),
				'purchased' => __('Purchased', 'giga-stock-alerts'),
				'unsubscribed' => __('Unsubscribed', 'giga-stock-alerts'),
			];

			$status_colors = [
				'pending' => 'background: #e2e8f0; color: #475569;',
				'confirmed' => 'background: #e0f2fe; color: #0284c7;',
				'notified' => 'background: #ffedd5; color: #c2410c;',
				'purchased' => 'background: #dcfce3; color: #166534;',
				'unsubscribed' => 'background: #fee2e2; color: #b91c1c;',
			];

			wp_send_json_success([
				'message' => __('Subscriber updated.', 'giga-stock-alerts'),
				'customer_name' => $customer_name,
				'email' => $email,
				'status' => $status,
				'status_label' => $status_labels[$status] ?? ucfirst($status),
				'status_style' => $status_colors[$status] ?? 'background: #eee; color: #333;',
			]);
		}

		// -----------------------------------------------------------------------
		// Feature 2: Product List Columns
		// -----------------------------------------------------------------------

		/**
		 * Add "Waiting" column to WooCommerce product list.
		 */
		public function add_product_columns(array $columns): array
		{
			$new_columns = [];
			foreach ($columns as $key => $label) {
				$new_columns[$key] = $label;
				if ('name' === $key) {
					$new_columns['giga_sa_waiting'] = __('Waiting', 'giga-stock-alerts');
				}
			}
			return $new_columns;
		}

		/**
		 * Render the "Waiting" column content.
		 */
		public function render_product_column(string $column, int $post_id): void
		{
			if ('giga_sa_waiting' !== $column) {
				return;
			}

			$count = Giga_SA_DB::count_subscriptions_by_product($post_id);

			if ($count > 0) {
				printf(
					'<span class="giga-sa-badge">%d %s</span>',
					(int) $count,
					esc_html__('waiting', 'giga-stock-alerts')
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
if (!class_exists('Giga_SA_List_Table')) {
	class Giga_SA_List_Table extends WP_List_Table
	{

		public function __construct()
		{
			parent::__construct([
				'singular' => __('Subscriber', 'giga-stock-alerts'),
				'plural' => __('Subscribers', 'giga-stock-alerts'),
				'ajax' => false,
			]);
		}

		protected function get_views(): array
		{
			global $wpdb;
			$table = esc_sql($wpdb->prefix . 'giga_stock_alerts');

			$base_url = admin_url('admin.php?page=giga-stock-alerts-subscribers');
			
			// Verify nonce for filter processing
			if (!empty($_REQUEST['status_filter'])) {
				if (!isset($_REQUEST['giga_sa_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['giga_sa_filter_nonce'])), 'giga_sa_subscribers_filter')) {
					wp_die('Security check failed');
				}
			}
			
			$current = isset($_REQUEST['status_filter']) ? sanitize_text_field(wp_unslash($_REQUEST['status_filter'])) : 'all';

			$views = [];
			$statuses = [
				'all' => __('All', 'giga-stock-alerts'),
				'pending' => __('Pending', 'giga-stock-alerts'),
				'confirmed' => __('Confirmed', 'giga-stock-alerts'),
				'notified' => __('Notified', 'giga-stock-alerts'),
				'purchased' => __('Purchased', 'giga-stock-alerts'),
				'unsubscribed' => __('Unsubscribed', 'giga-stock-alerts'),
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) as count FROM `{$table}` GROUP BY status"), OBJECT_K);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}`"));

			foreach ($statuses as $key => $label) {
				$count = 'all' === $key ? $total : (isset($counts[$key]) ? $counts[$key]->count : 0);

				$class = ($current === $key) ? 'current' : '';
				$url = 'all' === $key ? $base_url : add_query_arg([
					'status_filter' => $key,
					'giga_sa_filter_nonce' => wp_create_nonce('giga_sa_subscribers_filter')
				], $base_url);

				$views[$key] = sprintf(
					'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
					esc_url($url),
					$class,
					esc_html($label),
					(int) $count
				);
			}

			return $views;
		}

		public function get_columns(): array
		{
			return [
				'cb' => '<input type="checkbox" />',
				'email' => __('Email', 'giga-stock-alerts'),
				'product' => __('Product', 'giga-stock-alerts'),
				'status' => __('Status', 'giga-stock-alerts'),
				'subscribed_at' => __('Subscribed Date', 'giga-stock-alerts'),
				'actions' => __('Actions', 'giga-stock-alerts'),
			];
		}

		protected function get_sortable_columns(): array
		{
			return [
				'email' => ['email', false],
				'subscribed_at' => ['subscribed_at', true], // true = default desc
			];
		}

		protected function column_default($item, $column_name)
		{
			return esc_html($item[$column_name]);
		}

		protected function column_cb($item): string
		{
			return sprintf(
				'<input type="checkbox" name="subscription_ids[]" value="%s" />',
				$item['id']
			);
		}

		protected function column_email($item): string
		{
			$name = $item['customer_name'] ? '<br><small>' . esc_html($item['customer_name']) . '</small>' : '';

			return sprintf(
				'<strong>%s</strong>%s',
				esc_html($item['email']),
				$name
			);
		}

		protected function column_actions($item): string
		{
			$delete_nonce = wp_create_nonce('bulk-' . $this->_args['plural']);

			return sprintf(
				'<div class="giga-sa-actions">
				<a href="#" class="giga-sa-action-icon giga-sa-edit-btn" data-id="%d" title="%s">✏️</a>
				<a href="?page=%s&action=%s&subscription_ids[]=%s&_wpnonce=%s" class="giga-sa-action-icon giga-sa-delete-icon" title="%s" onclick="return confirm(\'Are you sure?\');">🗑️</a>
			</div>',
				absint($item['id']),
				__('Edit Subscriber', 'giga-stock-alerts'),
				esc_attr(isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : 'giga-stock-alerts'),
				'delete',
				absint($item['id']),
				$delete_nonce,
				__('Delete Subscriber', 'giga-stock-alerts')
			);
		}

		/**
		 * Override single_row to add data attributes for inline editing.
		 *
		 * @param object $item The current item.
		 */
		public function single_row($item): void
		{
			$alert_type = isset($item['alert_type']) ? $item['alert_type'] : 'restock';

			printf(
				'<tr data-id="%d" data-name="%s" data-email="%s" data-status="%s" data-alert-type="%s">',
				absint($item['id']),
				esc_attr($item['customer_name'] ?? ''),
				esc_attr($item['email']),
				esc_attr($item['status']),
				esc_attr($alert_type)
			);

			$this->single_row_columns($item);
			echo '</tr>';
		}

		protected function column_product($item): string
		{
			$target_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
			$product = wc_get_product($target_id);

			if (!$product) {
				return sprintf('<del>%s (ID: %d)</del>', __('Deleted Product', 'giga-stock-alerts'), $target_id);
			}

			$edit_url = admin_url('post.php?post=' . ($item['variation_id'] ? $item['product_id'] : $target_id) . '&action=edit');

			return sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url($edit_url),
				esc_html($product->get_name())
			);
		}

		protected function column_status($item): string
		{
			return sprintf(
				'<span class="giga-sa-badge giga-sa-badge-%s">%s</span>',
				esc_attr($item['status']),
				esc_html(ucfirst($item['status']))
			);
		}

		protected function column_subscribed_at($item): string
		{
			return esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item['subscribed_at'])));
		}

		protected function get_bulk_actions(): array
		{
			return [
				'delete' => __('Delete', 'giga-stock-alerts'),
			];
		}

		public function process_bulk_action(): void
		{
			if ('delete' === $this->current_action()) {
				check_admin_referer('bulk-' . $this->_args['plural']);

				$ids = isset($_REQUEST['subscription_ids']) ? array_map('absint', (array) $_REQUEST['subscription_ids']) : [];
				if (!empty($ids)) {
					global $wpdb;
					$table = esc_sql($wpdb->prefix . 'giga_stock_alerts');

					$placeholders = implode(',', array_fill(0, count($ids), '%d'));
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE id IN ($placeholders)", $ids));

					wp_cache_delete('giga_sa_subscriber_stats', 'giga_stock_alerts');

					/* translators: %d: number of subscriptions */
					add_settings_error('giga_sa_messages', 'giga_sa_deleted', sprintf(_n('%d subscription deleted.', '%d subscriptions deleted.', count($ids), 'giga-stock-alerts'), count($ids)), 'success');
				}
			}
		}

		public function prepare_items(): void
		{
			global $wpdb;
			$table = esc_sql($wpdb->prefix . 'giga_stock_alerts');

			// Verify nonce for filter/search actions
			if (!empty($_REQUEST['s']) || !empty($_REQUEST['status_filter'])) {
				if (!isset($_REQUEST['giga_sa_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['giga_sa_filter_nonce'])), 'giga_sa_subscribers_filter')) {
					wp_die('Security check failed');
				}
			}

			$per_page     = 20;
			$current_page = $this->get_pagenum();

			// Columns
			$columns               = $this->get_columns();
			$hidden                = [];
			$sortable              = $this->get_sortable_columns();
			$this->_column_headers = [$columns, $hidden, $sortable];

			$where  = "1=1";
			$params = [];

			// Search
			$search_where = '';
			if (!empty($_REQUEST['s'])) {
				$search_where = " AND email LIKE %s";
				$params[] = '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($_REQUEST['s']))) . '%';
			}

			// Filter
			$filter_where = '';
			if (!empty($_REQUEST['status_filter']) && 'all' !== $_REQUEST['status_filter']) {
				$filter_where = " AND status = %s";
				$params[] = sanitize_text_field(wp_unslash($_REQUEST['status_filter']));
			}

			// Build complete WHERE clause
			$complete_where = "1=1" . $search_where . $filter_where;

			// Order
			$orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'subscribed_at';
			$order   = !empty($_REQUEST['order']) && 'asc' === strtolower(sanitize_text_field(wp_unslash($_REQUEST['order']))) ? 'ASC' : 'DESC';

			$valid_columns = ['email', 'subscribed_at', 'id'];
			if (!in_array($orderby, $valid_columns, true)) {
				$orderby = 'subscribed_at';
			}

			// Meta counts
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$total_items = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE {$complete_where}", $params));

			// Build Final Query with proper parameterization
			$final_sql = "SELECT * FROM `{$table}` WHERE {$complete_where} ORDER BY " . esc_sql($orderby) . " " . esc_sql($order) . " LIMIT %d OFFSET %d";
			$params[]  = $per_page;
			$params[]  = ($current_page - 1) * $per_page;

			// Execute with Caching
			$cache_key = 'giga_sa_list_' . md5($final_sql . serialize($params));
			$this->items = wp_cache_get($cache_key, 'giga_stock_alerts');

			if (false === $this->items) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->items = $wpdb->get_results($wpdb->prepare($final_sql, ...$params), ARRAY_A);
				wp_cache_set($cache_key, $this->items, 'giga_stock_alerts', 300);
			}

			$this->set_pagination_args([
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil($total_items / $per_page),
			]);
		}
	}
}
