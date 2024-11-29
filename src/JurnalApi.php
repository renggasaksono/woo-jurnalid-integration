<?php

namespace Saksono\Woojurnal;

use Saksono\Woojurnal\MekariRequest;

class JurnalApi {

	private $journal_entry_meta_key = '_wji_journal_entry_id';
	private $stock_meta_key = '_wji_stock_adjustment_id';
	private $unpaid_meta_key = '_wji_journal_entry_unpaid_id';
	private $mekariRequest;

	/**
	 * Main table used for storing sync data
	 */
	private const SYNC_TABLE  = 'wji_order_sync_log';

	public function __construct() {
		$this->mekariRequest = new MekariRequest;
	}

	public function getJournalEntryMetaKey() {
		return $this->journal_entry_meta_key;
	}

	public function getStockMetaKey() {
		return $this->stock_meta_key;
	}

	public function getUnpaidMetaKey() {
		return $this->unpaid_meta_key;
	}

	public function getSyncTableName() {
		global $wpdb;
		return $wpdb->prefix . self::SYNC_TABLE;
	}

	public function checkApiKeyValid() {
		
		// Make a simple test API request
		$response = $this->mekariRequest->make(
			'GET',
			'/public/jurnal/api/v1/user_account/profile'
		);
	
		// Check if the response indicates success
		if ($response['success'] && $response['status_code'] === 200) {
			return true;
		}

		return false;
	}

	public function getAllJurnalWarehouses() {

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

	// Used by AjaxCallback:wji_get_jurnal_products_callback()
	public function getAllJurnalItems($params) {

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

	public function postJournalEntry($data) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'POST',
			'/public/jurnal/api/v1/journal_entries',
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
	}

	public function patchJournalEntry($journal_entry_id, $data) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'PATCH',
			'/public/jurnal/api/v1/journal_entries/'.$journal_entry_id,
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
	}

	public function deleteJournalEntry( $journal_entry_id ) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'DELETE',
			'/public/jurnal/api/v1/journal_entries/'.$journal_entry_id,
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
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

	public function getJurnalProductName( $product_id ) {

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

	public function get_paid_sync_data( $order ) {

		$get_options = get_option('wji_account_mapping_options');
		$general_options = get_option('wji_plugin_general_options');
		$accounts = array();

		// Verify account mapping
		if( ! isset($get_options['acc_payment_'.$order->get_payment_method()]) || ! isset($get_options['acc_tax']) || ! isset($get_options['acc_sales']) ) {
			return false;
		}

		// Verify order amount
		if( $order->get_total() == 0 ) {
			return false;
		}

		// Set tax accounts if enable in options
		if( $general_options['include_tax'] == 1 ) {

			// Reference https://woocommerce.github.io/code-reference/classes/WC-Abstract-Order.html#method_get_total
			$accounts = [
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_payment_'.$order->get_payment_method()] ),
					"debit" => $order->get_total(),
				],
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_tax'] ),
					"credit" => $order->get_total_tax(),
				],
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_sales'] ),
					"credit" => $order->get_total() - $order->get_total_tax(),
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
				"transaction_no" => $order->get_id() . '-' . strtoupper( $order->get_formatted_billing_full_name() ) . '-PAID',
				"memo" => get_bloginfo('name').' WooCommerce Order ID: '.$order->get_id(),
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
		$general_options = get_option('wji_plugin_general_options');
		$accounts = array();

		// Verify account mapping
		if( ! isset($get_options['acc_receivable']) || ! isset($get_options['acc_sales']) || ! isset($get_options['acc_tax']) ) {
			return false;
		}

		// Verify order amount
		if( $order->get_total() == 0 ) {
			return false;
		}

		// Set tax accounts if enable in options
		if( $general_options['include_tax'] == 1 ) {

			$accounts = [
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_receivable'] ),
					"debit" => $order->get_total(),
				],
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_tax'] ),
					"credit" => $order->get_total_tax(),
				],
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_sales'] ),
					"credit" => $order->get_total() - $order->get_total_tax(),
				]
			];

		} else {

			$accounts = [
				[
					"account_name" => $this->getJurnalAccountName( $get_options['acc_receivable'] ),
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
				"transaction_no" => $order->get_id() . '-' . strtoupper( $order->get_formatted_billing_full_name() ). '-UNPAID',
				"memo" => get_bloginfo('name').' WooCommerce Order ID: '.$order->get_id(),
				"transaction_account_lines_attributes" => $accounts,
				"tags" => [
					'WooCommerce',
					strtoupper( $order->get_formatted_billing_full_name() )
				]
			)
		);
	}

	public function get_payment_sync_data( $order ) {

		$get_options = get_option('wji_account_mapping_options');
		$accounts = array();

		// Verify account mapping
		if( ! isset($get_options['acc_receivable']) || ! isset($get_options['acc_payment_'.$order->get_payment_method()]) ) {
			return false;
		}

		// Verify order amount
		if( $order->get_total() == 0 ) {
			return false;
		}

		$accounts = [
			[
				"account_name" => $this->getJurnalAccountName( $get_options['acc_payment_'.$order->get_payment_method()] ),
				"debit" => $order->get_total(),
			],
			[
				"account_name" => $this->getJurnalAccountName( $get_options['acc_receivable'] ),
				"credit" => $order->get_total(),
			],
		];

		return array(
			"journal_entry" => array(
				"transaction_date" => $order->get_date_created()->format( 'Y-m-d' ),
				"transaction_no" => $order->get_id() . '-' . strtoupper( $order->get_formatted_billing_full_name() ). '-PAYMENT',
				"memo" => get_bloginfo('name').' WooCommerce Order ID: '.$order->get_id(),
				"transaction_account_lines_attributes" => $accounts,
				"tags" => [
					'WooCommerce',
					strtoupper( $order->get_formatted_billing_full_name() )
				]
			)
		);
	}

	public function getErrorMessages( $response ) {

		if( isset($response->errors) ) {
			$message = json_encode($response->errors);
		} elseif( isset($response->error_full_messages) ) {
			$message = implode(',',$response->error_full_messages);
		} else {
			$message = '';
		}
		return trim($message,'"');
	}
	
}
?>
