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
 * Version:           2.1.4
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
//check a plugin (WooCommerce) is active?
$all_plugins = (!is_multisite()) ? (array) get_option('active_plugins', array()) : (array) get_site_option('active_sitewide_plugins', array());

$result = implode($all_plugins) . implode(',',array_keys($all_plugins));

if (!stripos($result, 'woocommerce.php')) {
	add_action( 'admin_notices', 'relevatracking_render_wc_inactive_notice' );
	return;
}

/**
 * Renders a notice when WooCommerce version is outdated
 *
 * @since 2.3.1
 */
function relevatracking_render_wc_inactive_notice() {

	$message = sprintf(
		/* translators: %1$s and %2$s are <strong> tags. %3$s and %4$s are <a> tags */
		__( '%1$sreleva.nz is inactive%2$s as it requires WooCommerce. Please %3$sactivate WooCommerce version 2.4.13 or newer%4$s', 'relevatracking' ),
		'<strong>',
		'</strong>',
		'<a href="' . admin_url( 'plugins.php' ) . '">',
		'&nbsp;&raquo;</a>'
	);

	printf( '<div class="error"><p>%s</p></div>', $message );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-relevatracking-activator.php
 */
function activate_relevatracking($networkwide) {
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
