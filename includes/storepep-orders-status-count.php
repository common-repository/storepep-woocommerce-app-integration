<?php

class Storepep_Orders_Status_Count extends WC_REST_Controller {
	/**
	 * Register the routes for Count for different order statuses.
	 */
	public function register_routes() {
		register_rest_route( 'wc/storepep/v1', '/ordersStatusCount/' , array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		) );
    }

    public function get_items( $request = array() ) {
		/* Check whether dates have been passed as parameters */
		if ( empty($request['date_min']) || empty($request['date_max']) ){
			return array( "code" => "storepep_cannot_view", "message" => "Date limits missing" );
		};

        $ordersStatusCount = array();
		global $wpdb;
		
		/* Create Query for retrieving from database  */
		$initQuery = "
		SELECT post_status , count(*) as count
		FROM ".$wpdb->prefix."posts
		WHERE 
		post_type = 'shop_order'
		AND post_date >= '".$request['date_min']." 00:00:00' 
		AND post_date <= '".$request['date_max']." 23:59:59' 
		AND post_status NOT IN ( 'trash' , 'auto-draft' )
		GROUP BY post_status
		";

		$responseData = $wpdb->get_results($initQuery);
		foreach($responseData as $category){
			$status = str_replace( 'wc-' , '' , $category->post_status );
			$ordersStatusCount[ $status ] = $category->count;
		}
		return $ordersStatusCount;
    }

	/**
	 * Check whether a given request has permission to read reports.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! wc_rest_check_manager_permissions( 'reports', 'read' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

}

?>