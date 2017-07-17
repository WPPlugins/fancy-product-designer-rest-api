<?php
/*
Plugin Name: Fancy Product Designer REST API
Plugin URI: http://fancyproductdesigner.com/
Description: A REST-API Implementation to use with Fancy Product Designer - Admin.
Version: 1.1.7
Author: Rafael Dery | radykal.de
Author URI: http://fancyproductdesigner.com
*/

if( !class_exists('FPD_Rest_Api') ) {

	class FPD_Rest_Api {

		private $dependencies = array();
		private $rest_repsonse = null;

		public function __construct() {

			add_action( 'plugins_loaded', array( &$this,'plugins_loaded' ) );
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( &$this, 'add_action_links') );
			add_action( 'admin_notices',  array( &$this, 'display_admin_notices' ) );

			//Settings
			add_filter( 'fpd_settings_blocks', array( &$this,'register_settings' ) );
			add_action( 'fpd_block_options_end', array(&$this, 'add_block_options') );
			add_action( 'radykal_save_options', array( &$this, 'options_saved') );
			add_action( 'radykal_settings_footer_end', array( &$this, 'settings_footer_end') );

			//REST API Filters & Hooks
			add_action( 'rest_api_init', array( &$this, 'register_routes') );
			add_filter( 'authenticate', array( &$this, 'authenticate' ), 10, 3 );
			add_filter( 'determine_current_user', array( &$this, 'rest_api_auth_handler' ), 20 );
			add_filter( 'wp_rest_server_class', array( &$this, 'wp_rest_server_class' ) );
			add_filter( 'rest_request_before_callbacks', array( &$this, 'set_rest_response' ) );

		}

		public function plugins_loaded() {

			if( !class_exists('Fancy_Product_Designer') )
				$this->dependencies['http://fancyproductdesigner.com'] = 'Fancy Product Designer for WordPress';

		}

		public function add_action_links( $links ) {

			$mylinks = array(
				'<a href="' . admin_url( 'admin.php?page=fpd_settings&tab=general#rest-api' ) . '">Settings</a>',
			);
			return array_merge( $links, $mylinks );

		}

		public function register_settings( $settings ) {

			$settings['general']['rest-api'] = __('REST API', 'radykal');

			return $settings;

		}

		public static function get_options( ) {

			$options['rest-api'] = array(

				array(
						'title' => __( 'Disable Default CORS Header', 'radykal' ),
						'description' => __('This is sometimes useful if you already enabled the CORS for admin.fancyproductdesigner.com anywhere else (.htaccess, NGINX config).', 'radykal'),
						'id' 		=> 'fpd_rest_disable_default_cors',
						'default'	=> 'no',
						'type' 		=> 'radio',
						'options'   => array(
							'yes'	 => __( 'Yes', 'radykal' ),
							'no'	 => __( 'No', 'radykal' ),
						)
					),

				array(
					'title' => __( 'Authentication Password', 'radykal' ),
					'description' 		=> __( 'Set a password for the authentication. It is recommended to choose another password than the one you are using to log into your WordPress site.', 'radykal' ),
					'id' 		=> 'fpd_rest_auth_password',
					'css' 		=> 'width:500px; margin-right: 0; border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none;',
					'default'	=> '',
					'type' 		=> 'text'
				),

			);

			return $options;

		}

		public function add_block_options() {

			$options = self::get_options();
			FPD_Settings::$radykal_settings->add_block_options( 'rest-api', $options['rest-api']);

		}

		public function options_saved( $tab ) {


			if( $tab == 'general') {

				if( isset($_POST['fpd_rest_auth_password']) ) {

					$rest_auth_pw = $_POST['fpd_rest_auth_password'];

					if( empty($rest_auth_pw) ) {
						delete_option( 'fpd_rest_auth_password_hashed' );
					}
					else {
						update_option( 'fpd_rest_auth_password_hashed', wp_hash_password($rest_auth_pw) );
					}

				}

			}

		}

		public function settings_footer_end( $page_id ) {

			?>

			<script type="text/javascript">
				jQuery(document).ready(function() {

					var $passwordInput = jQuery('#fpd_rest_auth_password');

					$passwordInput.after('<span id="fpd-rest-toggle-password" class="button secondary" style="height: 26px; border-top-left-radius: 0;border-bottom-left-radius: 0; box-shadow: none;">Show/Hide</span>');

					jQuery('#fpd-rest-toggle-password').click(function(evt) {

						$passwordInput.attr('type', $passwordInput.attr('type') == 'text' ? 'password' : 'text');

					})

				});

			</script>

			<?php

		}

		public function register_routes() {

			if( empty($this->dependencies) )
				require_once( 'class-rest-routes.php' );

		}

		public function display_admin_notices() {

			foreach($this->dependencies as $url => $plugin): ?>

				<div class="error">
		        	<p>
			        	<a href="<?php echo $url; ?>" target="_blank"><?php echo $plugin; ?></a>
			        <?php _e( ' plugin is required for Fancy Product Designer - REST API plugin!', 'radykal' ); ?>
			        </p>
		    	</div>

			<?php
			endforeach;

		}

		public function rest_api_auth_handler( $input_user ) {

			// Don't authenticate twice
			if ( !empty( $input_user ) ) {
				return $input_user;
			}

			//only use this authentication for rest api
			$api_request = ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
			if ( ! apply_filters( 'application_password_is_api_request', $api_request ) ) {
				return $input_user;
			}

			//check if auth_user or auth_pw is not set in header
			if( !isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ) {

				//check if http basic auth is in header
				if( ( isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION']) ) ||
					( isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])  && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ) )
								{
					$http_auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
					//get user and pw from basic auth
					list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':' , base64_decode(substr($http_auth, 6)));
				}

			}

			//If auth username is not available in header, throw error
			if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
				$this->rest_repsonse = new WP_Error( 'rest_missing_header', __( 'The "HTTP_AUTHORIZATION" or "REDIRECT_HTTP_AUTHORIZATION" is missing in the global variable $_SERVER on your server. Be sure you added the RewriteRule as mentioned in the documentation. If you did that, please contact your provider and ask him how to do make one of these available, otherwise authentication is not working.', 'radykal' ));
				return $input_user;
			}

			$user = self::authenticate( $input_user, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
			if ( is_a( $user, 'WP_User' ) ) {
				return $user->ID;
			}

			// If it wasn't a user what got returned, just pass on what we had received originally.
			return $input_user;

		}

		public function authenticate( $input_user, $username, $password ) {

			$user = get_user_by( 'login',  $username );

			// If the login name is invalid, short circuit.
			if ( ! $user ) {
				return $input_user;
			}

			$hashed_password = get_option( 'fpd_rest_auth_password_hashed', false );
			if ( $hashed_password && wp_check_password( $password, $hashed_password, $user->ID ) ) {
				return $user;
			}

			// By default, return what we've been passed.
			return $input_user;

		}

		public function wp_rest_server_class( $class ) {

			global $current_user;
			if ( defined( 'REST_REQUEST' )
			     && REST_REQUEST
			     && $current_user instanceof WP_User
			     && 0 === $current_user->ID ) {
				$current_user = null;
			}

			return $class;

		}

		public function set_rest_response( $response ) {

			if( !is_null( $this->rest_repsonse ) )
				$response = $this->rest_repsonse;

			return $response;

		}

	}

}

new FPD_Rest_Api();

?>