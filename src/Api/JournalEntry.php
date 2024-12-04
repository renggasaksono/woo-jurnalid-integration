<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;
use Saksono\Woojurnal\Api\Account as AccountApi;

class JournalEntry {

	private $mekariRequest;
	private $account_api;

	public function __construct() {
		$this->mekariRequest = new MekariRequest();
		$this->account_api = new AccountApi();
	}

	public function create($data) {
		write_log($data);
		$response = $this->mekariRequest->make(
			'POST',
			'/public/jurnal/api/v1/journal_entries',
			'',
			$data
		);
		write_log($response);
		return $response;
	}

	public function delete( $journal_entry_id ) {
		$response = $this->mekariRequest->make(
			'DELETE',
			'/public/jurnal/api/v1/journal_entries/'.$journal_entry_id,
		);
		write_log($response);
		return $response;
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
		if( $general_options['include_tax'] == true ) {

			// Reference https://woocommerce.github.io/code-reference/classes/WC-Abstract-Order.html#method_get_total
			$accounts = [
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_payment_'.$order->get_payment_method()] ),
					"debit" => $order->get_total(),
				],
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_tax'] ),
					"credit" => $order->get_total_tax(),
				],
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_sales'] ),
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
					"account_name" => $this->account_api->getAccountName( $get_options['acc_sales'] ),
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
		if( $general_options['include_tax'] == true ) {

			$accounts = [
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_receivable'] ),
					"debit" => $order->get_total(),
				],
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_tax'] ),
					"credit" => $order->get_total_tax(),
				],
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_sales'] ),
					"credit" => $order->get_total() - $order->get_total_tax(),
				]
			];

		} else {

			$accounts = [
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_receivable'] ),
					"debit" => $order->get_total(),
				],
				[
					"account_name" => $this->account_api->getAccountName( $get_options['acc_sales'] ),
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
				"account_name" => $this->account_api->getAccountName( $get_options['acc_payment_'.$order->get_payment_method()] ),
				"debit" => $order->get_total(),
			],
			[
				"account_name" => $this->account_api->getAccountName( $get_options['acc_receivable'] ),
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
}