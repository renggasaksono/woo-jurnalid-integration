<?php

namespace Saksono\Woojurnal\Sync;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class OrderHandler {

	public function __construct()
    {
        add_action( 'woocommerce_thankyou', [$this, 'wji_run_sync_process'], 10, 1 );
        add_action( 'woocommerce_order_status_processing', [$this, 'wji_run_sync_process'], 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', [$this, 'wji_update_order_cancelled'], 10, 1 );
    }

	/**
     * Fungsi untuk menjalankan proses sync
     *
    */
    public function wji_run_sync_process( $order_id ) {
        global $wpdb;
        
        $api = new \Saksono\Woojurnal\Api\JurnalApi();
        $options = get_option('wji_plugin_general_options');

        // Check if order is paid
        $order = wc_get_order( $order_id );

        if( $order->is_paid() ) {
            
            // Validate apakah sudah pernah sync
            $order_sync_where = 'WHERE wc_order_id='.$order_id.' AND sync_status="SYNCED" AND sync_action="JE_PAID"';
            $get_order_sync = $wpdb->get_row("SELECT * FROM {$api->getSyncTableName()} {$order_sync_where}");
            
            if( ! $get_order_sync ) {

                // Create new sync action
                $wpdb->insert( $api->getSyncTableName(), [
                    'wc_order_id' => $order_id,
                    'sync_action' => 'JE_PAID',
                    'sync_status' => 'PENDING'
                ]);

                // Run sync process
                wji_sync_journal_entry( $wpdb->insert_id, $order_id );
            }

        } else {
            
            // Validate apakah sudah pernah sync
            $order_sync_where = 'WHERE wc_order_id='.$order_id.' AND sync_status="SYNCED" AND sync_action="JE_UNPAID"';
            $get_order_sync = $wpdb->get_row("SELECT * FROM {$api->getSyncTableName()} {$order_sync_where}");
            
            if( ! $get_order_sync ) {

                // Create new sync action
                $wpdb->insert( $api->getSyncTableName(), [
                    'wc_order_id' => $order_id,
                    'sync_action' => 'JE_UNPAID',
                    'sync_status' => 'PENDING'
                ]);

                // Run sync process
                wji_sync_journal_entry( $wpdb->insert_id, $order_id );
            }
        }  

        // 2. Sync stock adjustment if enabled
        if( isset($options['sync_stock']) ) {

            // Validate apakah sudah pernah sync
            $stock_sync_where = 'WHERE wc_order_id='.$order_id.' AND sync_status="SYNCED" AND sync_action="SA_CREATE"';
            $get_stock_sync = $wpdb->get_row("SELECT * FROM {$api->getSyncTableName()} {$stock_sync_where}");
            
            if( ! $get_stock_sync ) {
                
                // Add new sync record if haven't synced yet
                $wpdb->insert( $api->getSyncTableName(), [
                    'wc_order_id' => $order_id,
                    'sync_action' => 'SA_CREATE',
                    'sync_status' => 'PENDING'
                ]);

                // Run sync process
                wji_sync_stock_adjustment( $wpdb->insert_id, $order_id );
            }
        }
    }

    /**
     * Fungsi ini dipanggil setelah order status berubah menjadi Cancelled
     *
    */
    public function wji_update_order_cancelled( $order_id ) {
        global $wpdb;
        write_log('Update order cancelled Order #'.$order_id);

        $order = wc_get_order( $order_id );
        $api = new \Saksono\Woojurnal\Api\JurnalApi();
        
        // Check if order meta exists
        if( $journal_entry_id = $order->get_meta( $api->getJournalEntryMetaKey(), true )  ) {

            // 1. Add delete journal entry sync record
            $wpdb->insert( $api->getSyncTableName(), [
                'wc_order_id' => $order_id,
                'jurnal_entry_id' => $journal_entry_id,
                'sync_action' => 'JE_DELETE',
                'sync_status' => 'PENDING'
            ]);

            $desync_journal_entry_id = $wpdb->insert_id;

            // Run desync process
            $run_desync_journal_entry = wji_desync_journal_entry( $desync_journal_entry_id, $order_id );

            // 2. Check apakah ada data stock adjustment
            if( $stock_adjustment_id = $order->get_meta( $api->getStockMetaKey(), true )  ) {

                // Add delete stock adjusment sync record
                $wpdb->insert( $api->getSyncTableName(), [
                    'wc_order_id' => $order_id,
                    'stock_adj_id' => $stock_adjustment_id,
                    'sync_action' => 'SA_DELETE',
                    'sync_status' => 'PENDING'
                ]);

                $desync_stock_adjustment_id = $wpdb->insert_id;

                // Run desync process
                $run_desync_stock_adjustment = wji_desync_stock_adjustment( $desync_stock_adjustment_id, $order_id );
            }
        }
    }
}