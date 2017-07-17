<?php


if( !class_exists('FPD_Rest_Routes') ) {

	class FPD_Rest_Routes {

		const MAX_RESULTS = 20;

		/**
		 * Register the routes for the objects of the controller.
	 	*/
	 	public function __construct() {

		 	$version = '1';
		 	$namespace = 'fpd/v' . $version;

		 	$disable_default_cors = get_option('fpd_rest_disable_default_cors', false);
		 	if( !empty($disable_default_cors) ) {
			 	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
			 	//add_filter( 'rest_pre_serve_request', array( &$this, 'set_cors_header'), 15 );
		 	}

	 		register_rest_route( $namespace, '/order', array(
				'methods' => 'GET',
				'callback' => array( &$this, 'get_all_orders'),
				'permission_callback' => function () {
					return current_user_can( Fancy_Product_designer::CAPABILITY );
				}
			) );

			register_rest_route( $namespace, '/order/(?P<id>[\d]+)', array(
				'methods' => 'GET',
				'callback' => array( &$this, 'get_single_order'),
				'permission_callback' => function () {
					return current_user_can( Fancy_Product_designer::CAPABILITY );
				}
			) );

		}

		public function set_cors_header($value) {

			$allowed_origins = array(
				'http://admin.fancyproductdesigner.com',
				'https://admin.fancyproductdesigner.com',
			);

			$origin = get_http_origin();
			if ( $origin && in_array( $origin, $allowed_origins ) ) {
				header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
				header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
				header( 'Access-Control-Allow-Credentials: true' );
			}

			return $value;

		}

		public function get_all_orders( WP_REST_Request $request ) {

			global $wpdb;

			$order_type = $request->get_param('type'); //load orders by type: wc | shortcode
			$page = $request->get_param('page') ? $request->get_param('page') : 1; //pagination
			$offset = ( $page - 1 ) * self::MAX_RESULTS;

			//get shortcode orders
			$shortcode_orders = array();
			if( fpd_table_exists(FPD_ORDERS_TABLE) && (is_null($order_type) || $order_type === 'shortcode') ) {

				$shortcode_orders = $wpdb->get_results("
					SELECT ID, created_date FROM ".FPD_ORDERS_TABLE."
					ORDER BY ID DESC
					LIMIT ".self::MAX_RESULTS."
					OFFSET $offset
				", ARRAY_A);

			}

			//get woocommerce orders
			$wc_orders = array();
			if( class_exists('WooCommerce') && (is_null($order_type) || $order_type === 'wc') ) {

				$wc_orders = $wpdb->get_results("
					SELECT ID,post_date AS created_date FROM {$wpdb->prefix}posts
					WHERE ID IN(
						SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items
						WHERE order_item_id IN (
							SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = 'fpd_data'
						)
						GROUP BY order_id
					)
					AND post_status NOT LIKE 'trash'
					ORDER BY ID DESC
					LIMIT ".self::MAX_RESULTS."
					OFFSET $offset
				", ARRAY_A);

			}

			//get gravity form orders
			$gf_orders = array();
			if( class_exists('GFForms') && (is_null($order_type) || $order_type === 'gf') ) {

				$gf_orders = $wpdb->get_results("
					SELECT lead_id AS ID FROM {$wpdb->prefix}rg_lead_detail
					WHERE value LIKE '%{\"product\"%'
					ORDER BY ID DESC
					LIMIT ".self::MAX_RESULTS."
					OFFSET $offset
					",ARRAY_A);

				foreach($gf_orders as $key => $gf_order) {

					$gf_orders[$key]['created_date'] = $wpdb->get_var("SELECT date_created AS ID FROM {$wpdb->prefix}rg_lead WHERE id=".$gf_order['ID']."");

				}

			}

			//merge wc and shortcode orders
			$all_orders = array_merge($shortcode_orders, $wc_orders, $gf_orders);

			return new WP_REST_Response( $all_orders, 200 );

		}

		public function get_single_order( WP_REST_Request $request ) {

			$id = $request->get_param('id');
			$order_type = $request->get_param('type');
			$order_type = empty($order_type) ? 'wc' : $order_type;

			$response_data = array();

			$order = null;
			if( class_exists('WooCommerce') && $order_type === 'wc' ) {

				$response_data['ID'] = $id;

				$wc_order = wc_get_order($id);
				if( $wc_order ) {

					$order = get_post($request->get_param('id'), ARRAY_A);
					$response_data['created_date'] = $order['post_date'];
					$items = $wc_order->get_items();
					$response_data['items'] = array();
					foreach($items as $item_id => $item) {
						$fpd_data = $wc_order->get_item_meta($item_id, 'fpd_data', true);
						if($fpd_data && !empty($fpd_data)) {
							$response_data['items'][$item_id]['title'] = $item['name'];

							$order = $fpd_data['fpd_product'];
							json_decode($order);
							if (json_last_error() !== JSON_ERROR_NONE)
								$order = preg_replace("~\\\\+([\"\'\\x00\\\\])~", "$1", $order);

							$response_data['items'][$item_id]['order'] = $order;
						}

					}

				}
				else {
					$response_data = null;
				}

			}
			//gf order
			else if( class_exists('GFForms') && $order_type === 'gf' ) {

				global $wpdb;

				$order = $wpdb->get_row( "SELECT lead_id, value FROM {$wpdb->prefix}rg_lead_detail WHERE lead_id=$id AND value LIKE '%{\"product\"%'");

				if( !empty($order) ) {

					$order_data =  $this->replaceSingleToDoubleQuotes($order->value);
					$response_data = [
						'ID' => $order->lead_id,
						'order' => $order_data,
					];

				}
				else {
					$response_data = null;
				}

			}
			//shortcode order
			else if( fpd_table_exists(FPD_ORDERS_TABLE)  && $order_type === 'shortcode' ) {

				global $wpdb;

				$order = $wpdb->get_row( "SELECT * FROM ".FPD_ORDERS_TABLE." WHERE ID=$id");

				if( !empty($order) ) {

					$order_data = isset($order->views) ? $order->views : $order->order;
					$order_data =  $this->replaceSingleToDoubleQuotes($order_data);

					$response_data = [
						'ID' => $order->ID,
						'created_date' => $order->created_date,
						'order' => $order_data,
					];

				}
				else {
					$response_data = null;
				}

			}

			if( !empty( $response_data ) ) {
				return new WP_REST_Response( $response_data, 200 );
			}
			else {
				return new WP_Error( '404', 'Order not found!' );
			}

		}

		private function replaceSingleToDoubleQuotes( $str ) {

			return stripslashes( str_replace(chr(39), chr(34), $str) );

		}

	}

}

new FPD_Rest_Routes();

?>