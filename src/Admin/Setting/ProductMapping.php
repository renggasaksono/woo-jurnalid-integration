<?php

namespace Saksono\Woojurnal\Admin\Setting;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\Product as ProductApi;
use Saksono\Woojurnal\Admin\TableList;
use Saksono\Woojurnal\Model\ProductMapping as ProductModel;

class ProductMapping {

	private $api;
	private $product_mapping_model;

    public function __construct()
    {
		$this->product_mapping_model = new ProductModel();

        add_action('admin_init', [$this, 'intialize_product_mapping']);
        add_action('wp_ajax_wji_translasi_item_save', [$this, 'save_item_ajax_callback']);
        add_action('wp_ajax_wji_check_used_item', [$this, 'check_used_item_ajax_callback']);
        add_action('wp_ajax_wji_select2_products', [$this, 'get_jurnal_products_callback']);
	}

    /**
     * Initializes plugin's product mapping between products in WooCommerce and products in Jurnal.ID
     *
     */
    public function intialize_product_mapping() {
    
        add_settings_section(
            'product_settings_section',             // ID used to identify this section and with which to register options
            '',                                     // Title to be displayed on the administration page
            [$this, 'product_mapping_callback'],   	// Callback used to render the description of the section
            'wji_plugin_product_mapping_options'    // Page on which to add this section of options
        );
    }

    public function product_mapping_callback() {
        global $wpdb;
        $tablelist = new TableList();

        $data = [];
        $table_name = $this->product_mapping_model->getTableName();
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
		$params = [];
		$params['page'] = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 1;
		$params['q'] = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
		// write_log($params);

		$api = new ProductApi();
		$response = $api->getAll($params);
		echo $response;
		wp_die();
	}

    public static function save_item_ajax_callback(){
		global $wpdb;
		$instance = new self();

		$update = $instance->product_mapping_model->update(
			$_POST['wc_item_id'],
			$_POST['jurnal_item_id'],
			$_POST['jurnal_item_code']
		);
		write_log($update);

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