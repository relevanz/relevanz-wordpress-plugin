<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://releva.nz
 * @since      1.0.0
 *
 * @package    Relevatracking
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Dev guide §3.4: remove the configuration on uninstall (no call to releva.nz).
// Options are stored per site, so on multisite every site is cleaned up.
function relevatracking_delete_options() {
	foreach ( array( 'api_key', 'client_id', 'additional_html', 'active', 'last_callback' ) as $name ) {
		delete_option( 'relevatracking_' . $name );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		relevatracking_delete_options();
		restore_current_blog();
	}
} else {
	relevatracking_delete_options();
}
