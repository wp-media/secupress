<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

add_action( 'plugins_loaded', 'secupress_check_ban_ips' );
/**
 * Will remove expired banned IPs, then block the remaining ones. A form will be displayed to allow clumsy Administrators to unlock themselves.
 *
 * @since 1.0
 */
function secupress_check_ban_ips() {
	$ban_ips  = get_site_option( SECUPRESS_BAN_IP );
	$time_ban = (int) secupress_get_module_option( 'login-protection_time_ban', 5, 'users-login' );
	$update   = false;
	$redirect = false;

	// If we got banned ips.
	if ( $ban_ips && is_array( $ban_ips ) ) {
		// The link to be unlocked?
		if ( ! empty( $_GET['action'] ) && 'secupress_self-unban-ip' === $_GET['action'] ) { // WPCS: CSRF ok.
			$ip     = secupress_get_ip();
			$result = ! empty( $_GET['_wpnonce'] ) ? wp_verify_nonce( $_GET['_wpnonce'], 'secupress_self-unban-ip-' . $ip ) : false;

			if ( $result ) {
				// You're good to go.
				unset( $ban_ips[ $ip ] );
				$update   = true;
				$redirect = true;
			} elseif ( isset( $ban_ips[ $ip ] ) ) {
				// Cheating?
				$title   = '403 ' . get_status_header_desc( 403 );
				$content = __( 'Your unlock link expired (or you\'re cheating).', 'secupress' );

				secupress_die( $content, $title, array( 'response' => 403 ) );
			}
		}

		// Purge the expired banned IPs.
		foreach ( $ban_ips as $ip => $time ) {
			if ( ( $time + ( $time_ban * 60 ) ) < time() ) {
				unset( $ban_ips[ $ip ] );
				$update = true;
			}
		}

		// Save the changes.
		if ( $update ) {
			update_site_option( SECUPRESS_BAN_IP, $ban_ips );
		}

		// The user just got unlocked. Redirect to homepage.
		if ( $redirect ) {
			wp_redirect( esc_url_raw( home_url() ) );
			die();
		}

		// Block the user if the IP is still in the array.
		$ip = secupress_get_ip();

		if ( array_key_exists( $ip, $ban_ips ) ) {
			// Display a form in case of accidental ban.
			$unban_atts = secupress_check_ban_ips_maybe_send_unban_email( $ip );

			$title = ! empty( $unban_atts['title'] ) ? $unban_atts['title'] : ( '403 ' . get_status_header_desc( 403 ) );

			if ( $unban_atts['display_form'] ) {
				$in_ten_years = time() + YEAR_IN_SECONDS * 10;
				$time_ban     = $ban_ips[ $ip ] > $in_ten_years ? 0 : $time_ban;
				$error        = $unban_atts['message'];
				$content      = secupress_check_ban_ips_form( compact( 'ip', 'time_ban', 'error' ) );
			} else {
				$content = $unban_atts['message'];
			}

			secupress_die( $content, $title, array( 'response' => 403 ) );
		}
	} elseif ( false !== $ban_ips ) {
		delete_site_option( SECUPRESS_BAN_IP );
	}
}


/**
 * After submiting the email address with the form, send an email to the user or return an error.
 *
 * @since 1.0
 *
 * @param (string) $ip The user IP address.
 *
 * @return (array) An array containing at least a message and a "display_form" key to display or not the form after. Can contain a title.
 */
