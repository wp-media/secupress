// Global vars =====================================================================================
var SecuPress = {
	doingAjax:           false,
	deletedRowColor:     "#FF9966",
	addedRowColor:       "#CCEEBB",
	confirmSwalDefaults: {
		title:               window.l10nmodules.confirmTitle,
		cancelButtonText:    window.l10nmodules.confirmCancel,
		type:                "warning",
		showCancelButton:    true,
		confirmButtonColor:  "#DD6B55",
		showLoaderOnConfirm: true,
		closeOnConfirm:      false,
		allowOutsideClick:   true
	}
};


// Tools ===========================================================================================
// Shorthand to tell if a modifier key is pressed.
function secupressHasModifierKey( e ) {
	return e.altKey || e.ctrlKey || e.metaKey || e.shiftKey;
}
// Shorthand to tell if the pressed key is Space or Enter.
function secupressIsSpaceOrEnterKey( e ) {
	return ( e.which === 13 || e.which === 32 ) && ! secupressHasModifierKey( e );
}
// Shorthand to tell if the pressed key is Space.
function secupressIsSpaceKey( e ) {
	return e.which === 32 && ! secupressHasModifierKey( e );
}
// Shorthand to tell if the pressed key is Enter.
function secupressIsEnterKey( e ) {
	return e.which === 13 && ! secupressHasModifierKey( e );
}
// Shorthand to tell if the pressed key is Escape.
function secupressIsEscapeKey( e ) {
	return e.which === 27 && ! secupressHasModifierKey( e );
}

/**
 * Disable a button that calls an ajax action.
 * - Add a "working" class, so that the spinner can be displayed.
 * - Add a "aria-disabled" attribute.
 * - If it's a link: add a "disabled" attribute. If it's a button or input: add a "disabled" attribute.
 * - Change the button text if a "data-loading-i18n" attribute is present.
 * - Use `wp.a11y.speak` if a text is provided.
 * - Set `SecuPress.doingAjax` to `true`.
 *
 * @since 1.0
 *
 * @param (object) $button jQuery object of the button.
 * @param (string) speak   Text for `wp.a11y.speak`.
 */
function secupressDisableAjaxButton( $button, speak ) {
	var text     = $button.attr( "data-loading-i18n" ),
		isButton = $button.get( 0 ).nodeName.toLowerCase(),
		value;

	SecuPress.doingAjax = true;
	isButton = "button" === isButton || "input" === isButton;

	if ( undefined !== text && text ) {
		if ( isButton ) {
			value = $button.val();
			if ( undefined !== value && value ) {
				$button.val( text );
			} else {
				$button.text( text );
			}
		} else {
			$button.text( text );
		}
	}

	if ( isButton ) {
		$button.addClass( "working" ).attr( { "disabled": "disabled", "aria-disabled": "true" } );
	} else {
		$button.addClass( "disabled working" ).attr( "aria-disabled", "true" );
	}

	if ( wp.a11y && wp.a11y.speak && undefined !== speak && speak ) {
		wp.a11y.speak( speak );
	}
}

/**
 * Enable a button that calls an ajax action.
 * - Remove the "working" class, so that the spinner can be hidden again.
 * - Remove the "aria-disabled" attribute.
 * - If it's a link: remove the "disabled" attribute. If it's a button or input: remove the "disabled" attribute.
 * - Change the button text if a "data-original-i18n" attribute is present.
 * - Use `wp.a11y.speak` if a text is provided.
 * - Set `SecuPress.doingAjax` to `false`.
 *
 * @since 1.0
 *
 * @param (object) $button jQuery object of the button.
 * @param (string) speak   Text for `wp.a11y.speak`.
 */
