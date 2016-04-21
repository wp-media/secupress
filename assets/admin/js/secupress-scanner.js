jQuery( document ).ready( function( $ ) {

	// !Chart and score --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	var secupressChart,
		secupressChartEl = document.getElementById( 'status_chart' ),
		secupressChartData;

	if ( secupressChartEl && window.Chart ) {
		secupressChartData = [
			{
				value		: SecuPressi18nChart.good.value,
				color		: '#26B3A9',
				highlight	: '#2BCDC1',
				label		: SecuPressi18nChart.good.text,
				status		: 'good',
			},
			{
				value		: SecuPressi18nChart.bad.value,
				color		: '#CB234F',
				highlight	: '#F2295E',
				label		: SecuPressi18nChart.bad.text,
				status		: 'bad',
			},
			{
				value		: SecuPressi18nChart.warning.value,
				color		: '#F7AB13',
				highlight	: '#F1C40F',
				label		: SecuPressi18nChart.warning.text,
				status		: 'warning',
			},
			{
				value		: SecuPressi18nChart.notscannedyet.value,
				color		: '#5A626F',
				highlight	: '#888888',
				label		: SecuPressi18nChart.notscannedyet.text,
				status		: 'notscannedyet',
			},
		];

		secupressChart = new Chart( secupressChartEl.getContext( "2d" ) ).Doughnut( secupressChartData, {
			animationEasing			: 'easeInOutQuart',
			tooltipEvents			: [],
			showTooltips			: true,
			segmentShowStroke		: false,
			percentageInnerCutout	: 93,
			tooltipEvents			: ['mousemove'],
			onAnimationComplete 	: function() {
				//this.showTooltip( [ this.segments[0] ], true );
			}
		} );

		secupressChartEl.onclick = function( e ){
			var activePoints = secupressChart.getSegmentsAtEvent( e );
			$( '.square-filter.statuses button[data-type="' + activePoints[0].status + '"]' ).trigger( "filter.secupress" );
		};
	}

	if ( jQuery.timeago ) {
		jQuery.timeago.settings.strings = jQuery.extend( { numbers: [] }, SecuPressi18nTimeago );
		$( '.timeago' ).timeago();
	}

	function secupressPrependDataLi( percent, now ) {
		$( '.score_results ul' ).prepend( '<li class="hidden" data-percent="' + percent + '">' + now + '</li>' ).find( 'li.hidden' ).slideDown( 250 );
		if ( jQuery.timeago ) {
			$( '.timeago:first' ).timeago();
		}
	}

	function secupressUpdateScore( refresh ) {
		var total, status_good, status_warning, status_bad, status_notscannedyet, percent, letter,
			d, the_date, dashicon, $score_results_ul, replacement, last_percent, now;

		if ( ! secupressChartEl || ! jQuery.timeago ) {
			return;
		}

		total                = $( '.status-all' ).length;
		status_good          = $( '.secupress-table-prio-all .secupress-item-all.status-good' ).length;
		status_warning       = $( '.secupress-table-prio-all .secupress-item-all.status-warning' ).length;
		status_bad           = $( '.secupress-table-prio-all .secupress-item-all.status-bad' ).length;
		status_notscannedyet = $( '.secupress-table-prio-all .secupress-item-all.status-notscannedyet' ).length;
		percent              = Math.floor( status_good * 100 / total );
		letter               = '∅';

		$( '.secupress-score' ).find( '.percent' ).text( percent + '%' );

		if ( total != status_notscannedyet ) {
			if ( percent >= 90 ) {
				letter = 'A';
			} else if ( percent >= 80 ) {
				letter = 'B';
			} else if ( percent >= 70 ) {
				letter = 'C';
			} else if ( percent >= 60 ) {
				letter = 'D';
			} else if ( percent >= 50 ) {
				letter = 'E';
			} else {
				letter = 'F';
			}
		}

		if ( 'A' === letter ) {
			$( '#tweeterA' ).slideDown();
		} else {
			$( '#tweeterA' ).slideUp();
		}

		$( '.secupress-score' ).find( '.letter' ).html( letter ).removeClass( 'l∅ lA lB lC lD lE lF' ).addClass( 'l' + letter );

		if ( refresh ) {
			d                = new Date();
			the_date         = d.getFullYear() + "-" + ( "0" + ( d.getMonth() + 01 ) ).slice( -2 ) + "-" + ( "0" + d.getDate() ).slice( -2 ) + " " + ( "0" + d.getHours() ).slice( -2 ) + ":" + ( "0" + d.getMinutes() ).slice( -2 );
			dashicon         = '<span class="dashicons mini dashicons-arrow-?-alt2"></span>';
			$score_results_ul= $( '.score_results ul' );
			replacement      = 'right';
			last_percent     = $score_results_ul.find( 'li:first' ).data( 'percent' );

			if ( last_percent < percent ) {
				replacement = 'up';
			} else if ( last_percent > percent ) {
				replacement = 'down';
			}

			dashicon = dashicon.replace( '?', replacement );
			now = '<strong>' + dashicon + letter + ' (' + percent + ' %)</strong> <span class="timeago" title="' + the_date + '">' + the_date + '</span>';

			if ( $score_results_ul.find( 'li' ).length === 5 ) {
				$score_results_ul.find( 'li:last' ).slideUp( 250, function() {
					$( this ).remove();
					secupressPrependDataLi( percent, now );
				} );
			} else {
				secupressPrependDataLi( percent, now );
			}
		}

		secupressChart.segments[0].value = status_good;
		secupressChart.segments[1].value = status_bad;
		secupressChart.segments[2].value = status_warning;
		secupressChart.segments[3].value = status_notscannedyet;
		secupressChart.update();

		// legend counters
		$('.secupress-count-good').text( status_good );
		$('.secupress-count-bad').text( status_bad );
		$('.secupress-count-warning').text( status_warning );
		$('.secupress-count-notscannedyet').text( status_notscannedyet );
	}

	secupressUpdateScore();


	// !Big network: set some data ---------------------------------------------------------------------------------------------------------------------------------------------------------------------
	(function( w, d, $, undefined ) {
		function secupressSetBigData( href, $button, $spinner, $percent ) {
			$.getJSON( href )
			.done( function( r ) {
				if ( ! r.success ) {
					$spinner.replaceWith( '<span class="secupress-error-notif">' + SecuPressi18nScanner.error + '</span>' );
					$percent.remove();
					return;
				}
				if ( r.data ) {
					$percent.text( r.data + '%' );

					if ( r.data !== 100 ) {
						// We need more data.
						secupressSetBigData( href, $button, $spinner, $percent );
						return;
					}
				}
				// Finish.
				$button.closest( '.secupress-notice' ).fadeTo( 100 , 0, function() {
					$( this ).slideUp( 100, function() {
						$( this ).remove();
					} );
				} );
			} )
			.fail( function() {
				$spinner.replaceWith( '<span class="secupress-error-notif">' + SecuPressi18nScanner.error + '</span>' );
				$percent.remove();
			} );
		}


		$( '.secupress-centralize-blog-options' ).on( 'click', function( e ) {
			var $this    = $( this ),
				href     = $this.attr( 'href' ).replace( 'admin-post.php', 'admin-ajax.php' ),
				$spinner = $( '<img src="' + SecuPressi18nScanner.spinnerUrl + '" alt="" class="secupress-spinner" />' ),
				$percent = $( '<span class="secupress-ajax-percent">0%</span>' );

			if ( $this.hasClass( 'running' ) ) {
				return false;
			}
			$this.addClass( 'running' ).parent().append( $spinner ).append( $percent ).find( '.secupress-error-notif' ).remove();

			e.preventDefault();

			secupressSetBigData( href, $this, $spinner, $percent );
		} );
	} )( window, document, $ );


	// !Filter Rows (Status) ------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	(function( w, d, $, undefined ) {
		$( '#secupress-type-filters' ).find('a').on( 'click.secupress', function( e ) {
			var $this		= $( this ),
				priority	= $this.data( 'type' ),
				current		= 'active';

			if ( $this.hasClass( current ) ) {
				return false;
			}

			$this.closest('ul').find('a').removeClass( current );
			$this.addClass( current );

			$( '.status-all' ).addClass( 'hidden' ).attr( 'aria-hidden', true );
			$( '.status-' + priority ).removeClass( 'hidden' ).attr( 'aria-hidden', false );
		} );

		// pre-show Bad
		$( '#secupress-type-filters' ).find('.secupress-big-tab-bad').find('a').trigger('click');
	} )(window, document, $);


	// !Filter Rows (Priority) ------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	(function( w, d, $, undefined ) {
		$( '#secupress-priority-filters' ).find('input').on( 'change.secupress', function( e ) {
			var $this		= $( this ),
				priority	= $this.attr( 'name' );

			console.log(priority);
			if ( $this.is(':checked') ) {
				$('.secupress-table-prio-' + priority ).spFadeIn();
			} else {
				$('.secupress-table-prio-' + priority ).spHide();
			}
			return false;
		} );
	} )(window, document, $);


	// !"Select all" -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	(function( w, d, $, undefined ) {

		var checks, first, last, checked, sliced, lastClicked = {};

		// Check all checkboxes.
		$( "tbody" ).children().children( ".secupress-check-column" ).find( ":checkbox" ).on( "click", function( e ) {
			var prio;

			if ( "undefined" === e.shiftKey ) {
				return true;
			}

			prio = this.className.replace( /^.*secupress-checkbox-([^\s]+)(?:\s.*|$)/g, "$1" );

			if ( e.shiftKey ) {
				if ( ! lastClicked[ prio ] ) {
					return true;
				}
				checks  = $( lastClicked[ prio ] ).closest( ".secupress-table-prio-all" ).find( ":checkbox" ).filter( ":visible:enabled" );
				first   = checks.index( lastClicked[ prio ] );
				last    = checks.index( this );
				checked = $( this ).prop( "checked" );

				if ( 0 < first && 0 < last && first !== last ) {
					sliced = ( last > first ) ? checks.slice( first, last ) : checks.slice( last, first );
					sliced.prop( "checked", function() {
						if ( $( this ).closest( "tr" ).is( ":visible" ) ) {
							return checked;
						}

						return false;
					} );
				}
			}

			lastClicked[ prio ] = this;

			// toggle "check all" checkboxes
			var unchecked = $( this ).closest( "tbody" ).find( ":checkbox" ).filter( ":visible:enabled" ).not( ":checked" );
			$( this ).closest( "table" ).children( "thead, tfoot" ).find( ":checkbox" ).prop( "checked", function() {
				return ( 0 === unchecked.length );
			} );

			return true;
		} );

		$( "thead, tfoot" ).find( ".secupress-check-column :checkbox" ).on( "click.wp-toggle-checkboxes", function( e ) {
			var $this          = $(this),
				$table         = $this.closest( "table" ),
				controlChecked = $this.prop( "checked" ),
				toggle         = e.shiftKey || $this.data( "wp-toggle" );

			$table.children( "tbody" ).filter( ":visible" )
				.children().children( ".secupress-check-column" ).find( ":checkbox" )
				.prop( "checked", function() {
					if ( $( this ).is( ":hidden,:disabled" ) ) {
						return false;
					}

					if ( toggle ) {
						return ! $( this ).prop( "checked" );
					}

					return controlChecked ? true : false;
				} );

			$table.children( "thead, tfoot" ).filter( ":visible" )
				.children().children( ".secupress-check-column" ).find( ":checkbox" )
				.prop( "checked", function() {
					if ( toggle ) {
						return false;
					}

					return controlChecked ? true : false;
				} );
		} );

	} )(window, document, $);


	// !Scans and fixes --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	(function( w, d, $, undefined ) {
		var secupressScans = {
			// Scans.
			doingScan:    {},
			// Fixes.
			doingFix:     {},
			delayedFixes: [],
			// Manual fixes.
			manualFix:    {}
		};


		// Update counters of bad results.
		function secupressUpdateBadResultsCounters() {
			var count = $( ".secupress-item-all.status-bad" ).length,
				$counters = $( "#toplevel_page_secupress" ).find( ".update-plugins" );

			$counters.attr( "class", function( i, val ) {
				return val.replace( /^((?:.*\s)?)count-\d+((?:\s.*)?)$/g, "$1count-" + count + "$2" );
			} );

			$counters.children().text( count );
		}


		// Get test name from an URL.
		function secupressGetTestFromUrl( href ) {
			var test = href.match( /[&?]test=([^&]+)(?:$|&)/ );
			return test ? test[1] : false;
		}


		// Tell if a test is fixable.
		function secupressIsFixable( $row ) {
			return $row.hasClass( "status-bad" ) && ! $row.hasClass( "not-fixable" );
		}


		// Get current scan/fix status.
		function secupressGetCurrentStatus( $el ) {
			var classes, status = false;

			classes = $el.attr( 'class' ).replace( /(\s|^)(status-error|status-all)(\s|$)/g, " " ).replace( /^\s+|\s+$/g, "" ).replace( /\s+/, " " ).split( " " );

			$.each( classes, function( i, cl ) {
				if ( 0 === cl.indexOf( "status-" ) ) {
					status = cl.substr( 7 );
					return false;
				}
			} );

			return status;
		}


		// Set the scan/fix status class.
		function secupressSetStatusClass( $el, status ) {
			$el.removeClass( 'status-error status-good status-bad status-warning status-notscannedyet status-cantfix' ).addClass( 'status-' + status );
		}


		// Scan icon + status text.
		function secupressAddScanStatusText( $row, statusText ) {
			$row.find( '.secupress-status-text' ).html( statusText );
		}


		// Add a scan result.
		function secupressAddScanResult( $row, message ) {
			$row.find( '.secupress-scan-message' ).html( message );
		}


		// Replace a scan status with an error icon + message.
		function secupressDisplayScanError( $row ) {
			var status = '<span class="dashicons dashicons-no secupress-dashicon" aria-hidden="true"></span> ' + SecuPressi18nScanner.error;

			// Add the icon + text.
			secupressAddScanStatusText( $row, status );

			// Empty the scan results.
			secupressAddScanResult( $row, "" );

			// Add a "status-error" class to the row.
			$row.addClass( 'status-error' );

			return false;
		}


		// Fix icon + status text.
		function secupressAddFixStatusText( $row, statusText ) {
			$row.find( '.secupress-fix-status-text' ).html( statusText );
		}


		// Add a fix result.
		function secupressAddFixResult( $row, message ) {
			$row.find( '.secupress-fix-result-message' ).html( message );
		}


		// Replace a fix status with an error icon + message.
		function secupressDisplayFixError( $row, warn ) {
			var statusText = '<span class="dashicons dashicons-no secupress-dashicon" aria-hidden="true"></span> ' + SecuPressi18nScanner.error;

			// Add the icon + text.
			secupressAddFixStatusText( $row, statusText );

			// Empty the fix results.
			secupressAddFixResult( $row, "" );

			// Add a "status-error" class to the td.
			$row.find( '.secupress-fix-result' ).addClass( 'status-error' );

			if ( warn ) {
				secupressErrorWarn();
			}

			return false;
		}


		// Error popup.
		function secupressErrorWarn() {
			swal( {
				title: SecuPressi18nScanner.error,
				type: "error",
				allowOutsideClick: true
			} );
		}

		// Tell if the returned data (from a scan) has required infos.
		function secupressScanResponseHasRequiredData( r, $row ) {
			// Fail, or there's a problem with the returned data.
			if ( ! r.success || ! $.isPlainObject( r.data ) ) {
				return secupressDisplayScanError( $row );
			}

			// The data is incomplete.
			if ( ! r.data.status || ! r.data.class || ! r.data.message ) {
				return secupressDisplayScanError( $row );
			}

			return true;
		}


		// Tell if the returned data (from fix) has required infos.
		function secupressFixResponseHasRequiredData( r, $row, warn ) {
			warn = typeof warn === "undefined" ? false : warn;

			// Fail, or there's a problem with the returned data.
			if ( ! r.success || ! $.isPlainObject( r.data ) ) {
				return secupressDisplayFixError( $row, warn );
			}

			// The data is incomplete.
			if ( ! r.data.status || ! r.data.class || ! r.data.message ) {
				return secupressDisplayFixError( $row, warn );
			}

			return true;
		}


		// Deal with scan infos.
		function secupressDisplayScanResult( r, test ) {
			var $row = $( '#' + test ),
				oldStatus;

			// Fail, or there's a problem with the returned data.
			if ( ! secupressScanResponseHasRequiredData( r, $row ) ) {
				return false;
			}

			// Get current status.
			oldStatus = secupressGetCurrentStatus( $row );

			// Add the new status as a class.
			secupressSetStatusClass( $row, r.data.class );

			// Add status.
			secupressAddScanStatusText( $row, r.data.status );

			// Add scan results.
			secupressAddScanResult( $row, r.data.message );

			if ( oldStatus !== r.data.class ) {
				// Tell the row status has been updated.
				$( "body" ).trigger( "testStatusChange.secupress", [ {
					test:      test,
					newStatus: r.data.class,
					oldStatus: oldStatus
				} ] );
			}

			return true;
		}


		// Deal with fix infos.
		function secupressDisplayFixResult( r, test, warn ) {
			var $row = $( '#' + test ),
				$fix  = $row.find( '.secupress-fix-result' );

			warn = typeof warn === 'undefined' ? false : warn;

			// Fail, or there's a problem with the returned data.
			if ( ! secupressFixResponseHasRequiredData( r, $row, warn ) ) {
				return false;
			}

			// Add the new status as a class.
			secupressSetStatusClass( $fix, r.data.class );

			// Add status.
			secupressAddFixStatusText( $row, r.data.status );

			// Add fix results.
			secupressAddFixResult( $row, r.data.message );

			return true;
		}


		// Tell if we need a manual fix.
		function secupressManualFixNeeded( data ) {
			return data.form_contents && data.form_fields || data.manualFix;
		}


		// Perform a scan: spinner + row class + ajax call + display result.
		function secupressScanit( test, $row, href, isBulk ) {
			if ( ! test ) {
				// Something's wrong here.
				secupressDisplayScanError( $row ); // TOCHECK
				return secupressScanEnd( isBulk );
			}

			if ( secupressScans.doingScan[ test ] ) {
				// Oy! Slow down!
				return;
			}

			// Show our scan is running.
			secupressScans.doingScan[ test ] = 1;
			$row.addClass( 'scanning' ).removeClass( 'status-error' );

			// Add the spinner.
			secupressAddScanStatusText( $row, '<img src="' + SecuPressi18nScanner.spinnerUrl + '" alt="" />' );

			// Ajax call
			$.getJSON( href.replace( 'admin-post.php', 'admin-ajax.php' ) )
			.done( function( r ) {
				// Display scan result.
				if ( secupressDisplayScanResult( r, test ) ) {
					delete secupressScans.doingScan[ test ];

					// If it's an auto-scan and the result is good, remove the fix status.
					if ( $row.hasClass( 'autoscan' ) ) {
						$row.removeClass( 'autoscan' );

						if ( r.data.class === 'good' ) {
							$row.find( '.secupress-fix-result' ).html('');
						}
					}

					// Trigger an event.
					$( 'body' ).trigger( 'scanDone.secupress', [ {
						test:   test,
						href:   href,
						isBulk: isBulk,
						data:   r.data
					} ] );

				} else {
					delete secupressScans.doingScan[ test ];
				}
			} )
			.fail( function() {
				delete secupressScans.doingScan[ test ];

				// Error
				secupressDisplayScanError( $row );
			} )
			.always( function() {
				// Show our scan is completed.
				$row.removeClass( 'scanning' );

				secupressScanEnd( isBulk );
			} );
		}


		function secupressScanEnd( isBulk ) {
			if ( $.isEmptyObject( secupressScans.doingScan ) ) {
				$( 'body' ).trigger( 'allScanDone.secupress', [ { isBulk: isBulk } ] );
			}
		}


		// Perform a fix: spinner + row class + ajax call + display result + set the prop `secupressScans.manualFix` if a manual fix is needed.
		function secupressFixit( test, $row, href, isBulk ) {
			var $button;

			if ( ! test ) {
				// Something's wrong here.
				secupressDisplayFixError( $row, ! isBulk );
				return secupressFixEnd( isBulk );
			}

			if ( secupressScans.doingFix[ test ] ) {
				// Oy! Slow down!
				return;
			}

			if ( ! isBulk && ! $.isEmptyObject( secupressScans.doingFix ) ) {
				// One fix at a time if no bulk.
				return false;
			}

			if ( ! secupressIsFixable( $row ) ) {
				// Not fixable.
				return secupressFixEnd( isBulk );
			}

			$( '.secupress-fixit' ).addClass( 'disabled' );

			// Show our fix is running.
			secupressScans.doingFix[ test ] = 1;
			$row.addClass( 'fixing' ).removeClass( 'status-error' );

			// Add the spinner.
			secupressAddFixStatusText( $row, '<img src="' + SecuPressi18nScanner.spinnerUrl + '" alt="" />' );

			// Ajax call
			$.getJSON( href.replace( 'admin-post.php', 'admin-ajax.php' ) )
			.done( function( r ) {
				// Display fix result.
				if ( secupressDisplayFixResult( r, test, ! isBulk ) ) {

					delete secupressScans.doingFix[ test ];

					// If we need a manual fix, store the info.
					if ( secupressManualFixNeeded( r.data ) ) {
						secupressScans.manualFix[ test ] = r.data;
					}

					// Trigger an event.
					$( 'body' ).trigger( 'fixDone.secupress', [ {
						test:      test,
						href:      href,
						isBulk:    isBulk,
						manualFix: secupressManualFixNeeded( r.data ),
						data:      r.data
					} ] );

				} else {
					delete secupressScans.doingFix[ test ];
				}
			} )
			.fail( function() {
				delete secupressScans.doingFix[ test ];

				// Error
				secupressDisplayFixError( $row, ! isBulk );
			} )
			.always( function() {
				// Show our fix is completed.
				$row.removeClass( 'fixing' );

				// Enable fix buttons again.
				if ( ! isBulk ) {
					$( '.secupress-fixit' ).removeClass( 'disabled' );
				}

				secupressFixEnd( isBulk );
			} );
		}


		function secupressFixEnd( isBulk ) {
			if ( ! $.isEmptyObject( secupressScans.doingFix ) ) {
				// Some fixes are still running.
				return;
			}

			if ( ! secupressScans.delayedFixes.length ) {
				// No delayed fixes left in queue. This is the last fix!
				if ( isBulk ) {
					// Enable fix buttons again, only when all fixes are done.
					$( '.secupress-fixit' ).removeClass( 'disabled' );
				}
				// Finally, trigger an event.
				$( 'body' ).trigger( 'allFixDone.secupress', [ { isBulk: isBulk } ] );
			}
		}


		function secupressFixFirstQueued( isBulk ) {												console.log( '##secupressFixFirstQueued##' );
			var bulk = isBulk ? 'bulk' : '',
				elem = secupressScans.delayedFixes.shift();									console.log( elem );
					console.log( secupressScans.delayedFixes );
					console.log( '##end##' );
			$( elem ).trigger( bulk + 'fix.secupress' );
		}


		function secupressFilterNonDelayedButtons( $buttons ) {
			// If we're already performing a fix, do nothing.
			if ( ! $.isEmptyObject( secupressScans.doingFix ) ) {
				return $buttons;
			}
			// Some fixes may need to be queued and delayed.
			$buttons.filter( '.delayed-fix' ).each( function() {
				secupressScans.delayedFixes.push( this );
			} );																			console.log( '##secupressFilterNonDelayedButtons##' );
				console.log( secupressScans.delayedFixes );
				console.log( '##end##' );
			return $buttons.not( '.delayed-fix' );
		}


		function secupressLaunchSeparatedBulkFix( $buttons ) {
			if ( $buttons.length < 2 ) {													console.log( 'not a bulk:' );
				console.log( $buttons );
				// Not a bulk.
				$buttons.trigger( 'fix.secupress' );
				return;
			}
				console.log(1);

			$buttons = secupressFilterNonDelayedButtons( $buttons );

			if ( $buttons.length ) {
				console.log(2);
				// We still have "normal" fixes.
				$buttons.trigger( 'bulkfix.secupress' );
			} else {
				console.log(3);
				// OK, launch directly the fix of the first item in queue.
				secupressFixFirstQueued( true );
			}
		}


		// Perform a manual fix: display the form in a popup and launch an ajax call on submit.
		function secupressManualFixit( test ) {
			var content  = '',
				swalType = 'info',
				index, data;

			data = secupressScans.manualFix[ test ];
			delete secupressScans.manualFix[ test ];

			data.message = data.message.replace( /(<ul>|<li>|<\/li><\/ul>)/g, '' ).replace( /<\/li>/g, '<br/>' );

			// If the status is "bad" or "warning", `data.message` contains an error message.
			if ( data.class === 'bad' || data.class === 'warning' ) {
				content += '<div class="sa-error-container show"><div class="icon">!</div><p>' + data.message + '</p></div>';
				swalType = data.class === 'bad' ? 'error' : 'warning';
			}

			content += '<form method="post" id="form_manual_fix" class="secupress-swal-form show-input" action="' + ajaxurl + '">';

				for ( index in data.form_contents ) {
					content += data.form_contents[ index ];
				}
				content += data.form_fields;

			content += '</form>';

			swal( {
					title:               data.form_title,
					text:                content,
					html:                true,
					type:                swalType,
					showLoaderOnConfirm: true,
					closeOnConfirm:      false,
					allowOutsideClick:   true,
					showCancelButton:    true,
					confirmButtonText:   SecuPressi18nScanner.fixit
				},
				function() {
					var params = $( '#form_manual_fix' ).serializeArray(),
						$row   = $( '#' + test );

					$.post( ajaxurl, params )
					.done( function( r ) {
						// Display fix result.
						if ( secupressDisplayFixResult( r, test, true ) ) {

							// If we need a manual fix, store the info and re-run.
							if ( secupressManualFixNeeded( r.data ) ) {
								secupressScans.manualFix[ test ] = r.data;
								secupressManualFixit( test );
							}
							// The fix is successfull.
							else {
								// Trigger an event.
								$( 'body' ).trigger( 'manualFixDone.secupress', [ {
									test: test,
									data: r.data
								} ] );
							}

						}
					} )
					.fail( function() {
						// Error
						secupressDisplayFixError( $row, true );
					} );
				}
			);
		}


		// What to do when a scan ends.
		$( 'body' ).on( 'scanDone.secupress', function( e, extra ) {
			console.log('scanDone.secupress:', extra.test);
			/*
			* Available extras:
			* extra.test:   test name.
			* extra.href:   the admin-post.php URL.
			* extra.isBulk: tell if it's a bulk scan.
			* extra.data:   data returned by the ajax call.
			*/
			var $row = $( '#' + extra.test );

			// If we have delayed fixes, launch the first in queue now.
			if ( secupressScans.delayedFixes.length ) {
				secupressFixFirstQueued();
			}

			// If we have a good result, empty the fix cell.
			if ( extra.data.class === 'good' ) {
				secupressSetStatusClass( $row.children( '.secupress-fix-result' ), 'cantfix' );
				secupressAddFixStatusText( $row, '' );
				secupressAddFixResult( $row, '' );
			}
			if ( '' !== extra.data.fix_msg ) {
				secupressAddFixResult( $row, extra.data.fix_msg );
			}
		} );


		// What to do after ALL scans end.
		$( 'body' ).on( 'allScanDone.secupress', function( e, extra ) {
			console.log( 'allScanDone.secupress:', extra.isBulk );
			/*
			* Available extras:
			* extra.isBulk: tell if it's a bulk scan.
			*/

			// Update the donut only when all scans are done.
			secupressUpdateScore( true );
		} );


		// What to do when a fix ends.
		$( 'body' ).on( 'fixDone.secupress', function( e, extra ) {
			console.log('fixDone.secupress:', extra.test);
			/*
			* Available extras:
			* extra.test:      test name.
			* extra.href:      the admin-post.php URL.
			* extra.isBulk:    tell if it's a bulk fix.
			* extra.manualFix: tell if the fix needs a manual fix.
			* extra.data:      data returned by the ajax call.
			*/

			// Go for a new scan.
			$( '#' + extra.test ).find( '.secupress-scanit' ).trigger( 'scan.secupress' );
		} );


		// What to do after ALL fixes end.
		$( 'body' ).on( 'allFixDone.secupress', function( e, extra ) {
			console.log( 'allFixDone.secupress:', extra.isBulk );
			/*
			* Available extras:
			* extra.isBulk: tell if it's a bulk fix.
			*/
			var $rows        = '',
				manualFixLen = 0,
				oneTest;

			// If some manual fixes need to be done.
			if ( ! $.isEmptyObject( secupressScans.manualFix ) ) {
				// Add a message in each row.
				$.each( secupressScans.manualFix, function( test, data ) {
					if ( secupressScans.manualFix.hasOwnProperty( test ) ) {
						oneTest = test;
						++manualFixLen;
						$rows += ',.' + test;
					}
				} );
				$rows = $rows.substr( 1 );
				$rows = $( $rows ).find( '.secupress-scan-result' );
				$rows.find( '.manual-fix-message' ).remove();
				$rows.append( '<div class="manual-fix-message">' + SecuPressi18nScanner.manualFixMsg + '</div>' );

				if ( ! extra.isBulk ) {
					// If it's not a bulk, display the form.
					secupressManualFixit( oneTest );

				} else {
					// Bulk: warn the user that some manual fixes need to be done.
					swal( {
						title: manualFixLen === 1 ? SecuPressi18nScanner.oneManualFix : SecuPressi18nScanner.someManualFixes,
						type: "warning",
						allowOutsideClick: true
					} );
				}

				secupressScans.manualFix = {};
			}

			// Update the donut only when all fixes are done.
			secupressUpdateScore( true );
		} );


		// What to do after a manual fix.
		$( 'body' ).on( 'manualFixDone.secupress', function( e, extra ) {
			console.log('manualFixDone.secupress: ', extra.test );
		   /*
			* Available extras:
			* extra.test:      test name.
			* extra.data:      data returned by the ajax call.
			*/
			var title = SecuPressi18nScanner.notFixed,
				type  = 'error';

			// Go for a new scan.
			$( '#' + extra.test ).find( '.secupress-scanit' ).trigger( 'scan.secupress' );

			// Success! (or not)
			if ( extra.data.class === 'warning' ) {
				title = SecuPressi18nScanner.fixedPartial;
				type  = 'warning';
			} else if ( extra.data.class === 'good' ) {
				title = SecuPressi18nScanner.fixed;
				type  = 'success';
			}

			swal( {
				title: title,
				text:  extra.data.message.replace( /(<ul>|<li>|<\/li><\/ul>)/g, "" ).replace( /<\/li>/g, "<br/><br/>" ),
				type:  type,
				allowOutsideClick: true,
				html:  true
			} );
		} );


		// What to do when a status changes.
		$( 'body' ).on( 'testStatusChange.secupress', function( e, extra ) {
			console.log('testStatusChange.secupress: ', extra.test );
			/*
			* Available extras:
			* extra.test:      test name.
			* extra.newStatus: the new status.
			* extra.oldStatus: the old status.
			*/

			// Update the counters of bad results.
			secupressUpdateBadResultsCounters();
		} );


		// Show test details.
		$( "body" ).on( "click", ".secupress-details", function( e ) {
			var test = $( this ).attr( "data-test" );
			swal( {
				title: SecuPressi18nScanner.scanDetails,
				text:  $( "#details-" + test + " .details-content" ).html(),
				type:  'info',
				allowOutsideClick: true,
			} );
		} );


		// Show fix details.
		$( 'body' ).on( 'click', '.secupress-details-fix', function( e ) {
			var test = $( this ).attr( 'data-test' );
			swal( {
				text:                $( '#details-fix-' + test + ' .details-content' ).html(),
				title:               SecuPressi18nScanner.fixDetails,
				confirmButtonText:   SecuPressi18nScanner.fixit,
				type:                'info',
				closeOnConfirm:      true,
				showLoaderOnConfirm: true,
				allowOutsideClick:   true,
				showCancelButton:    true,
				html:                true,
			},
				function( isConfirm ) {
					if ( isConfirm ) {
						$( '#' + test + ' .secupress-fixit' ).trigger( 'click' );
					}
				}
			);
		} );


		// Perform a scan on click.
		$( 'body' ).on( 'click scan.secupress bulkscan.secupress', '.button-secupress-scan, .secupress-scanit', function( e ) {
			var $this = $( this ),
				href, test, $row, isBulk;

			e.preventDefault();

			if ( $this.hasClass( 'button-secupress-scan' ) ) {
				// It's the "One Click Scan" button.
				$( '.secupress-scanit' ).trigger( 'bulkscan.secupress' );
				return;
			}

			href   = $this.attr( 'href' );
			test   = secupressGetTestFromUrl( href );
			$row   = $this.closest( '.secupress-item-' + test );
			isBulk = e.type === 'bulkscan';

			secupressScanit( test, $row, href, isBulk );
		} );


		// Perform a fix on click.
		$( 'body' ).on( 'click fix.secupress bulkfix.secupress', '.button-secupress-fix, .secupress-fixit', function( e ) {
			var $this = $( this ),
				href, test, $row, isBulk;

			e.preventDefault();

			// It's the "One Click Fix" button.
			if ( $this.hasClass( 'button-secupress-fix' ) ) {
				secupressLaunchSeparatedBulkFix( $( '.secupress-fixit' ) );
				return;
			}

			href   = $this.attr( 'href' );
			test   = secupressGetTestFromUrl( href );
			$row   = $this.closest( '.secupress-item-' + test );
			isBulk = e.type === 'bulkfix';

			secupressFixit( test, $row, href, isBulk );
		} );


		// Autoscans.
		$( '.secupress-item-all.autoscan .secupress-scanit' ).trigger( 'bulkscan.secupress' );


		// Bulk.
		// TODO: NO MORE BULK, REMOVE?
		$( '#doaction-high, #doaction-medium, #doaction-low' ).on( 'click', function( e ) {
			var $this    = $( this ),
				prio     = $this.attr( 'id' ).replace( 'doaction-', '' ),
				action   = $this.siblings( "select" ).val(),
				$rows    = $this.parents( ".secupress-table-prio-all" ).find( "tbody .secupress-check-column :checked" ).parents( ".secupress-item-all" ),
				$buttons = $rows.find( ".secupress-" + action ),
				bulk;

			if ( action === "-1" || ! $buttons.length ) {
				return;
			}

			$this.siblings( "select" ).val( "-1" );

			switch ( action ) {
				case 'scanit':
					// Trigger scans.
					bulk = $buttons.length < 2 ? "" : "bulk";
					$buttons.trigger( bulk + "scan.secupress" );
					break;
				case 'fixit':
					// Uncheck rows that are not fixable.
					$rows.each( function() {
						var $row = $( this );
						if ( $row.hasClass( "not-fixable" ) ) {
							// TODO: REMOVE ?
							// secupressUncheckTest( $row );
						}
					} );
					// Trigger fixes.
					secupressLaunchSeparatedBulkFix( $buttons );
					break;
			}
		} );
	} )(window, document, $);
} );