function secupress_check_ban_ips_maybe_send_unban_email( $ip ) {
	global $wpdb;

	if ( ! isset( $_POST['email'] ) ) { // WPCS: CSRF ok.
		return array(
			'message'      => '',
			'display_form' => true,
		);
	}
	// Check nonce and referer.
	$siteurl = strtolower( set_url_scheme( site_url() ) );
	$result  = ! empty( $_POST['_wpnonce'] ) ? wp_verify_nonce( $_POST['_wpnonce'], 'secupress-unban-ip-' . $ip ) : false;
	$referer = strtolower( wp_unslash( $_POST['_wp_http_referer'] ) );

	if ( strpos( $referer, 'http' ) !== 0 ) {
		$port    = (int) $_SERVER['SERVER_PORT'];
		$port    = 80 !== $port && 443 !== $port ? ( ':' . $port ) : '';
		$url     = 'http' . ( is_ssl() ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . $port;
		$referer = $url . $referer;
	}

	if ( ! $result || strpos( $referer, $siteurl ) !== 0 ) {
		return array(
			'title'        => __( 'Cheatin&#8217; uh?' ),
			'message'      => __( 'Cheatin&#8217; uh?' ),
			'display_form' => false,
		);
	}

	// Check email.
	if ( empty( $_POST['email'] ) ) {
		return array(
			'message'      => __( '<strong>Error</strong>: the email field is empty.', 'secupress' ),
			'display_form' => true,
		);
	}

	$email    = wp_unslash( $_POST['email'] );
	$is_email = is_email( $email );

	if ( ! $is_email ) {
		return array(
			/* translators: guess what, %s is an email address */
			'message'      => sprintf( __( '<strong>Error</strong>: the email address %s is not valid.', 'secupress' ), '<code>' . esc_html( $email ) . '</code>' ),
			'display_form' => true,
		);
	}
	$email = $is_email;

	// Check user.
	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		// Try with the backup email.
		$user = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'backup_email' AND meta_value = %s LIMIT 1", $email ) );
		$user = $user ? get_userdata( $user ) : 0;
	}

	if ( ! $user || ! user_can( $user, secupress_get_capability() ) ) {
		return array(
			'message'      => __( '<strong>Error</strong>: this email address does not belong to an Administrator.', 'secupress' ),
			'display_form' => true,
		);
	}

	// Send message.
	$message  = '<p>' . __( 'A bit clumsy and got yourself locked out? No problem, it happens sometimes. I\'ve got your back! I won\'t tell anybody. Or maybe I will. It could be a great story to tell during a long winter evening.', 'secupress' ) . '</p>';
	$message .= '<p>' . sprintf(
		/* translators: %s is a "unlock yourself" link */
		__( 'Anyway, simply follow this link to %s.', 'secupress' ),
		'<a href="' . esc_url( wp_nonce_url( home_url() . '?action=secupress_self-unban-ip', 'secupress_self-unban-ip-' . $ip ) ) . '">' . __( 'unlock yourself', 'secupress' ) . '</a>'
	) . '</p>';

	$headers = array(
		secupress_get_email( true ),
		'content-type: text/html',
	);

	$bcc = get_user_meta( $user->ID, 'backup_email', true );

	if ( $bcc && $bcc = is_email( $bcc ) ) {
		$headers[] = 'bcc: ' . $bcc;
	}

	$sent = wp_mail( $user->user_email, SECUPRESS_PLUGIN_NAME, $message, $headers );

	if ( ! $sent ) {
		return array(
			'title'        => __( 'Oh ooooooh...', 'secupress' ),
			'message'      => __( 'The message could not be sent. I guess you have to wait now :(', 'secupress' ),
			'display_form' => false,
		);
	}

	return array(
		'title'        => __( 'Message sent', 'secupress' ),
		'message'      => __( 'Everything went fine, your message is on its way to your mailbox.', 'secupress' ),
		'display_form' => false,
	);
}


/**
 * Return the form where the user can enter his email address.
 *
 * @since 1.0
 *
 * @param (array) $args An array with the following:
 *                      - (string) $ip       The user IP.
 *                      - (int)    $time_ban Banishment duration in minutes. 0 means forever.
 *                      - (string) $error    An error text.
 *
 * @return (string) The form.
 */
function secupress_check_ban_ips_form( $args ) {
	$args = array_merge( array(
		'ip'       => '',
		'time_ban' => 0,
		'error'    => '',
	), $args );

	if ( $args['time_ban'] ) {
		$content = '<p>' . sprintf( _n( 'Your IP address <code>%1$s</code> has been banned for <strong>%2$d</strong> minute.', 'Your IP address <code>%1$s</code> has been banned for <strong>%2$d</strong> minutes.', $args['time_ban'], 'secupress' ), esc_html( $args['ip'] ), $args['time_ban'] ) . '</p>';
	} else {
		$content = '<p>' . sprintf( __( 'Your IP address <code>%s</code> has been banned.', 'secupress' ), esc_html( $args['ip'] ) ) . '</p>';
	}
	$content .= '<form method="post" autocomplete="on">';
		$content .= '<p>' . __( 'If you are an Administrator and have been accidentally locked out, enter your main email address or the backup one in the following field. A message will be sent to both addresses with a link allowing you to unlock yourself.', 'secupress' ) . '</p>';
		$content .= '<label for="email">';
			$content .= __( 'Your email address:', 'secupress' );
			$content .= ' <input id="email" type="email" name="email" value="" required="required" aria-required="true" />';
			$content .= $args['error'] ? '<br/><span class="error">' . $args['error'] . '</span>' : '';
		$content .= '</label>';
		$content .= '<p class="submit"><button type="submit" name="submit" class="button button-primary button-large">' . __( 'Submit', 'secupress' ) . '</button></p>';
		$content .= wp_nonce_field( 'secupress-unban-ip-' . $args['ip'], '_wpnonce', true , false );
	$content .= '</form>';

	return $content;
}


