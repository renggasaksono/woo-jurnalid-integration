<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class Product {

	private $mekariRequest;

	public function __construct() {
		$this->mekariRequest = new MekariRequest();
	}

	// Used by ProductMapping:get_jurnal_products_callback()
	public function getAll($params) {

		$page = isset($params['page']) ? $params['page'] : 1;
		$q = isset($params['q']) ? $params['q'] : '';

		// Get warehouse option
		$general_options = get_option('wji_plugin_general_options');
		$warehouse_id = isset( $general_options[ 'wh_id' ] ) ? $general_options['wh_id'] : '';

		$body = [
			'q'				=> $q,
			'page' 			=> $page,
			'type'			=> 'tracked',
			'warehouse_id'	=> $warehouse_id
		];

		// Use Select2 resources
		$response = $this->mekariRequest->make(
			'GET',
			'/public/jurnal/api/v1/select2_resources/get_product',
			'',
			$body
		);
	
		// Check if the response indicates success
		if ($response['success'] && $response['status_code'] === 200) {
			
			if(is_array($response)) {
				$data = json_decode($response['body']);
				// write_log($data);
				$formatted_data = array();
				$formatted_data['results'] = [];
				 foreach ($data->data as $key => $product) {
					 $formatted_data['results'][$key]['id'] = $product->id;
					 $formatted_data['results'][$key]['text'] = $product->product_code.' - '.$product->name;
				 }
				// write_log(json_encode($formatted_data));
				return json_encode($formatted_data);
			}
		}

		return false;
	}

	public function getProductName( $product_id ) {

		// Make request
		$response = $this->mekariRequest->make(
			'GET',
			'/public/jurnal/api/v1/products/'.$product_id
		);

		// Check if the response indicates success
		if ($response['success'] && $response['status_code'] === 200) {
			if(is_array($response)) {
				$data = json_decode($response['body']);
				return isset($data->product) ? $data->product->name : null;
			}
		}
		
		return null;
	}
}