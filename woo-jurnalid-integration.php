<?php
/**
 * Plugin Name:             WooCommerce Jurnal.ID Integration
 * Description:             Integrasi data pemesanan dan stok produk dari WooCommerce ke Jurnal.ID.
 * Version:                 5.0.0
 * Requires at least:       5.5
 * Author:                  Rengga Saksono
 * Author URI:              https://id.linkedin.com/in/renggasaksono
 * License:                 GPL v2 or later
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least:    7.1
 * WC tested up to:         8.2
 * Requires Plugins:        woocommerce
 */

defined( 'ABSPATH' ) || exit;

require_once(plugin_dir_path(__FILE__) . '/vendor/autoload.php');

use Saksono\Woojurnal\Admin\DbTableCreator;
use Saksono\Woojurnal\Admin\Setting\SettingsPage;
use Saksono\Woojurnal\Admin\Setting\AccountMapping;
use Saksono\Woojurnal\Admin\Setting\ProductMapping;
use Saksono\Woojurnal\Admin\Setting\SyncHistory;
use Saksono\Woojurnal\Sync\OrderHandler;
use Saksono\Woojurnal\Sync\ProductHandler;

// Initialize the plugin
add_action('plugins_loaded', function () {
    new SettingsPage();
    new AccountMapping();
    new ProductMapping();
    new SyncHistory();
    new OrderHandler();
    new ProductHandler();
});

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Enqueue Plugin Scripts
 */
function enqueue_scripts()
{
    wp_enqueue_style('wji_select2_style', plugin_dir_url( __FILE__ ) . 'assets/css/select2.min.css', null, '4.0.3' );
    wp_enqueue_style('wji_main_style', plugin_dir_url( __FILE__ ) . 'assets/css/wji_main.css', null, '1.1.5'); 
    wp_enqueue_script('wji_select2', plugin_dir_url( __FILE__ ) . 'assets/js/select2.min.js', array('jquery'), '4.0.3' );
    wp_enqueue_script('wji_main', plugin_dir_url( __FILE__ ) . 'assets/js/wji_main.js', array( 'jquery', 'select2' ), '1.1.53'); 
}
add_action('admin_enqueue_scripts', 'enqueue_scripts');

/**
 * Run on plugin activation
 */
function create_db_tables()
{
    $tc = new DbTableCreator();
    $tc->wji_create_product_mapping_table();
    $tc->wcbc_create_order_sync_table();
}
register_activation_hook( __FILE__, 'create_db_tables' );

/**
 * Run on plugin deactivation
 */
function clear_plugin_data()
{
    global $wpdb;

    // Clear cached data when settings changes
    delete_transient( 'wji_cached_journal_products' );
    delete_transient( 'wji_cached_journal_account' );
    delete_transient( 'wji_cached_journal_warehouses' );

    // Debug purpose, delete plugin options
    if ( true === WP_DEBUG ) {
        delete_option('wji_plugin_general_options');
        delete_option('wji_account_mapping_options');
    }
}
register_deactivation_hook( __FILE__, 'clear_plugin_data');

/* ------------------------------------------------------------------------ *
 * SYNC functions
 * ------------------------------------------------------------------------ */