add_filter( 'secupress.plugin.blacklist_logins_list', 'secupress_maybe_remove_admin_from_blacklist' );
/**
 * If user registrations are open, the "admin" user should not be blacklisted.
 * This is to avoid a conflict between "admin should exist" and "admin is a blacklisted username".
 *
 * @since 1.0
 *
 * @param (array) $list List of usernames.
 *
 * @return (array) List of usernames minus "admin" if registrations are open.
 */
function secupress_maybe_remove_admin_from_blacklist( $list ) {
	if ( secupress_users_can_register() ) {
		$list = array_diff( $list, array( 'admin' ) );
	}

	return $list;
}


add_action( 'plugins_loaded', 'secupress_rename_admin_username_logout', 50 );
/**
 * Will rename the "admin" account after the rename-admin-username manual fix
 *
 * @since 1.0
 */
function secupress_rename_admin_username_logout() {
	global $current_user, $pagenow, $wpdb;

	if ( ! empty( $_POST ) || defined( 'DOING_AJAX' ) || defined( 'DOING_AUTOSAVE' ) || defined( 'DOING_CRON' ) || 'admin-post.php' === $pagenow || ! is_user_logged_in() ) { // WPCS: CSRF ok.
		return;
	}

	$data = secupress_get_site_transient( 'secupress-rename-admin-username' );

	if ( ! $data ) {
		return;
	}

	if ( ! is_array( $data ) || ! isset( $data['ID'], $data['username'] ) ) {
		secupress_delete_site_transient( 'secupress-rename-admin-username' );
		return;
	}

	$current_user = wp_get_current_user();

	if ( (int) $current_user->ID !== (int) $data['ID'] || 'admin' !== $current_user->user_login ) {
		return;
	}

	secupress_delete_site_transient( 'secupress-rename-admin-username' );

	$is_super_admin = false;

	if ( is_multisite() && is_super_admin() ) {
		require_once( ABSPATH . 'wp-admin/includes/ms.php' );
		revoke_super_admin( $current_user->ID );
		$is_super_admin = true;
	}

	$wpdb->update( $wpdb->users, array( 'user_login' => $data['username'] ), array( 'user_login' => 'admin' ) );

	// Current user auth cookie is now invalid, log in again is mandatory.
	wp_clear_auth_cookie();

	if ( function_exists( 'wp_destroy_current_session' ) ) { // WP 4.0 min.
		wp_destroy_current_session();
	}

	wp_cache_delete( $current_user->ID, 'users' );

	if ( $is_super_admin ) {
		grant_super_admin( $current_user->ID );
	}

	secupress_fixit( 'Admin_User' );

	// Auto-login.
	$token = md5( time() );
	secupress_set_site_transient( 'secupress_auto_login_' . $token, array( $data['username'], 'Admin_User' ) );

	wp_safe_redirect( esc_url_raw( add_query_arg( 'secupress_auto_login_token', $token ) ) );
	die();
}


add_action( 'plugins_loaded', 'secupress_add_cookiehash_muplugin', 50 );
/**
 * Will create a mu plugin to modify the COOKIEHASH constant
 *
 * @since 1.0
 */
