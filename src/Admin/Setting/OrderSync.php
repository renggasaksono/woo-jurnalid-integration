<?php

namespace Saksono\Woojurnal\Admin\Setting;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\JurnalApi;
use Saksono\Woojurnal\Admin\TableList;

class OrderSync {

    public function __construct()
    {
        add_action('admin_init', [$this, 'initialize_order_sync']);
	}

    /**
     * Initializes plugin's order sync log section
     *
     */
    public function initialize_order_sync() {
    
        add_settings_section(
            'order_sync_section',               // ID used to identify this section and with which to register options
            '',                                 // Title to be displayed on the administration page
            [$this, 'order_sync_callback'],     // Callback used to render the description of the section
            'wji_plugin_order_sync_options'     // Page on which to add this section of options
        );
    }

    /* ------------------------------------------------------------------------ *
    * Section Callbacks
    * ------------------------------------------------------------------------ */

    public function order_sync_callback() {
        global $wpdb;

        $tablelist = new TableList();
        $api = new JurnalApi();
    
        // Retry sync function
        if ( isset($_GET['_wjinonce']) || wp_verify_nonce( isset($_GET['_wjinonce']), 'retry_sync' ) || is_numeric( isset($_GET[ '_syncid' ]) ) ) {
            
            write_log('Retry sync initiated ...');
            $sync_id = sanitize_key($_GET['_syncid']);
    
            // Get sync data
            $sync_data = $wpdb->get_row(
                $wpdb->prepare( "SELECT * from {$api->getSyncTableName()} where id = %d",
                    $sync_id
                )
            );
    
            if( $sync_data->sync_status != 'SYNCED' ) {
    
                // Get sync action
                $sync_action = $sync_data->sync_action;
                $order_id = $sync_data->wc_order_id;
    
                // Run sync process
                switch ( $sync_action ) {
                    case "JE_CREATE":
                    case "JE_UPDATE":
                    case 'JE_PAID':
                    case 'JE_UNPAID':
                        wji_sync_journal_entry( (int) $sync_id, (int) $order_id );
                        break;
                    case "SA_CREATE":
                        wji_sync_stock_adjustment( (int) $sync_id, (int) $order_id );
                        break;
                    case "JE_DELETE":
                        wji_desync_journal_entry( (int) $sync_id, (int) $order_id );
                        break;
                    case "SA_DELETE":
                        wji_desync_stock_adjustment( (int) $sync_id, (int) $order_id );
                        break;
                }
    
                // Remove retry sync url args
                function remove_retry_sync_query_args( $args ) {
                    $args[] = '_wjinonce';
                    $args[] = '_syncid';
                    return $args;
                }
                add_filter( 'removable_query_args', 'remove_retry_sync_query_args', 10, 1 );
            }
        }
    
        // Render table
        $data = [];
        $offset = $tablelist->getPerpage() * ($tablelist->get_pagenum() - 1);
        $where = ( isset($_GET['sync_status']) && $_GET['sync_status'] !== '' ) ? 'where sync_status="'.sanitize_text_field($_GET['sync_status']).'"' : '';
    
        $products = $wpdb->get_results("select * from {$api->getSyncTableName()} {$where} order by created_at desc limit {$tablelist->getPerpage()} offset {$offset}");
        $count = $wpdb->get_var("select count(id) from {$api->getSyncTableName()} {$where}");
        $tablelist->setTotalItem($count);
        $tablelist->setDatas($products);
        $tablelist->setColumns([
            'id'                => '#',
            'wc_order_id'       => 'Order',
            'sync_action'       => 'Task',
            'sync_status'       => 'Status',
            'sync_note'         => 'Message',
            'sync_at'           => 'Date'
        ]);
        
        $tablelist->generate();
    }
}