function secupressEnableAjaxButton( $button, speak ) {
	var text, isButton, value;

	if ( undefined !== $button && $button && $button.length ) {
		text     = $button.attr( "data-original-i18n" );
		isButton = $button.get( 0 ).nodeName.toLowerCase();
		isButton = "button" === isButton || "input" === isButton;

		if ( undefined !== text && text ) {
			if ( isButton ) {
				value = $button.val();
				if ( undefined !== value && value ) {
					$button.val( text );
				} else {
					$button.text( text );
				}
			} else {
				$button.text( text );
			}
		}

		if ( isButton ) {
			$button.removeClass( "working" ).removeAttr( "disabled aria-disabled" );
		} else {
			$button.removeClass( "disabled working" ).removeAttr( "aria-disabled" );
		}
	}

	if ( wp.a11y && wp.a11y.speak && undefined !== speak && speak ) {
		wp.a11y.speak( speak );
	}

	SecuPress.doingAjax = false;
}

/**
 * Before doing an ajax call, do some tests:
 * - test if we have an URL.
 * - if the event is "keyup", test if the key is the Space bar or Enter.
 * - test another ajax call is not running.
 * Also prevent default event.
 *
 * @since 1.0
 *
 * @param (string) href The URL.
 * @param (object) e    The jQuery event object.
 *
 * @return (bool|string) False on failure, the ajax URL on success.
 */
function secupressPreAjaxCall( href, e ) {
	if ( undefined === href || ! href ) {
		return false;
	}

	if ( "keyup" === e.type && ! secupressIsSpaceOrEnterKey( e ) ) {
		return false;
	}

	if ( SecuPress.doingAjax ) {
		return false;
	}

	e.preventDefault();

	return href.replace( "admin-post.php", "admin-ajax.php" );
}

/**
 * Display an error message via Sweet Alert and re-enable the button.
 *
 * @since 1.0
 *
 * @param (object) $button jQuery object of the button.
 * @param (string) text    Text for swal + `wp.a11y.speak`.
 */
function secupressDisplayAjaxError( $button, text ) {
	if ( undefined === text ) {
		text = window.l10nmodules.unknownError;
	}

	swal( {
		title:             window.l10nmodules.error,
		text:              text,
		type:              "error",
		allowOutsideClick: true
	} );

	secupressEnableAjaxButton( $button, text );
}


// Roles: at least one role must be chosen. ========================================================
(function($, d, w, undefined) {

	if ( "function" === typeof document.createElement( "input" ).checkValidity ) {
		$( ".affected-role-row :checkbox" ).on( "click", function() {
			this.setCustomValidity( '' );

			if ( 0 === $( '[name="' + this.name + '"]:checked' ).length ) {
				this.setCustomValidity( w.l10nmodules.selectOneRoleMinimum );
				$( "#secupress-module-form-settings [type='submit']" ).first().trigger( "click" );
			}
		} );
	} else {
		$( ".affected-role-row p.warning" ).removeClass( "hide-if-js" );
	}

} )(jQuery, document, window);


// Radioboxes: 1 checked at most. ==================================================================
(function($, d, w, undefined) {

	$( ".radiobox" ).on( "click", function() {
		$( '[name="' + this.name + '"]:checked' ).not( this ).removeAttr( "checked" ).trigger( "change" );
	} );

} )(jQuery, document, window);