function secupress_add_cookiehash_muplugin() {
	global $current_user, $pagenow, $wpdb;

	if ( ! empty( $_POST ) || defined( 'DOING_AJAX' ) || defined( 'DOING_AUTOSAVE' ) || defined( 'DOING_CRON' ) || 'admin-post.php' === $pagenow || ! is_user_logged_in() ) { // WPCS: CSRF ok.
		return;
	}

	$data = secupress_get_site_transient( 'secupress-add-cookiehash-muplugin' );

	if ( ! $data ) {
		return;
	}

	if ( ! is_array( $data ) || ! isset( $data['ID'], $data['username'] ) ) {
		secupress_delete_site_transient( 'secupress-add-cookiehash-muplugin' );
		return;
	}

	if ( get_current_user_id() !== (int) $data['ID'] ) {
		return;
	}

	secupress_delete_site_transient( 'secupress-add-cookiehash-muplugin' );

	$contents  = '<?php // Added by SecuPress' . PHP_EOL;
	$contents .= 'define( \'COOKIEHASH\', md5( __FILE__ . \'' . wp_generate_password( 64 ) . '\' ) );';

	if ( ! secupress_create_mu_plugin( 'COOKIEHASH_' . uniqid(), $contents ) ) {
		return;
	}

	wp_clear_auth_cookie();

	if ( function_exists( 'wp_destroy_current_session' ) ) { // WP 4.0 min.
		wp_destroy_current_session();
	}

	$token = md5( time() );
	secupress_set_site_transient( 'secupress_auto_login_' . $token, array( $data['username'], 'WP_Config' ) );

	wp_safe_redirect( esc_url_raw( add_query_arg( 'secupress_auto_login_token', $token, secupress_get_current_url( 'raw' ) ) ) );
	die();
}


add_action( 'plugins_loaded', 'secupress_add_salt_muplugin', 50 );
/**
 * Will create a mu plugin to early set the salt keys
 *
 * @since 1.0
 */
function secupress_add_salt_muplugin() {
	global $current_user, $pagenow, $wpdb;

	if ( ! empty( $_POST ) || defined( 'SECUPRESS_SALT_KEYS_ACTIVE' ) || defined( 'DOING_AJAX' ) || defined( 'DOING_AUTOSAVE' ) || defined( 'DOING_CRON' ) || 'admin-post.php' === $pagenow || ! is_user_logged_in() ) { // WPCS: CSRF ok.
		return;
	}

	$data = secupress_get_site_transient( 'secupress-add-salt-muplugin' );

	if ( ! $data ) {
		return;
	}

	if ( ! is_array( $data ) || ! isset( $data['ID'], $data['username'] ) ) {
		secupress_delete_site_transient( 'secupress-add-salt-muplugin' );
		return;
	}

	if ( get_current_user_id() !== (int) $data['ID'] ) {
		return;
	}

	secupress_delete_site_transient( 'secupress-add-salt-muplugin' );

	$wpconfig_filename = secupress_find_wpconfig_path();

	if ( ! is_writable( $wpconfig_filename ) ) {
		return;
	}

	$keys = array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' );

	foreach ( $keys as $constant ) {
		secupress_replace_content( $wpconfig_filename, '/define\(.*(\'' . $constant . '\'|"' . $constant . '").*,/', '/*Commented by SecuPress*/ // $0' );
	}

	$alicia_keys = file_get_contents( SECUPRESS_INC_PATH . 'data/salt-keys.phps' );
	$alicia_keys = str_replace( array( '{{HASH1}}', '{{HASH2}}' ), array( wp_generate_password( 64, true, true ), wp_generate_password( 64, true, true ) ), $alicia_keys );

	if ( ! $alicia_keys || ! secupress_create_mu_plugin( 'salt_keys_' . uniqid(), $alicia_keys ) ) {
		return;
	}

	wp_clear_auth_cookie();
	if ( function_exists( 'wp_destroy_current_session' ) ) { // WP 4.0 min.
		wp_destroy_current_session();
	}

	foreach ( $keys as $constant ) {
		delete_site_option( $constant );
	}

	$token = md5( time() );
	secupress_set_site_transient( 'secupress_auto_login_' . $token, array( $data['username'], 'Salt_Keys' ) );

	wp_safe_redirect( esc_url_raw( add_query_arg( 'secupress_auto_login_token', $token, secupress_get_current_url( 'raw' ) ) ) );
	die();
}


add_action( 'plugins_loaded', 'secupress_auto_username_login', 60 );
/**
 * Will autologin the user found in the transient 'secupress_auto_login_' . $_GET['secupress_auto_login_token']
 *
 * @since 1.0
 */
function secupress_auto_username_login() {
	if ( ! isset( $_GET['secupress_auto_login_token'] ) ) {
		return;
	}

	list( $username, $action ) = secupress_get_site_transient( 'secupress_auto_login_' . $_GET['secupress_auto_login_token'] );

	secupress_delete_site_transient( 'secupress_auto_login_' . $_GET['secupress_auto_login_token'] );

	if ( ! $username ) {
		return;
	}

	add_filter( 'authenticate', '__secupress_give_him_a_user', 1, 2 );
	$user = wp_signon( array( 'user_login' => $username ) );
	remove_filter( 'authenticate', '__secupress_give_him_a_user', 1, 2 );

	if ( is_a( $user, 'WP_User' ) ) {
		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID );
	}

	if ( $action ) {
		secupress_scanit( $action );
	}

	wp_safe_redirect( esc_url_raw( remove_query_arg( 'secupress_auto_login_token', secupress_get_current_url( 'raw' ) ) ) );
	die();
}


