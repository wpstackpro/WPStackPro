<?php
/*
Plugin Name: WPStackPro
Plugin URI: https://wpstackpro.com
Description: WordPress plugin that serves as a framework to quickly build products
Author: Ashfame
Version: 0.2-dev
Author URI: https://ashfame.com/
*/

class WPStackPro {

	public function __construct() {
		// Replace login logo/name url to site's url
		add_filter( 'login_headerurl', function( $url ) {
			return home_url();
		} );

		add_filter( 'login_headertitle', function() {
			return get_bloginfo( 'name' );
		});

		// Replace logo with just site name on login page
		add_action( 'login_enqueue_scripts', function() {
			?>
			<style type="text/css">
				div#login {
					width: 420px;
				}

				body.login div#login h1 a {
					#background-image: url(http://localhost/wordpress/one.jpeg);
					background: none;
					width: auto;
					height: auto;
					text-indent: 0;
					font-size: 75px;
				}
			</style>
			<?php
		} );

		// inject some CSS in admin
		add_action( 'admin_head', function() {
			?>
			<style>
				#collapse-menu, #wp-admin-bar-wp-logo {
					display: none;
				}
			</style>
			<?php
		} );

		// remove footer text in admin
		add_filter( 'admin_footer_text', '__return_empty_string', 11 );
		add_filter( 'update_footer', '__return_empty_string', 11 );

		add_action( 'init', function() {
			if ( ! current_user_can( 'manage_options' ) ) {
				// remove admin color scheme options from non-admin users
				remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

				// remove screen options & contextual help links
				add_filter( 'screen_options_show_screen', '__return_false' );
				add_filter( 'contextual_help', function( $old_help, $screen_id, $screen ) {
					$screen->remove_help_tabs();

					return $old_help;
				}, 999, 3 );
			}
		} );

		// set default color scheme for all users
		add_action( 'user_register', function( $user_id ) {
			wp_update_user( array(
				'ID'          => $user_id,
				'admin_color' => 'midnight'
			) );
		} );

		// remove dashboard widgets
		add_action( 'admin_init', function() {
			remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_secondary', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' ); //since 3.8
		} );

		// remove user profile fields
		add_action( 'admin_footer-profile.php', function() {
			//return;
			if ( ! current_user_can( 'manage_options' ) ) { ?>
				<script type="text/javascript">
					jQuery( "h2:contains('Personal Options')" ).next( '.form-table' ).remove();
					jQuery( "h2:contains('Personal Options')" ).remove();
					jQuery( "h2:contains('About Yourself')" ).next( '.form-table' ).remove();
					jQuery( "h2:contains('About Yourself')" ).remove();
					jQuery( "h2:contains('Name')" ).next( '.form-table' ).hide();
					jQuery( "h2:contains('Name')" ).remove();
					jQuery( 'tr.user-url-wrap' ).remove();
				</script>
			<?php }
		} );

		// Add Every-minute cron schedule
		add_filter( 'cron_schedules', function( $schedules ) {
			$schedules[ 'every_minute' ] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every Minute' ),
			);

			return $schedules;
		} );

		// redirect 404 back to homepage
		add_action( 'template_redirect', function() {
			if ( get_option( 'wpstackpro_redirect_non_homepage_requests_to_homepage', false ) && is_404() ) {
				wp_redirect( '/', 302 );
			}
		} );

		// Add body class in admin as per user roles + customer_designation metric
		add_filter( 'admin_body_class', function( $css_classes ) {
			$current_user = wp_get_current_user();
			foreach ( $current_user->roles as $role ) {
				$css_classes .= ' user-is-' . $role;
			}

			$customer_designation = get_user_meta( $current_user->ID, 'customer_designation', true );
			if ( $customer_designation ) {
				$css_classes .= ' customer-is-' . $customer_designation;
			}

			return $css_classes;
		} );

		// Show some message on login page + fill out email in login form, if available
		add_action( 'login_init', function() {
			if ( isset( $_REQUEST[ 'ux-message' ] ) ) {
				?>
				<script>
					setTimeout( function() {
						alert( '<?php echo $_REQUEST[ 'ux-message' ]; ?>' );
					}, 1000 );
				</script>
				<?php
			}

			if ( isset( $_REQUEST[ 'ux-email' ] ) ) {
				?>
				<script>
					setTimeout( function() {
						document.getElementById( 'user_login' ).value = '<?php echo $_REQUEST[ 'ux-email' ] ?>';
					}, 1 );
				</script>
				<?php
			}
		} );

		// Add dashboard widgets
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );

		// Ajax handlers
		add_action( 'wp_ajax_support_widget_submission', array( $this, 'support_widget_submission_handler' ) );
		add_action( 'wp_ajax_nopriv_support_widget_submission', array( $this, 'support_widget_submission_handler' ) );
		add_action( 'wp_ajax_nopriv_feature_tasting', array( $this, 'feature_tasting' ) );
		add_action( 'wp_ajax_feature_tasting', array( $this, 'feature_tasting' ) );
		add_action( 'wp_ajax_nopriv_friction_less_login', array( $this, 'friction_less_login_handler' ) );
		add_action( 'wp_ajax_friction_less_login', array( $this, 'friction_less_login_handler' ) );

		// load integrations, if they are enabled
		require_once( plugin_dir_path( __FILE__ ) . 'helper.php' );
		if ( get_option( 'wpstackpro_paddle_integration_enabled' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/paddle.php' );
		}
		if ( get_option( 'wpstackpro_pager_integration_enabled' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/pager.php' );
		}
		if ( get_option( 'wpstackpro_gumroad_integration_enabled' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/gumroad.php' );
		}
		if ( get_option( 'wpstackpro_paypal_integration_enabled' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/paypal.php' );
		}
	}

	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'welcome',
			apply_filters( 'wpstackpro_welcome_dashboard_widget_heading', 'Welcome! 👋' ),
			function() {
				echo apply_filters( 'wpstackpro_welcome_dashboard_text', '<h2>Thank you for being a customer! 🙏</h2>' );
			}
		);

		wp_add_dashboard_widget(
			'support',
			apply_filters( 'wpstackpro_support_dashboard_widget_heading', '🛎 Support' ),
			function() {
				?>
				<p>Need help with anything? Just ask!</p>
				<form id="supportbox">
					<textarea class="large-text" name="request" rows="7" placeholder="I need help on.."></textarea>
					<input type="hidden" name="action" value="support_widget_submission"/>
					<input type="submit" class="button button-primary"/>
				</form>
				<script>
					(function( $ ) {
						$( document ).ready( function() {
							$( '#supportbox' ).submit( function() {
								var $form = $( this );
								if ( !$.trim( $form.find( 'textarea[name=request]' ).val() ) ) {
									alert( 'Please enter your support request before submitting the form.' );
									return false;
								}
								$form.find( 'input[type=submit]' ).val( 'Sending..' );
								$.ajax( {
									url: window.ajaxurl,
									data: $form.serialize(),
									method: 'POST',
									dataType: 'JSON'
								} ).done( function( response ) {
									console.log( response );
									if ( response.success ) {
										$form.find( 'input[type=submit]' ).val( 'Sent!' );
										alert( 'Thanks for reaching out! We will revert at the earliest.' );
										setTimeout( function() {
											$form.find( 'input[type=submit]' ).val( 'Submit' );
											$form.find( 'textarea[name=request]' ).val( '' );
										}, 2000 );
									}
								} ).fail( function( response ) {
									console.log( response );
									alert( 'Something went wrong! Please try again.' );
									$form.find( 'input[type=submit]' ).val( 'Submit' );
								} );
								return false;
							} );
						} );
					})( jQuery );
				</script>
				<?php
			}
		);
	}

	public function feature_tasting() {
		if ( is_user_logged_in() ) {
			wp_redirect( admin_url() );
			die();
		}

		if ( ! isset( $_REQUEST[ 'email' ] ) ) {
			wp_redirect( '/' );
			die();
		}

		$email = $_REQUEST[ 'email' ];
		if ( ! is_email( $email ) ) {
			wp_redirect( '/' );
			die();
		}

		// check for spam bot submission now
		if ( $_REQUEST[ 'bottle' ] == 'wine' ) {
			wp_redirect( admin_url() ); // mimicking the same behavior as of a legit request
			die();
		}

		$user_id = WPStackPro_Helper::create_customer_account( $email );

		if ( is_wp_error( $user_id ) ) {
			if ( $user_id->get_error_code() == 'existing_user_login' ) {
				$url = add_query_arg( 'ux-message', 'You already have an account with us. Please login instead.', home_url( 'wp-login.php' ) );
				$url = add_query_arg( 'ux-email', $email, $url );
				wp_redirect( $url );
			} else {
				wp_redirect( '/' );
			}
			die();
		}

		wp_set_auth_cookie( $user_id, true );
		wp_redirect( admin_url() );
		die();
	}

	public function friction_less_login_handler() {
		global $wpdb;

		if ( is_user_logged_in() ) {
			wp_redirect( admin_url() );
			die();
		}

		$token = urldecode( $_REQUEST[ 'token' ] );

		$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'friction_less_login_token' AND meta_value = '%s';", $token ) );

		if ( $user_id ) {
			wp_set_auth_cookie( $user_id, true );
			delete_user_meta( $user_id, 'friction_less_login_token' );
		}

		wp_redirect( admin_url() );
		die();
	}

	public function support_widget_submission_handler() {
		global $current_user;

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'reason' => 'You need to be logged in to submit a support request' ) );
		}

		$headers = array( 'Reply-To: ' . $current_user->first_name . ' <' . $current_user->user_email . '>' );

		$email_body = $current_user->user_email . ' ' . $current_user->first_name . ' ' . '(User ID - ' . $current_user->ID . ')' . PHP_EOL . PHP_EOL . $_REQUEST[ 'request' ];
		wp_mail( get_option( 'wpstackpro_support_email' ), get_option( 'blogname' ) . ' Support Request', $email_body, $headers );

		wp_send_json_success();
	}
}

new WPStackPro();