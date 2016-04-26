<?php
defined( 'ABSPATH' ) or die( 'Cheatin\' uh?' );

/**
 * Actions Log class.
 *
 * @package SecuPress
 * @since 1.0
 */
class SecuPress_Action_Log extends SecuPress_Log {

	const VERSION = '1.0';


	// Instance ====================================================================================.

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 *
	 * @param (array|object) $args An array of arguments. If a `WP_Post` is used, it is converted in an adequate array.
	 *                             See `SecuPress_Log::__construct()` for the arguments.
	 *                             The data may need to be preprocessed.
	 */
	public function __construct( $args ) {
		parent::__construct( $args );

		/**
		 * If `$args` is an array, that means this Log doesn't exist yet and will be inserted in database.
		 * If `$args` is not an array, then it's a `WP_Post` object, so it already exists, no need to deal with the data any further.
		 */
		if ( ! is_array( $args ) ) {
			return;
		}

		/**
		 * The data needs to be preprocessed before being inserted in the database.
		 */
		$this->_pre_process_data();
	}


	// Private methods =============================================================================.

	// Pre-process data ============================================================================.

	/**
	 * Prepare the data to be ready for `vsprintf()`.
	 * This will be used before storing the Log in database.
	 *
	 * @since 1.0
	 */
	protected function _pre_process_data() {

		/**
		 * This filter allows not to log this Action.
		 *
		 * @since 1.0
		 *
		 * @param (bool)   $log_it True to log.
		 * @param (string) $type   The Log type.
		 * @param (string) $target An identifier (option name, action name, filter name).
		 * @param (array)  $data   The data.
		 */
		$log_it = apply_filters( 'secupress.logs.action-log.log-it', true, $this->type, $this->target, $this->data );

		if ( ! $log_it ) {
			return;
		}

		// Pre-proccess (maybe).
		$method_name = str_replace( array( '.', '-', '|' ), '_', $this->target );
		$method_name = '_pre_process_' . $this->type . ( $this->subtype ? '_' . $this->subtype : '' ) . '_' . $method_name;

		if ( method_exists( $this, $method_name ) ) {
			$this->data = (array) call_user_func_array( array( $this, $method_name ), $this->data );
		}

		/**
		 * Fires immediately after an Action Log pre-processing.
		 *
		 * @since 1.0
		 *
		 * @param (string) $type   The Log type.
		 * @param (string) $target An identifier (option name, action name, filter name).
		 * @param (array)  $data   The data.
		 */
		do_action( 'secupress.logs.action-log.after_pre-process', $this->type, $this->target, $this->data );
	}


	/**
	 * `add_option( 'active_plugins' )`: we need the activated plugins names.
	 *
	 * @since 1.0
	 *
	 * @param (string) $option The option name.
	 * @param (array)  $value  The option value.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $activated A comma-separated list of newly activated plugins.
	 */
	protected function _pre_process_option_add_active_plugins( $option, $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}

