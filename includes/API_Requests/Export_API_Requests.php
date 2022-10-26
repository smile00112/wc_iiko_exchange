<?php

namespace SkyWeb\WC_Iiko\API_Requests;

defined( 'ABSPATH' ) || exit;

use SkyWeb\WC_Iiko\HTTP_Request;
use SkyWeb\WC_Iiko\Logs;

class Export_API_Requests extends Common_API_Requests {

	/**
	 * Export WooCommerce order to iiko (delivery).
	 *
	 * @return boolean|array
	 */
	public function create_delivery( $delivery, $organization_id = null, $terminal_id = null ) {

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id ) ) {
			$organization_id = $this->organization_id;
		}

		// Take terminal ID from settings if parameter is empty.
		// if ( empty( $terminal_id ) && ! empty( $this->terminal_id ) ) {
		// 	$terminal_id = $this->terminal_id;
		// }

		$this->check_object( $delivery, 'Order is empty.' );

		if ( empty( $delivery ) || false === $access_token || empty( $organization_id ) || empty( $terminal_id ) ) {
			return false;
		}

		$url     = 'deliveries/create';
		$headers = array(
			'Authorization' => $access_token
		);
		$body    = array(
			'organizationId'  => $organization_id,
			'terminalGroupId' => $terminal_id,
			'order'           => $delivery,
		);

		debug_to_file('delivery-request__body');
		debug_to_file( print_R($body, true) );	
		debug_to_file( json_encode($body) );
		
// echo '$body=';
// print_R($body);
// exit;
		Logs::add_wc_debug_log( wp_json_encode( $body ), 'create-delivery-body' );

		return HTTP_Request::remote_post( $url, $headers, $body );
	}



	public function check_delivery( $delivery_id, $organization_id = null ) {

		if(!$delivery_id) return false;

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id ) ) {
			$organization_id = $this->organization_id;
		}

		$url     = 'deliveries/by_id';
		$headers = array(
			'Authorization' => $access_token
		);
		$body    = array(
			'organizationId'  => $organization_id,
			'orderIds' => [ $delivery_id ],
			
		);

		debug_to_file('check_delivery__body');
		debug_to_file( print_R($body, true) );	
		debug_to_file( json_encode($body) );
		
echo '$body=';
print_R($body);
echo json_encode($body);

		//Logs::add_wc_debug_log( wp_json_encode( $body ), 'create-delivery-body' );

		return HTTP_Request::remote_post( $url, $headers, $body );
	}
	/**
	 * Retrieve iiko order by ID.
	 *
	 * @param string $iiko_order_id
	 *
	 * @return boolean|array
	 */
	public function retrieve_order_by_id( $iiko_order_id, $organization_id = null ) {

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id ) ) {
			$organization_id = $this->organization_id;
		}

		if ( false === $access_token || empty( $organization_id ) || empty( $iiko_order_id ) ) {
			return false;
		}

		$url     = 'deliveries/by_id';
		$headers = array(
			'Authorization' => $access_token
		);
		$body    = array(
			'organizationId' => $organization_id,
			'orderIds'       => array( $iiko_order_id )
		);

		$responce  = HTTP_Request::remote_post( $url, $headers, $body );
		return ['request_body' => $body, 'responce_body' => $responce ];
		
	}
}