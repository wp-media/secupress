<?php
defined( 'ABSPATH' ) or die( 'Cheatin\' uh?' );


/**
 * Alerts class.
 *
 * @package SecuPress
 * @since 1.0
 */

class SecuPress_Alerts extends SecuPress_Singleton {

	const VERSION = '1.0';
	/**
	 * @var (object) The reference to the *Singleton* instance of this class.
	 */
	protected static $_instance;
	/**
	 * @var (array) Notification types selected by the user (email, SMS...).
	 */
	protected static $types;
	/**
	 * @var (int) Delay in seconds between two notifications of alerts of the same type.
	 */
	protected static $delay;
	/**
	 * @var (bool) Tells if the notification includes delayed alerts.
	 */
	protected $is_delayed = false;
	/**
	 * @var (array) Hooks that trigger an alert.
	 * @see `secupress.alerts.hooks` filter.
	 */
	protected $hooks;
	/**
	 * @var (array) Alerts that will be sent.
	 */
	protected $alerts = array();


	// Public methods ==============================================================================

	/**
	 * Get notification types selected by the user.
	 *
	 * @since 1.0
	 *
	 * @return (array) An array of types, as in `array( type1 => type1, type2 => type2 )`.
	 */
	public static function get_notification_types() {
		if ( isset( static::$types ) ) {
			return static::$types;
		}

		$defaults = array_flip( secupress_alerts_labels( secupress_is_pro() ) );

		static::$types = secupress_get_module_option( 'alerts_type', array(), 'alerts' );
		static::$types = array_intersect( static::$types, $defaults );
		static::$types = array_combine( static::$types, static::$types );

		return static::$types;
	}


	// Private methods =============================================================================

	/**
	 * Launch main hooks.
	 *
	 * @since 1.0
	 */
	protected function _init() {
		/**
		 * Options and network options.
		 */
		$hooks = array(
			'update_option_blogname'           => array(),
			'update_option_blogdescription'    => array(),
			'update_option_siteurl'            => array(),
			'update_option_home'               => array(),
			'update_option_admin_email'        => array(),
			'update_option_users_can_register' => array( 'test_value' => '!0' ),
			'update_option_default_role'       => array( 'test_value' => '!subscriber', 'pre_process' => array( $this, '_update_option_default_role_pre_process' ) ),
		);

		if ( is_multisite() ) {
			$hooks = array_merge( $hooks, array(
				'update_site_option_site_name'                => array(),
				'update_site_option_admin_email'              => array(),
				'update_site_option_registration'             => array( 'test_value' => '!none' ),
				'update_site_option_registrationnotification' => array( 'test_value' => '!yes' ),
				'update_site_option_add_new_users'            => array( 'test_value' => 1 ),
				'update_site_option_illegal_names'            => array(),
				'update_site_option_limited_email_domains'    => array(),
				'update_site_option_banned_email_domains'     => array(),
			) );
		}

		foreach ( $hooks as $hook => $atts ) {
			// Fill the blanks.
			$this->hooks[ $hook ] = array_merge( array(
				'immediately' => true,
				'callback'    => array( $this, '_option_cb' ),
				'priority'    => 1000,
				'nbr_args'    => 2,
				'test_value'  => null,
			), $atts );
		}

		/**
		 * Actions.
		 */
		$hooks = array(
			'secupress.block'     => array( 'immediately' => false ),
			'secupress.ip_banned' => array( 'immediately' => false ),
			'wp_login'            => array( 'test_cb' => array( __CLASS__, '_wp_login_test' ), 'nbr_args' => 2 ),
			//// File modif scan
			//// Malware scan
		);

		foreach ( $hooks as $hook => $atts ) {
			// Fill the blanks.
			$this->hooks[ $hook ] = array_merge( array(
				'immediately' => true,
				'callback'    => array( $this, '_action_cb' ),
				'priority'    => 1000,
				'nbr_args'    => 1,
				'test_cb'     => '__return_true',
			), $atts );
		}

		/**
		 * Filter the hooks that trigger an alert.
		 *
		 * @since 1.0
		 *
		 * @param (array) An array of arrays with hooks as keys and the values as follow:
		 *                - $immediately (bool)         Tells if the notification should be triggered immediately. Default is `true`.
		 *                - $callback    (string|array) Callback that will put new alerts in queue (or not). Default is `$this->_option_cb()` for options and `$this->_action_cb()` for other hooks.
		 *                - $priority    (int)          Used to specify the order in which the callbacks associated with a particular action are executed. Default is `1000`.
		 *                - $nbr_args    (int)          The number of arguments the callback accepts. Default is `2` for options and `1` for other hooks.
		 *                - $test_value  (mixed)        Used only for options. Value used to test the option new value against. If the test fails, the alert is not triggered. Default is null (means "any value"). See `$this->_option_test()`.
		 *                - $test_cb     (string|array) Used ony for non option hooks. Callback used to tell if the alert should be triggered. Default is `__return_true`.
		 *                - $pre_process (string|array) Callback to pre-process the data returned by the hook: the aim is to prepare the data to be ready for being displayed in a message. Facultative.
		 */
		$this->hooks = apply_filters( 'secupress.alerts.hooks', $this->hooks );

		// Launch the hooks.
		foreach ( $this->hooks as $hook => $atts ) {
			add_action( $hook, $this->hooks[ $hook ]['callback'], $this->hooks[ $hook ]['priority'], $this->hooks[ $hook ]['nbr_args'] );
		}

		// Maybe send notifications.
		add_action( 'shutdown', array( $this, '_maybe_notify' ) );
	}


