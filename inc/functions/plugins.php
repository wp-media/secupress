<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/**
 * Return true is secupress pro is installed
 *
 * @since 1.0
 * @source wp-admin/includes/plugin.php
 * @return bool
 */
function secupress_is_pro() {
	return defined( 'SECUPRESS_PRO_VERSION' );
}


/**
 * Check whether the plugin is active by checking the active_plugins list.
 *
 * @since 1.0
 *
 * @source wp-admin/includes/plugin.php
 * @return bool
 */
function secupress_is_plugin_active( $plugin ) {
	$plugins = (array) get_option( 'active_plugins', array() );
	$plugins = array_flip( $plugins );
	return isset( $plugins[ $plugin ] ) || secupress_is_plugin_active_for_network( $plugin );
}


/**
 * Check whether the plugin is active for the entire network.
 *
 * @since 1.0
 *
 * @source wp-admin/includes/plugin.php
 * @return bool
 */
function secupress_is_plugin_active_for_network( $plugin ) {
	if ( ! is_multisite() ) {
		return false;
	}

	$plugins = get_site_option( 'active_sitewide_plugins' );

	return isset( $plugins[ $plugin ] );
}


function secupress_is_submodule_active( $module, $plugin ) {
	$plugin         = sanitize_key( $plugin );
	$active_plugins = get_site_option( SECUPRESS_ACTIVE_SUBMODULES );

	if ( isset( $active_plugins[ $module ] ) ) {
		$active_plugins[ $module ] = array_flip( $active_plugins[ $module ] );
		return isset( $active_plugins[ $module ][ $plugin ] );
	}

	return false;
}


/**
 * Tell if a user is affected by its role for the asked module
 *
 * @return (-1)/(bool) -1 = every role is affected, true = the user's role is affected, false = the user's role isn't affected.
 */
function secupress_is_affected_role( $module, $submodule, $user ) {
	$roles = secupress_get_module_option( $submodule . '_affected_role', array(), $module );

	if ( ! $roles ) {
		return -1;
	}

	return is_a( $user, 'WP_User' ) && user_can( $user, 'exist' ) && ! count( (array) array_intersect( $roles, $user->roles ) );
}


/**
 * Validate a range
 *
 * @since 1.0
 * @return false/integer
 **/
function secupress_validate_range( $value, $min, $max, $default = false ) {
	$test = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => $min, 'max_range' => $max ) ) );
	if ( false === $test ) {
		return $default;
	}
	return $value;
}


/**
 * Register the correct setting with the correct callback for the module
 *
 * @since 1.0
 * @return void
 **/
function secupress_register_setting( $module, $option_name = false ) {
	$option_group      = "secupress_{$module}_settings";
	$option_name       = $option_name ? $option_name : "secupress_{$module}_settings";
	$sanitize_callback = str_replace( '-', '_', $module );
	$sanitize_callback = "__secupress_{$sanitize_callback}_settings_callback";

	if ( ! is_multisite() ) {
		register_setting( $option_group, $option_name, $sanitize_callback );
		return;
	}

	$whitelist = secupress_cache_data( 'new_whitelist_site_options' );
	$whitelist = is_array( $whitelist ) ? $whitelist : array();
	$whitelist[ $option_group ] = isset( $whitelist[ $option_group ] ) ? $whitelist[ $option_group ] : array();
	$whitelist[ $option_group ][] = $option_name;
	secupress_cache_data( 'new_whitelist_site_options', $whitelist );

	add_filter( "sanitize_option_{$option_name}", $sanitize_callback );
}


/**
 * Return the current URL
 *
 * @param $mode (string) base (before '?'), raw (all), uri (after '?')
 * @since 1.0
 * @return string $url
 **/
function secupress_get_current_url( $mode = 'base' ) {
	$mode = (string) $mode;
	$url  = ! empty( $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] ) ? $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] : ( ! empty( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );
	$url  = 'http' . ( is_ssl() ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . $url;

	switch ( $mode ) :
		case 'raw' :
			return $url;
		case 'uri' :
			$home_url = set_url_scheme( home_url() );
			$url      = reset( ( explode( '?', $url ) ) );
			$url      = reset( ( explode( '&', $url ) ) );
			$url      = str_replace( $home_url, '', $url );
			return trim( $url, '/' );
		default :
			$url = reset( ( explode( '?', $url ) ) );
			return reset( ( explode( '&', $url ) ) );
	endswitch;
}
