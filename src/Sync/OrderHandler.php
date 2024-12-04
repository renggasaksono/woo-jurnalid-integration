<?php

namespace Saksono\Woojurnal\Sync;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Model\JournalEntry;
use Saksono\Woojurnal\Model\StockAdjustment;
use Saksono\Woojurnal\Model\SyncLog;

class OrderHandler {

    private $journal_entry;
    private $stock_adjustment;
    private $sync_log;
    private $options;
    
	public function __construct()
    {
        $this->options = get_option('wji_plugin_general_options');
        $this->journal_entry = new JournalEntry();
        $this->stock_adjustment = new StockAdjustment();
        $this->sync_log = new SyncLog();

        add_action( 'woocommerce_thankyou', [$this, 'run_sync'], 10, 1 );
        add_action( 'woocommerce_order_status_processing', [$this, 'run_sync'], 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', [$this, 'remove_sync'], 10, 1 );
    }

	/**
     * Run Sync Process
     *
     * @param int $order_id WooCommerce Order ID.
     */
    public function run_sync( $order_id ) {
        global $wpdb;
        write_log('Running sync for Order #'.$order_id);

        $order = wc_get_order( $order_id );
        if (!$order) return;

        $sync_action = $order->is_paid() ? 'JE_PAID' : 'JE_UNPAID';
        $sync_exists = $this->sync_log->check_sync_exists($order_id, $sync_action);

        if (!$sync_exists) {
            $sync_data = [
                'wc_order_id' => $order_id,
                'sync_action' => $sync_action,
                'sync_status' => 'PENDING'
            ];
            $sync_log = $this->sync_log->create($sync_data);
            $this->journal_entry->create_sync( $sync_log->getField('id'), $order_id );
        }

        if ($this->options['sync_stock']==true) {
            $sync_exists = $this->sync_log->check_sync_exists($order_id, 'SA_CREATE');
            if (!$sync_exists) {
                $sync_data = [
                    'wc_order_id' => $order_id,
                    'sync_action' => 'SA_CREATE',
                    'sync_status' => 'PENDING'
                ];
                $sync_log = $this->sync_log->create($sync_data);
                $this->stock_adjustment->create_sync( $sync_log->getField('id'), $order_id );
            }
        }
    }

    /**
     * Remove sync process
     *
    */
    public function remove_sync( $order_id ) {
        global $wpdb;
        write_log('Removing sync for Order #'.$order_id);

        $order = wc_get_order( $order_id );
        
        if( $journal_entry_id = $order->get_meta($this->journal_entry->getMetaKey(), true)  ) {
        // if( $journal_entry_id = get_post_meta( $order->get_id(), $this->journal_entry->getUnpaidMetaKey(), true )  ) {
            $sync_data = [
                'wc_order_id' => $order_id,
                'jurnal_entry_id' => $journal_entry_id,
                'sync_action' => 'JE_DELETE',
                'sync_status' => 'PENDING'
            ];
            $sync_log = $this->sync_log->create($sync_data);
            $this->journal_entry->remove_sync( $sync_log->getField('id') );
        }

        // if( $stock_adjustment_id = get_post_meta( $order->get_id(), $this->stock_adjustment->getMetaKey(), true )  ) {
        if( $stock_adjustment_id = $order->get_meta($this->stock_adjustment->getMetaKey(), true)  ) {
            $sync_data = [
                'wc_order_id' => $order_id,
                'stock_adj_id' => $stock_adjustment_id,
                'sync_action' => 'SA_DELETE',
                'sync_status' => 'PENDING'
            ];
            $sync_log = $this->sync_log->create($sync_data);
            $this->stock_adjustment->remove_sync( $sync_log->getField('id') );
        }
    }
}