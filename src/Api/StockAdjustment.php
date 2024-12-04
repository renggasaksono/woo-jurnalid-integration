<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class StockAdjustment {

	private $mekariRequest;

	public function __construct() {
		$this->mekariRequest = new MekariRequest();
	}

	public function create($data) {
		write_log($data);
		$response = $this->mekariRequest->make(
			'POST',
			'/public/jurnal/api/v1/stock_adjustments',
			'',
			$data
		);
		write_log($response);
		return $response;
	}

	public function delete( $stock_adj_id ) {
		$response = $this->mekariRequest->make(
			'DELETE',
			'/public/jurnal/api/v1/stock_adjustments/'.$stock_adj_id
		);
		write_log($response);
		return $response;
	}
}