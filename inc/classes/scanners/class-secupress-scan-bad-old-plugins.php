<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );

/**
 * Bad Old Plugins scan class.
 *
 * @package SecuPress
 * @subpackage SecuPress_Scan
 * @since 1.0
 */

class SecuPress_Scan_Bad_Old_Plugins extends SecuPress_Scan implements iSecuPress_Scan {

	const VERSION = '1.0';

	/**
	 * @var Singleton The reference to *Singleton* instance of this class
	 */
	protected static $_instance;
	public    static $prio = 'high';


	protected static function init() {
		self::$type     = 'WordPress';
		self::$title    = __( 'Check if you are using plugins that have been deleted from the official repository or not updated since two years at least.', 'secupress' );
		self::$more     = __( 'Avoid to use a plugin that have been removed from the official repository, and avoid using a plugin that have not been maintained for two years at least.', 'secupress' );
		if ( is_network_admin() ) {
			self::$more_fix = __( 'This will ask you to select and delete these plugins. If some of them are activated on some of your websites, a new page similar to this one will be created in each related site, where administrators will be asked to select and delete these plugins.', 'secupress' );
		} else {
			self::$more_fix = __( 'This will ask you to deactivate or delete these plugins.', 'secupress' );
		}
	}


	public static function get_messages( $message_id = null ) {
		$messages = array(
			// good
			0   => __( 'You don\'t use bad or old plugins.', 'secupress' ),
			1   => __( 'You don\'t use bad or old plugins anymore.', 'secupress' ),
			2   => __( 'All bad or old plugins have been deleted.', 'secupress' ),
			3   => __( 'All deletable bad or old plugins have been deleted.', 'secupress' ),
			4   => __( 'All bad or old plugins have been deactivated.', 'secupress' ),
			// warning
			/* translators: %s is a file name. */
			100 => __( 'Error, could not read %s.', 'secupress' ),
			101 => __( 'No plugins selected for deletion.', 'secupress' ),
			102 => _n_noop( 'Selected plugin has been deleted (but some are still there).', 'All selected plugins have been deleted (but some are still there).', 'secupress' ),
			103 => _n_noop( 'Sorry, the following plugin could not be deleted: %s.', 'Sorry, the following plugins could not be deleted: %s.', 'secupress' ),
			104 => __( 'No plugins selected for deactivation.', 'secupress' ),
			105 => _n_noop( 'Selected plugin has been deactivated (but some are still there).', 'All selected plugins have been deactivated (but some are still there).', 'secupress' ),
			106 => _n_noop( 'Sorry, the following plugin could not be deactivated: %s.', 'Sorry, the following plugins could not be deactivated: %s.', 'secupress' ),
			// bad
			/* translators: 1 is a number, 2 is a plugin name (or a list of plugin names). */
			200 => _n_noop( '<strong>%1$d plugin</strong> is no longer in the WordPress directory: %2$s.', '<strong>%1$d plugins</strong> are no longer in the WordPress directory: %2$s.', 'secupress' ),
			/* translators: 1 is a number, 2 is a plugin name (or a list of plugin names). */
			201 => _n_noop( '<strong>%1$d plugin</strong> has not been updated for 2 years at least: %2$s.', '<strong>%1$d plugins</strong> have not been updated for 2 years at least: %2$s.', 'secupress' ),
			/* translators: %s is a plugin name. */
			202 => __( 'You should delete the plugin %s.', 'secupress' ),
			203 => _n_noop( 'Sorry, this plugin could not be deleted.', 'Sorry, those plugins could not be deleted.', 'secupress' ),
			204 => _n_noop( 'The following plugin should be deactivated if you don\'t need it: %s.', 'The following plugins should be deactivated if you don\'t need them: %s.', 'secupress' ),
			205 => _n_noop( 'Sorry, this plugin could not be deactivated.', 'Sorry, those plugins could not be deactivated.', 'secupress' ),
			// cantfix
			/* translators: %d is a number. */
			300 => _n_noop( '<strong>%d</strong> plugin can be <strong>deleted</strong>.', '<strong>%d</strong> plugins can be <strong>deleted</strong>.', 'secupress' ),
			/* translators: %d is a number. */
			301 => _n_noop( '<strong>%d</strong> plugin can be <strong>deactivated</strong>.', '<strong>%d</strong> plugins can be <strong>deactivated</strong>.', 'secupress' ),
			302 => __( 'Unable to locate WordPress Plugin directory.' ), // WPi18n
			/* translators: %s is the plugin name. */
			303 => sprintf( __( 'A new %s menu item has been activated in the relevant site\'s administration area to let Administrators know which plugins to deactivate.', 'secupress' ), '<strong>' . SECUPRESS_PLUGIN_NAME . '</strong>' ),
			304 => __( 'No plugins selected.', 'secupress' ),
		);

		if ( isset( $message_id ) ) {
			return isset( $messages[ $message_id ] ) ? $messages[ $message_id ] : __( 'Unknown message', 'secupress' );
		}

		return $messages;
	}


