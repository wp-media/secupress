<?php
/*
Module Name: Google Authenticator
Description: Two-Factor Authentication using a mobile app as OTP (One Time Password) generator.
Main Module: users_login
Author: SecuPress
Version: 1.0
*/

add_action( 'login_head', 'secupress_googleauth_css' );
function secupress_googleauth_css() {
?>
<style>
	.googleauth{font-size: 2em; text-align: center; font-weight: bold; letter-spacing: 0.2em; border-radius: 8px; color: #666;}
	.button-full{width: 100%}
	.dashright{float:right}
	.authinfo { padding:5px 0px;display:inline-block}
	.authinfo img{height:72px; width:72px; float:left; padding-right: 7px }
	.no-button,.no-button:hover, .no-button:focus {cursor: pointer;text-decoration: none; padding: 0; border: none; margin: 0; font-size: 1em; line-height: inherit; font-style:inherit; text-align: left; text-transform: inherit; color: #427FED; text-shadow: none; background: none; -webkit-border-radius: 0; border-radius: 0; -webkit-box-shadow: none; box-shadow: none; }
</style>
<?php
}

/**
 * Add verification code field to login form.
 */
add_action( 'login_form', 'secupress_googleauth_loginform' );
function secupress_googleauth_loginform( $echo = false ) {
	// var_dump(get_secupress_module_option( 'double_auth_affected_role', false, 'users_login' ));
	if ( $echo || array() == get_secupress_module_option( 'double_auth_affected_role', false, 'users_login' ) ) {
	?>
	<p>
	    <label>
	    <h3><?php printf( __( '2-Step Verification for %s', 'secupress' ), get_bloginfo( 'name', 'display' ) ); ?></h3>
	    <span class="authinfo">
	    	<img src="<?php echo plugins_url( '/inc/img/authenticator-Android-phone-icon_2X.png', __FILE__ ) ?>">
	    	<i><?php _e( 'Enter the verification code generated by your mobile application.', 'secupress' ); ?></i>
	    </span>
	    <input type="text" class="googleauth" onkeypress="return event.charCode >= 48 && event.charCode <= 57 || event.charCode == 13" name="otp" id="otp" size="20" style="ime-mode: inactive;" /></label>
	</p>
	<?php
	}
}

add_action( 'login_form_googleauth_lost_redir', 'secupress_googleauth_lost_form_redir' );
function secupress_googleauth_lost_form_redir() {
	global $wpdb;
	if ( ! isset( $_POST['googleauth_lost_method'] ) ) {
		secupress_die( sprintf( __( 'Invalid Link.<br>Please try to <a href="%s">log in again</a>.', 'secupress' ), wp_login_url( '', true ) ) );
	}
	$CLEAN = array();
	$CLEAN['token'] = isset( $_REQUEST['token'] ) ? sanitize_key( $_REQUEST['token'] ) : false;
	$CLEAN['rememberme'] = isset( $_REQUEST['rememberme'] );
	$CLEAN['uid'] = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_value = %s", $CLEAN['token'] ) );
	$CLEAN['uid'] = (int) reset( $CLEAN['uid'] );
	$user = get_user_by( 'id', $CLEAN['uid'] );
	if ( user_can( $user, 'exist' ) ) {
		$time = get_user_option( 'auth_timeout', $user->ID );
		if ( $time >= time() ) {
			update_user_option( $user->ID, 'auth_lost', '1', false );
			update_user_option( $user->ID, 'auth_timeout', time() + 10 * MINUTE_IN_SECONDS, false );
			$redirect_to = add_query_arg( array('action' => 'googleauth_lost_form_' . sanitize_key( $_POST['googleauth_lost_method'] ),
												'token' => $CLEAN['token'], 
												'rememberme' => $CLEAN['rememberme'] ), 
											wp_login_url()
										);
			wp_redirect( $redirect_to );
			die();
		} else {
			do_action( 'secupress_autologin_error', $user, 'expired key' );
			secupress_die( sprintf( __( 'You waited too long between the first step and now.<br>Please try to <a href="%s">log in again</a>.', 'secupress' ) . '</p>', wp_login_url( '', true ) ) );
		}
	} else {
		if ( ! $CLEAN['token'] || 1 != count( $CLEAN['uid'] ) ) {
			secupress_die( sprintf( __( 'Invalid Link.<br>Please try to <a href="%s">log in again</a>.', 'secupress' ), wp_login_url( '', true ) ) );
		}
	}
}

add_action( 'login_form_googleauth_lost_form_backupcode', 'secupress_googleauth_lost_form_backupcode' );
function secupress_googleauth_lost_form_backupcode() {
global $wpdb;
	$messages = array();
	$errors = null;
	if ( isset( $_GET['emailed'] ) ) {
		$messages[] = __( 'You will receive an e-mail on your backup e-mail address, containing a backup code.', 'secupress' );
	}
	$do_delete = $show_form = true;

	$CLEAN = array();
	$CLEAN['token'] = isset( $_REQUEST['token'] ) ? sanitize_key( $_REQUEST['token'] ) : false;
	$CLEAN['rememberme'] = isset( $_REQUEST['rememberme'] );
	$CLEAN['uid'] = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_value = %s", $CLEAN['token'] ) );
	$CLEAN['uid'] = (int) reset( $CLEAN['uid'] );
	$user = get_user_by( 'id', $CLEAN['uid'] );
	$server = rand( 0, 3 );
	?>
	<style>
	.login h1 a{background-image: url('http://<?php echo $server; ?>.gravatar.com/avatar/<?php echo md5( $user->user_email ); ?>?s=180&d=<?php echo admin_url( '/images/wordpress-logo.svg?ver=20131107' ); ?>') !important; border-radius: 100%}
	</style>
	<?php
	if ( ! $CLEAN['token'] || 1 != count( $CLEAN['uid'] ) || ! get_user_option( 'auth_lost', $user->ID ) ) {
		secupress_die( sprintf( __( 'Invalid Link.<br>Please try to <a href="%s">log in again</a>.', 'secupress' ), wp_login_url( '', true ) ) );
	} elseif ( isset( $_POST['otp'], $_POST['token'] ) ) {
		$time = get_user_option( 'auth_timeout', $user->ID );
		if ( $time >= time() ) {
			$backupcodes = get_user_option( 'secupress_google_auth_backupcodes', $user->ID );
			if ( $timeslot = in_array( $_POST['otp'], $backupcodes ) ) {
				delete_user_option( 'auth_lost', $user->ID );
				$backupcodes[ array_search( $_POST['otp'], $backupcodes ) ] = false;
				update_user_option( $user->ID, 'secupress_google_auth_backupcodes', $backupcodes );
				$secure_cookie = apply_filters( 'secure_signon_cookie', is_ssl(), array( 'user_login' => $user_by_check->user_login, 'user_password' => time() ) ); // we don't have the real password, just pass something
				wp_set_auth_cookie( $CLEAN['uid'], $CLEAN['rememberme'], $secure_cookie );
				do_action( 'wp_login', $user->user_login, $user );
				$redirect_to = apply_filters( 'login_redirect', admin_url(), admin_url(), $user_by_check );
				do_action( 'googleauth_autologin_success', $user );
				wp_redirect( $redirect_to );
				die( 'login_redirect' );
			} else {
				do_action( 'secupress_autologin_error', $user, 'invalid password' );
				add_action( 'login_head', 'wp_shake_js', 12 );
				$errors = new WP_Error( 'invalid_password', __( '<strong>ERROR</strong>: Invalid Google Auth. Backup Code.', 'secupress' ) );
				$do_delete = false;
			}
		} else {
			do_action( 'secupress_autologin_error', $user, 'expired key' );
			$errors = new WP_Error( 'expired_key', sprintf( __( 'You waited too long between the first step and now.<br>Please try to <a href="%s">log in again</a>.', 'secupress' ) . '</p>', wp_login_url( '', true ) ) );
			$show_form = false;
		}
		if ( $do_delete ) {
			delete_user_option( $CLEAN['uid'], 'password_token' );
			delete_user_option( $CLEAN['uid'], 'auth_timeout' );
		}
	}
	$messages = count( $messages ) ? '<p class="message error">' . implode( '</p><br><p class="message">', $messages ) . '</p><br>' : '';
	login_header( __( 'Log In' ), $messages, $errors );
	if ( $show_form ) {
		?>
		<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php?action=googleauth_lost_form_backupcode' ) ); ?>" method="post">
			<p>
			    <label>
			    <h3><?php printf( __( '2-Step Verification for %s', 'secupress' ), get_bloginfo( 'name', 'display' ) ); ?></h3>
			    <span class="authinfo">
			    	<img src="<?php echo plugins_url( '/inc/img/backup-codes-icon_2X.png', __FILE__ ) ?>">
			    	<i><?php _e( 'Enter one of your backup codes.', 'secupress' ); ?></i>
			    </span>
			    <input type="text" class="googleauth" onkeypress="return event.charCode >= 48 && event.charCode <= 57 || event.charCode == 13" name="otp" id="otp" size="20" style="ime-mode: inactive;" /></label>
			</p>
			<input type="hidden" name="token" value="<?php echo esc_attr( $CLEAN['token'] ); ?>">
			<?php if ( $CLEAN['rememberme'] ) { ?>
			<input type="hidden" name="rememberme" value="forever">
			<?php } ?>
			<p class="submit">
				<input type="submit" name="wp-submit" id="main-submit" class="button button-primary button-large button-full" value="<?php esc_attr_e('Verify'); ?>" />
			</p>
		</form>
		<?php
		login_footer( 'otp' );
	} else {
		login_footer();
	}
	die();
}

add_action( 'login_form_googleauth_lost_form_backupmail', 'secupress_googleauth_lost_form_backupmail' );
function secupress_googleauth_lost_form_backupmail() {
	global $wpdb;

	$CLEAN = array();
	$CLEAN['token'] = isset( $_REQUEST['token'] ) ? sanitize_key( $_REQUEST['token'] ) : false;
	$CLEAN['rememberme'] = isset( $_REQUEST['rememberme'] );
	$CLEAN['uid'] = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_value = %s", $CLEAN['token'] ) );
	$CLEAN['uid'] = (int) reset( $CLEAN['uid'] );
	$user = get_user_by( 'id', $CLEAN['uid'] );
	if ( user_can( $user, 'exist' ) && is_email( get_user_option( 'backup_email', $user->ID ) ) ) {
		$codes = get_user_option( 'secupress_google_auth_backupcodes', $user->ID );
		if ( is_array( $codes ) && count( array_filter( $codes ) ) ) {
 			$code = reset( array_filter( $codes ) );
		} else {
			$code = str_pad( wp_rand( 0, 9999999999 ), 10, '0', STR_PAD_BOTH );
			$codes[1] = $code;
			update_user_option( $user->ID, 'googleauth_backupcodes', $codes );
		}
		delete_user_option( 'auth_timeout', $user->ID );
		delete_user_option( 'auth_lost', $user->ID );

		$subject = apply_filters( 'secupress_googleauth_backupcode_email_subject', 
			sprintf( __( '[%1$s] Google Authenticator Backup Code request', 'secupress' ), get_bloginfo( 'name' ) ) );
		$message = apply_filters( 'secupress_googleauth_backupcode_email_message', 
			sprintf( __( 'Hello %1$s, you ask for a backup code, here it comes: %2$s.' ), 
				$user->display_name, $code ) );
			wp_mail( get_user_option( 'backup_email', $user->ID ), $subject, $message, 'content-type: text/html' );
			wp_redirect( add_query_arg( array( 'action' => 'googleauth_lost_form_backupcode', 'token' => $CLEAN['token'], 'rememberme' => $CLEAN['rememberme'], 'emailed' => 1 ), wp_login_url() ) );
			die();
	}
}

add_action( 'login_form_googleauth', 'secupress_googleauth_login_form_add_form' );
function secupress_googleauth_login_form_add_form() {
	global $wpdb;
	$messages = array();
	$errors = null;
	$messages[] = __( 'Your account or role requires an additionnal verification step.', 'secupress' );
	$do_delete = $show_form = true;

	$CLEAN = array();
	$CLEAN['token'] = isset( $_REQUEST['token'] ) ? sanitize_key( $_REQUEST['token'] ) : false;
	$CLEAN['rememberme'] = isset( $_REQUEST['rememberme'] );
	$CLEAN['uid'] = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_value = %s", $CLEAN['token'] ) );
	$CLEAN['uid'] = (int) reset( $CLEAN['uid'] );
	$user = get_user_by( 'id', $CLEAN['uid'] );
	$server = rand( 0, 3 );
	?>
	<style>
	.login h1 a{background-image: url('http://<?php echo $server; ?>.gravatar.com/avatar/<?php echo md5( $user->user_email ); ?>?s=180&d=<?php echo admin_url( '/images/wordpress-logo.svg?ver=20131107' ); ?>') !important; border-radius: 100%}
	</style>
	<?php

	if ( ! $CLEAN['token'] || 1 != count( $CLEAN['uid'] ) ) {
		secupress_die( sprintf( __( 'Invalid Link.<br>Please try to <a href="%s">log in again</a>.', 'secupress' ), wp_login_url( '', true ) ) );
	} elseif ( isset( $_POST['otp'], $_POST['token'] ) ) {
		$time = get_user_option( 'auth_timeout', $user->ID );
		if ( $time >= time() ) {
			$googleauth_secret = get_user_option( 'secupress_google_auth_secret', $user->ID );
			$lasttimeslot = get_user_option( 'secupress_google_auth_lasttimeslot', $user->ID );

			if ( $timeslot = __secupress_base32_verify( $googleauth_secret, $_POST['otp'], $lasttimeslot ) ) {
				update_user_option( $user->ID, 'secupress_google_auth_lasttimeslot', $timeslot, true );
				$secure_cookie = apply_filters( 'secure_signon_cookie', is_ssl(), array( 'user_login' => $user->user_login, 'user_password' => time() ) ); // we don't have the real password, just pass something
				wp_set_auth_cookie( $CLEAN['uid'], $CLEAN['rememberme'], $secure_cookie );
				do_action( 'wp_login', $user->user_login, $user );
				$redirect_to = apply_filters( 'login_redirect', admin_url(), admin_url(), $user );
				do_action( 'googleauth_autologin_success', $user );
				wp_redirect( $redirect_to );
				die( 'login_redirect' );
			} else {
				do_action( 'secupress_autologin_error', $user, 'invalid password' );
				add_action( 'login_head', 'wp_shake_js', 12 );
				$errors = new WP_Error( 'invalid_password', __( '<strong>ERROR</strong>: Invalid Google Authenticator Code.', 'secupress' ) );
				$do_delete = false;
			}
		} else {
			do_action( 'secupress_autologin_error', $user, 'expired key' );
			$errors = new WP_Error( 'expired_key', sprintf( __( 'You waited too long between the first step and now.<br>Please try to <a href="%s">log in again</a>.', 'secupress' ) . '</p>', wp_login_url( '', true ) ) );
			$show_form = false;
		}
		if ( $do_delete ) {
			delete_user_option( $CLEAN['uid'], 'password_token' );
			delete_user_option( $CLEAN['uid'], 'auth_timeout' );
		}
	}
	$messages = count( $messages ) ? '<p class="message error">' . implode( '</p><br><p class="message">', $messages ) . '</p><br>' : '';
	login_header( __( 'Log In' ), $messages, $errors );
	if ( $show_form ) {
		?>
		<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php?action=googleauth' ) ); ?>" method="post">
			<?php secupress_googleauth_loginform( true ); ?>
			<input type="hidden" name="token" value="<?php echo esc_attr( $CLEAN['token'] ); ?>">
			<?php if ( $CLEAN['rememberme'] ) { ?>
			<input type="hidden" name="rememberme" value="forever">
			<?php } ?>
			<p class="submit">
				<input type="submit" name="wp-submit" id="main-submit" class="button button-primary button-large button-full" value="<?php esc_attr_e('Verify'); ?>" />
			</p>
		</form>

		<form style="padding:26px 24px 26px" action="<?php echo esc_url( site_url( 'wp-login.php?action=googleauth_lost_redir' ) ); ?>" method="post">
		<input type="hidden" name="token" value="<?php echo esc_attr( $CLEAN['token'] ); ?>">
		<?php if ( $CLEAN['rememberme'] ) { ?>
			<input type="hidden" name="rememberme" value="forever">
		<?php } ?>
		<button type="button" class="no-button button-full" id="help_lost"><?php _e( 'Problems with your code?', 'secupress' ); ?> <span class="dashicons dashicons-arrow-right-alt2 dashright"></span></button>
		<div id="help_info">
			<h3><?php _e( 'Try one of these alternate methods.', 'secupress' ); ?></h3>
			<p><label><input type="radio" name="googleauth_lost_method" value="backupcode" checked="checked"> <?php _e( 'Use a backup code.', 'secupress' ); ?></label></p>
			<?php if ( get_user_meta( $user->ID, 'backup_email', true ) ) { ?>
			<p><label><input type="radio" name="googleauth_lost_method" value="backupmail"> <?php _e( 'Send a backup code on backup email.', 'secupress' ); ?></label></p>
			<?php } ?>
			<p class="submit">
				<input type="submit" id="secondary-submit" class="button button-secondary button-large button-full" value="<?php esc_attr_e( 'Use this method', 'secupress' ); ?>" />
			</p>
		</div>
		</form>
		<?php
		login_footer( 'otp' );
	} else {
		login_footer();
	}
	die();
}

add_action( 'login_footer', 'secupress_googleauth_hideifnojs' );
function secupress_googleauth_hideifnojs() {
	if ( isset( $_GET['action'] ) && 'googleauth' == $_GET['action'] ) {
	?>
	<script>
		jQuery( '#help_info' ).hide();
		jQuery( '#help_lost' ).click( function(e) {
			e.preventDefault();
			jQuery(this).hide();
			jQuery( '#help_info' ).slideDown();
		} );
	</script>
	<?php
	}
}

/**
 * Login form handling.
 * Check Google Authenticator verification code, if user has been setup to do so.
 * @param wordpressuser
 * @return user/loginstatus
 */
add_filter( 'authenticate', 'secupress_googleauth_otp', PHP_INT_MAX, 3 );
function secupress_googleauth_otp( $raw_user, $username, $password ) {
	if ( defined( 'XMLRPC_REQUEST' ) || defined( 'APP_REQUEST' ) ) {
		$user = get_user_by( 'login', $username );
		if ( $user && $password === get_user_option( 'secupress_google_auth_app_pass', $user->ID ) ) {
			return $user;
		} else {
			return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: The Google Authenticator app password is incorrect.', 'google-authenticator' ) );
		} 		 
	} 		 

	if ( ! is_wp_error( $raw_user ) && ! empty( $_POST ) ) {

		if ( -1 !== secupress_is_affected_role( 'users_login', 'double_auth', $raw_user ) &&
			secupress_is_affected_role( 'users_login', 'double_auth', $raw_user ) && 
			get_user_option( 'secupress_google_auth_secret', $raw_user->ID )
		) {

			$token = wp_hash( wp_generate_password( 32, false ), 'nonce' );

			update_user_option( $raw_user->ID, 'auth_token', $token );
			update_user_option( $raw_user->ID, 'auth_timeout', time() + 10 * MINUTE_IN_SECONDS );
			$raw_user = null;
			$rememberme = isset( $_POST['rememberme'] );
			$redirect_to = add_query_arg( array( 'action' => 'googleauth', 
												 'token' => $token, 
												 'rememberme' => $rememberme ), 
											wp_login_url()
										);
			wp_redirect( $redirect_to );
			die();

		} elseif( -1 === secupress_is_affected_role( 'users_login', 'double_auth', $raw_user ) &&
				get_user_option( 'secupress_google_auth_secret', $raw_user->ID )
			) {

			$otp = isset( $_POST['otp'] ) ? $_POST['otp'] : '';
			$googleauth_secret = get_user_option( 'secupress_google_auth_secret', $raw_user->ID );
			$lasttimeslot = get_user_option( 'secupress_google_auth_lasttimeslot', $raw_user->ID );

			if ( $timeslot = __secupress_base32_verify( $googleauth_secret, $otp, $lasttimeslot ) ) {
				update_user_option( $raw_user->ID, 'secupress_google_auth_lasttimeslot', $timeslot, true );
				do_action( 'googleauth_autologin_success', $CLEAN['uid'], $CLEAN['token'] );
			} else {
				return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Invalid Google Authenticator Code.', 'secupress' ) );
			}

		}
	}

	return $raw_user;
}


/**
 * Enqueue the jQuery QRCodejs script
 */
add_action( 'admin_print_scripts-profile.php', 'secupress_googleauth_add_jqrcode' );
function secupress_googleauth_add_jqrcode() {
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
    wp_enqueue_script( 'qrcode_script', plugins_url( 'inc/js/jquery.qrcode-0.12.0' . $suffix . '.js', __FILE__ ), array( 'jquery' ), '0.12.0', true );
}


/**
 * Check the verification code entered by the user.
 */
function __secupress_base32_verify( $secretkey, $thistry, $lasttimeslot ) {

    require_once( dirname( __FILE__ ) . '/inc/php/base32.php' );


	$tm = floor( time() / 30 );
	
	$secretkey = Base32::decode( $secretkey );
	// Key from 30 seconds before is also valid.
	for ($i=-1; $i<=0; $i++) {
		// Pack time into binary string
		$time=chr(0).chr(0).chr(0).chr(0).pack('N*',$tm+$i);
		// Hash it with users secret key
		$hm = hash_hmac( 'SHA1', $time, $secretkey, true );
		// Use last nipple of result as index/offset
		$offset = ord(substr($hm,-1)) & 0x0F;
		// grab 4 bytes of the result
		$hashpart=substr($hm,$offset,4);
		// Unpak binary value
		$value=unpack("N",$hashpart);
		$value=$value[1];
		// Only 32 bits
		$value = $value & 0x7FFFFFFF;
		$value = $value % 1000000;
		if ( $value === (int) $thistry ) {
			// Check for replay (Man-in-the-middle) attack.
			if ( $lasttimeslot >= ($tm+$i) ) {
				//// secupress log
				return false;
			}
			// Return timeslot in which login happened.
			return $tm+$i;
		}
	}
	return false;
}

/**
 * Extend personal profile page with Google Authenticator settings.
 */
add_action( 'profile_personal_options', 'secupress_googleauth_profile_personal_options' );
function secupress_googleauth_profile_personal_options() {
	global $user_id;

	$googleauth_secret = get_user_option( 'secupress_google_auth_secret', $user_id );
	$googleauth_backupcodes = get_user_option( 'secupress_google_auth_backupcodes', $user_id );
	$googleauth_app_pass = get_user_option( 'secupress_google_auth_app_pass', $user_id );
	if ( $googleauth_app_pass ) {
		$googleauth_app_pass = str_split( $googleauth_app_pass );
		$googleauth_app_pass[3] = '<span style="letter-spacing:1em">' . $googleauth_app_pass[3] . '</span>';
		$googleauth_app_pass[7] = '<span style="letter-spacing:1em">' . $googleauth_app_pass[7] . '</span>';
		$googleauth_app_pass[11] = '<span style="letter-spacing:1em">' . $googleauth_app_pass[11] . '</span>';
		$googleauth_app_pass = implode( "", $googleauth_app_pass );
	}

	$key_info = '<p class="description hidden">' . __( 'You can <span class="hide-if-no-js">scan the QRCode or </span>use this text key.', 'secupress' ) . '</p>';
	if ( '' == $googleauth_secret ) {
		$googleauth_secret = __( '[Google Authenticator not configured, generate a key first.]', 'secupress' );
		$key_info = '<p class="description">' . __( 'To get an authentication key, just click the <b>Generate a new app key</b> button below.', 'secupress' ) . '</p>';
	} elseif ( get_site_transient( 'secupress_googleauth_regen_secret' . $user_id ) ) {
		delete_site_transient( 'secupress_googleauth_regen_secret' . $user_id );
		$key_info = '<p class="description">' . __( 'You can <span class="hide-if-no-js">scan the QRCode or </span>use this text key.', 'secupress' ) . '</p>';
	} else {
		$googleauth_secret = str_repeat( '&bull;', 16 ) . __( ' (hidden for safety)', 'secupress' );
	}

	$app_info = '<p class="description hidden">' . __( 'You can use this key for any external application.', 'secupress' ) . '</p>';
	if ( '' == $googleauth_app_pass ) {
		$googleauth_app_pass = __( '[No application password yet.]', 'secupress' );
		$app_info = '<p class="description">' . __( 'To get an application password, just click the <b>Generate a new application key</b> button below.', 'secupress' ) . '</p>';
	} elseif ( get_site_transient( 'secupress_googleauth_regen_app_password_reset' . $user_id ) ) {
		delete_site_transient( 'secupress_googleauth_regen_app_password_reset' . $user_id );
		$app_info = '<p class="description">' . __( 'You can use this key for any external application.', 'secupress' ) . '</p>';
	} else {
		$googleauth_app_pass = str_repeat( '&bull;', 16 ) . __( ' (hidden for safety)', 'secupress' );
	}
	?>
	<h3><?php _e( 'Google Authenticator Settings', 'secupress' ); ?></h3>

	<table class="form-table">
		<tbody>
			<tr>
				<th>
					<?php _e( 'Secret App Key', 'secupress' ); ?>
					<p class="description"><?php _e( 'Get Google Authenticator mobile application on <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=fr">Android</a> and <a href="https://itunes.apple.com/fr/app/google-authenticator/id388497605?mt=8">iOS</a>.', 'secupress' ); ?></p>
				</th>
				<td>
					<code id="googleauth_secret"><?php echo $googleauth_secret; ?></code>
					<div id="googleauth_qrcode_desc" class="hide-if-js">
						<span id="googleauth_qrcode"></span>
						<br>
						<span class="description">
							<?php echo $key_info; ?>
						</span>
					</div>
					<p>
						<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=googleauthenticator_secret&uid=' . $user_id ), 'secupress_google_auth_secret-' . $user_id ); ?>" id="googleauth_newkey" class="button button-secondary button-small">
							<?php _e( 'Generate a new app key', 'secupress' ); ?>
						</a>
					</p>
				</td>
			</tr>	
			<tr>			
				<th>
					<?php _e( 'Backup Codes', 'secupress' ); ?>
					<p class="description"><?php _e( 'For when your phone is unavailable or just can\'t log in your account using the Google Authenticator.', 'secupress' ); ?></p>
				</th>
				<td>
					<p id="backupcodes_codes_description" data-desc="<?php echo esc_attr( __( 'You have no backup codes yet.', 'secupress' ) ); ?>" class="description">
				<?php
					$backup_codes_count = count( array_filter( (array) $googleauth_backupcodes ) );
					$backupcodes_codes_description = sprintf( _n( 'You have %d unused code.', 'You have %d unused codes.', $backup_codes_count, 'secupress' ), $backup_codes_count );
					if ( $backup_codes_count > 0 ) {
						echo esc_html( $backupcodes_codes_description );
					} else {
						esc_html_e( 'You have no backup codes yet.', 'secupress' );
					}
				?>
					</p>
					<style>
					#backupcodes_codes ol {
					    width: 300px;
					}
					#backupcodes_codes ol:after {
					    content: "";
					    display: block;
					    width: 100%;
					    clear: both;
					}
					#backupcodes_codes ol li {
					    float: left;
					    width: 50%;
					}
					#backupcodes_codes ol li:nth-child(odd) {
					    clear: left;
					}
					</style>
					<div id="backupcodes_codes" class="hide-if-js">
					<?php
					if ( is_array( $googleauth_backupcodes ) ) {
						echo '<ol>';
						foreach ( $googleauth_backupcodes as $bkcode ) {
							$bkcode = $bkcode ? $bkcode : __( '-- (used)', 'secupress' );
							if ( is_numeric( $bkcode[3] ) ) {
								$bkcode = str_split( $bkcode );
								$bkcode[3] = '<span style="letter-spacing:1em">' . $bkcode[3] . '</span>';
								$bkcode = implode( "", $bkcode );
							}
							echo "<li><code>$bkcode</code></li>";
						}
						echo '</ol>';
					} else {
						_e( 'You don\'t have any backup codes yet, generate some first.', 'secupress' );
					}
					?>
					</div>
					<p id="backupcodes_warning" class="hidden description">
						<?php _e( 'Keep them someplace accessible, like your wallet. Each code can be used only once.<br>Before running out of backup codes, generate new ones. Only the latest set of backup codes will work.', 'secupress' ); ?>
					</p>
					<?php if ( $backup_codes_count > 0 ) { ?>
					<p id="backupcodes_show_button" class="hide-if-no-js">
						<button class="button button-secondary button-small" type="button">
							<?php _e( 'Show backup codes', 'secupress' ); ?>
						</button>
					</p>
					<?php } ?>
					<p>
						<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=googleauthenticator_new_backup_codes&uid=' . $user_id ), 'secupress_google_auth_new_backup_codes-' . $user_id ); ?>" id="googleauth_newcodes" class="button button-secondary button-small">
							<?php _e( 'Generate new backup codes', 'secupress' ); ?>
						</a>
					</p>
				</td>
			</tr>			
			<tr>
			<th>
				<?php _e( 'Application Password', 'secupress' ); ?>
				<p class="description">
					<?php _e( 'If an external application needs to log in your website, simply generate a secret application password.', 'secupress' ); ?>
				</p>
			</th>
				<td>
					<p>
						<code id="app_password"><?php echo $googleauth_app_pass; ?></code>
						<p>
							<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=googleauthenticator_app_password&uid=' . $user_id ), 'secupress_google_auth_app_password-' . $user_id ); ?>" id="googleauth_app_password" class="button button-secondary button-small">
								<?php _e( 'Generate a new application password', 'secupress' );  ?>
							</a>
						</p>
						<p class="<?php echo $googleauth_app_pass ? '' : 'hide-if-no-js'; ?>">
							<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=googleauthenticator_app_password_reset&uid=' . $user_id ), 'secupress_google_auth_app_password_reset-' . $user_id ); ?>" id="googleauth_app_password_reset" class="hide-if-js button button-secondary button-small">
								<?php _e( 'Remove the application password', 'secupress' ); ?>
							</a>
						</p>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	<script type="text/javascript">
	jQuery( document ).ready( function($) {

		if ( $( '#app_password' ).text() != '<?php echo esc_js( __( 'No app password.', 'secupress' ) ); ?>' ) {
			$( '#googleauth_app_password_reset' ).css('display','inline-block');
		}

		// googleauthenticator_secret
		$( '#googleauth_newkey' ).click( function(e) {
			e.preventDefault();
			if ( confirm( '<?php echo esc_js( __( "Renewing your application key will force you to re-add an account on the Google Authenticator Mobile App.\nAre you sure to continue?", 'secupress' ) ); ?>' )
			) {
				$( '#googleauth_secret' ).html( '<img src="<?php echo admin_url( '/images/wpspin_light.gif' ); ?>" />' );
				var href = $(this).attr('href');
				$.get( href.replace( 'admin-post', 'admin-ajax' ), function( data ) { 
					if ( data.success ) {
						var qrcode = "otpauth://totp/WordPress:"+escape('<?php echo esc_js( get_bloginfo( 'name' ) ); ?>')+"?secret="+data.data.key+"&issuer=WordPress";
						$( '#googleauth_qrcode_desc, #googleauth_qrcode_desc .description' ).show();
						$( '#googleauth_qrcode' ).html( '' ).qrcode( { "render":"image", "background":"#ffffff", "size": 200, "text":qrcode } );
						$( '#googleauth_secret' ).text( data.data.key ).css('font-size','1.5em');
					}
				} );
			}
		});

		// googleauthenticator_new_backup_codes
		$( '#googleauth_newcodes' ).click( function(e) {
			e.preventDefault();
			if ( confirm( '<?php echo esc_js( __( "Renewing your backup codes will revoke all old ones.\nAre you sure to continue?", 'secupress' ) ); ?>' )
			) {
				$( '#backupcodes_codes li' ).html( '<img src="<?php echo admin_url( '/images/wpspin_light.gif' ); ?>" />' );
				var href = $(this).attr('href');
				$( '#backupcodes_codes' ).show();
				$( '#backupcodes_warning' ).show();
				$( '#backupcodes_show_button' ).hide();
				$.get( href.replace( 'admin-post', 'admin-ajax' ), function( data ) { 
					if ( data.success ) {
						$( '#backupcodes_codes_description' ).text( $( '#backupcodes_codes_description' ).data( 'desc' ) );
						var lis = '<ol>';
						for ( index in data.data.backupcodes ) {
							var orig = data.data.backupcodes[ index ];
							var code = orig.substr( 0, 3 ) + '<span style="letter-spacing:1em">' + orig[3] + '</span>' + orig.substr(4,8);
							lis += '<li><code>' + code + '</code></li>';
						} 
						lis += '</ol>';
						$('#backupcodes_codes').html( lis );
					}
				} );
			}
		});

		// googleauthenticator_app_password
		$( '#googleauth_app_password' ).click( function(e) {
			e.preventDefault();
			if ( confirm( '<?php echo esc_js( __( "Renewing your application password will forbid old password to work again.\nAre you sure to continue?", 'secupress' ) ); ?>' )
			) {
				$( '#app_password' ).html( '<img src="<?php echo admin_url( '/images/wpspin_light.gif' ); ?>" />' );
				var href = $(this).attr('href');
				$.get( href.replace( 'admin-post', 'admin-ajax' ), function( data ) { 
					if ( data.success ) {
						var orig = data.data;
						var code = orig.substr(0,3) + '<span style="letter-spacing:1em">' + orig[3] + '</span>' + orig.substr(4,3) + '<span style="letter-spacing:1em">' + orig[7] + '</span>' + orig.substr(8,3) + '<span style="letter-spacing:1em">' + orig[11] + '</span>' + orig.substr(12,4);
						$( '#app_password' ).html( code );
						$( '#googleauth_app_password_reset' ).css('display','inline-block');
					}
				} );
			}
		});

		// googleauthenticator_app_password_reset
		$( '#googleauth_app_password_reset' ).click( function(e) {
			e.preventDefault();
			if ( confirm( '<?php echo esc_js( __( "Deleting your application password will forbid old password to work again.\nAre you sure to continue?", 'secupress' ) ); ?>' )
			) {
				$( '#app_password' ).html( '<img src="<?php echo admin_url( '/images/wpspin_light.gif' ); ?>" />' );
				var href = $(this).attr('href');
				$.get( href.replace( 'admin-post', 'admin-ajax' ), function( data ) { 
					if ( data.success ) {
						$('#app_password').html( '<i><?php echo esc_js( __( 'No app password.', 'secupress' ) ); ?></i>' );
						$( '#googleauth_app_password_reset' ).hide();
					}
				} );
			}
		});

		// backupcodes_show_button
		$( '#backupcodes_show_button' ).click( function(e) {
			e.preventDefault();
			$( this ).hide();
			$( '#backupcodes_codes' ).show();
			$( '#backupcodes_warning' ).show();
		} );

	} );
	</script>
