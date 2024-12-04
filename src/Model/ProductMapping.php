<?php

namespace Saksono\Woojurnal\Model;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Model\SyncLog;
use Saksono\Woojurnal\Api\Product as ProductApi;

class ProductMapping {

    /**
	 * Main table used for storing sync data
	 */
    private const MAPPING_TABLE  = 'wji_product_mapping';

	private $api;

	public function __construct()
    {
       $this->api = new ProductApi();
    }

    /**
     * Get the sync table name.
     *
     * @return string
     */
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::MAPPING_TABLE;
    }

    /**
     * Get product mapping from database
     *
     * @param int $product_id Product ID
     * @return object|null Product mapping data
     */
    public function get(int $product_id): ?object
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM {$this->getTableName()} WHERE wc_item_id = %d AND jurnal_item_id IS NOT NULL", $product_id);
        return $wpdb->get_row($query);
    }

    /**
     * Update product mapping to database
     *
     * @param int $product_id Product ID
     * @param int $jurnal_item_id Jurnal product ID
     * @param string $jurnal_item_code Jurnal product code
     * @return bool|int Number of rows updated or false on failure
     */
    public function update(int $product_id, int $jurnal_item_id, string $jurnal_item_code)
    {
        global $wpdb;

        return $wpdb->update($this->getTableName(), [
            'jurnal_item_id' => (int) sanitize_text_field($jurnal_item_id), 
            'jurnal_item_code' => sanitize_text_field($jurnal_item_code)
        ], [
            'wc_item_id' => (int) sanitize_text_field($product_id)
        ]);
    }

}