	public function scan() {
		// Multisite, for the current site.
		if ( $this->is_for_current_site() ) {
			// Plugins no longer in directory or not updated in over 2 years.
			$bad_plugins = $this->get_installed_plugins_to_remove();
			$bad_plugins = $bad_plugins['to_deactivate'];

			if ( $count = count( $bad_plugins ) ) {
				// bad
				$this->add_message( 204, array( $count, $bad_plugins ) );
			}
		}
		// Network admin or not Multisite.
		else {
			// If we're in a sub-site, don't list the plugins enabled in the network.
			$to_keep = array();

			// Plugins no longer in directory.
			$bad_plugins = static::get_installed_plugins_no_longer_in_directory();

			if ( $count = count( $bad_plugins ) ) {
				// bad
				$this->add_message( 200, array( $count, $count, self::wrap_in_tag( $bad_plugins ) ) );
			}

			// Plugins not updated in over 2 years.
			$bad_plugins = static::get_installed_plugins_over_2_years();
			$bad_plugins = $to_keep ? array_diff_key( $bad_plugins, $to_keep ) : $bad_plugins;

			if ( $count = count( $bad_plugins ) ) {
				// bad
				$this->add_message( 201, array( $count, $count, self::wrap_in_tag( $bad_plugins ) ) );
			}

			// Check for Hello Dolly existence.
			if ( $hello = $this->has_hello_dolly() ) {
				// bad
				$this->add_message( 202, $hello );
			}
		}

		// good
		$this->maybe_set_status( 0 );

		return parent::scan();
	}


	public function fix() {
		// Plugins no longer in directory or not updated in over 2 years or Hello Dolly.
		$bad_plugins = $this->get_installed_plugins_to_remove();

		if ( $bad_plugins['count'] ) {
			if ( $count = count( $bad_plugins['to_delete'] ) ) {
				// cantfix
				$this->add_fix_message( 300, array( $count, $count ) );
				$this->add_fix_action( 'delete-bad-old-plugins' );
			}
			if ( $count = count( $bad_plugins['to_deactivate'] ) ) {
				// cantfix
				$this->add_fix_message( 301, array( $count, $count ) );
				$this->add_fix_action( 'deactivate-bad-old-plugins' );
			}
		} else {
			// good
			$this->add_fix_message( 1 );
		}

		return parent::fix();
	}


	public function manual_fix() {
		$bad_plugins = $this->get_installed_plugins_to_remove();

		if ( $bad_plugins['count'] ) {
			if ( $this->has_fix_action_part( 'delete-bad-old-plugins' ) ) {
				$delete = $this->manual_delete( $bad_plugins['to_delete'], (bool) $bad_plugins['to_deactivate'] );
			}

			if ( $this->has_fix_action_part( 'deactivate-bad-old-plugins' ) ) {
				$deactivate = $this->manual_deactivate( $bad_plugins['to_deactivate'], (bool) $bad_plugins['to_delete'] );
			}

			if ( ! empty( $delete ) && ! empty( $deactivate ) ) {
				// cantfix: nothing selected in both lists.
				$this->add_fix_message( 304 );
			} elseif ( ! empty( $delete ) ) {
				// warning: no plugins selected.
				$this->add_fix_message( $delete );
			} elseif ( ! empty( $deactivate ) ) {
				// warning: no plugins selected.
				$this->add_fix_message( $deactivate );
			}
		} else {
			// good
			$this->add_fix_message( 1 );
		}

		return parent::manual_fix();
	}


