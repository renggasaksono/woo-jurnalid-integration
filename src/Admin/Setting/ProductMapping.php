<?php

namespace Saksono\Woojurnal\Admin\Setting;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\JurnalApi;
use Saksono\Woojurnal\Admin\TableList;

class ProductMapping {

    public function __construct()
    {
        add_action('admin_init', [$this, 'intialize_product_mapping']);
        add_action('wp_ajax_wji_translasi_item_save', [$this, 'save_item_ajax_callback']); // ajax save mapping item
        add_action('wp_ajax_wji_check_used_item', [$this, 'check_used_item_ajax_callback']); // ajax cek mapping jika sudah digunakan
        add_action('wp_ajax_wji_select2_products', [$this, 'get_jurnal_products_callback']); // ajax get select2 products resource
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

    /* ------------------------------------------------------------------------ *
    * AJAX Callbacks
    * ------------------------------------------------------------------------ */

    public static function get_jurnal_products_callback() {
		// Set params
		$params = [];
		$params['page'] = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 1;
		$params['q'] = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
		// write_log($params);

		// Iniate API class
		$api = new JurnalApi();
		$response = $api->getAllJurnalItems($params);
		echo $response;
		wp_die();
	}

    public static function save_item_ajax_callback(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'wji_product_mapping';
		$update = $wpdb->update($table_name, [
				'jurnal_item_id' => (int) sanitize_text_field($_POST['jurnal_item_id']), 
				'jurnal_item_code' => sanitize_text_field($_POST['jurnal_item_code'])
			], 
			[
				'wc_item_id' => (int) sanitize_text_field($_POST['wc_item_id'])
			]);

		if($update) {
			esc_html_e($_POST['jurnal_item_code']);wp_die();
		}

	 	echo json_encode(false);wp_die();
	}

    public static function check_used_item_ajax_callback(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'wji_product_mapping';
		$jurnal_item = (int) sanitize_text_field($_POST['jurnal_item_id']);

		$translasi = $wpdb->get_results("select wc_item_id from {$table_name} where jurnal_item_id = {$jurnal_item}");

		if($translasi) {
			$items = [];
			foreach($translasi as $t) {
				$pf = new \WC_Product_Factory;
				$items[] = $pf->get_product($t->wc_item_id)->get_name();
			}

			echo json_encode([
				'status' => true,
				'data' => $items
			]);wp_die();
		}
		else {
			echo json_encode([
				'status' => false,
				'data' => $translasi
			]);wp_die();	
		}
	}
}