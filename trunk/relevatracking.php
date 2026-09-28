<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://releva.nz
 * @since             2.0.6
 * @package           releva.nz
 *
 * @wordpress-plugin
 * Plugin Name:       releva.nz
 * Plugin URI:        https://releva.nz
 * Description:       Technology for personalized advertising
 * Version:           2.3.0
 * Requires at least: 4.5
 * Requires PHP:      7.0
 * WC tested up to:   11.1
 * Author:            releva.nz
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       relevatracking
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Current plugin version — the single source of truth in code (dev guide §15.1);
 * must match the `Version:` header above.
 */
define( 'RELEVATRACKING_VERSION', '2.3.0' );

/**
 * Declare compatibility with WooCommerce HPOS (custom order tables) and the
 * cart/checkout blocks. Orders are only read through the WC CRUD API.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-relevatracking-activator.php
 */
function activate_relevatracking() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-relevatracking-activator.php';

	Relevatracking_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-relevatracking-deactivator.php
 */
function deactivate_relevatracking() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-relevatracking-deactivator.php';
	Relevatracking_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_relevatracking' );
register_deactivation_hook( __FILE__, 'deactivate_relevatracking' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-relevatracking.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_relevatracking() {

	$plugin = new Relevatracking();
	$plugin->run();

}
run_relevatracking();