	protected function manual_delete( $bad_plugins, $has_plugins_to_deactivate ) {
		if ( ! $bad_plugins ) {
			// good
			return $this->add_fix_message( 1 );
		}

		// Get the list of plugins to uninstall.
		$selected_plugins = ! empty( $_POST['secupress-fix-delete-bad-old-plugins'] ) && is_array( $_POST['secupress-fix-delete-bad-old-plugins'] ) ? array_filter( $_POST['secupress-fix-delete-bad-old-plugins'] ) : array();
		$selected_plugins = $selected_plugins ? array_fill_keys( $selected_plugins, 1 ) : array();
		$selected_plugins = $selected_plugins ? array_intersect_key( $bad_plugins, $selected_plugins ) : array();

		if ( ! $selected_plugins ) {
			if ( $this->has_fix_action_part( 'deactivate-bad-old-plugins' ) ) {
				/*
				 * warning: no plugins selected.
				 * No `add_fix_message()`, we need to change the status from warning to cantfix if both lists have no selection.
				 */
				return 101;
			}
			// cantfix: no plugins selected.
			return $this->add_fix_message( 304 );
		}

		// Get filesystem.
		$wp_filesystem = secupress_get_filesystem();
		//Get the base plugin folder
		$plugins_dir = $wp_filesystem->wp_plugins_dir();

		if ( empty( $plugins_dir ) ) {
			// cantfix: plugins dir not located.
			return $this->add_fix_message( 302 );
		}

		$plugins_dir = trailingslashit( $plugins_dir );

		$plugin_translations = wp_get_installed_translations( 'plugins' );

		ob_start();

		// Deactivate
		deactivate_plugins( array_keys( $selected_plugins ) );

		$deleted_plugins = array();

		foreach ( $selected_plugins as $plugin_file => $plugin_data ) {
			// Run Uninstall hook
			if ( is_uninstallable_plugin( $plugin_file ) ) {
				uninstall_plugin( $plugin_file );
			}

			/**
			 * Fires immediately before a plugin deletion attempt.
			 *
			 * @since 1.0
			 * @since WP 4.4.0
			 *
			 * @param string $plugin_file Plugin file name.
			 */
			do_action( 'delete_plugin', $plugin_file );

			$this_plugin_dir = trailingslashit( dirname( $plugins_dir . $plugin_file ) );

			// If plugin is in its own directory, recursively delete the directory.
			if ( strpos( $plugin_file, '/' ) && $this_plugin_dir !== $plugins_dir ) { // base check on if plugin includes directory separator AND that its not the root plugin folder.
				$deleted = $wp_filesystem->delete( $this_plugin_dir, true );
			}
			else {
				$deleted = $wp_filesystem->delete( $plugins_dir . $plugin_file );
			}

			/**
			 * Fires immediately after a plugin deletion attempt.
			 *
			 * @since 1.0
			 * @since WP 4.4.0
			 *
			 * @param string $plugin_file Plugin file name.
			 * @param bool   $deleted     Whether the plugin deletion was successful.
			 */
			do_action( 'deleted_plugin', $plugin_file, $deleted );

			if ( $deleted ) {
				$deleted_plugins[ $plugin_file ] = 1;

				// Remove language files, silently.
				$plugin_slug = dirname( $plugin_file );
				if ( '.' !== $plugin_slug && ! empty( $plugin_translations[ $plugin_slug ] ) ) {
					$translations = $plugin_translations[ $plugin_slug ];

					foreach ( $translations as $translation => $data ) {
						$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.po' );
						$wp_filesystem->delete( WP_LANG_DIR . '/plugins/' . $plugin_slug . '-' . $translation . '.mo' );
					}
				}
			}
		}

		ob_end_clean();

		// Everything's deleted, no plugins left.
		if ( ! array_diff_key( $bad_plugins, $deleted_plugins ) ) {
			// good
			if ( $has_plugins_to_deactivate ) {
				$this->add_fix_message( 3 );
			} else {
				$this->add_fix_message( 2 );
			}
		}
		// All selected plugins deleted.
		elseif ( ! array_diff_key( $deleted_plugins, $selected_plugins ) ) {
			// "partial": some plugins still need to be deleted.
			$this->add_fix_message( 102, array( count( $selected_plugins ) ) );
		}
		// No plugins deleted.
		elseif ( ! $deleted_plugins ) {
			// bad
			$this->add_fix_message( 203, array( count( $bad_plugins ) ) );
		}
		// Some plugins could not be deleted.
		else {
			// cantfix
			$not_removed = array_diff_key( $selected_plugins, $deleted_plugins );
			$not_removed = array_map( 'strip_tags', $not_removed );
			$not_removed = array_map( 'esc_html', $not_removed );
			$this->add_fix_message( 103, array( count( $not_removed ), $not_removed ) );
		}

		// Force refresh of plugin update information.
		if ( $deleted_plugins && $current = get_site_transient( 'update_plugins' ) ) {
			$current->response = array_diff_key( $current->response, $deleted_plugins );
			set_site_transient( 'update_plugins', $current );
		}
	}