<?php
}

add_action( 'wp_ajax_googleauthenticator_secret', 'secupress_googleauth_regen_secret' );
add_action( 'admin_post_googleauthenticator_secret', 'secupress_googleauth_regen_secret' );
function secupress_googleauth_regen_secret( $uid = false ) {
	if ( $uid || isset( $_GET['_wpnonce'], $_GET['uid'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'secupress_google_auth_secret-' . $_GET['uid'] ) ) {
		$user_id = isset( $_GET['uid'] ) ? (int) $_GET['uid'] : $uid;
		$newkeys = array();
		$newkeys['key'] = secupress_generate_key();
		// $newkeys['backupcodes'] = secupress_generate_backupcodes();
		update_user_option( $user_id, 'secupress_google_auth_secret', $newkeys['key'] );
		// update_user_option( $user_id, 'secupress_google_auth_backupcodes', $newkeys['backupcodes'] );
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_success( $newkeys );
		} else {
			set_site_transient( 'secupress_googleauth_regen_secret' . $GLOBALS['current_user']->ID, '1' );
			wp_redirect( wp_get_referer() );
			die();
		}
	} else {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_error();
		} else {
			wp_nonce_ays( '' );
		}
	}
}

add_action( 'wp_ajax_googleauthenticator_new_backup_codes', 'secupress_googleauthenticator_new_backup_codes' );
add_action( 'admin_post_googleauthenticator_new_backup_codes', 'secupress_googleauthenticator_new_backup_codes' );
function secupress_googleauthenticator_new_backup_codes( $uid = false ) {
	if ( $uid || isset( $_GET['_wpnonce'], $_GET['uid'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'secupress_google_auth_new_backup_codes-' . $_GET['uid'] ) ) {
		$user_id = isset( $_GET['uid'] ) ? (int) $_GET['uid'] : $uid;
		$newkeys = array();
		// $newkeys['key'] = secupress_generate_key();
		$newkeys['backupcodes'] = secupress_generate_backupcodes();
		// update_user_option( $user_id, 'secupress_google_auth_secret', $newkeys['key'] );
		update_user_option( $user_id, 'secupress_google_auth_backupcodes', $newkeys['backupcodes'] );
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_success( $newkeys );
		} else {
			wp_redirect( wp_get_referer() );
			die();
		}
	} else {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_error();
		} else {
			wp_nonce_ays( '' );
		}
	}
}

