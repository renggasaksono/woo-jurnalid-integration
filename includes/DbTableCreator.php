<?php

namespace Saksono\Woojurnal;

class DbTableCreator {

	function wji_create_product_mapping_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wji_product_mapping';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         			BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				wc_item_id    		BIGINT(20)   NOT NULL,
				jurnal_item_id    	BIGINT(20)   ,
				jurnal_item_code    VARCHAR(255) ,
				updated_at 			TIMESTAMP NOT NULL DEFAULT NOW() ON UPDATE NOW(),
				primary key (id)
			) {$charset};
		";

		dbDelta( $query );

		// initiation fill the existed product
		$wooproduct = wc_get_products([
			'limit'		=> -1, // All products
			'status'	=> 'publish' // Only published products
		]);
		foreach ($wooproduct as $product) {
			$id = $product->get_id();
			$data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$id}");

			if(count($data) < 1) {
				$wpdb->insert($table_name, ['wc_item_id' => $id, 'jurnal_item_id' => null]);
			}
			else {
				write_log("Produk ".$id. " sudah ada!");
			}

			// Check for product variations
			if ( $product->is_type( "variable" ) ) {
				$childrens = $product->get_children();
				if(count($childrens) > 0) {
			        foreach ( $childrens as $child_id ) {
			            $variation = wc_get_product( $child_id ); 
			            if ( ! $variation || ! $variation->exists() ) {
			                continue;
			            }
			            $data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$child_id}");
						if(count($data) < 1) {
							$wpdb->insert($table_name, ['wc_item_id' => $child_id, 'jurnal_item_id' => null]);
						}
						else {
							write_log("Produk ".$id. " sudah ada!");
						}
			        }
			    }
		    }
		}
	}

	function wcbc_create_order_sync_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wji_order_sync_log';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         			BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				wc_order_id			BIGINT NOT NULL,
				jurnal_entry_id		BIGINT,
				stock_adj_id		BIGINT,
				sync_data    		TEXT NOT NULL,
				sync_action			VARCHAR(10),
				sync_status			VARCHAR(10) NOT NULL DEFAULT 'PENDING',
				sync_note			TEXT,
				sync_at 			TIMESTAMP,
				created_at 			TIMESTAMP NOT NULL DEFAULT NOW(),
				primary key (id)
			) {$charset};
		";
		// sync_action => 'JE_CREATE', 'JE_UPDATE', 'JE_DELETE', 'SA_CREATE', 'SA_DELETE', 'JE_PAID', 'JE_UNPAID'
		// sync_status => 'PENDING', 'SYNCED', 'ERROR'

		dbDelta( $query );
	}
}
?>
