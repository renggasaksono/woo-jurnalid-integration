<?php

class WJI_IntegrationAPI {

	private $apikey;
	const 	baseUrl 	= 'https://api.jurnal.id/core/api/v1/';
	private $endpoint;
	private $body;
	private $meta_key = '_wji_journal_entry_id';
	private $stock_meta_key = '_wji_stock_adjustment_id';

	public function __construct() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';
		if($options = get_option('wji_plugin_general_options') ) {
			if($options['api_key']) {
				$this->apikey = $options['api_key'];
			}
		}
	}

	private function getUrl() {
		return self::baseUrl . $this->endpoint;
	}

	private function post() {
		return wp_remote_post( $this->getUrl(), [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'apikey' => $this->apikey
			],
			'method' => 'POST',
			'timeout' => 75,				    
			'body' => $this->body
		]);	
	}

	private function patch() {
		return wp_remote_post( $this->getUrl(), [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'apikey' => $this->apikey
			],
			'method' => 'PATCH',
			'timeout' => 75,				    
			'body' => $this->body
		]);	
	}

	private function get() {
		return wp_remote_get( $this->getUrl(), [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'apikey' => $this->apikey
			],
			'method' => 'GET',
			'timeout' => 75,				    
			'body' => $this->body
		]);	
	}

	private function delete() {
		return wp_remote_request( $this->getUrl(), [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'apikey' => $this->apikey
			],
			'method' => 'DELETE',
			'timeout' => 75,			    
			'body' => $this->body
		]);	
	}

	public function getMetaKey() {
		return $this->meta_key;
	}

	public function getStockMetaKey() {
		return $this->stock_meta_key;
	}

	public function getAllJurnalWarehouses() {

		$this->endpoint = 'warehouses';
		$this->body = '';
		$response = $this->get();
		$response_code = wp_remote_retrieve_response_code( $response );

		if($response_code == 200) {
			$body = json_decode( $response['body'] );
			$formatted_data = array();
			if(isset($body->warehouses)) {
				foreach ($body->warehouses as $key => $wh) {
		 			$formatted_data[$key]['id'] = $wh->id;
		 			$formatted_data[$key]['text'] = $wh->name;
		 		}
		 		return $formatted_data;
			} else {
				return false;
			}
		} elseif ($response_code == 500) {
			wp_die('Internal Server Error');
		}
	}

	public function getAllJurnalItems() {

		$page_size = 25;
		$page = 1;

		// Check if item count is set
		$general_options = get_option('wji_plugin_general_options');
    	if( isset( $general_options[ 'jurnal_item_count' ] ) ) {
    		$page_size = $general_options['jurnal_item_count'];
    	}

		$this->endpoint = 'products';
		$this->body = [
			'page' 		=> $page,
			'page_size'	=> $page_size
		];

		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);
			$formatted_data = array();
	 		foreach ($data->products as $key => $product) {
	 			$formatted_data[$key]['id'] = $product->id;
	 			$formatted_data[$key]['text'] = $product->product_code.' - '.$product->name;
	 		}
	 		return $formatted_data;
		}
		else {
			return $response;
		}
	}

	public function getListItemAjax($param) {

		$page_size = 10;
		$page = 1;

		$this->endpoint = 'products';
		$this->body = [
			'q'		 	=> $param,
			'page' 		=> $page,
			'page_size'	=> $page_size
		];

		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);
			
			$formatted_data = array();
	 		foreach ($data->products as $key => $product) {
	 			$formatted_data[$key]['id'] = $product->id;
	 			$formatted_data[$key]['text'] = $product->name;
	 		}
	 		return $formatted_data;
		}
		else {
			return $response;
		}
	}

	public function postJournalEntry($data) {
		$this->endpoint = 'journal_entries';
		$this->body = json_encode($data);
		$response = $this->post();
		$data = json_decode($response['body']); // Ini outputnya jd object
		return $data;
	}

	public function patchJournalEntry($journal_entry_id, $data) {
		$this->endpoint = 'journal_entries/'.$journal_entry_id;
		$this->body = json_encode($data);
		$response = $this->patch();
		$data = json_decode($response['body']); // Ini outputnya jd object
		return $data;
	}

	public function deleteJournalEntry( $journal_entry_id ) {
		$this->endpoint = 'journal_entries/'.$journal_entry_id;
		$this->body = '';
		$response = $this->delete();
		$data = json_decode($response['body']); // Ini outputnya jd object
		return $data;
	}

	public function postStockAdjustments($data) {
		$this->endpoint = 'stock_adjustments';
		$this->body = json_encode($data);
		$response = $this->post();
		$data = json_decode($response['body']); // Ini outputnya jd object
		return $data;
	}

	public function deleteStockAdjustments( $stock_adj_id ) {
		$this->endpoint = 'stock_adjustments/'.$stock_adj_id;
		$this->body = '';
		$response = $this->delete();
		$data = json_decode($response['body']); // Ini outputnya jd object
		return $data;
	}

	public function getJurnalProductName( $product_id ) {

		$this->endpoint = 'products/'.$product_id;
		$this->body = '';

		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);
	 		return $data->product->name;
		}
		else {
			return $response;
		}
	}

	public function checkApiKeyValid() {
		// Check if any key is set
		if(!$this->apikey) {
			return false;
		}
		// Make a simple test api request
		$this->endpoint = 'user_account/profile';
		$this->body = '';
		$response = $this->get();
		$response_code = wp_remote_retrieve_response_code( $response );
		if($response_code == 200) {
	 		return true;
		} elseif($response_code == 500) {
			wp_die( '<pre>Respon dari Jurnal.ID API: Internal Server Error. Silahkan mencoba kembali beberapa waktu kedepan.</pre>' );
		} else {
			return false;
		}
	}

	public function getAllJurnalAccounts() {

		$this->endpoint = 'accounts';
		$this->body = '';

		$response = $this->get();
		if(is_array($response)) {
			$data = json_decode($response['body']);
			$formatted_data = array();
	 		foreach ($data->accounts as $key => $account) {
	 			$formatted_data[$key]['id'] = $account->id;
	 			$formatted_data[$key]['text'] = $account->number.' - '.$account->name;
	 		}
	 		return $formatted_data;
		}
		else {
			return $response;
		}
	}

	public function getJurnalAccountName( $account_id ) {

		$this->endpoint = 'accounts/'.$account_id;
		$this->body = '';

		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);
	 		return $data->account->name;
		}
		else {
			return $response;
		}
	}

	public function getJournalProducts() {
		// Check cached data exists
		if( false === ( $jurnal_products = get_transient( 'wji_cached_journal_products' ) ) ) {
			$jurnal_products = $this->getAllJurnalItems();

	 		if(is_array($jurnal_products)) {
		 		// Stores data in cache for future uses
		 		set_transient( 'wji_cached_journal_products', $jurnal_products, 1 * DAY_IN_SECONDS );
		 		return $jurnal_products;
		 	} else {
		 		return false;
		 	}
 		} else {
 			return $jurnal_products;
 		}
	}

	public function getSyncTableName() {
		global $wpdb;
		return $wpdb->prefix . 'wji_order_sync_log';
	}

	public function get_paid_sync_data( $order ) {

		$get_options = get_option('wji_account_mapping_options');
		$general_options = get_option('wji_plugin_general_options');
		$accounts = array();

		// Set tax accounts if enable in options
		if( $general_options['include_tax'] ) {

			$order_tax = round($order->get_total() * 0.1);
			$order_total_after_tax = $order->get_total() - $order_tax;

			$accounts = [
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_payment_'.$order->get_payment_method()] ),
					"debit" => $order->get_total(),
				],
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_tax'] ),
					"credit" => $order_tax,
				],
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_sales'] ),
					"credit" => $order_total_after_tax,
				]
			];

		} else {

			$accounts = [
				[
					"account_name" => $get_options['acc_payment_'.$order->get_payment_method()],
					"debit" => $order->get_total(),
				],
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_sales'] ),
					"credit" => $order->get_total(),
				]
			];

		}

		return array(
			"journal_entry" => array(
				"transaction_date" => $order->get_date_created()->format( 'Y-m-d' ),
				"transaction_no" => $order->get_id() . '-' . strtoupper( $order->get_formatted_billing_full_name() ),
				"memo" => 'WooCommerce Order ID: '.$order->get_id(),
				"transaction_account_lines_attributes" => $accounts,
				"tags" => [
					'WooCommerce',
					strtoupper( $order->get_formatted_billing_full_name() )
				]
			)
		);

	}

	public function get_unpaid_sync_data( $order ) {

		$get_options = get_option('wji_account_mapping_options');

		return array(
			"journal_entry" => array(
				"transaction_date" => $order->get_date_created()->format( 'Y-m-d' ),
				"transaction_no" => $order->get_id() . '-' . strtoupper( $order->get_formatted_billing_full_name() ),
				"memo" => 'WooCommerce Order ID: '.$order->get_id(),
				"transaction_account_lines_attributes" => [
					[
						"account_name" => $this->getJurnalAccountName( $get_options['acc_receivable'] ),
						"debit" => $order->get_total(),
					],
					[
						"account_name" => $this->getJurnalAccountName( $get_options['acc_sales'] ),
						"credit" => $order->get_total(),
					]
				],
				"tags" => [
					'WooCommerce',
					strtoupper( $order->get_formatted_billing_full_name() )
				]
			)
		);
	}
	
}
?>