add_action( 'wp_ajax_googleauthenticator_app_password', 'secupress_googleauth_regen_app_password' );
add_action( 'admin_post_googleauthenticator_app_password', 'secupress_googleauth_regen_app_password' );
function secupress_googleauth_regen_app_password( $uid = false ) {
	if ( $uid || isset( $_GET['_wpnonce'], $_GET['uid'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'secupress_google_auth_app_password-' . $_GET['uid'] ) ) {
		$user_id = isset( $_GET['uid'] ) ? (int) $_GET['uid'] : $uid;
		$newkey = secupress_generate_password( 16, array( 'min' => true, 'maj' => false, 'num' => false ) );
		update_user_option( $user_id, 'secupress_google_auth_app_pass', $newkey );//// multiple ?
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_success( $newkey );
		} else {
			set_site_transient( 'secupress_googleauth_regen_app_password_reset' . $GLOBALS['current_user']->ID, '1' );
			wp_redirect( wp_get_referer() );
			die();
		}
	} else {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_error();
		} else {
			wp_nonce_ays( '' );
		}
	}
}

add_action( 'wp_ajax_googleauthenticator_app_password_reset', 'secupress_googleauth_regen_app_password_reset' );
add_action( 'admin_post_googleauthenticator_app_password_reset', 'secupress_googleauth_regen_app_password_reset' );
function secupress_googleauth_regen_app_password_reset( $uid = false ) {
	if ( $uid || isset( $_GET['_wpnonce'], $_GET['uid'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'secupress_google_auth_app_password_reset-' . $_GET['uid'] ) ) {
		$user_id = isset( $_GET['uid'] ) ? (int) $_GET['uid'] : $uid;
		update_user_option( $user_id, 'secupress_google_auth_app_pass', '' );//// multiple ?
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_success();
		} else {
			wp_redirect( wp_get_referer() );
			die();
		}
	} else {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_error();
		} else {
			wp_nonce_ays( '' );
		}
	}
}

