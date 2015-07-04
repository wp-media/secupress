<?php
/*
Module Name: No Plugin Installation
Description: Disabled the plugin installation from repository
Main Module: plugins_themes
Author: SecuPress
Version: 1.0
*/
defined( 'SECUPRESS_VERSION' ) or die( 'Cheatin&#8217; uh?' );

if ( is_admin() ) {

	add_action( 'admin_print_styles-plugins.php', 'secupress_no_plugin_install_tab_css' );
	function secupress_no_plugin_install_tab_css() {
		?><style>h2 a.add-new-h2{display:none}</style><?php
	}

	add_action( 'admin_print_styles-plugin-install.php', 'secupress_no_plugin_upload_tab_css' );
	function secupress_no_plugin_upload_tab_css() {
		?><style>h2 a.upload{display:none}</style><?php
	}

	add_action( 'load-plugin-install.php', 'secupress_no_plugin_install_page_redirect' );
	function secupress_no_plugin_install_page_redirect() {
		if ( ! isset( $_GET['tab'] ) || 'plugin-information' != $_GET['tab'] ) {
			secupress_die( __( 'You do not have sufficient permissions to install plugins on this site.' ) );
		}
	}

	add_action( 'check_admin_referer', 'secupress_avoid_install_plugin' );
	function secupress_avoid_install_plugin( $action ) {
		if ( 'plugin-upload' === $action || strpos( $action, 'install-plugin_' ) === 0 ) {
			secupress_die( __( 'You do not have sufficient permissions to install plugins on this site.' ) );
		}
	}

	add_action( 'admin_menu', 'secupress_remove_new_plugins_link', 100 );
	function secupress_remove_new_plugins_link() {
		global $submenu;
		if ( isset( $submenu['plugins.php'][10] ) ) {
			unset( $submenu['plugins.php'][10] );
		}
	}

}

if ( isset( $_FILES['pluginzip'] ) ) {
	secupress_die( __( 'You do not have sufficient permissions to install plugins on this site.' ) );
}