	protected function manual_deactivate( $bad_plugins, $has_plugins_to_delete ) {
		if ( ! $bad_plugins ) {
			if ( $this->is_network_admin() ) {
				// Remove all previously stored messages for sub-sites.
				$this->set_empty_data_for_subsites();
			}
			// good
			return $this->add_fix_message( 1 );
		}

		// Get the list of plugins to deactivate.
		$selected_plugins = ! empty( $_POST['secupress-fix-deactivate-bad-old-plugins'] ) && is_array( $_POST['secupress-fix-deactivate-bad-old-plugins'] ) ? array_filter( $_POST['secupress-fix-deactivate-bad-old-plugins'] ) : array();
		$selected_plugins = $selected_plugins ? array_fill_keys( $selected_plugins, 1 ) : array();
		$selected_plugins = $selected_plugins ? array_intersect_key( $bad_plugins, $selected_plugins ) : array();

		if ( ! $selected_plugins ) {
			if ( $this->is_network_admin() ) {
				// Remove all previously stored messages for sub-sites.
				$this->set_empty_data_for_subsites();
			}

			if ( $this->has_fix_action_part( 'delete-bad-old-plugins' ) ) {
				/*
				 * warning: no plugins selected.
				 * No `add_fix_message()`, we need to change the status from warning to cantfix if both lists have no selection.
				 */
				return 104;
			}
			// cantfix: no plugins selected.
			return $this->add_fix_message( 304 );
		}

		// In the network admin we disable nothing. We only store the selected plugins for later use in the sub-sites scans page.
		if ( $this->is_network_admin() ) {
			$active_subsites_plugins = get_site_option( 'secupress_active_plugins' );

			foreach ( $active_subsites_plugins as $site_id => $active_subsite_plugins ) {
				$data = array_intersect_key( $selected_plugins, $active_subsite_plugins );

				if ( $data ) {
					$data = array( count( $data ), $data );
					// Add a scan message for each listed sub-site.
					$this->add_subsite_message( 204, $data, 'scan', $site_id );
				} else {
					$this->set_empty_data_for_subsite( $site_id );
				}
			}
			// cantfix
			return $this->add_fix_message( 303 );
		}

		// In a sub-site, deactivate plugins.
		ob_start();
		deactivate_plugins( array_keys( $selected_plugins ) );
		ob_end_clean();

		// Try to see if everything is fine.
		$site_id        = get_current_blog_id();
		$active_plugins = get_site_option( 'secupress_active_plugins' );
		$active_plugins = isset( $active_plugins[ $site_id ] ) ? $active_plugins[ $site_id ] : array();

		// Everything's deactivated, no plugins left.
		if ( ! array_intersect_key( $bad_plugins, $active_plugins ) ) {
			// good
			$this->add_fix_message( 4 );
		}
		// All selected plugins deactivated.
		elseif ( ! array_intersect_key( $active_plugins, $selected_plugins ) ) {
			// "partial": some plugins still need to be deactivated.
			$this->add_fix_message( 105, array( count( $selected_plugins ) ) );
		}
		// Some plugins could not be deactivated.
		else {
			$selected_plugins_still_active = array_intersect_key( $active_plugins, $selected_plugins );
			$deactivated_plugins = array_diff_key( $selected_plugins, $selected_plugins_still_active );

			// No plugins deactivated.
			if ( ! $deactivated_plugins ) {
				// bad
				$this->add_fix_message( 205, array( count( $bad_plugins ) ) );
			} else {
				// cantfix
				$selected_plugins_still_active = array_intersect_key( $bad_plugins, $selected_plugins_still_active );
				$selected_plugins_still_active = array_map( 'strip_tags', $selected_plugins_still_active );
				$selected_plugins_still_active = array_map( 'esc_html', $selected_plugins_still_active );
				$this->add_fix_message( 106, array( count( $selected_plugins_still_active ), $selected_plugins_still_active ) );
			}
		}
	}


