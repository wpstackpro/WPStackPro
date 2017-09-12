<?php
/*
Plugin Name: WPStackPro
Plugin URI: https://wpstackpro.com
Description: WordPress plugin that serves as a framework to quickly build products
Author: Ashfame
Version: 0.1.2
Author URI: https://ashfame.com/
*/

class WPStackPro {

	public function __construct() {
		// Replace login logo/name url to site's url
		add_filter( 'login_headerurl', function( $url ) {
			return home_url();
		} );

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

		// remove screen options & contextual help links
		add_filter( 'screen_options_show_screen', '__return_false' );
		add_filter( 'contextual_help', function( $old_help, $screen_id, $screen ) {
			$screen->remove_help_tabs();

			return $old_help;
		}, 999, 3 );

		// remove footer text in admin
		add_filter( 'admin_footer_text', '__return_empty_string', 11 );
		add_filter( 'update_footer', '__return_empty_string', 11 );

		// remove admin color scheme options from non-admin users
		add_action( 'init', function() {
			if ( ! current_user_can( 'manage_options' ) ) {
				remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
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
			if ( is_404() ) {
				wp_redirect( '/', 302 );
			}
		} );

		// Add body class in admin as per user roles
		add_filter( 'admin_body_class', function( $css_classes ) {
			$current_user = wp_get_current_user();
			foreach ( $current_user->roles as $role ) {
				$css_classes .= ' useris-' . $role;
			}

			return $css_classes;
		} );

		// Ajax handlers
		add_action( 'wp_ajax_nopriv_feature_tasting', array( $this, 'feature_tasting' ) );
		add_action( 'wp_ajax_nopriv_friction_less_login', array( $this, 'friction_less_login_handler' ) );

		// load other files
		require_once( plugin_dir_path( __FILE__ ) . 'helper.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/gumroad.php' );
	}

	public function feature_tasting() {
		if ( isset( $_REQUEST[ 'email' ] ) ) {
			$email = urldecode( $_REQUEST[ 'email' ] );
			if ( ! is_email( $email ) ) {
				wp_redirect( '/' );
				die();
			}

			$generated_password = wp_generate_password( 10, true, true );
			$user_id            = wp_create_user( $email, $generated_password, $email );

			if ( is_wp_error( $user_id ) ) {
				wp_redirect( '/' );
				die();
			}

			WPStackPro_Helper::send_generated_credentials_to_user_by_email( $user_id, $email, $generated_password );

			wp_set_auth_cookie( $user_id, true );
			wp_redirect( admin_url() );
			die();

		} else {
			wp_redirect( '/' );
			die();
		}
	}

	public function friction_less_login_handler() {
		global $wpdb;

		$token = urldecode( $_REQUEST[ 'token' ] );

		$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'friction_less_login_token' AND meta_value = '%s';", $token ) );

		if ( $user_id ) {
			wp_set_auth_cookie( $user_id, true );
			delete_user_meta( $user_id, 'friction_less_login_token' );
		}

		wp_redirect( admin_url() );
		die();
	}
}

new WPStackPro();