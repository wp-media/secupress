<?php
defined( 'ABSPATH' ) or die( 'Cheatin\' uh?' );

/**
 * Get the plugins removed from repo from our local file.
 *
 * @since 1.0.3 Use the whitelist
 * @since 1.0
 *
 * @return (array|bool) The plugins removed from the repository: dirname as array keys and plugin path as values. Return false if the file is not readable.
 */
function secupress_get_removed_plugins() {
	static $removed_plugins;

	if ( isset( $removed_plugins ) ) {
		return $removed_plugins;
	}

	if ( false !== ( $from_transient = get_site_transient( 'secupress_removed_plugins' ) ) ) {
		return $from_transient;
	}

	$plugins_list_file = SECUPRESS_INC_PATH . 'data/no-longer-in-directory-plugin-list.data';

	if ( ! is_readable( $plugins_list_file ) ) {
		return false;
	}

	$removed_plugins = array_flip( array_map( 'trim', file( $plugins_list_file ) ) );
	$whitelist       = secupress_get_plugins_whitelist();

	if ( $whitelist ) {
		$removed_plugins = array_diff_key( $removed_plugins, $whitelist );
	}

	$all_plugins     = array_keys( get_plugins() );
	$all_plugins     = array_combine( array_map( 'dirname', $all_plugins ), $all_plugins );
	$removed_plugins = array_intersect_key( $all_plugins, $removed_plugins );

	set_site_transient( 'secupress_removed_plugins', $removed_plugins, 6 * HOUR_IN_SECONDS );

	return $removed_plugins;
}


/**
 * Get the plugins not update since 2 years from repo from our local file.
 *
 * @since 1.0
 *
 * @return (array|bool) The plugins from the repository not updated for 2 years: dirname as array keys and plugin path as values. Return false if the file is not readable.
 */
function secupress_get_notupdated_plugins() {
	static $notupdated_plugins;

	if ( isset( $notupdated_plugins ) ) {
		return $notupdated_plugins;
	}

	if ( false !== ( $from_transient = get_site_transient( 'secupress_notupdated_plugins' ) ) ) {
		return $from_transient;
	}

	$plugins_list_file = SECUPRESS_INC_PATH . 'data/not-updated-in-over-two-years-plugin-list.data';

	if ( ! is_readable( $plugins_list_file ) ) {
		return false;
	}

	$notupdated_plugins = array_flip( array_map( 'trim', file( $plugins_list_file ) ) );

	$all_plugins = array_keys( get_plugins() );
	$all_plugins = array_combine( array_map( 'dirname', $all_plugins ), $all_plugins );
	$all_plugins = array_intersect_key( $all_plugins, $notupdated_plugins );

	$notupdated_plugins = $all_plugins;
	set_site_transient( 'secupress_notupdated_plugins', $notupdated_plugins, 6 * HOUR_IN_SECONDS );

	return $notupdated_plugins;
}


/**
 * Get the plugins vulnerable from an option, from our option, set by `secupress_refresh_vulnerable_plugins()`.
 *
 * @since 1.0
 *
 * @return (array) The vulnerables plugins.
 */
function secupress_get_vulnerable_plugins() {
	static $vulnerable_plugins;

	if ( isset( $vulnerable_plugins ) ) {
		return $vulnerable_plugins;
	}

	if ( false !== ( $from_transient = get_site_transient( 'secupress_vulnerable_plugins' ) ) ) {
		return $from_transient;
	}

	$temp = get_site_option( 'secupress_bad_plugins' );
	$temp = $temp ? (array) json_decode( $temp ) : array();

	if ( $temp ) {
		$vulnerable_plugins = $temp;
		set_site_transient( 'secupress_vulnerable_plugins', $vulnerable_plugins, 6 * HOUR_IN_SECONDS );
		return $vulnerable_plugins;
	}

	return array();
}

/**
 * Get the plugins whitelist from our local file.
 *
 * @since 1.0
 *
 * @return (array|bool) The plugins whitelist, with dirname as keys. Return false if the file is not readable.
 */
function secupress_get_plugins_whitelist() {
	static $whitelist;

	if ( isset( $whitelist ) ) {
		return $whitelist;
	}

	$whitelist_file = SECUPRESS_INC_PATH . 'data/whitelist-plugin-list.data';

	if ( ! is_readable( $whitelist_file ) ) {
		return false;
	}

	$whitelist = file( $whitelist_file );
	$whitelist = array_map( 'trim', $whitelist );
	$whitelist = array_flip( $whitelist );

	return $whitelist;
}


/* THEMES */

/**
 * Get the vulnerable themes from an option, from our option, set by `secupress_refresh_vulnerable_themes()`.
 *
 * @since 1.0
 *
 * @return (array) The vulnerables themes.
 */
function secupress_get_vulnerable_themes() {
	static $vulnerable_themes;

	if ( isset( $vulnerable_themes ) ) {
		return $vulnerable_themes;
	}

	if ( false !== ( $from_transient = get_site_transient( 'secupress_vulnerable_themes' ) ) ) {
		return $from_transient;
	}

	$temp = get_site_option( 'secupress_bad_themes' );
	$temp = $temp ? (array) json_decode( $temp ) : array();

	if ( $temp ) {
		$vulnerable_themes = $temp;
		set_site_transient( 'secupress_vulnerable_themes', $vulnerable_themes, 6 * HOUR_IN_SECONDS );
		return $vulnerable_themes;
	}

	return array();
}
