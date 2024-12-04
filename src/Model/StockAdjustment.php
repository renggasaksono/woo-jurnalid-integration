<?php

namespace Saksono\Woojurnal\Model;

use Saksono\Woojurnal\Model\SyncLog;
use Saksono\Woojurnal\Model\ProductMapping;
use Saksono\Woojurnal\Api\StockAdjustment as StockAdjustmentApi;
use Saksono\Woojurnal\Api\Account as AccountApi;
use Saksono\Woojurnal\Api\Product as ProductApi;

defined( 'ABSPATH' ) || exit;

class StockAdjustment {

    /**
     * Meta key constants
     */
    private const STOCK_META_KEY = '_wji_stock_adjustment_id';

    /**
     * Stock adjustment API instance
     */
	private $stock_adjustment_api;

    /**
     * Account API instance
     */
	private $account_api;

    /**
     * Plugin general options
     */
	private $general_options;

    /**
     * Plugin general options
     */
	private $account_options;

    /**
     * Product mapping model
     */
	private $product_mapping;

    /**
     * Product API
     */
	private $product_api;

    /**
     * Constructor to initialize API instance
     */
	public function __construct()
    {
        $this->stock_adjustment_api = new StockAdjustmentApi();
        $this->account_api = new AccountApi();
        $this->product_mapping = new ProductMapping();
        $this->product_api = new ProductApi();

        $this->general_options = get_option('wji_plugin_general_options');
        $this->account_options = get_option('wji_account_mapping_options');
    }

    /**
     * Get the stock meta key.
     *
     * @return string
     */
    public function getMetaKey(): string
    {
        return self::STOCK_META_KEY;
    }

    /**
     * Create stock adjustment sync function
     * @param sync_id INT - ID from wji_order_sync_log table
     * @param order_id INT - WC order ID
     * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
     */
    public function create_sync( int $sync_id, int $order_id ) {
        global $wpdb;
        write_log('Sync stock adjustment #'.$sync_id);
        
        $sync_log = new SyncLog(sync_id: $sync_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            return $sync_log->update([
                'sync_status' => 'ERROR',
                'sync_note' => 'Invalid WooCommerce order ID'
            ]);
        }

        $warehouse_id = $this->general_options['wh_id'] ?? null;
        if (empty($warehouse_id)) {
            return $sync_log->update([
                'sync_status' => 'ERROR',
                'sync_note' => 'Warehouse not configured in plugin settings'
            ]);
        }

        if (empty($this->account_options['acc_stock_adjustments'])) {
            return $sync_log->update([
                'sync_status' => 'ERROR',
                'sync_note' => 'Account mapping for stock adjustments is missing'
            ]);
        }

        $data = $this->prepare_data($order, $warehouse_id);

        // Update sync log data
        $sync_data['sync_data'] = json_encode( $data );
        $sync_log->update($sync_data);

        if (empty($data['stock_adjustment']['lines_attributes'])) {
            return $sync_log->update([
                'sync_status' => 'ERROR',
                'sync_note' => 'No product mappings found for order items'
            ]);
        }

        $run_sync = $this->stock_adjustment_api->create($data);

        if( isset($run_sync['success']) && $run_sync['success']==true ) {
            $body = json_decode($run_sync['body']);
            update_post_meta( $order->get_id(), $this->getMetaKey(), $body->stock_adjustment->id );
            // $order->update_meta_data($this->getMetaKey(), $body->stock_adjustment->id);

            // Success
            $sync_data = [
                'stock_adj_id' => $body->stock_adjustment->id,
                'sync_status' => 'SYNCED',
                'sync_note' => '',
                'sync_data' => json_encode($data)
            ];
        } else {
            // Failed
            $sync_data = [
                'sync_status' => 'ERROR',
                'sync_note' => $run_sync['body'] ?? 'Unknown error during API call'
            ];
        }

        return $sync_log->update($sync_data);
    }

    /**
     * Prepare stock adjustment data for syncing
     *
     * @param \WC_Order $order WooCommerce order
     * @param string $warehouse_id Warehouse ID
     * @return array Prepared data for API
     */
    private function prepare_data(\WC_Order $order, string $warehouse_id): array
    {
        global $wpdb;

        $data = [
            "stock_adjustment" => [
                "stock_adjustment_type" => 'general',
                "warehouse_id" => $warehouse_id,
                "account_name" => $this->account_api->getAccountName($this->account_options['acc_stock_adjustments']),
                "date" => date("Y-m-d"),
                "memo" => get_bloginfo('name') . ' WooCommerce Order ID#' . $order->get_id(),
                "maintain_actual" => false,
                "lines_attributes" => []
            ]
        ];

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $mapping = $this->product_mapping->get($product_id);

            if (!$mapping || empty($mapping->jurnal_item_id)) {
                continue;
            }

            $product_name = $this->product_api->getProductName($mapping->jurnal_item_id);
            if (!$product_name) {
                continue;
            }

            $existing_product = array_search($product_name, array_column($data['stock_adjustment']['lines_attributes'], 'product_name'));

            if ($existing_product !== false) {
                $data['stock_adjustment']['lines_attributes'][$existing_product]['difference'] -= $item->get_quantity();
            } else {
                $data['stock_adjustment']['lines_attributes'][] = [
                    "product_name" => $product_name,
                    "difference" => -$item->get_quantity(),
                    "use_custom_average_price" => false
                ];
            }
        }

        return $data;
    }

    /**
     * Delete stock adjustment sync function
     * @param sync_id INT - ID from wji_order_sync_log table
     * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
     */
    public function remove_sync( int $sync_id ) {
        global $wpdb;
        write_log('Desync stock adjustment #'.$sync_id);

        $sync_log = new SyncLog(sync_id: $sync_id);
        $run_sync = $this->stock_adjustment_api->delete( $sync_log->getField('stock_adj_id') );

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
}