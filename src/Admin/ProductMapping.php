<?php

namespace Saksono\Woojurnal\Admin;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\JurnalApi;
use Saksono\Woojurnal\TableList;

class ProductMapping {

    public function __construct()
    {
        add_action('admin_init', [$this, 'intialize_product_mapping']);
	}

    /**
     * Initializes plugin's product mapping between products in WooCommerce and products in Jurnal.ID
     *
     */
    public function intialize_product_mapping() {
    
        add_settings_section(
            'product_settings_section',             // ID used to identify this section and with which to register options
            '',                                     // Title to be displayed on the administration page
            [$this, 'product_mapping_callback'],         // Callback used to render the description of the section
            'wji_plugin_product_mapping_options'    // Page on which to add this section of options
        );
    }

    /* ------------------------------------------------------------------------ *
    * Section Callbacks
    * ------------------------------------------------------------------------ */

    public function product_mapping_callback() {
        global $wpdb;
        $tablelist = new TableList();

        $data = [];
        $table_name = $wpdb->prefix . 'wji_product_mapping';
        $table_post = $wpdb->prefix . 'posts';
        $offset = $tablelist->getPerpage() * ($tablelist->get_pagenum() - 1);
        $where = '';
    
        $products = $wpdb->get_results("select wjpm.* from {$table_name} wjpm join {$table_post} p on wjpm.wc_item_id=p.id {$where} order by id limit {$tablelist->getPerpage()} offset {$offset}");
        $count = $wpdb->get_var("select count(*) from {$table_name} wjpm join {$table_post} p on wjpm.wc_item_id=p.id {$where}");
    
        $tablelist->setTotalItem($count);
        $tablelist->setDatas($products);
        $tablelist->setColumns([
            'wcproductname' => 'Produk pada Woocommerce (SKU - Nama Produk)',
            'jurnal_item_code' => 'Produk pada Jurnal.ID (SKU - Nama Produk)'
        ]);
        
        $tablelist->generate();
    }
}