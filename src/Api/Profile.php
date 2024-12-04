<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class Profile {

	private $mekariRequest;

	public function __construct() {
		$this->mekariRequest = new MekariRequest();
	}

	public function getProfile() {
		
		// Make a simple test API request
		$response = $this->mekariRequest->make(
			'GET',
			'/public/jurnal/api/v1/user_account/profile'
		);
	
		// Check if the response indicates success
		if ($response['success'] && $response['status_code'] === 200) {

			// Update option value
			update_option( 'wji_plugin_api_valid', true );
			$body = json_decode($response['body']);
			if ( $full_name = $body->profile->full_name )
				update_option( 'wji_plugin_profile_full_name', $full_name );
			
			return true;
		}

		return false;
	}
}