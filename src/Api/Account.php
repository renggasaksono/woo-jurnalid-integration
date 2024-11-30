<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class Account {

	private $mekariRequest;

	public function __construct() {
		$this->mekariRequest = new MekariRequest;
	}

	public function getAllJurnalAccounts() {

		// Make request
		$response = $this->mekariRequest->make(
			'GET',
			'/public/jurnal/api/v1/accounts'
		);
	
		// Check if the response indicates success
		if ($response['success'] && $response['status_code'] === 200) {
			if(is_array($response)) {
				$data = json_decode($response['body']);
				$formatted_data = array();
				 foreach ($data->accounts as $key => $account) {
					 $formatted_data[$key]['id'] = $account->id;
					 $formatted_data[$key]['text'] = $account->number.' - '.$account->name;
				 }
				 return $formatted_data;
			}
		}
	
		return false;
	}

	public function getJurnalAccountName( $account_id ) {

		// Make request
		$response = $this->mekariRequest->make(
			'GET',
			'/public/jurnal/api/v1/accounts/'.$account_id
		);

		// Check if the response indicates success
		if ($response['success'] && $response['status_code'] === 200) {
			if(is_array($response)) {
				$data = json_decode($response['body']);
				return isset($data->account->name) ? $data->account->name : false;
			}
		}
		
		return false;
	}
}