	// Hook callbacks ==============================================================================

	/**
	 * Maybe queue an option hook.
	 *
	 * @since 1.0
	 *
	 * @param (mixed) $old_value_or_option The option old value or the option name, depending if the option is a network option or not. This is not used.
	 * @param (mixed) $value               The option new value.
	 */
	public function _option_cb( $old_value_or_option, $value ) {
		if ( $this->_option_test( $value ) ) {
			$this->_queue_alert( array( $value ) );
		}
	}


	/**
	 * Maybe queue a non option hook.
	 *
	 * @since 1.0
	 *
	 * @param (mixed) Depends on which hook is currently triggered.
	 */
	public function _action_cb() {
		$hook = current_filter();
		$args = func_get_args();

		if ( call_user_func( $this->hooks[ $hook ]['test_cb'], $args ) ) {
			$this->_queue_alert( $args );
		}

		// In case we're hooking a filter, return the first argument.
		return $args[0];
	}


	/**
	 * Add an alert to the queue.
	 *
	 * @since 1.0
	 *
	 * @param (array) $args Array of parameters returned by the hook.
	 */
	protected function _queue_alert( $args ) {
		$hook = current_filter();

		// Pre-process data.
		if ( ! empty( $this->hooks[ $hook ]['pre_process'] ) && is_callable( $this->hooks[ $hook ]['pre_process'] ) ) {
			$args = (array) call_user_func_array( $this->hooks[ $hook ]['pre_process'], $args );
		}

		// Escape and prepare data.
		$args = static::_escape_data( $args );

		// Queue the alert.
		$this->alerts[ $hook ]   = isset( $this->alerts[ $hook ] ) ? $this->alerts[ $hook ] : array();
		$this->alerts[ $hook ][] = array(
			'time' => time(),
			'data' => $args,
		);
	}


	/**
	 * Get the delay in seconds between two notifications of alerts of the same type.
	 *
	 * @since 1.0
	 *
	 * @return (int)
	 */
	protected static function _get_delay() {
		if ( isset( static::$delay ) ) {
			return static::$delay;
		}

		static::$delay = (int) secupress_get_module_option( 'alerts_frequency', 15, 'alerts' );
		static::$delay = secupress_minmax_range( static::$delay, 5, 60 );
		static::$delay = static::$delay * MINUTE_IN_SECONDS;

		return static::$delay;
	}


	// Test callbacks ==============================================================================