// Show/Hide panels, depending on some other field value. ==========================================
(function($, d, w, undefined) {

	var $depends   = $( "#wpbody-content" ).find( '[class*="depends-"]' ), // Rows that will open/close.
		dependsIds = {}, // IDs of the checkboxes, radios, etc that will trigger a panel open/close.
		dependsRadioNames = {}; // names of the radios.

	$depends.each( function() {
		var classes = $( this ).attr( "class" ).replace( /^\s+|\s+$/g, "" ).replace( /\s+/, " " ).split( " " );

		$.each( classes, function( i, id ) {
			var $input,        // input element
				inputTagName,  // input tag name
				inputTypeAttr, // input type
				inputNameAttr, // input name
				inputIsValid = false;

			// If the class is not a "depends-XXXXXX", bail out.
			if ( 0 !== id.indexOf( "depends-" ) ) {
				return true;
			}

			id = id.substr( 8 );

			// If the ID was previously delt with, bail out.
			if ( "undefined" !== typeof dependsIds[ id ] ) {
				return true;
			}
			dependsIds[ id ] = 1;

			$input = $( "#" + id );

			// Uh? The input doesn't exist?
			if ( ! $input.length ) {
				return true;
			}

			// We need to know which type of input we deal with, the way we deal with it is not the same.
			inputTagName = $input.get( 0 ).nodeName.toLowerCase();

			if ( "input" === inputTagName ) {
				inputTypeAttr = $input.attr( "type" ).toLowerCase();

				if ( "checkbox" === inputTypeAttr || "radio" === inputTypeAttr ) {
					inputIsValid = true;
				}

			} else if ( "button" === inputTagName ) {
				inputIsValid = true;
			}

			// Only checkboxes, radios groups and buttons so far.
			if ( ! inputIsValid ) {
				return true;
			}

			// Attach the events.
			// Buttons
			if ( "button" === inputTagName ) {

				$input.on( "click", function() {
					var id = $( this ).attr( "id" );
					$( ".depends-" + id ).toggle( 250 );
				} );

			}
			// Radios
			else if ( "radio" === inputTypeAttr ) {

				inputNameAttr = $input.attr( "name" );

				// If the name was previously delt with, bail out.
				if ( "undefined" !== typeof dependsRadioNames[ inputNameAttr ] ) {
					return true;
				}
				dependsRadioNames[ inputNameAttr ] = 1;

				$( '[name="' + inputNameAttr + '"]' ).on( "change init.secupress", function( e ) {
					var $this   = $( this ),
						$toShow = $( ".depends-" + $this.attr( "id" ) ), // Elements to show.
						toHide  = [], // Elements to hide.
						tempo   = "init" === e.type && "secupress" === e.namespace ? 0 : 250; // On page load, no animation.

					// The radio is checked: open the desired boxes if not visible.
					$toShow.not( ":visible" ).trigger( "secupressbeforeshow" ).show( tempo, function() {
						$( this ).trigger( "secupressaftershow" );
					} );

					// Find boxes to hide.
					$( '[name="' + $this.attr( "name" ) + '"]' ).not( $this ).each( function() {
						toHide.push( ".depends-" + $( this ).attr( "id" ).replace( /^\s+|\s+$/g, "" ) );
					} );

					$( toHide.join( "," ) ).not( $toShow ).filter( ":visible" ).trigger( "secupressbeforehide" ).hide( tempo, function() {
						$( this ).trigger( "secupressafterhide" );
					} );
				} ).filter( ":checked" ).trigger( "init.secupress" );

			}
			// Checkboxes
			else if ( "checkbox" === inputTypeAttr ) {

				$input.on( "change init.secupress", function( e ) {
					var $this  = $( this ),
						id     = $this.attr( "id" ),
						$elems = $( ".depends-" + id ), // Elements to hide or show.
						tempo  = "init" === e.type && "secupress" === e.namespace ? 0 : 250; // On page load, no animation.

					// Uh? No rows?
					if ( ! $elems.length ) {
						return true;
					}

					// The checkbox is checked: open if not visible.
					if ( $this.is( ":checked" ) ) {
						$elems.not( ":visible" ).trigger( "secupressbeforeshow" ).show( tempo, function() {
							$( this ).trigger( "secupressaftershow" );
						} );
					}
					// The checkbox is not checked: close if visible and no other checkboxes that want this row to be open is checked.
					else {
						$elems.filter( ":visible" ).each( function() {
							var $this   = $( this ),
								classes = $this.attr( "class" ).replace( /^\s+|\s+$/g, "" ).replace( /\s+/, " " ).split( " " ),
								others  = []; // Other checkboxes

							$.each( classes, function( i, v ) {
								if ( "depends-" + id !== v && 0 === v.indexOf( "depends-" ) ) {
									others.push( "#" + v.substr( 8 ) + ":checked" );
								}
							} );

							others = others.join( "," );

							if ( ! $( others ).length ) {
								$this.trigger( "secupressbeforehide" ).hide( tempo, function() {
									$( this ).trigger( "secupressafterhide" );
								} );
							}
						} );
					}
				} ).filter( ":checked" ).trigger( "init.secupress" );

			}
		} );
	} );

} )(jQuery, document, window);


