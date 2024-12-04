<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class Warehouse {

	private $mekariRequest;

	public function __construct() {
		$this->mekariRequest = new MekariRequest();
	}

	public function getAll() {

		// Make request
		$response = $this->mekariRequest->make(
			'GET',
			'/public/jurnal/api/v1/warehouses'
		);
	
		// Check if the response indicates success
		if ($response['success'] && $response['status_code'] === 200) {

			$body = json_decode( $response['body'] );
			$formatted_data = array();

			if(isset($body->warehouses)) {
				foreach ($body->warehouses as $key => $wh) {
		 			$formatted_data[$key]['id'] = $wh->id;
		 			$formatted_data[$key]['text'] = $wh->name;
		 		}
		 		return $formatted_data;
			}
		}

		return false;
	}
}