	/**
	 * Tell if the option should trigger an alert, depending no its value.
	 *
	 * @since 1.0
	 *
	 * @param (mixed) $value The option new value.
	 *
	 * @return (bool)
	 */
	protected function _option_test( $value ) {
		$hook    = current_filter();
		$compare = $this->hooks[ $hook ]['test_value'];

		// null => any change will be logged.
		if ( null === $compare ) {
			return true;
		}
		// '1' => only this numeric value will be logged.
		elseif ( is_int( $compare ) || is_numeric( $compare ) ) {
			if ( (int) $compare === (int) $value ) {
				return true;
			}
		}
		// '!xxx' => any value that is not this one will be logged.
		elseif ( is_string( $compare ) && substr( $compare, 0, 1 ) === '!' ) {
			$compare = substr( $compare, 1 );

			// '!1'
			if ( is_numeric( $compare ) ) {
				if ( (int) $compare !== (int) $value ) {
					return true;
				}
			}
			// '!subscriber'
			elseif ( $compare !== $value ) {
				return true;
			}
		}
		// 'xxx' => only this value will be logged.
		elseif ( $compare === $value ) {
			return true;
		}

		return false;
	}


	/**
	 * Fires after the user has successfully logged in with `wp_signon()`. But notify only if the user is an administrator.
	 *
	 * @since 1.0
	 *
	 * @param (string) $user_login The user login.
	 * @param (object) $user       WP_User object.
	 *
	 * @return (bool) True if the user is an Administrator.
	 */
	public static function _wp_login_test( $user_login, $user ) {
		return user_can( $user, 'administrator' );
	}


	// Data ========================================================================================

	/**
	 * Pre-process data for the `default_role` option: given a role name, return a translated role label instead.
	 *
	 * @since 1.0
	 *
	 * @param (string) $role The user role.
	 *
	 * @return (string)
	 */
	protected function _update_option_default_role_pre_process( $role ) {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		return isset( $wp_roles->role_names[ $role ] ) ? translate_user_role( $wp_roles->role_names[ $role ] ) : __( 'None' );
	}


	/**
	 * Prepare and escape the data. This phase is mandatory before displaying it in a notification.
	 *
	 * @since 1.0
	 *
	 * @param (array) $args An array of data.
	 *
	 * @return (array) $args An array of escaped data. They are also wrapped in html tags.
	 */
	protected function _escape_data( $args ) {
		if ( ! $args ) {
			return $args;
		}

		// Prepare and escape the data.
		foreach ( $args as $key => $data ) {
			if ( is_null( $data ) ) {
				$args[ $key ] = '<em>[null]</em>';
			} elseif ( true === $data ) {
				$args[ $key ] = '<em>[true]</em>';
			} elseif ( false === $data ) {
				$args[ $key ] = '<em>[false]</em>';
			} elseif ( '' === $data ) {
				$args[ $key ] = '<em>[' . __( 'empty string', 'secupress' ) . ']</em>';
			} elseif ( is_scalar( $data ) ) {
				$count = substr_count( $data, "\n" );

				// 50 seems to be a good limit. **Magic Number**
				if ( $count || strlen( $data ) > 50 ) {
					$args[ $key ] = '<pre>' . esc_html( $data ) . '</pre>';
				} else {
					$args[ $key ] = '<code>' . esc_html( $data ) . '</code>';
				}
			} else {
				$args[ $key ] = '<pre>' . esc_html( print_r( $data, true ) ) . '</pre>';
			}
		}

		return $args;
	}


	// Notifications ===============================================================================