/**
 * This warnings are displayed when you ran out of google auth backup codes
 *
 * @since 1.0
 */
add_action( 'admin_notices', 'secupress_googleauth_warning_no_backup_codes' );
function secupress_googleauth_warning_no_backup_codes()
{
	global $current_user;
	$codes = get_user_option( 'secupress_google_auth_backupcodes', $current_user->ID );
	if ( is_array( $codes ) && ! count( array_filter( $codes ) ) ) {
		?>
		<div class="error">
			<p>
				<b><?php echo SECUPRESS_PLUGIN_NAME; ?></b>: 
				<?php echo sprintf( __( 'You ran out of backup codes! Please <a href="%s#googleauth_secret">renew your app key</a> to get new backup codes.', 'secupress' ), get_edit_profile_url() ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * This warnings are displayed when you did not yet generate a key
 *
 * @since 1.0
 */
add_action( 'admin_notices', 'secupress_googleauth_warning_not_set_yet' );
function secupress_googleauth_warning_not_set_yet()
{
	global $current_user;
	if ( ! get_user_option( 'secupress_google_auth_secret', $current_user->ID ) ) {
		?>
		<div class="error">
			<p>
				<b><?php echo SECUPRESS_PLUGIN_NAME; ?></b>: 
				<?php echo sprintf( __( 'Your account or role requires a double authentication using Google Authenticator, you have to <a href="%s#googleauth_secret">generate an application key</a> to continue.', 'secupress' ), get_edit_profile_url() ); ?>
			</p>
		</div>
		<?php
	}
}

add_action( 'current_screen', 'secupress_googleauth_redirect' );
function secupress_googleauth_redirect() {
	global $current_user, $current_screen, $pagenow;
	if ( 'secupress_page_secupress_modules' != $current_screen->id &&
		! in_array( $pagenow, array( 'profile.php', 'admin-ajax.php', 'admin-post.php' ) ) && 
		is_user_logged_in() && ! get_user_option( 'secupress_google_auth_secret', $current_user->ID )
	) {
		wp_redirect( admin_url( 'profile.php' ) );
		die();
	}
}

/*
TODO :
multiple app password
hash app password
*/
