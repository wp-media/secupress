<?php
/*
Module Name: Captcha for Login
Description: Add a gentle captcha on the login form
Main Module: users_login
Author: SecuPress
Version: 1.0
*/
defined( 'SECUPRESS_VERSION' ) or die( 'Cheatin&#8217; uh?' );

add_action( 'login_form', 'secupress_add_captcha_on_login_form' );
function secupress_add_captcha_on_login_form( $echo = false ) {
	?>
	<div>
	    <div id="areyouhuman">
	    	<label>
		    	<span class="checkme" role="checkbox" tabindex="0" aria-checked="false"></span>
		    	<i class="checkme"><?php _e( 'Yes, I\'m a Human.', 'secupress' ); ?></i>
	    	</label>
	    </div>
    	<div id="msg" class="hidden"><?php _e( 'Session expired, please try again.', 'secupress' ); ?></div>
	    <input type="hidden" name="captcha_key" id="captcha_key" value="" />
	</div>
	<?php
}

add_action( 'login_head', 'secupress_login_captcha_css' );
function secupress_login_captcha_css() {
?>
<style>
	#areyouhuman{
	    height: auto;
	    background-color: #EEE;
	    padding: 20px;
	    border: 1px solid #CCC;
	    border-radius: 3px;
	    margin-bottom: 10px;
	    font-size: 1.3em;
	}
	span.checkme{
	    height: 28px;
	    width: 28px;
	    margin-right: 5px;
	    display: inline-block;
	    overflow: hidden;
	    cursor: pointer;
	    outline: 0;
	    opacity: 0.8;
		background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFQAAAJMCAYAAABkcYEeAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6MDk2QzQ2NjAyOEVFMTFFNUJCQTlCNTcxN0RBMjQxNzkiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6MDk2QzQ2NjEyOEVFMTFFNUJCQTlCNTcxN0RBMjQxNzkiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDowOTZDNDY1RTI4RUUxMUU1QkJBOUI1NzE3REEyNDE3OSIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDowOTZDNDY1RjI4RUUxMUU1QkJBOUI1NzE3REEyNDE3OSIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PmgyWG0AACLsSURBVHja7J0LlFxF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);
	}
	i.checkme{
		position: relative;
		font-size: 1.2em;
		top: -10px;
	    outline: 0;	
	}	
	#areyouhuman label:hover i.checkme{
		color: #4e92df;
	}	
	#areyouhuman span.checkme:focus, #areyouhuman span.checkme:hover{
		opacity: 1;
	}
	i.checkme:hover{
	    border-color: #666;
	}
	#msg{
		color: #F00;
	    font-weight: bold;
	    font-style: italic;
	    font-size: 0.9em;
	    padding-bottom: 5px;
	}
	.hidden{display:none}
</style>
<?php
wp_enqueue_script( 'jquery', false, array(), false, true );
}

add_action( 'login_footer', 'secupress_login_captcha_js' );
function secupress_login_captcha_js() {
	if ( ! isset( $_GET['action'] ) || 'login' == $_GET['action'] ) {
	?>
	<script>
		jQuery( document ).ready( function( $ ) {
			$('noscript.nojs').parent().hide();
			function secupress_sleep(millis){
				var date = new Date();
				var curDate = null;
				do { curDate = new Date(); }
				while(curDate-date < millis);
			}
			function secupress_captcha_do_fail() {
			    $('span.checkme').css('background-position-x', 28);
			    $('span.checkme').css('background-position-y', 0);
				$('#captcha_token').val('');
				$('#msg').show();
			}
			function secupress_set_flag_ok() {
				numImgs = 20;
				flag = 1;
			}
			var captcha_session;
			function secupress_set_captcha_timeout() {
				captcha_session = setTimeout( secupress_captcha_do_fail, 1000 * 59 * 2 ); // ~2 mn 
			}
			function secupress_clear_captcha_timeout() {
				clearTimeout( captcha_session );
			}
			var doing_ajax = false;
			var flag = 0; // 0 = nothing, 1 = ok, -1 = ko, 2 = -1 + return to start
			var numImgs = 9;
			$('.checkme').click( function() {
				if ( doing_ajax || 0 != flag ) {
					return;
				}
				var imgHeight = 28;
				var cont = 1;
				var inc = 1;
				var bgposx = 0;

				var animation = setInterval( function() {
					// switch ajax state
					switch( flag ) {
						case 1: bgposx = -28; break;
						case -1: bgposx = 28; break;
						case 0: bgposx = 0; break;
					}
					// position the css chekcbox
				    var position =  -1 * (cont*imgHeight);
				    $('span.checkme').css('background-position-y', position).css('background-position-x', bgposx);
				    // if animation returned to start
				    if ( cont == 0 ) {
				    	inc = 1;
				    	if ( flag == 2 ) {
				    		clearInterval(animation);
				    	}
				    }
				    // is animation hits the end
				    if ( cont == numImgs ) {
				    	switch( flag ) {
				    		case 0:
				    			inc = -1;
				    		break;
				    		case 1:
				    			clearInterval(animation);
			    			break;
					    	case -1: 
					    		secupress_sleep( 2000 );
					    		flag = 2;
					    		inc = -1;
				    		break;
					    }
				    }
				    // inc/decrement
			        cont = cont + inc;
				}, 50 );

				$('#msg').hide();
				doing_ajax = true;
				$.get( '<?php echo esc_js( esc_url( admin_url( 'admin-ajax.php' ) ) ); ?>?action=captcha_check&oldvalue=' + $('#captcha_key').val() )
				.done( function( data ) {
					setTimeout( secupress_set_flag_ok, 2000 );					
					$('#captcha_key').val( data.data );
					secupress_clear_captcha_timeout();
					secupress_set_captcha_timeout();
					$('.checkme').css('cursor', 'default');
				})
				.fail( function() {
					flag = -1;
					numImgs = 21;
					secupress_captcha_do_fail();
				})
				.always( function( data ) {;
					doing_ajax = false;
				});
			});
			$('span.checkme').keypress(function(e){
				if ( 32 == e.charCode || 13 == e.charCode ) {
					$(this).click();
				}
			});
		} );
	</script>
	<?php
	}
}