	protected function get_fix_action_template_parts() {
		$plugins = $this->get_installed_plugins_to_remove();
		$out     = array(
			'delete-bad-old-plugins'     => static::get_messages( 1 ),
			'deactivate-bad-old-plugins' => static::get_messages( 1 ),
		);

		if ( $plugins['to_delete'] ) {
			$form  = '<h4 id="secupress-fix-bad-old-plugins">' . __( 'Checked plugins will be deleted:', 'secupress' ) . '</h4>';
			$form .= '<fieldset aria-labelledby="secupress-fix-bad-old-plugins" class="secupress-boxed-group">';

				foreach ( $plugins['to_delete'] as $plugin_file => $plugin_name ) {
					$is_symlinked = secupress_is_plugin_symlinked( $plugin_file );
					$plugin_name  = esc_html( strip_tags( $plugin_name ) );

					$form .= '<input type="checkbox" id="secupress-fix-delete-bad-old-plugins-' . sanitize_html_class( $plugin_file ) . '" name="secupress-fix-delete-bad-old-plugins[]" value="' . esc_attr( $plugin_file ) . '" ' . ( $is_symlinked ? 'disabled="disabled"' : 'checked="checked"' ) . '/> ';
					$form .= '<label for="secupress-fix-delete-bad-old-plugins-' . sanitize_html_class( $plugin_file ) . '">';
						if ( $is_symlinked ) {
							$form .= '<del>' . $plugin_name . '</del> <span class="description">(' . __( 'symlinked', 'secupress' ) . ')</span>';
						} else {
							$form .= $plugin_name;
						}
					$form .= '</label><br/>';
				}

			$form .= '</fieldset>';
			$out['delete-bad-old-plugins'] = $form;
		}

		if ( $plugins['to_deactivate'] ) {
			if ( $this->is_for_current_site() ) {
				$form  = '<h4 id="secupress-fix-bad-old-plugins-deactiv">' . __( 'Checked plugins will be deactivated:', 'secupress' ) . '</h4>';
			} else {
				$form  = '<h4 id="secupress-fix-bad-old-plugins-deactiv">' . __( 'Checked plugins will be deactivated by Administrators:', 'secupress' ) . '</h4>';
				$form .= '<span class="description">' . _n( 'The following plugin is activated in some of your sites and must be deactivated first. Administrators will be asked to do so.', 'The following plugins are activated in some of your sites and must be deactivated first. Administrators will be asked to do so.', count( $plugins['to_deactivate'] ), 'secupress' ) . '</span>';
			}

			$form .= '<fieldset aria-labelledby="secupress-fix-bad-old-plugins-deactiv" class="secupress-boxed-group">';

				foreach ( $plugins['to_deactivate'] as $plugin_file => $plugin_name ) {
					$is_symlinked = secupress_is_plugin_symlinked( $plugin_file );
					$plugin_name  = esc_html( strip_tags( $plugin_name ) );

					$form .= '<input type="checkbox" id="secupress-fix-deactivate-bad-old-plugins-' . sanitize_html_class( $plugin_file ) . '" name="secupress-fix-deactivate-bad-old-plugins[]" value="' . esc_attr( $plugin_file ) . '" ' . ( $is_symlinked ? 'disabled="disabled"' : 'checked="checked"' ) . '/> ';
					$form .= '<label for="secupress-fix-deactivate-bad-old-plugins-' . sanitize_html_class( $plugin_file ) . '">';
						if ( $is_symlinked ) {
							$form .= '<del>' . $plugin_name . '</del> <span class="description">(' . __( 'symlinked', 'secupress' ) . ')</span>';
						} else {
							$form .= $plugin_name;
						}
					$form .= '</label><br/>';
				}

			$form .= '</fieldset>';
			$out['deactivate-bad-old-plugins'] = $form;
		}

		return $out;
	}