	/**
	 * Send notifications if needed, store the remaining ones.
	 * Mix new alerts with old ones, then choose which ones should be sent:
	 * - the new alerts with the "immediately" attribute,
	 * - the old alerts whom the delay is exceeded.
	 *
	 * @since 1.0
	 */
	public function _maybe_notify() {
		$trigger_now = array();
		$delayed     = get_site_option( 'secupress_delayed_alerts', array() );
		$delayed     = is_array( $delayed ) ? $delayed : array();
		/**
		 * Testing for:    current-time < alert-time + delay
		 * is the same as: current-time - delay < alert-time
		 * But in this last case, we do the substraction only once instead of doing the addition multiple times in a loop.
		 */
		$time = time() - static::_get_delay();

		// Deal with new alerts that should pop now.
		if ( $this->alerts ) {
			foreach ( $this->alerts as $hook => $hooks ) {
				foreach ( $hooks as $i => $atts ) {
					// If this hook does not have previous iterations and should trigger an alert immediately, add it to the "trigger now" list.
					if ( empty( $delayed[ $hook ] ) && $this->hooks[ $hook ]['immediately'] ) {
						$trigger_now[ $hook ]   = isset( $trigger_now[ $hook ] ) ? $trigger_now[ $hook ] : array();
						$trigger_now[ $hook ][] = $atts;
					}
					// Store this alert with the others.
					$delayed[ $hook ]   = isset( $delayed[ $hook ] ) ? $delayed[ $hook ] : array();
					$delayed[ $hook ][] = $atts;
				}
			}
		}

		// Deal with old alerts that should pop now.
		if ( $delayed ) {
			foreach ( $delayed as $hook => $hooks ) {
				// Get the oldest alert of this type.
				$atts = reset( $hooks );

				// We haven't reached the delay yet.
				if ( $time < $atts['time'] ) {
					continue;
				}

				// If there is only one alert of this type and the notification has been sent, no need to do it again, just remove it.
				if ( $this->hooks[ $hook ]['immediately'] && count( $hooks ) === 1 ) {
					unset( $delayed[ $hook ] );
					continue;
				}

				// If "immediately", the first one has been notified already: remove it.
				if ( $this->hooks[ $hook ]['immediately'] ) {
					$key = key( $hooks );
					unset( $hooks[ $key ] );
				}

				// Now we have at least one alert to pop out.
				$trigger_now[ $hook ] = isset( $trigger_now[ $hook ] ) ? $trigger_now[ $hook ] : array();
				$trigger_now[ $hook ] = array_merge( $hooks, $trigger_now );
				unset( $delayed[ $hook ] );
				$this->is_delayed = true;
			}
		}

		// Store the alerts.
		update_site_option( 'secupress_delayed_alerts', $delayed );

		if ( ! $trigger_now ) {
			// Nothing to send right now.
			return;
		}

		// For each type of notification, shout out.
		$this->alerts = $trigger_now;
		$types        = static::get_notification_types();

		foreach ( $types as $type ) {
			call_user_func( array( $this, '_notify_' . $type ) );
		}
	}


	/**
	 * Notifiy by email.
	 *
	 * @since 1.0
	 */
	protected function _notify_email() {
		$count = array_sum( array_map( 'count', $this->alerts ) );

		// To
		$to = secupress_get_module_option( 'alerts_email', '', 'alerts' );
		$to = explode( ',', $to );

		if ( ! $to ) {
			return;
		}

		$to = array_map( 'trim', $to );

		if ( ! secupress_is_pro() ) {
			$to = array( reset( $to ) );
		}

		// From
		$from = secupress_get_email( true );

		// Subject
		$subject = _n( 'New important event on your site', 'New important events on your site', $count, 'secupress' );

		// Message
		$messages = array();

		foreach ( $this->alerts as $hook => $hooks ) {
			foreach ( $hooks as $i => $atts ) {
				$messages[] = vsprintf( static::_get_message( $hook ), $atts['data'] );
			}
		}

		$tmp_messages = array_count_values( $messages );
		$messages     = array();

		foreach ( $tmp_messages as $message => $nbr_message ) {
			if ( $nbr_message > 1 ) {
				$messages[] = sprintf( _n( '%1$s (%2$s occurrence)', '%1$s (%2$s occurrences)', $nbr_message, 'secupress' ), $message, number_format_i18n( $nbr_message ) );
			} else {
				$messages[] = $message;
			}
		}

		$messages = '<ol><li>' . implode( '</li><li>', $messages ) . '</li></ol>';

		if ( ! $this->is_delayed ) {
			$messages = _n( 'An important event just happened on your site:', 'Some important events just happened on your site:', $count, 'secupress' ) . $messages;
		} else {
			$mins     = round( static::_get_delay() / MINUTE_IN_SECONDS );
			$messages = sprintf( _n( 'An important event happened on your site for the last %d minutes:', 'Some important events happened on your site for the last %d minutes:', $count, 'secupress' ), $mins ) . $messages;
		}

		// Headers
		$headers = array(
			$from,
			'content-type: text/html',
		);

		// Go!
		wp_mail( $to, $subject, $messages, $headers );
	}