add_action( 'wp_ajax_captcha_check', 'secupress_captcha_check' );
add_action( 'wp_ajax_nopriv_captcha_check', 'secupress_captcha_check' );
function secupress_captcha_check() {
	if ( ! isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || 'XMLHttpRequest' != $_SERVER['HTTP_X_REQUESTED_WITH'] || // a "real" ajax request
		! empty( $_POST['captcha_key'] )
	) {
		status_header( 400 );
		wp_send_json_error();
	}
	$token = wp_generate_password( 12, false );
	$captcha_keys = get_option( 'secupress_captcha_keys', array() );
	$captcha_keys[ $token ] = time();
	$t = time();
	foreach ( $captcha_keys as $key => $value ) {
		if ( $t > $value ) {
			unset( $captcha_keys[ $key ] );
		}
	}
	if ( version_compare( $GLOBALS['wp_version'], '4.2' ) < 0 ) {
		delete_option( 'secupress_captcha_keys' );
		add_option( 'secupress_captcha_keys', $captcha_keys, false );
	} else {
		update_option( 'secupress_captcha_keys', $captcha_keys, false );
	}
	wp_send_json_success( $token );
}

add_action( 'authenticate', 'secupress_manage_captcha', 20, 2 );
function secupress_manage_captcha( $raw_user, $username ) {
	if ( ! defined( 'XMLRPC_REQUEST' ) && ! defined( 'APP_REQUEST' ) &&
		 ! is_wp_error( $raw_user ) && ! empty( $_POST ) &&
		 isset( $_POST['log'], $_POST['pwd'] )
	) {
		$captcha_key = isset( $_POST['captcha_key'] ) ? $_POST['captcha_key'] : null;
		$captcha_keys = get_option( 'secupress_captcha_keys', array() );
		if ( ! isset( $captcha_keys[ $captcha_key ] ) || 
			time() > $captcha_keys[ $captcha_key ] + 2 * MINUTE_IN_SECONDS ||
			time() < $captcha_keys[ $captcha_key ] + 2
		) {
			return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: The Human verification is incorrect.', 'secupress' ) );
		}
		unset( $captcha_keys[ $captcha_key ] );
		if ( version_compare( $GLOBALS['wp_version'], '4.2' ) < 0 ) {
			delete_option( 'secupress_captcha_keys' );
			add_option( 'secupress_captcha_keys', $captcha_keys, false );
		} else {
			update_option( 'secupress_captcha_keys', $captcha_keys, false );
		}

	}
	return $raw_user;
}


add_filter( 'login_message', 'secupress_login_form_nojs_error' );
function secupress_login_form_nojs_error( $message ) {
	if ( ! isset( $_GET['action'] ) || 'login' == $_GET['action'] ) {
		$message .= '<noscript><p class="message">' . __( 'You need to enable JavaScript to send this form correctly.', 'secupress' ) . '</p></noscript>'; 
	}
	return $message;
}