	/*--------------------------------------------------------------------------------------------*/
	/* TOOLS ==================================================================================== */
	/*--------------------------------------------------------------------------------------------*/

	// All plugins to remove.

	final protected function get_installed_plugins_to_remove() {
		$plugins = array();

		// Plugins no longer in directory.
		$tmp = static::get_installed_plugins_no_longer_in_directory( true );
		if ( $tmp ) {
			$plugins = $tmp;
		}

		// Plugins not updated in over 2 years.
		$tmp = static::get_installed_plugins_over_2_years( true );
		if ( $tmp ) {
			$plugins = array_merge( $plugins, $tmp );
		}

		// Byebye Dolly.
		$tmp = $this->has_hello_dolly();
		if ( $tmp ) {
			$plugins = array_merge( $plugins, $tmp );
		}

		return $this->separate_deletable_from_deactivable( $plugins );
	}


	// Plugins no longer in directory - http://plugins.svn.wordpress.org/no-longer-in-directory/trunk/

	final protected static function get_installed_plugins_no_longer_in_directory( $for_fix = false ) {
		$plugins_list_file = 'data/no-longer-in-directory-plugin-list.data';
		return static::get_installed_bad_plugins( $plugins_list_file, $for_fix );
	}


	// Plugins not updated in over 2 years - http://plugins.svn.wordpress.org/no-longer-in-directory/trunk/

	final protected static function get_installed_plugins_over_2_years( $for_fix = false ) {
		$plugins_list_file = 'data/not-updated-in-over-two-years-plugin-list.data';
		return static::get_installed_bad_plugins( $plugins_list_file, $for_fix );
	}


	// Return an array of plugin names like `array( $path => $name, $path => $name )`.

	final protected static function get_installed_bad_plugins( $plugins_list_file, $for_fix = false ) {
		static $whitelist;

		$plugins_list_file = SECUPRESS_INC_PATH . $plugins_list_file;

		if ( ! is_readable( $plugins_list_file ) ) {
			$args =  array( '<code>' . str_replace( ABSPATH, '', $plugins_list_file ) . '</code>' );
			// warning
			if ( $for_fix ) {
				$this->add_fix_message( 100, $args );
			} else {
				$this->add_message( 100, $args );
			}
			return false;
		}

		// Deal with the white list.
		if ( ! isset( $whitelist ) ) {
			$whitelist_file = SECUPRESS_INC_PATH . 'data/whitelist-plugin-list.data';

			if ( ! is_readable( $whitelist_file ) ) {
				$args = array( '<code>' . str_replace( ABSPATH, '', $whitelist_file ) . '</code>' );
				// warning
				if ( $for_fix ) {
					$this->add_fix_message( 100, $args );
				} else {
					$this->add_message( 100, $args );
				}
				$whitelist_file = false;
				return false;
			}

			$whitelist = file( $whitelist_file );
			$whitelist = array_map( 'trim', $whitelist );
			$whitelist = array_flip( $whitelist );
		}

		if ( ! $whitelist ) {
			// No need to trigger a new warning, already done.
			return false;
		}

		$plugins_by_path = get_plugins();

		$not_in_directory = file( $plugins_list_file );
		$not_in_directory = array_map( 'trim', $not_in_directory );
		$not_in_directory = array_flip( $not_in_directory );
		$not_in_directory = array_diff_key( $not_in_directory, $whitelist );
		$bad_plugins      = array();

		foreach ( $plugins_by_path as $plugin_path => $plugin_data ) {
			if ( preg_match( '/([^\/]+)\//', $plugin_path, $matches ) ) {
				if ( isset( $not_in_directory[ $matches[1] ] ) ) {
					$bad_plugins[ $plugin_path ] = $plugin_data['Name'];
				}
			}
		}

		return $bad_plugins;
	}


