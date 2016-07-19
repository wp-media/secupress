<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );
?>
<div class="secupress-step-content-header secupress-flex secupress-flex-spaced">

	<?php
	$main_button =
		'<button class="secupress-button secupress-button-tertiary secupress-button-autofix shadow" type="button">
			<span class="icon">
				<i class="icon-wrench" aria-hidden="true"></i>
			</span>
			<span class="text">' . __( 'Fix all checked issues', 'secupress') . '</span>
		</button>
		<a href="' . esc_url( secupress_admin_url( 'scanners' ) ) . '&amp;step=3" class="secupress-button shadow light hidden">
			<span class="icon">
				<i class="icon-cross" aria-hidden="true"></i>
			</span>
			<span class="text">' . __( 'Ignore this step', 'secupress') . '</span>
		</a>';
	?>

	<p class="secupress-step-title"><?php _e( 'Checked items will be automatically fixed', 'secupress' ); ?></p>
	<p>
		<?php echo $main_button; ?>
	</p>
</div>

<div id="secupress-tests" class="secupress-tests">
	<?php
	$modules = secupress_get_modules();

	foreach ( $secupress_tests as $module_name => $class_name_parts ) {
		// `lower_class_name_part` => `Camel_Class_Name_Part`.
		$class_name_parts          = array_combine( array_map( 'strtolower', $class_name_parts ), $class_name_parts );
		// Step 2 = keep only bad scan results.
		$class_name_parts          = array_intersect_key( $class_name_parts, $bad_scans );
		$section_has_items         = false;
		$section_has_fixable_items = false;

		foreach ( $class_name_parts as $option_name => $class_name_part ) {
			if ( ! file_exists( secupress_class_path( 'scan', $class_name_part ) ) ) {
				unset( $class_name_parts[ $option_name ] );
				continue;
			}

			secupress_require_class( 'scan', $class_name_part );

			$class_name   = 'SecuPress_Scan_' . $class_name_part;
			$current_test = $class_name::get_instance();

			// Step 2 = keep only auto fixable items and pro items.
			if ( false === $current_test->is_fixable() || $current_test->need_manual_fix() ) {
				unset( $class_name_parts[ $option_name ] );
				continue;
			}

			$section_has_items = true;
			if ( ! $section_has_fixable_items && ( true === $current_test->is_fixable() || 'pro' === $current_test->is_fixable() && secupress_is_pro() ) ) {
				$section_has_fixable_items = true;
			}
		}

		// This section has no items.
		if ( ! $section_has_items ) {
			continue;
		}

		$module_title   = ! empty( $modules[ $module_name ]['title'] )              ? $modules[ $module_name ]['title']              : '';
		$module_summary = ! empty( $modules[ $module_name ]['summaries']['small'] ) ? $modules[ $module_name ]['summaries']['small'] : '';
		?>
		<div class="secupress-scans-group secupress-group-<?php echo $module_name; ?>">
			<div class="secupress-sg-header secupress-flex secupress-flex-spaced">

				<div class="secupress-sgh-name">
					<i class="icon-user-login" aria-hidden="true"></i>
					<p class="secupress-sgh-title"><?php echo $module_title; ?></p>
					<p class="secupress-sgh-description"><?php echo $module_summary; ?></p>
				</div>

				<div class="secupress-sgh-actions secupress-flex">
					<?php if ( $section_has_fixable_items ) : ?>
						<label class="text hide-if-no-js" for="secupress-toggle-check-<?php echo $module_name; ?>">
							<span class="label-before-text"><?php _e( 'Toggle group check', 'secupress' ); ?></span>
							<input type="checkbox" id="secupress-toggle-check-<?php echo $module_name; ?>" class="secupress-checkbox secupress-toggle-check" checked="checked"/>
							<span class="label-text"></span>
						</label>
					<?php endif; ?>
				</div>

			</div><!-- .secupress-sg-header -->

			<div id="secupress-group-content-<?php echo $module_name; ?>" class="secupress-sg-content">
				<?php
				foreach ( $class_name_parts as $option_name => $class_name_part ) {
					$class_name   = 'SecuPress_Scan_' . $class_name_part;
					$current_test = $class_name::get_instance();
					$referer      = urlencode( esc_url_raw( self_admin_url( 'admin.php?page=' . SECUPRESS_PLUGIN_SLUG . '_scanners&step=2#' . $class_name_part ) ) );
					$needs_pro    = 'pro' === $current_test->is_fixable() && ! secupress_is_pro();

					// Scan.
					$scanner        = isset( $scanners[ $option_name ] ) ? $scanners[ $option_name ] : array();
					$scan_status    = ! empty( $scanner['status'] ) ? $scanner['status'] : 'notscannedyet';
					$scan_nonce_url = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_scanner&test=' . $class_name_part . '&_wp_http_referer=' . $referer ), 'secupress_scanner_' . $class_name_part );

					// Fix.
					$fix           = ! empty( $fixes[ $option_name ] ) ? $fixes[ $option_name ] : array();
					$fix_nonce_url = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_fixit&test=' . $class_name_part . '&_wp_http_referer=' . $referer ), 'secupress_fixit_' . $class_name_part );

					// Row css class.
					$row_css_class  = ' status-' . sanitize_html_class( $scan_status );
					$row_css_class .= ! empty( $fix['has_action'] ) ? ' status-hasaction' : '';
					$row_css_class .= ! empty( $fix['status'] ) && empty( $fix['has_action'] ) ? ' has-fix-status' : ' no-fix-status';
					$row_css_class .= $needs_pro ? ' secupress-only-pro not-fixable' : '';
					?>
					<div class="secupress-item-all secupress-item-<?php echo $class_name_part; ?> type-all status-all <?php echo $row_css_class; ?>" id="<?php echo $class_name_part; ?>" data-scan-url="<?php echo esc_url( $scan_nonce_url ); ?>">
						<div class="secupress-flex">

							<p class="secupress-item-status secupress-status-mini">
								<span class="secupress-dot-bad"></span>
							</p>

							<p class="secupress-item-title"><?php echo wp_kses( $current_test->more_fix, $allowed_tags ); ?></p>

							<p class="secupress-row-actions">
								<?php
								if ( $needs_pro ) {
									// It is fixable with the pro version but the free version is used.
									?>
									<span class="secupress-get-pro-version">
										<?php printf( __( 'Available in <a href="%s">Pro Version</a>', 'secupress' ), esc_url( secupress_admin_url( 'get_pro' ) ) ); ?>
									</span>
									<?php
								} else {
									// It can be fixed.
									?>
									<input type="checkbox" id="secupress-item-<?php echo $class_name_part; ?>" class="secupress-checkbox secupress-row-check hide-if-no-js" checked="checked"/>
									<label for="secupress-item-<?php echo $class_name_part; ?>" class="label-text hide-if-no-js">
										<span class="screen-reader-text"><?php _e( 'Auto-fix this item', 'secupress' ); ?></span>
									</label>
									<a class="secupress-button-primary secupress-button-mini hide-if-js secupress-fixit<?php echo $current_test->is_delayed_fix() ? ' delayed-fix' : ''; ?>" href="<?php echo esc_url( $fix_nonce_url ); ?>">
										<span class="icon" aria-hidden="true">
											<i class="icon-shield"></i>
										</span>
										<span class="text">
											<?php _e( 'Fix it', 'secupress' ); ?>
										</span>
									</a>
									<?php
								}
								?>
							</p>

						</div><!-- .secupress-flex -->
					</div><!-- .secupress-item-all -->
				<?php
			}
			?>
			</div><!-- .secupress-sg-content -->
		</div><!-- .secupress-scans-group -->
		<?php
	}
	?>
</div><!-- .secupress-tests -->

<div class="secupress-step-content-footer secupress-flex secupress-flex-top secupress-flex-spaced">
	<span><?php //flex col placeholder ?></span>
	<p>
		<?php echo $main_button; ?>
	</p>
</div>