/**
 * Do sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - woocommerce order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_sync_journal_entry( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Sync Jurnal Entry running for ID #'.$sync_id);
    
    $api = new \Saksono\Woojurnal\Api\JurnalApi();
    $order = wc_get_order( $order_id );
    $data = [];
    $sync_data = [];
    $do_sync = false;

    // Get sync data
    $get_sync_data = $wpdb->get_row(
        $wpdb->prepare( "SELECT * from {$api->getSyncTableName()} where id = %d",
            $sync_id
        )
    );

    // Create data format for sync
    if( $get_sync_data->sync_action == 'JE_PAID' ) {

        // Validate if previous UNPAID journal entry has been created, we need to format the data to reverse and balance the previous record
        if( ! $order->get_meta($api->getUnpaidMetaKey(),true) ) {
            $data = $api->get_paid_sync_data( $order );
        } else {
            $data = $api->get_payment_sync_data( $order );
        }

    } elseif( $get_sync_data->sync_action == 'JE_UNPAID' ) {
        $data = $api->get_unpaid_sync_data( $order );
    }

    // Verify data
    if( empty($data) ) {
        return false;
    }

    // Run sync function
    $do_sync = $api->postJournalEntry( $data );

    if( isset( $do_sync->journal_entry ) ) {
        
        // Success
        $sync_data['jurnal_entry_id']   = $do_sync->journal_entry->id;
        $sync_data['sync_data']         = json_encode( $data );
        $sync_data['sync_status']       = 'SYNCED';
        $sync_data['sync_note']         = '';
        $sync_data['sync_at']           = date("Y-m-d H:i:s");

        // Update post order metadata
        $order->update_meta_data( $api->getJournalEntryMetaKey(), $do_sync->journal_entry->id );
        $order->update_meta_data( $api->getUnpaidMetaKey(), $do_sync->journal_entry->id );

    } else {
        
        // Failed
        $sync_data['sync_status']       = 'ERROR';
        $sync_data['sync_data']         = json_encode( $data );
        $sync_data['sync_note']         = $do_sync['body'];
    }

    // Update sync log
    return $wpdb->update( $api->getSyncTableName(), $sync_data, [ 'id' => $sync_id ]);
}

/**
 * Delete sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - WC order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_desync_journal_entry( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Desync journal entry #'.$sync_id);
    
    $order = wc_get_order( $order_id );
    $api = new \Saksono\Woojurnal\Api\JurnalApi();
    
    // Delete journal entry if exists
    $journal_entry_id = $order->get_meta( $api->getJournalEntryMetaKey(), true );
    
    if( $journal_entry_id ) {

        // Make the API call
        $deleteEntryResponse = $api->deleteJournalEntry( $journal_entry_id );
    
        // Update sync status in db
        if( ! isset($deleteEntryResponse->errors) ) {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'SYNCED',
                    'sync_note'     => '',
                    'sync_action'   => 'JE_DELETE',
                    'sync_at'       => date("Y-m-d H:i:s")
                ],
                [ 'id' => $sync_id ]
            );
        } else {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'ERROR',
                    'sync_action'   => 'JE_DELETE',
                    'sync_note'     => $deleteEntryResponse['body']
                ],
                [ 'id' => $sync_id ]
            );
        }
    }
}

/**
 * Create stock adjustment sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - WC order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_sync_stock_adjustment( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Sync stock adjustment #'.$sync_id);
    
    $api = new \Saksono\Woojurnal\Api\JurnalApi();
    $order = wc_get_order( $order_id );
    $get_general_options = get_option('wji_plugin_general_options');
    $get_account_options = get_option('wji_account_mapping_options');
    $get_warehouse = $get_general_options['wh_id'];
    $sync_note = 'Product mapping tidak tersedia: ';

    // Verify warehouse
    if( empty($get_warehouse) ) {
        // Return error immediately
        return $wpdb->update($api->getSyncTableName(), [
                'sync_status' => 'ERROR',
                'sync_note' => 'Pengaturan Warehouse belum di set'
            ],
            [ 'id' => $sync_id ]
        );
    }

    // Verify account mapping
    if( ! isset($get_account_options['acc_stock_adjustments']) ) {
        return false;
    }
    
    $data = array(
        "stock_adjustment"  => array(
            "stock_adjustment_type" => 'general',
            "warehouse_id"          => $get_warehouse,
            "account_name"          => $api->getJurnalAccountName( $get_account_options['acc_stock_adjustments'] ),
            "date"                  => date("Y-m-d"),
            "memo"                  => get_bloginfo('name').' WooCommerce Order ID#'.$order_id,
            "maintain_actual"       => false,
            "lines_attributes"      => []
        )
    );

    // Loop order items
    foreach ( $order->get_items() as $order_item_id => $wc_item ) {

        // Get product_id
        $product_id = $wc_item->get_product_id();
        
        // Get item mapping
        $product_mapping_table = $wpdb->prefix . 'wji_product_mapping';
        $product_mapping_where = 'WHERE wc_item_id='.$product_id.' AND jurnal_item_id IS NOT NULL';
        $product_mapping = $wpdb->get_row("SELECT * FROM {$product_mapping_table} {$product_mapping_where}");

        // Check mapping
        if( ! $product_mapping ) {
            $sync_note .= $wc_item->get_name().", ";
            continue;
        }

        // Check product name
        $product_name = $api->getJurnalProductName($product_mapping->jurnal_item_id);

        // If data valid
        if ( $product_mapping && $product_name ) {

            $lines_attributes = array_column( $data['stock_adjustment']['lines_attributes'], 'product_name' );
            $found_key = array_search( $product_name, $lines_attributes );

            // Check for duplicates
            if ( $found_key !== false ) {

                // Merge values
                $current_diff = $data['stock_adjustment']['lines_attributes'][$found_key]['difference'];
                $new_diff = $current_diff - $wc_item->get_quantity();

                // Set data
                $data['stock_adjustment']['lines_attributes'][$found_key]['difference'] = $new_diff;
 
            } else {

                // Set data
                $data['stock_adjustment']['lines_attributes'][] = array(
                    "product_name" => $product_name,
                    "difference" => - $wc_item->get_quantity(),
                    "use_custom_average_price" => false
                );
            }
        }
    }

    // write_log($data);

    // Kalau ada data product nya
    if( ! empty($data['stock_adjustment']['lines_attributes']) ) {

        // Make the API call
        $postStockAdjustments = $api->postStockAdjustments($data);

        // Update order sync status in db
        if( isset($postStockAdjustments->stock_adjustment) ) {
            
            // Update post order metadata
            $order->update_meta_data( $api->getStockMetaKey(), $postStockAdjustments->stock_adjustment->id );

            return $wpdb->update( $api->getSyncTableName(), [
                    'stock_adj_id'  => $postStockAdjustments->stock_adjustment->id,
                    'sync_data'     => json_encode( $data ),
                    'sync_status'   => 'SYNCED',
                    'sync_note'     => '',
                    'sync_at'       => date("Y-m-d H:i:s")
                ],
                [ 'id' => $sync_id ]
            );

        } else {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_data'     => json_encode( $data ),
                    'sync_status'   => 'ERROR',
                    'sync_note'     => $postStockAdjustments['body']
                ],
                [ 'id' => $sync_id ]
            );
        } // end if postStockAdjustments

    } else {

        return $wpdb->update( $api->getSyncTableName(), [
                'sync_status'   => 'ERROR',
                'sync_note'     => rtrim( trim($sync_note), ','),
            ],
            [ 'id' => $sync_id ]
        );
    }
    
}

/**
 * Delete stock adjustment sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - WC order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_desync_stock_adjustment( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Desync stock adjustment #'.$sync_id);

    $api = new \Saksono\Woojurnal\Api\JurnalApi();
    $order = wc_get_order( $order_id );

    // Delete journal entry if exists
    $stock_adjustment_id = $order->get_meta( $api->getStockMetaKey(), true );
    
    if( $stock_adjustment_id ) {

        // Make the API call
        $deleteEntryResponse = $api->deleteStockAdjustments( $stock_adjustment_id );
        
        // Update sync status in db
        if( ! isset( $deleteEntryResponse->errors ) ) {
            
            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'SYNCED',
                    'sync_action'   => 'SA_DELETE',
                    'sync_note'     => '',
                    'sync_at'       => date("Y-m-d H:i:s")
                ],
                [ 'id' => $sync_id ]
            );

        } else {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'ERROR',
                    'sync_note'     => $deleteEntryResponse['body']
                ],
                [ 'id' => $sync_id ]
            );

        }
    }
}

/* ------------------------------------------------------------------------ *
 * DEBUG only, write to file debug.log on wp-content dir
 * ------------------------------------------------------------------------ */

if (!function_exists('write_log')) {
    function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            // $pluginlog = plugin_dir_path(__FILE__).'debug.log';
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ).PHP_EOL );
            } else {
                error_log( $log.PHP_EOL );
            }
        }
    }
}