	// Dolly are you here?

	final protected function has_hello_dolly() {
		$plugins = array();

		// Sub-sites don't need to delete Dolly.
		if ( ! $this->is_for_current_site() && file_exists( WP_PLUGIN_DIR . '/hello.php' ) ) {
			$plugins['hello.php'] = '<code>Hello Dolly</code> (autoinstalled version)';
		}

		return $plugins;
	}


	/*
	 * From a list of plugins, separate them in 2: those that can be deleted and those that can be deactivated first (from a sub-site).
	 *
	 * @since 1.0
	 */
	final protected function separate_deletable_from_deactivable( $plugins ) {
		static $active_subsites_plugins;
		static $subsite_plugins;

		if ( ! $plugins ) {
			return array(
				'to_delete'     => array(),
				'to_deactivate' => array(),
				'count'         => 0,
			);
		}

		// Network: plugins activated in sub-sites must be deactivated in each sub-site first.
		if ( $this->is_network_admin() ) {
			if ( ! isset( $active_subsites_plugins ) ) {
				$active_subsites_plugins_tmp = get_site_option( 'secupress_active_plugins' );
				$active_subsites_plugins     = array();

				foreach ( $active_subsites_plugins_tmp as $site_id => $active_subsite_plugins ) {
					$active_subsites_plugins = array_merge( $active_subsites_plugins, $active_subsite_plugins );
				}

				// Let's act like Hello Dolly is not enabled in any sub-site, so we won't need Administrators aproval and we'll be able to delete it directly.
				unset( $active_subsites_plugins_tmp, $active_subsites_plugins['hello.php'] );
				$active_subsites_plugins = array_diff_key( $active_subsites_plugins, get_site_option( 'active_sitewide_plugins' ) );
			}

			$out = array(
				// Plugins that are network activated or not activated in any site can be deleted.
				'to_delete'     => array_diff_key( $plugins, $active_subsites_plugins ),
				// Plugins activated in subsites.
				'to_deactivate' => array_intersect_key( $plugins, $active_subsites_plugins ),
			);
		}
		// Sub-site: limit to plugins activated in this sub-site.
		elseif ( $this->is_for_current_site() ) {
			if ( ! isset( $subsite_plugins ) ) {
				$site_id         = get_current_blog_id();
				$subsite_plugins = get_site_option( 'secupress_active_plugins' );
				$subsite_plugins = ! empty( $subsite_plugins[ $site_id ] ) ? $subsite_plugins[ $site_id ] : array();
			}

			$out = array(
				// In a sub-site we don't delete any plugin.
				'to_delete'     => array(),
				// We only deactivate them.
				'to_deactivate' => array_intersect_key( $plugins, $subsite_plugins ),
			);
		}
		// Not a multisite.
		else {
			$out = array(
				// All plugins can be deleted.
				'to_delete'     => $plugins,
				// No need to deactivate anything.
				'to_deactivate' => array(),
			);
		}

		$out['count'] = count( $out['to_delete'] ) + count( $out['to_deactivate'] );
		return $out;
	}
}