/**
 * Used in secupress_rename_admin_username_login() to force a user when auto authenticating
 *
 * @since 1.0
 *
 * @param (null|object) $user     WP_User object if the user is authenticated.
 *                                WP_Error object or null otherwise.
 * @param (string)      $username Username or email address.
 *
 * @return (object|bool) A WP_User object or false.
 */
function __secupress_give_him_a_user( $user, $username ) {
	return get_user_by( 'login', $username );
}


add_action( 'plugins_loaded', 'secupress_downgrade_author_administrator', 70 );
/**
 * Admin As Author fix: a new Administrator account has been created, now we need to downgrade the old one.
 *
 * @since 1.0
 */
function secupress_downgrade_author_administrator() {
	if ( ! is_admin() ) {
		return;
	}

	// "{$new_user_id}|{$old_user_id}".
	$data = secupress_get_site_transient( 'secupress-admin-as-author-administrator' );

	// Nope.
	if ( ! $data ) {
		return;
	}

	if ( ! is_string( $data ) ) {
		// Dafuk.
		secupress_delete_site_transient( 'secupress-admin-as-author-administrator' );
		return;
	}

	list( $new_user_id, $old_user_id ) = array_map( 'absint', explode( '|', $data ) );

	if ( ! isset( $new_user_id, $old_user_id ) || ! $new_user_id || ! $old_user_id || $new_user_id === $old_user_id ) {
		// Dafuk.
		secupress_delete_site_transient( 'secupress-admin-as-author-administrator' );
		return;
	}

	if ( ! file_exists( secupress_class_path( 'scan', 'Admin_As_Author' ) ) ) {
		// Dafuk.
		secupress_delete_site_transient( 'secupress-admin-as-author-administrator' );
		return;
	}

	// These aren't the droids you're looking for.
	if ( get_current_user_id() !== $new_user_id ) {
		return;
	}

	if ( ! user_can( $new_user_id, 'administrator' ) || ! user_can( $old_user_id, 'administrator' ) ) {
		// Hey! What did you do?!
		secupress_delete_site_transient( 'secupress-admin-as-author-administrator' );
		return;
	}

	// The old account (the one with Posts).
	$user = get_user_by( 'id', $old_user_id );

	if ( ! $user ) {
		continue;
	}

	secupress_require_class( 'scan' );
	secupress_require_class( 'scan', 'Admin_As_Author' );

	$role = SecuPress_Scan_Admin_As_Author::get_new_role();

	/**
	 * No suitable user role: create one (who the fuck deleted it?!).
	 */
	if ( ! $role ) {
		$role = SecuPress_Scan_Admin_As_Author::create_editor_role();

		if ( ! $role ) {
			// The user role could not be created.
			return;
		}

		$role = $role['name'];
	}

	// Finally, change the user role.
	$user->remove_role( 'administrator' );
	$user->add_role( $role );

	// Not a Super Admin anymore.
	if ( is_multisite() && is_super_admin() && is_super_admin( $old_user_id ) ) {
		revoke_super_admin( $old_user_id );
	}

	// Update scan result.
	secupress_scanit( 'Admin_As_Author' );

	// Bye bye!
	secupress_delete_site_transient( 'secupress-admin-as-author-administrator' );
}


add_action( 'secupress.loaded', '__secupress_process_file_monitoring_tasks' );
/**
 * Launch file monitoring in background.
 *
 * @since 1.0
 */
function __secupress_process_file_monitoring_tasks() {
	if ( false === secupress_get_site_transient( 'secupress_toggle_file_scan' ) ) {
		return;
	}
	secupress_require_class_async();
	secupress_require_class( 'Admin', 'file-monitoring' );

	SecuPress_File_Monitoring::get_instance();
}

add_action( 'secupress.loaded', '__secupress_check_token_wp_registration_url' );
function __secupress_check_token_wp_registration_url() {
	if ( ! empty( $_POST['secupress_token'] ) && false !== ( $token = get_transient( 'secupress_scan_subscription_token' ) ) && $token === $_POST['secupress_token'] ) {
		add_action( 'wp_mail', '__return_false' );
	}
}