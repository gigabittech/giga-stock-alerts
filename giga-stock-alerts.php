<?php
/**
 * Plugin Name:       Giga Stock Alerts — Back in Stock Notifier for WooCommerce
 * Plugin URI:        https://github.com/gigabittech/giga-stock-alerts
 * Description:       Capture customer demand on out-of-stock products and notify them via email when restocked.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gigabit
 * Author URI:        https://gigabit.agency/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       giga-stock-alerts
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.0
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIGA_SA_VERSION',    '1.0.0' );
define( 'GIGA_SA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIGA_SA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GIGA_SA_PLUGIN_FILE', __FILE__ );

require_once GIGA_SA_PLUGIN_DIR . 'includes/class-giga-sa-core.php';

register_activation_hook( __FILE__, [ 'Giga_SA_Core', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Giga_SA_Core', 'deactivate' ] );

// Hooking at plugins_loaded 10 allows all WC subsystems to exist.
add_action( 'plugins_loaded', [ 'Giga_SA_Core', 'instance' ], 10 );
