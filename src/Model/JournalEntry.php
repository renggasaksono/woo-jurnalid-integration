<?php

namespace Saksono\Woojurnal\Model;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Model\SyncLog;
use Saksono\Woojurnal\Api\JournalEntry as JournalEntryApi;

class JournalEntry {

	private $journal_entry_meta_key = '_wji_journal_entry_id';
	private $unpaid_meta_key = '_wji_journal_entry_unpaid_id';
	private $api;

	public function __construct()
    {
       $this->api = new JournalEntryApi();
    }

	public function getMetaKey() {
		return $this->journal_entry_meta_key;
	}

	public function getUnpaidMetaKey() {
		return $this->unpaid_meta_key;
	}

    /**
     * Do sync function
     * @param sync_id INT - ID from wji_order_sync_log table
     * @param order_id INT - woocommerce order ID
     * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
     */
    public function create_sync( int $sync_id, int $order_id ) {
        global $wpdb;
        write_log('Sync Jurnal Entry running for ID #'.$sync_id);
        
        $order = wc_get_order( $order_id );
        $data = [];
        $sync_data = [];

		$sync_log = new SyncLog(sync_id: $sync_id);
		$sync_action = $sync_log->getField('sync_action');

		// Prepare data for sync based on action
		switch ($sync_action) {
			case 'JE_PAID':
				$data = $this->prepare_paid_sync_data($order);
				break;
			case 'JE_UNPAID':
				$data = $this->prepare_unpaid_sync_data($order);
				break;
			default:
				write_log("Invalid sync action: $sync_action");
				return false;
		}

        if( empty($data) ) {
            return false;
        }

        $run_sync = $this->api->create($data);

        if( isset($run_sync['success']) && $run_sync['success']==true ) {
            $body = json_decode($run_sync['body']);
            
            update_post_meta( $order->get_id(), $this->getMetaKey(), $body->journal_entry->id );
            update_post_meta( $order->get_id(), $this->getUnpaidMetaKey(), $body->journal_entry->id );
            // $order->update_meta_data( $this->getMetaKey(), $body->journal_entry->id );
            // $order->update_meta_data( $this->getUnpaidMetaKey(), $body->journal_entry->id );
            
            // Success
            $sync_data = [
                'jurnal_entry_id' => $body->journal_entry->id,
                'sync_status' => 'SYNCED',
                'sync_note' => '',
                'sync_data' => json_encode( $data ),
                'sync_at' => date("Y-m-d H:i:s")
            ];
        } else {
            // Failed
            $sync_data = [
                'sync_status' => 'ERROR',
                'sync_data' => json_encode( $data ),
                'sync_note' => $run_sync['body'] ?? 'Unknown error during API call'
            ];
        }

        return $sync_log->update($sync_data);
    }

    /**
     * Delete sync function
     * @param sync_id INT - ID from wji_order_sync_log table
     * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
     */
    public function remove_sync( int $sync_id ) {
        global $wpdb;
        write_log('Remove sync journal entry #'.$sync_id);
        
		$sync_log = new SyncLog(sync_id: $sync_id);
        $run_sync = $this->api->delete( $sync_log->getField('jurnal_entry_id') );

        if( isset($run_sync['success']) && $run_sync['success']==true ) {
            $sync_data = [
                'sync_status'   => 'SYNCED',
                'sync_note' => '',
                'sync_at'       => date("Y-m-d H:i:s")
            ];

        } else {
            $sync_data = [
                'sync_status'   => 'ERROR',
                'sync_note'     => $run_sync['body']
            ];
        }

        return $sync_log->update($sync_data);
    }

	/**
	 * Prepare data for paid journal entry sync.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return array|null Prepared data or null if validation fails.
	 */
	private function prepare_paid_sync_data($order)
	{
        $getMeta = get_post_meta($order->get_id(),$this->getUnpaidMetaKey(), true);
        // $getMeta = $order->get_meta($this->getUnpaidMetaKey(), true)
		// Check if unpaid journal entry exists
        if (!$getMeta) {
			return $this->api->get_paid_sync_data($order);
		}
		return $this->api->get_payment_sync_data($order);
	}

	/**
	 * Prepare data for unpaid journal entry sync.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return array|null Prepared data or null if validation fails.
	 */
	private function prepare_unpaid_sync_data($order)
	{
		return $this->api->get_unpaid_sync_data($order);
	}
}