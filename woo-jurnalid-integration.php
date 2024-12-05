<?php
/**
 * Plugin Name:             Jurnal.ID Sync for WooCommerce
 * Description:             Seamlessly sync orders and product data between WooCommerce and Jurnal.ID, ensuring accurate stock updates and automatic journal entries.
 * Version:                 5.0.0
 * Requires at least:       5.5
 * Author:                  Rengga Saksono
 * Author URI:              https://id.linkedin.com/in/renggasaksono
 * License:                 GPL v2 or later
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least:    7.1
 * WC tested up to:         9.4.3
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

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function () {
    new SettingsPage();
    new AccountMapping();
    new ProductMapping();
    new SyncHistory();
    new OrderHandler();
    new ProductHandler();
});

/**
 * Enqueue plugin scripts
 */
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('wji_select2_style', plugin_dir_url( __FILE__ ) . 'assets/css/select2.min.css', null, '4.0.3' );
    wp_enqueue_style('wji_main_style', plugin_dir_url( __FILE__ ) . 'assets/css/wji_main.css', null, '1.1.5'); 
    wp_enqueue_script('wji_select2', plugin_dir_url( __FILE__ ) . 'assets/js/select2.min.js', array('jquery'), '4.0.3' );
    wp_enqueue_script('wji_main', plugin_dir_url( __FILE__ ) . 'assets/js/wji_main.js', array( 'jquery', 'select2' ), '1.1.53'); 
});

/**
 * Run on plugin activation
 */
register_activation_hook( __FILE__, 'create_db_tables' );
function create_db_tables()
{
    $tc = new DbTableCreator();
    $tc->wji_create_product_mapping_table();
    $tc->wcbc_create_order_sync_table();
}

/**
 * Run on plugin deactivation
 */
register_deactivation_hook( __FILE__, 'clear_plugin_data');
function clear_plugin_data()
{
    global $wpdb;

    delete_transient( 'wji_cached_journal_products' );
    delete_transient( 'wji_cached_journal_account' );
    delete_transient( 'wji_cached_journal_warehouses' );
}

/**
 * Declare WooCommerce HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/* 
 * Write to file debug.log on wp-content dir
 */
if (!function_exists('write_log')) {
    function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ).PHP_EOL );
            } else {
                error_log( $log.PHP_EOL );
            }
        }
    }
}