// Action/404 Logs =================================================================================
(function($, d, w, undefined) {

	if ( ! w.l10nLogs ) {
		return;
	}

	// Delete all logs.
	function secupressDeleteAllLogs( $button, href ) {
		secupressDisableAjaxButton( $button, w.l10nLogs.clearingText );

		$.getJSON( href )
		.done( function( r ) {
			if ( $.isPlainObject( r ) && r.success ) {
				swal.close();
				// Empty the list and add a "No Logs" text.
				$button.closest( "td" ).text( "" ).append( "<p><em>" + w.l10nLogs.noLogsText + "</em></p>" );

				secupressEnableAjaxButton( $button, w.l10nLogs.clearedText );
			} else {
				secupressDisplayAjaxError( $button, w.l10nLogs.clearImpossible );
			}
		} )
		.fail( function() {
			secupressDisplayAjaxError( $button );
		} );
	}

	// Delete one log.
	function secupressDeleteLog( $button, href ) {
		secupressDisableAjaxButton( $button, w.l10nLogs.deletingText );

		$.getJSON( href )
		.done( function( r ) {
			if ( $.isPlainObject( r ) && r.success ) {
				swal.close();
				// r.data contains the number of logs.
				if ( r.data ) {
					$( ".logs-count" ).text( r.data );

					$button.closest( "li" ).css( "backgroundColor", SecuPress.deletedRowColor ).hide( "normal", function() {
						$( this ).remove();
						SecuPress.doingAjax = false;
					} );
				} else {
					// Empty the list and add a "No Logs" text.
					$button.closest( "td" ).text( "" ).append( "<p><em>" + w.l10nLogs.noLogsText + "</em></p>" );
					SecuPress.doingAjax = false;
				}

				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( w.l10nLogs.deletedText );
				}
			} else {
				secupressDisplayAjaxError( $button, w.l10nLogs.deleteImpossible );
			}
		} )
		.fail( function() {
			secupressDisplayAjaxError( $button );
		} );
	}

	// Ajax call that clears logs.
	$( ".secupress-clear-logs" ).on( "click keyup", function( e ) {
		var $this = $( this ),
			href  = secupressPreAjaxCall( $this.attr( "href" ), e );

		if ( ! href ) {
			return false;
		}

		if ( "function" === typeof w.swal ) {
			swal(
				$.extend( {}, SecuPress.confirmSwalDefaults, {
					text:              w.l10nLogs.clearConfirmText,
					confirmButtonText: w.l10nLogs.clearConfirmButton
				} ),
				function () {
					secupressDeleteAllLogs( $this, href );
				}
			);
		} else if ( w.confirm( w.l10nmodules.confirmTitle + "\n" + w.l10nLogs.clearConfirmText ) ) {
			secupressDeleteAllLogs( $this, href );
		}
	} ).attr( "role", "button" ).removeAttr( "aria-disabled" );

	// Ajax call that delete a log.
	$( ".secupress-delete-log" ).on( "click keyup", function( e ) {
		var $this = $( this ),
			href  = secupressPreAjaxCall( $this.attr( "href" ), e );

		if ( ! href ) {
			return false;
		}

		if ( "function" === typeof w.swal ) {
			swal(
				$.extend( {}, SecuPress.confirmSwalDefaults, {
					text:              w.l10nLogs.deleteConfirmText,
					confirmButtonText: w.l10nLogs.deleteConfirmButton
				} ),
				function () {
					secupressDeleteLog( $this, href );
				}
			);
		} else if ( w.confirm( w.l10nmodules.confirmTitle + "\n" + w.l10nLogs.deleteConfirmText ) ) {
			secupressDeleteLog( $this, href );
		}
	} ).attr( "role", "button" ).removeAttr( "aria-disabled" );

	// Expand <pre> tags.
	$( ".secupress-code-chunk" )
		.prepend( '<button type="button" class="no-button secupress-expand-code"><span class="dashicons-before dashicons-visibility" aria-hidden="true"></span><span class="dashicons-before dashicons-hidden" aria-hidden="true"></span><span class="screen-reader-text">' + w.l10nLogs.expandCodeText + '</span></button>' )
		.children( ".secupress-expand-code" )
		.on( "click", function() {
			$( this ).parent().toggleClass( "secupress-code-chunk" );
		} );

} )(jQuery, document, window);


