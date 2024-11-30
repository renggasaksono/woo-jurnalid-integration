<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class StockAdjustment {

	private $mekariRequest;

	public function __construct() {
		$this->mekariRequest = new MekariRequest;
	}

	public function postStockAdjustments($data) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'POST',
			'/public/jurnal/api/v1/stock_adjustments',
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
	}

	public function deleteStockAdjustments( $stock_adj_id ) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'DELETE',
			'/public/jurnal/api/v1/stock_adjustments/'.$stock_adj_id,
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
	}
}