	/**
	 * Notifiy by sms.
	 *
	 * @since 1.0
	 */
	protected function _notify_sms() {
		//// Nothing yet.
	}


	/**
	 * Notifiy by push notification..
	 *
	 * @since 1.0
	 */
	protected function _notify_push() {
		//// Nothing yet.
	}


	/**
	 * Notifiy with Slack.
	 *
	 * @since 1.0
	 */
	protected function _notify_slack() {
		//// Nothing yet.
	}


	/**
	 * Notifiy with Twitter.
	 *
	 * @since 1.0
	 */
	protected function _notify_twitter() {
		//// Nothing yet.
	}


	/**
	 * Get a message for a specific hook.
	 *
	 * @since 1.0
	 *
	 * @param (string) $hook Hook name.
	 *
	 * @return (string) Message.
	 */
	protected static function _get_message( $hook ) {
		$messages = array(
			'update_option_blogname'                      => __( 'Your site\'s name has been changed to: %s.', 'secupress' ),
			'update_option_blogdescription'               => __( 'Your site\'s description has been changed to: %s.', 'secupress' ),
			'update_option_siteurl'                       => __( 'Your site\'s URL has been changed to: %s.', 'secupress' ),
			'update_option_home'                          => __( 'Your site\'s home URL has been changed to: %s.', 'secupress' ),
			'update_option_admin_email'                   => __( 'Your admin email address has been changed to: %s.', 'secupress' ),
			'update_option_users_can_register'            => __( 'Users can now register on your site.', 'secupress' ),
			'update_option_default_role'                  => __( 'When users register on your site, their user role is now %s.', 'secupress' ),
			'update_site_option_site_name'                => __( 'Your network\'s name has been changed to: %s.', 'secupress' ),
			'update_site_option_admin_email'              => __( 'Your network admin email address has been changed to: %s.', 'secupress' ),
			'update_site_option_registration'             => __( 'Users can now register on your network, and maybe create sites.', 'secupress' ),
			'update_site_option_registrationnotification' => __( 'Email notifications have been disabled when users or sites register.', 'secupress' ),
			'update_site_option_add_new_users'            => __( 'Administrators can now add new users to their site.', 'secupress' ),
			'update_site_option_illegal_names'            => __( 'The list of banned user names has been emptied.', 'secupress' ),
			'update_site_option_limited_email_domains'    => __( 'The list of email domains allowed to create sites has been modified.', 'secupress' ),
			'update_site_option_banned_email_domains'     => __( 'The list of email domains not allowed to create sites has been modified.', 'secupress' ),
			'secupress.block'                             => __( 'The IP address %2$s has been blocked.<br/>Module: %1$s<br/>Data: %3$s', 'secupress' ),
			'secupress.ip_banned'                         => __( 'The IP address %1$s has been banned.', 'secupress' ),
			'wp_login'                                    => __( 'The user %s just logged in.', 'secupress' ),
		);

		/**
		 * Filter the messages used in the alerts.
		 *
		 * @since 1.0
		 *
		 * @param (array) An array of messages with hooks as keys.
		 */
		$messages = apply_filters( 'secupress.alerts.messages', $messages );

		return isset( $messages[ $hook ] ) ? $messages[ $hook ] : sprintf( __( 'Missing message for key %s.', 'secupress' ), '<strong>' . $hook . '</strong>' );
	}
}
