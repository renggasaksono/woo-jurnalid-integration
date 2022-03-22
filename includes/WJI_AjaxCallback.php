<?php

class WJI_AjaxCallback {

	static function wji_item_ajax_callback(){
	 	$api = new WJI_IntegrationAPI();

	 	$data = $api->getListItemAjax(WJI_Helper::sanitize($_GET['q']));

	 	if($data) {
	 		echo json_encode($data);wp_die();
	 	}
	 	else {
	 		echo json_encode([]);wp_die();
	 	}
	}

	static function wji_check_used_item_ajax_callback(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'wji_product_mapping';
		$jurnal_item = WJI_Helper::sanitize_int($_POST['jurnal_item_id']);

		$translasi = $wpdb->get_results("select wc_item_id from {$table_name} where jurnal_item_id = {$jurnal_item}");

		if($translasi) {
			$items = [];
			foreach($translasi as $t) {
				$pf = new WC_Product_Factory;
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

	static function wji_save_item_ajax_callback(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'wji_product_mapping';
		$update = $wpdb->update($table_name, [
				'jurnal_item_id' => WJI_Helper::sanitize_int($_POST['jurnal_item_id']), 
				'jurnal_item_code' => WJI_Helper::sanitize($_POST['jurnal_item_code'])
			], 
			[
				'wc_item_id' => WJI_Helper::sanitize_int($_POST['wc_item_id'])
			]);

		if($update) {
			esc_html_e($_POST['jurnal_item_code']);wp_die();
		}

	 	echo json_encode(false);wp_die();
	}
}
?>
