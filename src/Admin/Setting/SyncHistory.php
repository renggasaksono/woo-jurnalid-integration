<?php

namespace Saksono\Woojurnal\Admin\Setting;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Admin\TableList;
use Saksono\Woojurnal\Model\SyncLog;
use Saksono\Woojurnal\Model\JournalEntry;
use Saksono\Woojurnal\Model\StockAdjustment;

class SyncHistory {

    private $journal_entry;
    private $stock_adjustment;
    private $sync_log;

    public function __construct()
    {
        $this->journal_entry = new JournalEntry();
        $this->stock_adjustment = new StockAdjustment();
        $this->sync_log = new SyncLog();

        add_action('admin_init', [$this, 'initialize_order_sync']);
        add_filter('removable_query_args', [$this, 'remove_retry_sync_query_args'], 10, 1);        
	}

    /**
     * Initializes plugin's order sync log section
     *
     */
    public function initialize_order_sync() {
    
        add_settings_section(
            'order_sync_section',
            '',
            [$this, 'order_sync_callback'],
            'wji_plugin_order_sync_options'
        );
    }

    public function order_sync_callback() {
        global $wpdb;

        $tablelist = new TableList();
    
        // Retry sync function
        if ( isset($_GET['_wjinonce'])
            || wp_verify_nonce( isset($_GET['_wjinonce']), 'retry_sync' )
            || is_numeric( isset($_GET[ '_syncid' ]) ) )
        {
            $this->retry_sync( $_GET['_syncid'] );
        }

        // Render table
        $sync_status = isset($_GET['sync_status']) ? sanitize_text_field($_GET['sync_status']) : '';
        $where = $sync_status ? $wpdb->prepare("sync_status = %s", $sync_status) : '';

        $per_page = $tablelist->getPerpage();
        $current_page = $tablelist->get_pagenum();
        $offset = $per_page * ($current_page - 1);

        $products = $this->sync_log->all($where, $tablelist->getPerpage(), $offset, 'created_at', 'DESC');
        $count = $this->sync_log->count($where);
        
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

    public function remove_retry_sync_query_args( $args ) {
        $args[] = '_wjinonce';
        $args[] = '_syncid';
        return $args;
    }

    public function retry_sync( $sync_id )
    {
        write_log('Retry sync initiated ...');
        $log = new SyncLog($sync_id);
        
        if( $log->isSynced() ) {
            return;
        }

        switch ( $log->getField('sync_action') ) {
            case "JE_CREATE":
            case 'JE_PAID':
            case 'JE_UNPAID':
                $this->journal_entry->create_sync( (int) $sync_id, (int) $log->getField('wc_order_id') );
                break;
            case "SA_CREATE":
                $this->stock_adjustment->create_sync( (int) $sync_id, (int) $log->getField('wc_order_id') );
                break;
            case "JE_DELETE":
                $this->journal_entry->remove_sync( (int) $sync_id );
                break;
            case "SA_DELETE":
                $this->stock_adjustment->remove_sync( (int) $sync_id );
                break;
        }
    }
}