// Backups =========================================================================================
(function($, d, w, undefined) {

	if ( ! w.l10nmodules ) {
		return;
	}

	function secupressUpdateAvailableBackupCounter( r ) {
		$( "#secupress-available-backups" ).text( r.data.countText );
	}

	function secupressUpdateBackupVisibility() {
		if ( 0 === $( ".db-backup-row" ).length ) {
			$( "#form-delete-db-backups" ).hide();
			$( "#secupress-no-db-backups" ).show();
		} else {
			$( "#secupress-no-db-backups" ).hide();
			$( "#form-delete-db-backups" ).show();
		}
	}

	// Delete all backups.
	function secupressDeleteAllBackups( $button, href ) {
		secupressDisableAjaxButton( $button, w.l10nmodules.deletingAllText );

		$.getJSON( href )
		.done( function( r ) {
			if ( $.isPlainObject( r ) && r.success ) {
				swal.close();
				$button.closest( "form" ).find( "fieldset" ).text( "" );

				secupressUpdateBackupVisibility();
				secupressEnableAjaxButton( $button, w.l10nmodules.deletedAllText );
			} else {
				secupressDisplayAjaxError( $button, w.l10nmodules.deleteAllImpossible );
			}
		} )
		.fail( function() {
			secupressDisplayAjaxError( $button );
		} );
	}

	// Delete a backup.
	function secupressDeleteOneBackup( $button, href ) {
		secupressDisableAjaxButton( $button, w.l10nmodules.deletingOneText );

		$.getJSON( href )
		.done( function( r ) {
			if ( $.isPlainObject( r ) && r.success ) {
				swal.close();

				$button.closest( ".db-backup-row" ).css( "backgroundColor", SecuPress.deletedRowColor ).hide( "normal", function() {
					$( this ).remove();

					secupressUpdateAvailableBackupCounter( r );
					secupressUpdateBackupVisibility();
					SecuPress.doingAjax = false;
				} );

				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( w.l10nmodules.deletedOneText );
				}
			} else {
				secupressDisplayAjaxError( $button, w.l10nmodules.deleteOneImpossible );
			}
		} )
		.fail( function() {
			secupressDisplayAjaxError( $button );
		} );
	}

	// Do a DB backup.
	function secupressDoDbBackup( $button, href ) {
		secupressDisableAjaxButton( $button, w.l10nmodules.backupingText );

		$.post( href )
		.done( function( r ) {
			if ( $.isPlainObject( r ) && r.success ) {
				$( r.data.elemRow ).addClass( "hidden" ).css( "backgroundColor", SecuPress.addedRowColor ).prependTo( "#form-delete-db-backups fieldset" ).show( "normal", function() {
					$( this ).css( "backgroundColor", "" );
				} );

				secupressUpdateAvailableBackupCounter( r );
				secupressUpdateBackupVisibility();
				secupressEnableAjaxButton( $button, w.l10nmodules.backupedText );
			} else {
				secupressDisplayAjaxError( $button, w.l10nmodules.backupImpossible );
			}
		} )
		.fail( function() {
			secupressDisplayAjaxError( $button );
		} );
	}

	// Ajax call that delete all backups.
	$( "#submit-delete-db-backups" ).on( "click keyup", function( e ) {
		var $this = $( this ),
			href  = secupressPreAjaxCall( $this.closest( "form" ).attr( "action" ), e );

		if ( ! href ) {
			return false;
		}

		if ( "function" === typeof w.swal ) {
			swal(
				$.extend( {}, SecuPress.confirmSwalDefaults, {
					text:              w.l10nmodules.confirmDeleteBackups,
					confirmButtonText: w.l10nmodules.yesDeleteAll,
				} ),
				function () {
					secupressDeleteAllBackups( $this, href );
				}
			);
		} else if ( w.confirm( w.l10nmodules.confirmTitle + "\n" + w.l10nmodules.confirmDeleteBackups ) ) {
			secupressDeleteAllBackups( $this, href );
		}

	} ).removeAttr( "disabled aria-disabled" );


	// Ajax call that delete one Backup.
	$( "body" ).on( "click keyup", ".a-delete-backup", function( e ) {
		var $this = $( this ),
			href  = secupressPreAjaxCall( $this.attr( "href" ), e );

		if ( ! href ) {
			return false;
		}

		if ( "function" === typeof w.swal ) {
			swal(
				$.extend( {}, SecuPress.confirmSwalDefaults, {
					text:              w.l10nmodules.confirmDeleteBackup,
					confirmButtonText: w.l10nmodules.yesDeleteOne
				} ),
				function () {
					secupressDeleteOneBackup( $this, href );
				}
			);
		} else if ( w.confirm( w.l10nmodules.confirmTitle + "\n" + w.l10nmodules.confirmDeleteBackup ) ) {
			secupressDeleteOneBackup( $this, href );
		}
	} );

	// Ajax call that do a Backup.
	$( "#submit-backup-db" ).on( "click", function( e ) {
		var $this = $( this ),
			href  = secupressPreAjaxCall( $this.closest( "form" ).attr( "action" ), e );

		if ( ! href ) {
			return false;
		}

		secupressDoDbBackup( $this, href );
	} ).removeAttr( "disabled aria-disabled" );

} )(jQuery, document, window);


