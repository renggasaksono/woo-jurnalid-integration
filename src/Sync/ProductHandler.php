<?php

namespace Saksono\Woojurnal\Sync;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class ProductHandler {

	public function __construct()
    {
        add_action( 'save_post_product', [$this, 'wji_check_new_product'], 10, 3 );
        add_action( 'before_delete_post', [$this, 'wji_delete_product'], 10, 1 );
    }

	/**
     * Fungsi ini dipanggil ketika ada product yang di modify
     *
    */
    public function wji_check_new_product( $post_id, $post, $update ){
        global $wpdb;

        // Get a WC_Product object https://woocommerce.github.io/code-reference/classes/WC-Product.html
        $product = wc_get_product( $post_id );
        if( !$product ) {
            return;
        }

        // Add new product mapping record
        $table_name = $wpdb->prefix . 'wji_product_mapping';
        $id = $product->get_id();
        $data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$id}");
        if(count($data) < 1) {
            $wpdb->insert($table_name, ['wc_item_id' => $id, 'jurnal_item_id' => null]);
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
                }
            }
        }
    }

    /**
     * Fungsi ini dipanggil ketika ada product yang di hapus
     *
    */
    public function wji_delete_product( $post_id ) {
        global $wpdb;

        $product = wc_get_product($post_id);
        if ( empty($product) ) {
            return;
        }

        // Delete product
        $table_name = $wpdb->prefix . 'wji_product_mapping';
        $product = wc_get_product($post_id);
        $id = $product->get_id();
        $data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$id}");
        if(count($data) < 1) {
            $wpdb->delete($table_name, ['wc_item_id' => $id]);
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
                        $wpdb->delete($table_name, ['wc_item_id' => $child_id]);
                    }
                }
            }
        }
    }
}