		foreach ( $value as $i => $plugin_path ) {
			$plugin      = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_path, false, false );
			$value[ $i ] = $plugin['Name'];
		}

		$sep   = sprintf( __( '%s, %s' ), '', '' );
		$value = implode( $sep, $value );

		return array( 'activated' => $value );
	}


	/**
	 * `update_option( 'active_plugins' )`: we need the activated/deactivated plugins names.
	 *
	 * @since 1.0
	 *
	 * @param (string) $option    The option name.
	 * @param (array)  $value     The option new value.
	 * @param (array)  $old_value The option old value.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $activated   A comma-separated list of newly activated plugins.
	 *                 - (string) $deactivated A comma-separated list of newly deactivated plugins.
	 */
	protected function _pre_process_option_update_active_plugins( $option, $value, $old_value ) {
		$old_value   = is_array( $old_value ) ? $old_value : array();
		$value       = is_array( $value )     ? $value     : array();
		$activated   = array_diff( $value, $old_value );
		$deactivated = array_diff( $old_value, $value );

		if ( $activated ) {
			foreach ( $activated as $i => $plugin_path ) {
				$plugin          = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_path, false, false );
				$activated[ $i ] = $plugin['Name'];
			}
		}

		if ( $deactivated ) {
			foreach ( $deactivated as $i => $plugin_path ) {
				$plugin            = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_path, false, false );
				$deactivated[ $i ] = $plugin['Name'];
			}
		}

		$sep = sprintf( __( '%s, %s' ), '', '' );
		$activated   = implode( $sep, $activated );
		$deactivated = implode( $sep, $deactivated );

		return compact( 'activated', 'deactivated' );
	}


	/**
	 * `add_site_option( 'active_sitewide_plugins' )`: we need the activated plugins names.
	 *
	 * @since 1.0
	 *
	 * @param (string) $option The option name.
	 * @param (array)  $value  The option value.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $activated A comma-separated list of newly activated plugins.
	 */
	protected function _pre_process_network_option_add_active_sitewide_plugins( $option, $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}

		foreach ( $value as $i => $plugin_path ) {
			$plugin      = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_path, false, false );
			$value[ $i ] = $plugin['Name'];
		}

		$sep   = sprintf( __( '%s, %s' ), '', '' );
		$value = implode( $sep, $value );

		return array( 'activated' => $value );
	}


	/**
	 * `update_site_option( 'active_sitewide_plugins' )`: we need the activated/deactivated plugins names.
	 *
	 * @since 1.0
	 *
	 * @param (string) $option    The option name.
	 * @param (array)  $value     The option new value.
	 * @param (array)  $old_value The option old value.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $activated   A comma-separated list of newly activated plugins.
	 *                 - (string) $deactivated A comma-separated list of newly deactivated plugins.
	 */
	protected function _pre_process_network_option_update_active_sitewide_plugins( $option, $value, $old_value ) {
		$old_value   = is_array( $old_value ) ? $old_value : array();
		$value       = is_array( $value )     ? $value     : array();
		$activated   = array_diff( $value, $old_value );
		$deactivated = array_diff( $old_value, $value );

		if ( $activated ) {
			foreach ( $activated as $i => $plugin_path ) {
				$plugin          = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_path, false, false );
				$activated[ $i ] = $plugin['Name'];
			}
		}

		if ( $deactivated ) {
			foreach ( $deactivated as $i => $plugin_path ) {
				$plugin            = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_path, false, false );
				$deactivated[ $i ] = $plugin['Name'];
			}
		}

		$sep = sprintf( __( '%s, %s' ), '', '' );
		$activated   = implode( $sep, $activated );
		$deactivated = implode( $sep, $deactivated );

		return compact( 'activated', 'deactivated' );
	}


	/**
	 * Fires when `secupress_block()` is called.
	 *
	 * @since 1.0
	 *
	 * @param (string) $module The name of the "module".
	 * @param (string) $ip     The IP blocked.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $url    The current URL, made relative.
	 *                 - (string) $ip     The IP blocked.
	 *                 - (string) $module The "module".
	 *                 - (array)  $server The `$_SERVER` superglobal.
	 */
	protected function _pre_process_action_secupress_block( $module, $ip ) {
		$url = wp_make_link_relative( secupress_get_current_url() );
		return array( 'url' => $url, 'ip' => $ip, 'module' => $module, 'server' => $_SERVER );
	}


	/**
	 * Fires after the user has successfully logged in with `wp_signon()`. Log only administrators.
	 *
	 * @since 1.0
	 *
	 * @param (string) $user_login The user login.
	 * @param (object) $user       WP_User object.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $user The user name followed by the user ID.
	 */
	protected function _pre_process_action_wp_login( $user_login, $user ) {
		if ( ! user_can( $user, 'administrator' ) ) {
			return array();
		}
		$user = static::_format_user_login( $user );
		return compact( 'user' );
	}


	/**
	 * Fires immediately before a user is deleted from the database by `wp_delete_user()`.
	 *
	 * @since 1.0
	 *
	 * @param (int)      $id       ID of the user to delete.
	 * @param (int|null) $reassign ID of the user to reassign posts and links to.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $user     The user name followed by the user ID.
	 *                 - (string) $reassign The user to reassign posts and links to: the user name followed by the user ID.
	 */
	protected function _pre_process_action_delete_user( $id, $reassign ) {
		$user     = static::_format_user_login( $id );
		$reassign = null === $reassign ? static::_format_user_login( $reassign ) : __( 'Nobody', 'secupress' );
		return compact( 'user', 'reassign' );
	}


	/**
	 * Fires immediately after an existing user is updated with `wp_insert_user()`.
	 *
	 * @since 1.0
	 *
	 * @param (int)    $user_id       User ID.
	 * @param (object) $old_user_data Object containing user's data prior to update.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $user The user name followed by the user ID.
	 *                 - (array)  $old  The old data.
	 *                 - (array)  $new  The new data.
	 */
	protected function _pre_process_action_profile_update( $user_id, $old_user_data ) {
		$user          = static::_format_user_login( $user_id );
		$old_user_data = (array) $old_user_data;
		$user_data     = (array) get_userdata( $user_id )->data;
		$user_keys     = array_merge( $old_user_data, $user_data );
		unset( $user_keys['ID'], $user_keys['user_status'], $user_keys['user_activation_key'] );
		$user_keys     = array_keys( $user_keys );

		$old = array();
		$new = array();

		foreach ( $user_keys as $data_name ) {
			if ( ! isset( $old_user_data[ $data_name ], $user_data[ $data_name ] ) || $old_user_data[ $data_name ] !== $user_data[ $data_name ] ) {
				$old[ $data_name ] = isset( $old_user_data[ $data_name ] ) ? $old_user_data[ $data_name ] : '';
				$new[ $data_name ] = isset( $user_data[ $data_name ] )     ? $user_data[ $data_name ]     : '';
			}
		}

		return $old ? compact( 'user', 'old', 'new' ) : array();
	}


	/**
	 * Fires immediately after a new user is registered with `wp_insert_user()`.
	 *
	 * @since 1.0
	 *
	 * @param (int) $user_id User ID.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $user The user name followed by the user ID.
	 */
	protected function _pre_process_action_user_register( $user_id ) {
		$user = static::_format_user_login( $user_id );
		return compact( 'user' );
	}


	/**
	 * Fires immediately after a user meta is added with `add_metadata()`.
	 * Don't log the `session_tokens` meta.
	 *
	 * @since 1.0
	 *
	 * @param (int)    $mid        The meta ID after successful update.
	 * @param (int)    $object_id  Object ID.
	 * @param (string) $meta_key   Meta key.
	 * @param (mixed)  $meta_value Meta value.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $user       The user name followed by the user ID.
	 *                 - (string) $meta_key   The meta key.
	 *                 - (mixed)  $meta_value The meta value.
	 */
	protected function _pre_process_action_added_user_meta( $mid, $object_id, $meta_key, $meta_value ) {
		if ( 'session_tokens' === $meta_key ) {
			return array();
		}
		$user = static::_format_user_login( $object_id );
		return compact( 'user', 'meta_key', 'meta_value' );
	}


	/**
	 * Fires immediately after a user meta is updated with `update_metadata()`.
	 * Don't log the `session_tokens` meta.
	 *
	 * @since 1.0
	 *
	 * @param (int)    $meta_id    ID of updated metadata entry.
	 * @param (int)    $object_id  Object ID.
	 * @param (string) $meta_key   Meta key.
	 * @param (mixed)  $meta_value Meta value.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $user       The user name followed by the user ID.
	 *                 - (string) $meta_key   The meta key.
	 *                 - (mixed)  $meta_value The meta value.
	 */
	protected function _pre_process_action_updated_user_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'session_tokens' === $meta_key ) {
			return array();
		}
		$user = static::_format_user_login( $object_id );
		return compact( 'user', 'meta_key', 'meta_value' );
	}


	/**
	 * Fires immediately after a user meta is deleted with `delete_metadata()`.
	 * Don't log the `session_tokens` meta.
	 *
	 * @since 1.0
	 *
	 * @param (array)  $meta_ids   An array of deleted metadata entry IDs.
	 * @param (int)    $object_id  Object ID.
	 * @param (string) $meta_key   Meta key.
	 * @param (mixed)  $meta_value Meta value.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $user       The user name followed by the user ID.
	 *                 - (string) $meta_key   The meta key.
	 *                 - (mixed)  $meta_value The meta value.
	 */
	protected function _pre_process_action_deleted_user_meta( $meta_ids, $object_id, $meta_key, $meta_value ) {
		if ( 'session_tokens' === $meta_key ) {
			return array();
		}
		$user = $object_id ? static::_format_user_login( $object_id ) : __( 'All Users' ); // WP i18n.
		return compact( 'user', 'meta_key', 'meta_value' );
	}


	/**
	 * Fires immediately after a new site is created with `wpmu_create_blog()`.
	 *
	 * @since 1.0
	 *
	 * @param (int) $blog_id Blog ID.
	 * @param (int) $user_id The user ID of the new site's admin.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $blog The blog name followed by the blog ID.
	 *                 - (string) $user The user name followed by the user ID.
	 */
	protected function _pre_process_action_wpmu_new_blog( $blog_id, $user_id ) {
		switch_to_blog( $blog_id );
		$blog = get_option( 'blogname' ) . ' (' . $blog_id . ')';
		$user = static::_format_user_login( $user_id );
		restore_current_blog();

		return compact( 'blog', 'user' );
	}


	/**
	 * Fires before a blog is deleted with `wpmu_delete_blog()`.
	 *
	 * @since 1.0
	 *
	 * @param (int) $blog_id The blog ID.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $blog The blog name followed by the blog ID.
	 */
	protected function _pre_process_action_delete_blog( $blog_id ) {
		$blog = get_option( 'blogname' ) . ' (' . $blog_id . ')';
		return compact( 'blog' );
	}


	/**
	 * Fires after PHPMailer is initialized and before an e-mail is sent by `wp_mail()`.
	 *
	 * @since 1.0
	 *
	 * @param (object) $phpmailer The PHPMailer instance, passed by reference.
	 *
	 * @return (array) An array containing:
	 *                 - (string) $from    The "From" name + address.
	 *                 - (string) $to      The "To" addresses.
	 *                 - (string) $subject The Subject (no kidding).
	 */
	protected function _pre_process_action_phpmailer_init( $phpmailer ) {
		$from    = $phpmailer->FromName . '[' . $phpmailer->From . ']';
		$to      = implode( ', ', array_keys( $phpmailer->getAllRecipientAddresses() ) );
		$subject = $phpmailer->Subject;
		return compact( 'from', 'to', 'subject' );
	}


	/**
	 * Fires after an HTTP API response is received and before the response is returned.
	 *
	 * @since 1.0
	 *
	 * @param (array|object) $response HTTP response or WP_Error object.
	 * @param (string)       $context  Context under which the hook is fired.
	 * @param (string)       $class    HTTP transport used.
	 * @param (array)        $args     HTTP request arguments.
	 * @param (string)       $url      The request URL.
	 *
	 * @return (array) An array containing:
	 *                 - (string)       $url      The requested URL.
	 *                 - (array)        $args     The request arguments.
	 *                 - (array|object) $response Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A WP_Error instance upon error.
	 */
	protected function _pre_process_action_http_api_debug( $response, $context, $class, $args, $url ) {
		if ( 'response' !== $context ) {
			return array();
		}
		return compact( 'url', 'args', 'response' );
	}


	// Title =======================================================================================.

	/**
	 * Set the Log title.
	 *
	 * @since 1.0
	 */
	protected function _set_title() {
		switch ( $this->type ) {
			case 'option':
				$this->_set_option_title();
				break;
			case 'network_option':
				$this->_set_network_option_title();
				break;
			case 'filter':
				$this->_set_filter_title();
				break;
			case 'action':
				$this->_set_action_title();
				break;
			default:
				return;
		}

		parent::_set_title();
	}


	/**
	 * Set the raw Log title for an option.
	 *
	 * @since 1.0
	 */
	protected function _set_option_title() {
		if ( 'active_plugins' === $this->target ) {
			$has_deactivated = ! empty( $this->data['deactivated'] ) && '<em>[' . __( 'empty string', 'secupress' ) . ']</em>' !== $this->data['deactivated'];

			if ( 'add' === $this->subtype ) {

				$this->title = __( 'Plugin(s) activated.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) && $has_deactivated ) {

				$this->title = __( 'Plugin(s) activated and deactivated.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) ) {

				$this->title = __( 'Plugin(s) activated.', 'secupress' );

			} elseif ( $has_deactivated ) {

				$this->title = __( 'Plugin(s) deactivated.', 'secupress' );

			} else {
				// Bug.
				$this->title = __( 'Plugin(s) activated and/or deactivated.', 'secupress' );
			}
			return;
		}

		if ( 'add' === $this->subtype ) {
			$this->title = __( 'Option %s created.', 'secupress' );
		} else {
			$this->title = __( 'Option %s updated.', 'secupress' );
		}
	}


	/**
	 * Set the raw Log title for a network option.
	 *
	 * @since 1.0
	 */
	protected function _set_network_option_title() {
		if ( 'active_sitewide_plugins' === $this->target ) {
			$has_deactivated = ! empty( $this->data['deactivated'] ) && '<em>[' . __( 'empty string', 'secupress' ) . ']</em>' !== $this->data['deactivated'];

			if ( 'add' === $this->subtype ) {

				$this->title = __( 'Plugin(s) network activated.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) && $has_deactivated ) {

				$this->title = __( 'Plugin(s) network activated and network deactivated.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) ) {

				$this->title = __( 'Plugin(s) network activated.', 'secupress' );

			} elseif ( $has_deactivated ) {

				$this->title = __( 'Plugin(s) network deactivated.', 'secupress' );

			} else {
				// Bug.
				$this->title = __( 'Plugin(s) network activated and/or network deactivated.', 'secupress' );
			}
			return;
		}

		if ( 'add' === $this->subtype ) {
			$this->title = __( 'Network option %s created.', 'secupress' );
		} else {
			$this->title = __( 'Network option %s updated.', 'secupress' );
		}
	}


	/**
	 * Set the raw Log title for a filter.
	 *
	 * @since 1.0
	 */
	protected function _set_filter_title() {
		$titles = array(
			'wpmu_validate_user_signup' => __( 'New user added (or not).', 'secupress' ),
		);

		$this->title = isset( $titles[ $this->target ] ) ? $titles[ $this->target ] : '';
	}


	/**
	 * Set the raw Log title for an action.
	 *
	 * @since 1.0
	 */
	protected function _set_action_title() {
		$titles = array(
			'secupress.block'         => str_replace( '%PLUGIN-NAME%', '<b>' . SECUPRESS_PLUGIN_NAME . '</b>', __( '%PLUGIN-NAME% prevented a request at %1$s.', 'secupress' ) ),
			'secupress.ban.ip_banned' => __( 'IP banned: %s.', 'secupress' ),
			'switch_theme'            => __( 'Theme activated: %s.', 'secupress' ),
			'wp_login'                => __( 'Administrator %s logged in.', 'secupress' ),
			'delete_user'             => __( 'User deleted: %s.', 'secupress' ),
			'profile_update'          => __( '%s\'s user data changed.', 'secupress' ),
			'user_register'           => __( 'New user %s created.', 'secupress' ),
			'added_user_meta'         => __( 'User meta %2$s added to %1$s.', 'secupress' ),
			'updated_user_meta'       => __( 'User meta %2$s updated for %1$s.', 'secupress' ),
			'deleted_user_meta'       => __( 'User meta %2$s deleted for %1$s.', 'secupress' ),
			'wpmu_new_blog'           => __( 'Blog %1$s created with %2$s as Administrator.', 'secupress' ),
			'delete_blog'             => __( 'Blog %s deleted.', 'secupress' ),
			'phpmailer_init'          => __( 'E-mail sent from %1$s to %2$s.', 'secupress' ),
			'http_api_debug'          => __( 'External request to %s.', 'secupress' ),
		);

		$this->title = isset( $titles[ $this->target ] ) ? $titles[ $this->target ] : '';
	}


	// Message =====================================================================================.

	/**
	 * Set the Log message.
	 *
	 * @since 1.0
	 */
	protected function _set_message() {
		// Set the raw message.
		switch ( $this->type ) {
			case 'option':
				$this->_set_option_message();
				break;
			case 'network_option':
				$this->_set_network_option_message();
				break;
			case 'filter':
				$this->_set_filter_message();
				break;
			case 'action':
				$this->_set_action_message();
				break;
			default:
				return;
		}

		parent::_set_message();
	}


	/**
	 * Set the raw Log message for an option.
	 *
	 * @since 1.0
	 */
	protected function _set_option_message() {
		if ( 'active_plugins' === $this->target ) {
			$has_deactivated = ! empty( $this->data['deactivated'] ) && '<em>[' . __( 'empty string', 'secupress' ) . ']</em>' !== $this->data['deactivated'];

			if ( 'add' === $this->subtype ) {

				$this->message = __( 'Plugin(s) activated: %s.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) && $has_deactivated ) {

				$this->message = __( 'Plugin(s) activated: %1$s. Plugin(s) deactivated: %2$s.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) ) {

				$this->message = __( 'Plugin(s) activated: %s.', 'secupress' );

			} elseif ( $has_deactivated ) {

				$this->message = __( 'Plugin(s) deactivated: %2$s.', 'secupress' );

			} else {
				// Bug.
				$this->message = __( 'Raw data: %2$s %3$s', 'secupress' );
			}
			return;
		}

		if ( 'add' === $this->subtype ) {
			$this->message = __( 'Option %1$s created with the following value: %2$s.', 'secupress' );
		} else {
			$this->message = __( 'Option %1$s updated from the value %3$s to %2$s.', 'secupress' );
		}
	}


	/**
	 * Set the raw Log message for a network option.
	 *
	 * @since 1.0
	 */
	protected function _set_network_option_message() {
		if ( 'active_sitewide_plugins' === $this->target ) {
			$has_deactivated = ! empty( $this->data['deactivated'] ) && '<em>[' . __( 'empty string', 'secupress' ) . ']</em>' !== $this->data['deactivated'];

			if ( 'add' === $this->subtype ) {

				$this->message = __( 'Plugin(s) network activated: %s.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) && $has_deactivated ) {

				$this->message = __( 'Plugin(s) network activated: %1$s. Plugin(s) network deactivated: %2$s.', 'secupress' );

			} elseif ( ! empty( $this->data['activated'] ) ) {

				$this->message = __( 'Plugin(s) network activated: %s.', 'secupress' );

			} elseif ( $has_deactivated ) {

				$this->message = __( 'Plugin(s) network deactivated: %2$s.', 'secupress' );

			} else {
				// Bug.
				$this->message = __( 'Raw data: %2$s %3$s', 'secupress' );
			}
			return;
		}

		if ( 'add' === $this->subtype ) {
			$this->message = __( 'Network option %1$s created with the following value: %2$s.', 'secupress' );
		} else {
			$this->message = __( 'Network option %1$s updated from the value %3$s to %2$s', 'secupress' );
		}
	}


	/**
	 * Set the raw Log message for a filter.
	 *
	 * @since 1.0
	 */
	protected function _set_filter_message() {
		$messages = array(
			'wpmu_validate_user_signup' => __( 'New user added (or not) using the following data: %s', 'secupress' ),
		);

		$this->message = isset( $messages[ $this->target ] ) ? $messages[ $this->target ] : '';
	}


	/**
	 * Set the raw Log message for an action.
	 *
	 * @since 1.0
	 */
	protected function _set_action_message() {
		$messages = array(
			'secupress.block'         => str_replace( '%PLUGIN-NAME%', '<strong>' . SECUPRESS_PLUGIN_NAME . '</strong>', __( '%PLUGIN-NAME% prevented a request at %1$s from the IP %2$s. Block ID: %3$s. The server configuration at the moment: %4$s', 'secupress' ) ),
			'secupress.ban.ip_banned' => __( 'IP banned: %s.', 'secupress' ),
			'switch_theme'            => __( 'Theme activated: %s.', 'secupress' ),
			'wp_login'                => __( 'Administrator %s logged in.', 'secupress' ),
			'delete_user'             => __( 'User deleted: %1$s. Post assigned to: %2$s.', 'secupress' ),
			'profile_update'          => __( '%1$s\'s user data changed from: %2$s To: %3$s', 'secupress' ),
			'user_register'           => __( 'New user %s created.', 'secupress' ),
			'added_user_meta'         => __( 'User meta %2$s added to %1$s with the value %3$s', 'secupress' ),
			'updated_user_meta'       => __( 'User meta %2$s updated for %1$s with the value %3$s Previous value was: %3$s', 'secupress' ),
			'deleted_user_meta'       => __( 'User meta %2$s deleted for %1$s.', 'secupress' ),
			'wpmu_new_blog'           => __( 'Blog %1$s created with %2$s as Administrator.', 'secupress' ),
			'delete_blog'             => __( 'Blog %s deleted.', 'secupress' ),
			'phpmailer_init'          => __( 'E-mail sent from %1$s to %2$s with the following subject: %3$s', 'secupress' ),
			'http_api_debug'          => __( 'External request to: %1$s with the following arguments: %2$s The response was: %3$s', 'secupress' ),
		);

		$this->message = isset( $messages[ $this->target ] ) ? $messages[ $this->target ] : '';
	}


	// Criticity ===================================================================================.

	/**
	 * Set the Log criticity.
	 *
	 * @since 1.0
	 */
	protected function _set_criticity() {
		switch ( $this->type ) {
			case 'option':
				$this->_set_option_criticity();
				break;
			case 'network_option':
				$this->_set_network_option_criticity();
				break;
			case 'filter':
				$this->_sget_filter_criticity();
				break;
			case 'action':
				$this->_set_action_criticity();
				break;
		}
	}


	/**
	 * Get the Log criticity for an option.
	 *
	 * @since 1.0
	 */
	protected function _set_option_criticity() {
		switch ( $this->target ) {
			case 'default_role':
				$this->critic = 'high';
				break;
			default:
				$this->critic = 'normal';
		}
	}


	/**
	 * Get the Log criticity for a network option.
	 *
	 * @since 1.0
	 */
	protected function _set_network_option_criticity() {
		$this->critic = 'normal';
	}


	/**
	 * Set the Log criticity for a filter.
	 *
	 * @since 1.0
	 */
	protected function _set_filter_criticity() {
		$this->critic = 'normal';
	}


	/**
	 * Set the Log criticity for an action.
	 *
	 * @since 1.0
	 */
	protected function _set_action_criticity() {
		switch ( $this->target ) {
			case 'secupress.block':
				$this->critic = 'high';
				break;
			default:
				$this->critic = 'normal';
		}
	}


	// Tools =======================================================================================.

	/**
	 * Get a user login followed by his/her ID.
	 *
	 * @since 1.0
	 *
	 * @param (int|object) $user_id A user ID or a WP_User object.
	 *
	 * @return (string) This user login followed by his ID.
	 */
	protected static function _format_user_login( $user_id ) {
		if ( $user_id && is_numeric( $user_id ) ) {
			$user_id = (int) $user_id;
			$user    = get_userdata( $user_id );
			$user_id = $user && ! empty( $user->ID ) ? $user->ID : $user_id;
		} elseif ( $user_id && is_object( $user_id ) ) {
			$user    = $user_id;
			$user_id = $user && ! empty( $user->ID ) ? $user->ID : 0;
		} else {
			$user    = false;
			$user_id = 0;
		}

		return ( $user ? $user->user_login : '[' . __( 'Unknown user', 'secupress' ) . ']' ) . ' (' . $user_id . ')';
	}
}