// Countries =======================================================================================
(function($, d, w, undefined) {

	$( ".continent, .continent input" ).on( "click", function( e ) {
		var val = $( this ).val().replace( 'continent-', '' );
		$( this ).css( '-webkit-appearance', 'none' );
		$( ".depends-geoip-system_type_blacklist.depends-geoip-system_type_whitelist [data-code-country='" + val + "']" ).prop( "checked", $( this ).is( ":checked" ) );
	} );

	$( "[data-code-country]" ).on( "click", function( e ) {
		var code = $( this ).data( "code-country" );
		secupress_set_indeterminate_state( code );
	} );

	function secupress_set_indeterminate_state( code ) {
		var all_boxes = $( "[data-code-country='" + code + "']" ).length;
		var checked_boxes = $( "[data-code-country='" + code + "']:checked" ).length;
		if ( checked_boxes == all_boxes ) {
			$( "[value='continent-" + code + "']" ).prop( "checked", true );
			$( "[value='continent-" + code + "']" ).prop( "indeterminate", false ).css( '-webkit-appearance', 'none' );
		} else if ( checked_boxes == 0 ) {
			$( "[value='continent-" + code + "']" ).prop( "checked", false );
			$( "[value='continent-" + code + "']" ).prop( "indeterminate", false ).css( '-webkit-appearance', 'none' );
		} else {
			$( "[value='continent-" + code + "']" ).prop( "checked", false );
			$( "[value='continent-" + code + "']" ).prop( "indeterminate", true ).css( '-webkit-appearance', 'checkbox' );
		}
	}

	$( ".continent input" ).each( function(i) {
		var code = $( this ).val().replace( 'continent-', '' );
		secupress_set_indeterminate_state( code );
	} );

	$( ".expand_country" ).on( "click", function( e ) {
		$( this ).next( 'fieldset' ).toggleClass( 'hide-if-js' );
	} );

} )(jQuery, document, window);